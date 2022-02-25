<?php

namespace Drupal\sync\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sync\Plugin\SyncResourceManager;

/**
 * Process a queue of Sync items to process their data.
 *
 * @QueueWorker(
 *   id = "sync",
 *   title = @Translation("Sync"),
 *   cron = {"time" = 180}
 * )
 */
class Sync extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The sync resource manager.
   *
   * @var \Drupal\sync\Plugin\SyncResourceManager
   */
  protected $syncResourceManager;

  /**
   * Constructs a new class instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\sync\Plugin\SyncResourceManager $sync_resource_manager
   *   The resource manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger, SyncResourceManager $sync_resource_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->syncResourceManager = $sync_resource_manager;
    $this->logger = $logger->get('sync');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('plugin.manager.sync_resource')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $plugin = $this->syncResourceManager->createInstance($data['plugin_id']);
    $op = $data['op'];
    if (method_exists($plugin, $op)) {
      $item = $data['data'] ?? [];
      $item['%sync_as_job'] = TRUE;
      try {
        $plugin->{$op}($item);
      }
      catch (\Exception $e) {
        // Do nothing. Exceptions have already been handled.
      }
    }
    else {
      $this->logger->error('[Queue Worker: %plugin_label] FAIL: %op method could not be found in %class_name.', [
        '%plugin_label' => $plugin->label(),
        '%op' => $data['op'],
        '%class_name' => get_class($plugin),
      ]);
    }
  }

}
