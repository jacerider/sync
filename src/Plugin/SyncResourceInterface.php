<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Sync Resource plugins.
 */
interface SyncResourceInterface extends PluginInspectionInterface {

  /**
   * Get the resource label.
   *
   * @return string
   *   The resource label.
   */
  public function label();

  /**
   * The sync client.
   *
   * @return array
   *   The client definition.
   */
  public function getClient();

  /**
   * The sync fetcher.
   *
   * @return \Drupal\sync\Plugin\SyncFetcherInterface
   *   The sync fetcher plugin.
   */
  public function getFetcher();

  /**
   * The sync parser.
   *
   * @return \Drupal\sync\Plugin\SyncParserInterface
   *   The sync fetcher plugin.
   */
  public function getParser();

  /**
   * The job called for each item of a sync.
   *
   * @param array $datas
   *   An array of items.
   *
   * @return array
   *   An array of results.
   */
  public function manualProcessMultiple(array $datas);

  /**
   * The job called for each item of a sync.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $extend_item
   *   An item to add to all processed items.
   *
   * @return \Drupal\core\Entity\EntityInterface[]
   *   An array of created/updated entities.
   */
  public function manualProcess(SyncDataItem $extend_item = NULL);

  /**
   * Fetch the data and create jobs.
   *
   * This will make any necessary API calls and store retrieved data as a job
   * for future processing.
   *
   * @param array $context
   *   Additional context that can be passed to the build.
   *
   * @return $this
   */
  public function build(array $context = []);

  /**
   * Run jobs as subqueue.
   *
   * @param array $context
   *   Additional context.
   */
  public function runAsSubResource(array $context);

  /**
   * Run jobs as a batch.
   */
  public function runAsBatch();

  /**
   * Runs all queued jobs.
   *
   * @return $this
   */
  public function runJobs();

  /**
   * Runs the first queued job.
   *
   * @return $this
   */
  public function runJob();

  /**
   * Get data via fetcher.
   *
   * @param \Drupal\sync\Plugin\SyncDataItems $previous_data
   *   The data used on the previous request. Used when paging.
   * @param array $context
   *   Additional context.
   *
   * @return \Drupal\sync\Plugin\SyncDataItems
   *   A collection of items.
   */
  public function fetchData(SyncDataItems $previous_data = NULL, array $context = []);

  /**
   * Method called when resetting plugin.
   */
  public function resetLastRun();

}
