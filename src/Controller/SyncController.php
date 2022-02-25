<?php

namespace Drupal\sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sync\Plugin\SyncResourceManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class SyncController.
 */
class SyncController extends ControllerBase {

  /**
   * Drupal\sync\Plugin\SyncResourceManager definition.
   *
   * @var \Drupal\sync\Plugin\SyncResourceManager
   */
  protected $syncResourceManager;

  /**
   * Constructs a new SyncController object.
   */
  public function __construct(SyncResourceManager $plugin_id_manager_sync_resource) {
    $this->syncResourceManager = $plugin_id_manager_sync_resource;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.sync_resource')
    );
  }

  /**
   * Log.
   *
   * @param string $plugin_id
   *   A sync resource plugin id.
   *
   * @return string
   *   Return Hello string.
   */
  public function log($plugin_id) {
    /** @var \Drupal\sync\Plugin\SyncResourceInterface $resource */
    $resource = $this->syncResourceManager->getResource($plugin_id);
    if (empty($resource)) {
      throw new NotFoundHttpException('Plugin does not exist.');
    }
    return [
      '#title' => $this->t('Sync Log: %label', [
        '%label' => $resource->label(),
      ]),
      '#type' => 'view',
      '#name' => 'sync_watchdog',
      '#display_id' => 'default',
      '#arguments' => [
        'sync_' . $resource->getPluginId(),
        $this->syncResourceManager->getLastRunStart($resource->getPluginDefinition()),
        $this->syncResourceManager->getLastRunEnd($resource->getPluginDefinition()),
      ],
    ];
  }

}
