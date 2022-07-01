<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;

/**
 * Plugin implementation of the 's3' sync parser.
 *
 * @SyncParser(
 *   id = "s3",
 *   label = @Translation("S3"),
 *   provider = "s3fs",
 * )
 */
class S3 extends SyncParserBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    /** @var \Aws\Result $data */
    if (!empty($data['Contents'])) {
      return $data['Contents'];
    }
    if (!empty($data['CommonPrefixes'])) {
      return $data['CommonPrefixes'];
    }
    return [$data->toArray()];
  }

}
