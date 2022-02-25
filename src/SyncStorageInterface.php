<?php

namespace Drupal\sync;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface SyncStorageInterface.
 */
interface SyncStorageInterface {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query.
   */
  public function getQuery();

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   The select query.
   */
  public function getDataQuery($group = 'default');

  /**
   * Load entities by their property values.
   *
   * @param array $values
   *   An associative array where the keys are the property names and the
   *   values are the values those properties must have.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their ids.
   */
  public function loadByProperties(array $values = []);

  /**
   * Delete entities by their property values.
   *
   * @param array $values
   *   An associative array where the keys are the property names and the
   *   values are the values those properties must have.
   */
  public function deleteByProperties(array $values = []);

  /**
   * Load an entity via sync id and entity type.
   *
   * @param string $id
   *   The sync id.
   * @param string $entity_type
   *   The entity type id.
   *
   * @return \Drupal\core\Entity\EntityInterface|null
   *   The loaded entity.
   */
  public function loadEntity($id, $entity_type);

  /**
   * Save a sync record of an entity.
   *
   * @param string $id
   *   The sync id.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   * @param bool $locked
   *   Flag to lock synced entity from further automated changes.
   * @param string $group
   *   The sync group id.
   */
  public function save($id, EntityInterface $entity, $locked = FALSE, $group = 'default');

  /**
   * Save a sync record given an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   */
  public function saveEntity(EntityInterface $entity);

  /**
   * Get last updated datetime by sync id and group.
   *
   * @param string $id
   *   The sync id.
   * @param string $group
   *   The sync group id.
   */
  public function lastUpdated($id, $group = 'default');

}
