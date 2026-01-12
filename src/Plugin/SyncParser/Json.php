<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;

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
    if (!$fetcher->handlesPagination()) {
      $page_size = $fetcher->getPageSize();

      // If pagination is enabled, use streaming to avoid loading everything.
      if ($page_size) {
        $fetcher->setPageEnabled(TRUE);
        $max = $page_size * $fetcher->getPageNumber();
        $min = $max - $page_size;

        // Stream parse only the slice we need.
        $result = $this->streamParseSlice($data, $base_key, $min, $page_size);
        return $result;
      }
    }

    $decoded = json_decode($data, TRUE) ?: [];
    if (!empty($base_key) && isset($decoded[$base_key])) {
      $decoded = $decoded[$base_key];
    }
    return $decoded;
  }

  /**
   * Stream parse a slice of the JSON data.
   */
  protected function streamParseSlice($json, $base_key, $offset, $limit) {
    // Check if JsonMachine is available.
    if (class_exists('\JsonMachine\Items')) {
      // For very large files, use a streaming parser.
      // Install: composer require halaxa/json-machine && composer dump-autoload -o.
      try {
        $items = [];
        $options = ['decoder' => new \JsonMachine\JsonDecoder\ExtJsonDecoder(TRUE)];

        if (!empty($base_key)) {
          $options['pointer'] = '/' . $base_key;
        }

        $parser = \JsonMachine\Items::fromString($json, $options);

        $index = 0;
        foreach ($parser as $item) {
          if ($index >= $offset && $index < ($offset + $limit)) {
            $items[] = $item;
          }
          $index++;

          // Stop early once we have what we need.
          if ($index >= ($offset + $limit)) {
            break;
          }
        }

        return $items;
      }
      catch (\Exception $e) {
        // If streaming fails, fall through to standard method.
      }
    }

    // Fallback to original method if JsonMachine not available or fails.
    $decoded = json_decode($json, TRUE) ?: [];
    if (!empty($base_key) && isset($decoded[$base_key])) {
      $decoded = $decoded[$base_key];
    }
    return array_slice($decoded, $offset, $limit);
  }

}
