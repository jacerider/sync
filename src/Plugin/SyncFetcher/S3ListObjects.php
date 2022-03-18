<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 's3_list_objects' sync resource.
 *
 * @SyncFetcher(
 *   id = "s3_list_objects",
 *   label = @Translation("S3 List Objects"),
 *   provider = "s3fs",
 * )
 */
class S3ListObjects extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'page_enabled' => TRUE,
      'page_size' => 100,
      's3args' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $config = \Drupal::config('s3fs.settings')->get();
    /** @var \Drupal\s3fs\S3fsService */
    $s3fs = \Drupal::service('s3fs');
    $s3client = $s3fs->getAmazonS3Client($config);
    $args = $this->configuration['s3args'];
    $args['MaxKeys'] = $this->configuration['page_size'];

    // Support paging.
    if ($previous_data->hasItems()) {
      $item = $previous_data->last();
      $args['StartAfter'] = $item['Key'] ?? $item['Prefix'];
    }

    $data = $s3client->listObjectsV2($args);
    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * @return $this
   */
  public function setS3Arg($key, $value) {
    $this->configuration['s3args'][$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getS3Arg($key) {
    return isset($this->configuration['s3args'][$key]) ? $this->configuration['s3args'][$key] : NULL;
  }

}
