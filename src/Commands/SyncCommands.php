<?php

namespace Drupal\sync\Commands;

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
  public function __construct(SyncResourceManager $sync_resource_manager, QueueFactory $queue_factory) {
    $this->syncResourceManager = $sync_resource_manager;
    $this->queueFactory = $queue_factory;
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

}
