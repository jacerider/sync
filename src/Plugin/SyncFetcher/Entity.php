<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 'entity' sync resource.
 *
 * @SyncFetcher(
 *   id = "entity",
 *   label = @Translation("Entity"),
 * )
 */
class Entity extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'entity_type' => '',
      'properties' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $data = [];
    foreach (\Drupal::entityTypeManager()->getStorage($this->configuration['entity_type'])->loadByProperties($this->configuration['properties']) as $entity) {
      $data[]['entity'] = $entity;
    }
    return $data;
  }

}
