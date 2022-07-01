<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;

/**
 * Plugin implementation of the 'stream' sync parser.
 *
 * @SyncParser(
 *   id = "stream",
 *   label = @Translation("Guzzle Stream"),
 * )
 */
class Stream extends SyncParserBase {

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    $data = [['contents' => $data->getContents()]];
    return $data;
  }

}
