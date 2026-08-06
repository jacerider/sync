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

  /**
   * A boolean indicating if source records should be fingerprinted.
   *
   * When enabled, a hash of each source record is stored after a successful
   * sync and compared on the next run. Records that have not changed are never
   * queued, which skips the entity load and save entirely.
   *
   * This is opt-in because it requires the sync id returned by ::id() to be
   * derivable from the raw source item alone. A resource that computes or
   * normalizes its id inside ::prepareItem() would produce a different id at
   * queue time than at process time and silently mismatch every record.
   *
   * @var bool
   */
  public $hash = FALSE;

  /**
   * A salt that invalidates every stored hash for this resource when changed.
   *
   * Bump this whenever ::processItem() changes what it writes, otherwise
   * unchanged source records will be skipped and never pick up the new
   * mapping. Set to "auto" to derive the salt from the resource class file, so
   * any edit to the class invalidates the stored hashes automatically.
   *
   * Only used when $hash is TRUE.
   *
   * @var string|int
   */
  public $hash_version = 1;

  /**
   * A boolean indicating if a hash should be trusted only when its entity exists.
   *
   * Guards against deletions that bypassed hook_entity_delete(). Costs one
   * extra indexed query per page.
   *
   * Only used when $hash is TRUE.
   *
   * @var bool
   */
  public $verify_entities = TRUE;

  /**
   * What to do when a new run starts while jobs are still queued.
   *
   * - 'append': leave the pending jobs in place and add the new run behind
   *   them. This is the historical behavior and remains the default.
   * - 'restart': discard the pending jobs and start clean. Correct for a full
   *   snapshot feed, where yesterday's leftovers are stale by definition.
   * - 'resume': skip building entirely and let the pending run finish first.
   *   Correct for delta feeds, where every item matters.
   *
   * @var string
   */
  public $build_policy = 'append';

}
