<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Sync Parser plugins.
 */
interface SyncParserInterface extends PluginInspectionInterface {

  /**
   * Get a setting.
   *
   * @param string $key
   *   The setting key.
   */
  public function getSetting($key);

  /**
   * Set a setting.
   *
   * @param string $key
   *   The setting key.
   * @param string $value
   *   The setting value.
   *
   * @return $this
   */
  public function setSetting($key, $value);

  /**
   * Parse the data.
   *
   * @param mixed $data
   *   The data to parse.
   *
   * @return array
   *   The data as an array.
   */
  public function doParse($data);

}
