<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 's3_get_object' sync resource.
 *
 * @SyncFetcher(
 *   id = "s3_get_object",
 *   label = @Translation("S3 Get Object"),
 *   provider = "s3fs",
 * )
 */
class S3GetObject extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
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
    /** @var \Aws\Result $object */
    return $s3client->getObject($this->configuration['s3args']);
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
