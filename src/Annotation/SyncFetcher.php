<?php

namespace Drupal\sync\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Sync Fetcher item annotation object.
 *
 * @see \Drupal\sync\Plugin\SyncFetcherManager
 * @see plugin_api
 *
 * @Annotation
 */
class SyncFetcher extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
