<?php

namespace Drupal\sync\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Sync Resource item annotation object.
 *
 * @see \Drupal\sync\Plugin\SyncResourceManager
 * @see plugin_api
 *
 * @Annotation
 */
class SyncResource extends Plugin {

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

  /**
   * A boolean indicating if plugin is active.
   *
   * @var bool
   */
  public $status = TRUE;

  /**
   * A boolean indicating if plugin should show in UI.
   *
   * @var bool
   */
  public $no_ui = FALSE;

  /**
   * A boolean indicating if plugin should execute cleanup operations.
   *
   * @var bool
   */
  public $cleanup = FALSE;

  /**
   * A boolean indicating if plugin last run time can be reset via UI.
   *
   * @var bool
   */
  public $reset = FALSE;

  /**
   * A boolean indicating the weight of this plugin.
   *
   * @var int
   */
  public $weight = 0;

  /**
   * The type of entity this resource shoulld create/update.
   *
   * @var string
   */
  public $entity_type = '';

  /**
   * The bundle of the entity type this resource shoulld create/update.
   *
   * @var string
   */
  public $bundle = '';

  /**
   * A comma-deliniated string of times this resource should be run.
   *
   * @var string
   */
  public $cron = '00:00';

  /**
   * A comma-deliniated string of days this resource should be run.
   *
   * @var string
   */
  public $day = 'mon,tue,wed,thu,fri';

}
