<?php

namespace Drupal\sync\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Sync Parser item annotation object.
 *
 * @see \Drupal\sync\Plugin\SyncParserManager
 * @see plugin_api
 *
 * @Annotation
 */
class SyncParser extends Plugin {


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
