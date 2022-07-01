<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;
use function GuzzleHttp\json_decode;

/**
 * Plugin implementation of the 'object_to_array' sync parser.
 *
 * @SyncParser(
 *   id = "object_to_array",
 *   label = @Translation("Object to Array"),
 * )
 */
class ObjectToArray extends SyncParserBase {

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    return json_decode(json_encode($data), TRUE);
  }

}
