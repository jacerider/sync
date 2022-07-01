<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;
use function GuzzleHttp\json_decode;

/**
 * Plugin implementation of the 'json' sync parser.
 *
 * @SyncParser(
 *   id = "json",
 *   label = @Translation("JSON"),
 * )
 */
class Json extends SyncParserBase {

  /**
   * Constructs a SyncParser object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $configuration += [
      'base_key' => '',
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    $base_key = $this->configuration['base_key'];
    $data = json_decode($data, TRUE);
    if (!empty($base_key) && isset($data[$base_key])) {
      $data = $data[$base_key];
    }
    return $data;
  }

}
