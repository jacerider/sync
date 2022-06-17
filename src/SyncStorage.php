<?php

namespace Drupal\sync;

use Drupal\mysql\Driver\Database\mysql\Connection;
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
  public function __construct(Connection $database, EntityTypeManagerInterface $entity_type_manager) {
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
    foreach ($data as $id => $item) {
      $query = $this->database->delete('sync');
      $this->buildPropertyQuery($query, $values);
      $status = $query->execute();
      if ($status) {
        $query = $this->database->delete('sync_data');
        $query->condition('id', $id);
        $query->execute();
      }
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
      $entity = $this->entityTypeManager->getStorage($data[$id]->entity_type)->load($data[$id]->entity_id);
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
      ->key(['id' => $id, 'entity_type' => $entity->getEntityTypeId()])
      ->fields([
        'entity_id' => $entity->id(),
        'locked' => $locked === TRUE ? 1 : 0,
      ])
      ->execute();
    if ($status) {
      $changed = \Drupal::time()->getRequestTime();
      $status = $this->database->merge('sync_data')
        ->key(['id' => $id, 'segment' => $group])
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

}
