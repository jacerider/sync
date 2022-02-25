<?php

/**
 * @file
 * Drush commands for Sync.
 */

/**
 * Implements hook_drush_command().
 */
function sync_drush_command() {
  $items = [];

  $items['sync'] = [
    'description' => 'Run sync by resource id.',
    'examples' => [
      'drush sync node_index' => dt('Sync the resource with the ID @name.', ['@name' => 'node_index']),
    ],
    'arguments' => [
      'resource_id' => dt('The machine name of an sync resource.'),
    ],
    'options' => [
      'continue' => [
        'description' => 'Continue a sync.',
      ],
    ],
    'aliases' => ['sync'],
  ];

  return $items;
}

/**
 * Prints a list of all search indexes.
 */
function drush_sync($resource_id = NULL) {
  // If no $name provided, abort.
  if (!$resource_id) {
    drush_print(dt('Resource id missing. See help using drush sync --help.'));
    return;
  }

  $continue = drush_get_option('continue', FALSE);
  $manager = \Drupal::service('plugin.manager.sync_resource');
  if ($manager->hasDefinition($resource_id)) {
    $queue_name = 'sync_' . $resource_id;
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get($queue_name);
    $instance = $manager->createInstance($resource_id);

    if ($continue) {
      // Release all jobs.
      $database = \Drupal::database();
      $database->update('queue')
        ->fields([
          'expire' => 0,
        ])
        ->condition('name', $queue_name)
        ->condition('expire', 0, '<>')
        ->execute();
    }
    else {
      // Always purge queue when running as command.
      $queue->deleteQueue();
      $instance->build();
    }

    $instance->runJobs();
  }
  else {
    drush_print(dt('Trying to call a non-existent resource. See help using drush sync --help.'));
  }
}
