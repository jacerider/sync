<?php

namespace Drupal\sync\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Sync Parser plugin manager.
 */
class SyncParserManager extends DefaultPluginManager {


  /**
   * Constructs a new SyncParserManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/SyncParser', $namespaces, $module_handler, 'Drupal\sync\Plugin\SyncParserInterface', 'Drupal\sync\Annotation\SyncParser');

    $this->alterInfo('sync_sync_parser_info');
    $this->setCacheBackend($cache_backend, 'sync_sync_parser_plugins');
  }

}
