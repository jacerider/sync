<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 'file' sync resource.
 *
 * @SyncFetcher(
 *   id = "file",
 *   label = @Translation("File"),
 * )
 */
class File extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'path' => '',
      'remote' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $filepath = $this->configuration['path'];
    if (strpos($this->configuration['path'], '://') === FALSE) {
      $filepath = \Drupal::root() . '/' . $filepath;
    }
    if (empty($this->configuration['remote']) && !file_exists($filepath)) {
      $message = t('The file %filepath could not be found.', [
        '%filepath' => $filepath,
      ]);
      \Drupal::messenger()->addError($message);
      throw new \Exception($message);
    }
    return file_get_contents($filepath);
  }

}
