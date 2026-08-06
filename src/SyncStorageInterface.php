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
   * @param bool|null $locked
   *   Whether the entity should be locked against further automated changes.
   *   NULL, the default, leaves any existing lock untouched, which is what a
   *   routine sync wants: writing a sync record says nothing about whether the
   *   entity is locked. Pass TRUE or FALSE only to deliberately change it.
   * @param string $group
   *   The sync group id.
   */
  public function save($id, EntityInterface $entity, $locked = NULL, $group = 'default');

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

  /**
   * Get the stored source fingerprints for a set of sync ids.
   *
   * Only ids that are both tracked and still resolve to an existing entity are
   * returned. Anything missing from the result should be treated as changed.
   *
   * @param array $ids
   *   The sync ids to look up.
   * @param string $group
   *   The sync group id.
   * @param string|null $entity_type
   *   The entity type id the ids are expected to map to. Required in order to
   *   verify entity existence.
   * @param bool $verify_entities
   *   When TRUE, drop any hash whose mapped entity no longer exists. This
   *   catches deletions that bypassed hook_entity_delete().
   *
   * @return array
   *   An array of hashes, keyed by sync id.
   */
  public function getHashes(array $ids, $group = 'default', $entity_type = NULL, $verify_entities = TRUE);

  /**
   * Refresh the changed timestamp for a set of sync ids without touching hash.
   *
   * Used when a record is skipped as unchanged, so that cleanup does not treat
   * it as stale.
   *
   * @param array $ids
   *   The sync ids to touch.
   * @param string $group
   *   The sync group id.
   * @param int|null $timestamp
   *   The timestamp to set. Defaults to the current request time.
   *
   * @return int
   *   The number of rows updated.
   */
  public function touch(array $ids, $group = 'default', $timestamp = NULL);

  /**
   * Store the source fingerprint for a sync id.
   *
   * @param string $id
   *   The sync id.
   * @param string $hash
   *   The fingerprint of the source record.
   * @param string $group
   *   The sync group id.
   */
  public function saveHash($id, $hash, $group = 'default');

  /**
   * Lock or unlock a synced record.
   *
   * A locked record is left alone by sync: SyncResourceBase::accessEntity()
   * refuses to update it, and cleanup will not delete it. The lock persists
   * across runs until it is explicitly cleared.
   *
   * @param string $id
   *   The sync id.
   * @param string $entity_type
   *   The entity type id.
   * @param bool $locked
   *   TRUE to lock, FALSE to unlock.
   *
   * @return int
   *   The number of rows affected.
   */
  public function setLocked($id, $entity_type, $locked = TRUE);

  /**
   * Determine whether a synced record is locked.
   *
   * @param string $id
   *   The sync id.
   * @param string $entity_type
   *   The entity type id.
   *
   * @return bool
   *   TRUE if the record is locked.
   */
  public function isLocked($id, $entity_type);

  /**
   * Count the sync records that are currently locked.
   *
   * @return int
   *   The number of locked records.
   */
  public function countLocked();

  /**
   * Load the sync records that point at an entity.
   *
   * An entity usually has one, but nothing prevents several sync ids from
   * resolving to the same entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return object[]
   *   Sync records keyed by sync id, or an empty array if the entity is not
   *   tracked by sync.
   */
  public function loadByEntity(EntityInterface $entity);

  /**
   * Lock or unlock every sync record pointing at an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param bool $locked
   *   TRUE to lock, FALSE to unlock.
   *
   * @return int
   *   The number of rows affected.
   */
  public function setEntityLocked(EntityInterface $entity, $locked = TRUE);

}
