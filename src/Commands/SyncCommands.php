<?php

namespace Drupal\sync\Commands;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\sync\Plugin\SyncResourceManager;
use Drush\Commands\DrushCommands;

/**
 * Defines Drush commands for the Search API.
 */
class SyncCommands extends DrushCommands {

  /**
   * The sync resource manager.
   *
   * @var \Drupal\sync\Plugin\SyncResourceManager
   */
  protected $syncResourceManager;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Constructs a SyncCommands object.
   *
   * @param \Drupal\sync\Plugin\SyncResourceManager $sync_resource_manager
   *   The sync resource manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the "search_api_index" or "search_api_server" entity types'
   *   storage handlers couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the "search_api_index" or "search_api_server" entity types are
   *   unknown.
   */
  public function __construct(SyncResourceManager $sync_resource_manager, QueueFactory $queue_factory, ?LockBackendInterface $lock = NULL) {
    $this->syncResourceManager = $sync_resource_manager;
    $this->queueFactory = $queue_factory;
    // Optional so that an un-rebuilt container keeps working after update.
    $this->lock = $lock ?: \Drupal::service('lock');
  }

  /**
   * Sets the search server used by a given index.
   *
   * @param string $resource_id
   *   The sync resource to run.
   * @param array $options
   *   (optional) An array of options.
   *
   * @command sync:sync
   *
   * @option continue
   *   If TRUE, will continue the last import of this resource.
   *   Defaults to FALSE.
   *
   * @usage drush sync:sync resource_name
   *   Run the sync for the provided resource.
   *
   * @aliases sync
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function sync($resource_id, array $options = ['continue' => FALSE]) {
    $continue = !empty($options['continue']);
    if ($this->syncResourceManager->hasDefinition($resource_id)) {
      $queue_name = 'sync_' . $resource_id;
      $queue = $this->queueFactory->get($queue_name);
      $instance = $this->syncResourceManager->createInstance($resource_id);
      if ($continue) {
        // Release all jobs.
        $database = \Drupal::database();
        $database->update('queue')
          ->fields([
            'expire' => 0,
          ])
          ->condition('name', $queue_name)
          ->condition('expire', 0, '<>')
          ->execute();
      }
      else {
        // Always purge queue when running as command.
        $queue->deleteQueue();
        $instance->build();
      }
      $instance->runJobs();
    }
    else {
      throw new \Exception('Trying to call a non-existent resource. See help using drush sync --help.');
    }
  }

  /**
   * Build and drain a single sync resource, safely re-entrant.
   *
   * Unlike sync:sync this is designed to be called on a short interval by an
   * external scheduler. It takes a lock, honours a wall-clock budget, and
   * leaves the queue in a resumable state when it runs out of time.
   *
   * @param string $resource_id
   *   The sync resource to run.
   * @param array $options
   *   (optional) An array of options.
   *
   * @command sync:run
   *
   * @option build
   *   When to build a new run: auto (only when due and the queue is empty),
   *   always, or never. Defaults to auto.
   * @option time-limit
   *   Seconds of wall clock to spend draining. 0 for unlimited.
   * @option lease
   *   Seconds to lease each claimed queue item for.
   * @option force
   *   Ignore stored change-detection hashes and process every record.
   * @option max-items
   *   Stop after this many items. 0 for unlimited.
   * @option memory-limit
   *   Stop once memory usage exceeds this. Accepts sizes like 1200M.
   *
   * @usage drush sync:run members --time-limit=540
   *   Build if due, then drain for up to nine minutes.
   * @usage drush sync:run members --force --time-limit=0
   *   Re-sync every record, however long it takes.
   *
   * @throws \Exception
   *   If the resource does not exist.
   */
  public function run($resource_id, array $options = [
    'build' => 'auto',
    'time-limit' => 540,
    'lease' => 120,
    'force' => FALSE,
    'max-items' => 0,
    'memory-limit' => '1200M',
  ]) {
    if (!$this->syncResourceManager->hasDefinition($resource_id)) {
      throw new \Exception('Trying to call a non-existent resource. See help using drush sync:run --help.');
    }
    $time_limit = (int) $options['time-limit'];
    // The lock must outlive the drain, otherwise a second invocation could
    // start while this one is still working.
    $lock_name = 'sync.run.' . $resource_id;
    if (!$this->lock->acquire($lock_name, $time_limit + 120)) {
      $this->logger()->notice(dt('Sync @id is already running; nothing to do.', [
        '@id' => $resource_id,
      ]));
      return;
    }
    try {
      $instance = $this->syncResourceManager->createInstance($resource_id);
      $build = $this->shouldBuild($instance, $resource_id, $options['build']);
      if ($build) {
        $instance->build([
          '%sync_as_cron' => TRUE,
          '%force' => !empty($options['force']),
        ]);
      }
      $start = microtime(TRUE);
      $result = $instance->drain([
        'time_limit' => $time_limit,
        'lease' => (int) $options['lease'],
        'max_items' => (int) $options['max-items'],
        'memory_limit' => $options['memory-limit'] ? Bytes::toNumber($options['memory-limit']) : 0,
      ]);
      $this->logger()->notice(dt('Sync @id: processed @processed, @remaining remaining, stopped (@stopped) after @elapsed s.', [
        '@id' => $resource_id,
        '@processed' => $result['processed'],
        '@remaining' => $result['remaining'],
        '@stopped' => $result['stopped'],
        '@elapsed' => round(microtime(TRUE) - $start, 1),
      ]));
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Build and drain every sync resource that is due.
   *
   * This is the command an external scheduler should call. It replaces relying
   * on core cron, whose per-queue time budget is far too small for a large
   * sync.
   *
   * @param array $options
   *   (optional) An array of options.
   *
   * @command sync:cron
   *
   * @option time-limit
   *   Seconds of wall clock to spend across all resources. 0 for unlimited.
   * @option lease
   *   Seconds to lease each claimed queue item for.
   * @option memory-limit
   *   Stop once memory usage exceeds this. Accepts sizes like 1200M.
   *
   * @usage drush sync:cron --time-limit=540
   *   Run every due resource, spending at most nine minutes in total.
   */
  public function cron(array $options = [
    'time-limit' => 540,
    'lease' => 120,
    'memory-limit' => '1200M',
  ]) {
    $total_limit = (int) $options['time-limit'];
    $deadline = $total_limit > 0 ? microtime(TRUE) + $total_limit : NULL;
    // Anything already queued still needs draining, even if it is not due to
    // build again today.
    foreach ($this->syncResourceManager->getDefinitions() as $id => $definition) {
      if (empty($definition['status'])) {
        continue;
      }
      $remaining = $deadline === NULL ? 0 : (int) ceil($deadline - microtime(TRUE));
      if ($deadline !== NULL && $remaining <= 0) {
        $this->logger()->notice(dt('Sync cron: time limit reached, stopping before @id.', [
          '@id' => $id,
        ]));
        break;
      }
      $queue = $this->queueFactory->get('sync_' . $id);
      $due = array_key_exists($id, $this->syncResourceManager->getForCron());
      if (!$due && $queue->numberOfItems() === 0) {
        continue;
      }
      $this->run($id, [
        'build' => 'auto',
        'time-limit' => $remaining,
        'lease' => (int) $options['lease'],
        'force' => FALSE,
        'max-items' => 0,
        'memory-limit' => $options['memory-limit'],
      ]);
    }
  }

  /**
   * Decide whether a new run should be built.
   *
   * @param \Drupal\sync\Plugin\SyncResourceInterface $instance
   *   The resource instance.
   * @param string $resource_id
   *   The resource id.
   * @param string $mode
   *   One of auto, always or never.
   *
   * @return bool
   *   TRUE if build() should be called.
   */
  protected function shouldBuild($instance, $resource_id, $mode) {
    if ($mode === 'never') {
      return FALSE;
    }
    if ($mode === 'always') {
      return TRUE;
    }
    // Auto: defer entirely to the resource's own cron schedule, and never
    // build on top of a run that is still draining.
    if (!array_key_exists($resource_id, $this->syncResourceManager->getForCron())) {
      return FALSE;
    }
    return $this->queueFactory->get('sync_' . $resource_id)->numberOfItems() === 0;
  }

}
