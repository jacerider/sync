<?php

namespace Drupal\sync;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sync\Plugin\SyncResourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic sync permissions.
 */
class SyncPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

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
   * Get permissions for Taxonomy Views Integrator.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions() {
    $permissions = [];
    foreach ($this->syncResourceManager->getDefinitions() as $definition) {
      $permissions += [
        'sync run ' . $definition['id'] => [
          'title' => $this->t('Allowing running sync %label', ['%label' => $definition['label']]),
        ],
      ];
    }
    return $permissions;
  }

}
