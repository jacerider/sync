<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for Sync Parser plugins.
 */
abstract class SyncParserBase extends PluginBase implements SyncParserInterface {

  /**
   * Constructs a SyncFetcher object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $configuration += $this->defaultSettings();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Provides default settings.
   */
  protected function defaultSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key) {
    return isset($this->configuration[$key]) ? $this->configuration[$key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    $this->configuration[$key] = $value;
    return $this;
  }

  /**
   * Parse the data.
   */
  public function doParse($data, SyncFetcherInterface $fetcher) {
    return $this->parse($data, $fetcher);
  }

  /**
   * Parse the data.
   */
  abstract protected function parse($data, SyncFetcherInterface $fetcher);

}
