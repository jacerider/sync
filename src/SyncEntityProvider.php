<?php

namespace Drupal\sync;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * The sync entity provider.
 */
class SyncEntityProvider implements SyncEntityProviderInterface {

  /**
   * The sync storage.
   *
   * @var \Drupal\sync\SyncStorageInterface
   */
  protected $syncStorage;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * An array of local entities keyed by entity_type > bundle > Sf ID.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   *   An array of stored entities.
   */
  protected $entities;

  /**
   * Constructs a new SyncEntityProvider object.
   */
  public function __construct(SyncStorageInterface $sync_storage, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user) {
    $this->syncStorage = $sync_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function get($id, $entity_type, $bundle = NULL, array $values = [], $group = 'default') {
    $entity = $this->syncStorage->loadEntity($id, $entity_type);
    if (!$entity && !empty($bundle) && !empty($values)) {
      // We do not have a record of this entity within sync. We check to see if
      // one already exists.
      $bundle_key = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
      if ($bundle_key) {
        $values[$bundle_key] = $bundle;
      }
      $results = $this->entityTypeManager->getStorage($entity_type)->loadByProperties($values);
      if (!empty($results)) {
        $entity = reset($results);
      }
    }
    if ($entity) {
      $entity->__sync_changed = \Drupal::time()->getRequestTime();
      // Create sync record so it can be retrieved faster next time.
      // @see sync_entity_update().
      $this->attachProperties($entity, $id, $group);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrNew($id, $entity_type, $bundle, array $values = [], $group = 'default') {
    $entity = $this->get($id, $entity_type, $bundle, $values, $group);
    if (empty($entity)) {
      $entity = $this->getNew($id, $entity_type, $bundle, $values, $group);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOrCreate($id, $entity_type, $bundle, array $values = [], $group = 'default') {
    $entity = $this->getOrNew($id, $entity_type, $bundle, $values, $group);
    if ($entity->isNew()) {
      $entity->save();
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function getNew($id, $entity_type, $bundle, array $values = [], $group = 'default') {
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
    if ($bundle_key) {
      $values[$bundle_key] = $bundle;
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->create($values);
    if ($entity instanceof ContentEntityInterface && $field_name = $entity->getEntityType()->getKey('uid')) {
      $uid = $this->currentUser->id();
      if (empty($uid)) {
        $uid = 1;
      }
      $entity->set($field_name, $uid);
    }
    $this->attachProperties($entity, $id, $group);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function attachProperties($entity, $id, $group = 'default') {
    // @see sync_entity_update().
    $entity->__sync_id = $id;
    $entity->__sync_group = $group;
  }

}
