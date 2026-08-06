<?php

namespace Drupal\sync;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class SyncStorage.
 */
class SyncStorage implements SyncStorageInterface {

  /**
   * Drupal\mysql\Driver\Database\mysql\Connection definition.
   *
   * @var \Drupal\mysql\Driver\Database\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SyncStorage object.
   */
  public function __construct($database, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery() {
    return $this->database->select('sync')->fields('sync');
  }

  /**
   * {@inheritdoc}
   */
  public function getDataQuery($group = 'default') {
    $query = $this->database->select('sync_data');
    $query->join('sync', 'sync', 'sync.id = sync_data.id');
    $query->fields('sync_data');
    $query->fields('sync');
    $query->condition('sync_data.segment', $group);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = []) {
    $query = $this->getQuery();
    $this->buildPropertyQuery($query, $values);
    return $query->execute()->fetchAllAssoc('id');
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByProperties(array $values = []) {
    $data = $this->loadByProperties($values);
    if (!$data) {
      return;
    }
    // A single delete removes every row matching the conditions, so this must
    // run once rather than once per matched row.
    $query = $this->database->delete('sync');
    $this->buildPropertyQuery($query, $values);
    if ($query->execute()) {
      $this->database->delete('sync_data')
        ->condition('id', array_keys($data), 'IN')
        ->execute();
    }
  }

  /**
   * Builds an entity query.
   *
   * @param \Drupal\Core\Database\Query\ConditionInterface $query
   *   Query instance.
   * @param array $values
   *   An associative array of properties of the entity, where the keys are the
   *   property names and the values are the values those properties must have.
   */
  protected function buildPropertyQuery(ConditionInterface $query, array $values) {
    foreach ($values as $name => $value) {
      // Cast scalars to array so we can consistently use an IN condition.
      $query->condition($name, (array) $value, 'IN');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadEntity($id, $entity_type) {
    $entity = NULL;
    $data = $this->loadByProperties([
      'id' => $id,
      'entity_type' => $entity_type,
    ]);
    if (isset($data[$id])) {
      $storage = $this->entityTypeManager->getStorage($data[$id]->entity_type);
      // Disable static cache to avoid consuming all the memory.
      $storage->getEntityType()->set('static_cache', FALSE);
      $entity = $storage->load($data[$id]->entity_id);
      if ($entity) {
        // We temporarily store the locked state on the entity.
        $entity->syncIsLocked = !empty($data[$id]->locked);
      }
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save($id, EntityInterface $entity, $locked = FALSE, $group = 'default') {
    $status = $this->database->merge('sync')
      ->keys(['id' => $id, 'entity_type' => $entity->getEntityTypeId()])
      ->fields([
        'entity_id' => $entity->id(),
        'locked' => $locked === TRUE ? 1 : 0,
      ])
      ->execute();
    if ($status) {
      $changed = \Drupal::time()->getRequestTime();
      $status = $this->database->merge('sync_data')
        ->keys(['id' => $id, 'segment' => $group])
        ->fields([
          'changed' => $changed,
        ])
        ->execute();
    }
    return $status;
  }

  /**
   * {@inheritdoc}
   */
  public function saveEntity(EntityInterface $entity) {
    if (isset($entity->__sync_id)) {
      $this->save($entity->__sync_id, $entity, FALSE, $entity->__sync_group);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lastUpdated($id, $group = 'default') {
    $query = $this->database->select('sync_data');
    $query->fields('sync_data', ['changed']);
    $query->condition('id', $id);
    $query->condition('segment', $group);
    $query->range(0, 1);
    return $query->execute()->fetchField(0);
  }

  /**
   * {@inheritdoc}
   */
  public function getHashes(array $ids, $group = 'default', $entity_type = NULL, $verify_entities = TRUE) {
    $ids = $this->filterIds($ids);
    if (!$ids) {
      return [];
    }
    $rows = [];
    foreach (array_chunk($ids, 1000) as $chunk) {
      $query = $this->database->select('sync_data', 'sd');
      $query->fields('sd', ['id', 'hash']);
      $query->condition('sd.segment', $group);
      $query->condition('sd.id', $chunk, 'IN');
      // A NULL hash means "never fingerprinted", which must always process.
      $query->isNotNull('sd.hash');
      if ($entity_type) {
        // Only trust a hash that still maps to a tracked entity.
        $query->innerJoin('sync', 's', 's.id = sd.id AND s.entity_type = :entity_type', [
          ':entity_type' => $entity_type,
        ]);
        $query->condition('s.entity_id', '', '<>');
        $query->addField('s', 'entity_id', 'entity_id');
      }
      foreach ($query->execute() as $row) {
        $rows[$row->id] = [
          'hash' => $row->hash,
          'entity_id' => $row->entity_id ?? NULL,
        ];
      }
    }
    if ($rows && $entity_type && $verify_entities) {
      $rows = $this->filterMissingEntities($rows, $entity_type);
    }
    return array_map(function (array $row) {
      return $row['hash'];
    }, $rows);
  }

  /**
   * {@inheritdoc}
   */
  public function touch(array $ids, $group = 'default', $timestamp = NULL) {
    $ids = $this->filterIds($ids);
    if (!$ids) {
      return 0;
    }
    $timestamp = $timestamp ?? \Drupal::time()->getRequestTime();
    $count = 0;
    foreach (array_chunk($ids, 1000) as $chunk) {
      $count += (int) $this->database->update('sync_data')
        ->fields(['changed' => $timestamp])
        ->condition('segment', $group)
        ->condition('id', $chunk, 'IN')
        ->execute();
    }
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function saveHash($id, $hash, $group = 'default') {
    return $this->database->merge('sync_data')
      ->keys(['id' => $id, 'segment' => $group])
      ->fields([
        'hash' => $hash,
        'changed' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();
  }

  /**
   * Normalize a list of sync ids for use in a query.
   *
   * @param array $ids
   *   The raw ids.
   *
   * @return array
   *   Unique, non-empty ids, re-indexed.
   */
  protected function filterIds(array $ids) {
    $ids = array_filter($ids, function ($id) {
      return $id !== NULL && $id !== '' && !is_array($id);
    });
    return array_values(array_unique($ids));
  }

  /**
   * Drop rows whose mapped entity no longer exists.
   *
   * hook_entity_delete() normally clears the sync record, so this only matters
   * for deletions that bypassed the entity API (direct SQL, a restored
   * database, a failed migration). Without it a stale hash would cause the
   * record to be skipped forever and never recreated.
   *
   * @param array $rows
   *   Rows keyed by sync id, each with 'hash' and 'entity_id' keys.
   * @param string $entity_type
   *   The entity type id.
   *
   * @return array
   *   The rows whose entity could be confirmed to exist.
   */
  protected function filterMissingEntities(array $rows, $entity_type) {
    try {
      $definition = $this->entityTypeManager->getDefinition($entity_type);
    }
    catch (\Exception $e) {
      return $rows;
    }
    $base_table = $definition->getBaseTable();
    $id_key = $definition->getKey('id');
    // Config entities have no base table; there is nothing cheap to verify
    // against, so trust the sync record.
    if (!$base_table || !$id_key || !$this->database->schema()->tableExists($base_table)) {
      return $rows;
    }
    $entity_ids = array_filter(array_column($rows, 'entity_id'), function ($id) {
      return $id !== NULL && $id !== '';
    });
    if (!$entity_ids) {
      return [];
    }
    $existing = [];
    foreach (array_chunk(array_values(array_unique($entity_ids)), 1000) as $chunk) {
      $found = $this->database->select($base_table, 'b')
        ->fields('b', [$id_key])
        ->condition('b.' . $id_key, $chunk, 'IN')
        ->execute()
        ->fetchCol();
      foreach ($found as $id) {
        $existing[(string) $id] = TRUE;
      }
    }
    return array_filter($rows, function (array $row) use ($existing) {
      return isset($existing[(string) $row['entity_id']]);
    });
  }

}
