<?php

namespace Drupal\sync;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\sync\Plugin\SyncResourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access for resources.
 */
class SyncResourceAccess implements AccessInterface {

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
   * A custom access check.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    $requirement = $route->getRequirement('_sync_resource_access');
    list($resource_id, $operation) = explode('.', $requirement);
    $parameters = $route_match->getParameters();
    if ($parameters->has($resource_id)) {
      $resource_id = $parameters->get($resource_id);
      if ($this->syncResourceManager->hasDefinition($resource_id)) {
        $resource = $this->syncResourceManager->getDefinition($resource_id);
        if ($this->syncResourceManager->access($resource, $account, $operation)) {
          return AccessResult::allowed();
        }
      }
    }
    // No opinion, so other access checks should decide if access should be
    // allowed or not.
    return AccessResult::neutral();
  }

}
