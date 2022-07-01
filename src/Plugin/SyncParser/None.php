<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;

/**
 * Plugin implementation of the 'none' sync parser.
 *
 * @SyncParser(
 *   id = "none",
 *   label = @Translation("None"),
 * )
 */
class None extends SyncParserBase {

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    return $data;
  }

}
