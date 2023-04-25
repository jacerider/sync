<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 'entity' sync resource.
 *
 * @SyncFetcher(
 *   id = "entity_query",
 *   label = @Translation("Entity Query"),
 * )
 */
class EntityQuery extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'page_enabled' => TRUE,
      'page_size' => 20,
      'entity_type' => '',
      // Should be ['field' => '', 'value' => '', 'operator' => ''].
      'conditions' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $query = \Drupal::entityTypeManager()->getStorage($this->configuration['entity_type'])->getQuery();
    $query->accessCheck(FALSE);
    $length = $this->getPageSize();
    $start = ($page_number - 1) * $length;
    $query->range($start, $length);
    foreach ($this->configuration['conditions'] as $condition) {
      $condition += [
        'field' => '',
        'value' => '',
        'operator' => '',
      ];
      if (!empty($condition['field'])) {
        $query->condition($condition['field'], $condition['value'], $condition['operator']);
      }
    }
    return $query->execute();
  }

}
