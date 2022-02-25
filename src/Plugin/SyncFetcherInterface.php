<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for Sync Fetcher plugins.
 */
interface SyncFetcherInterface extends PluginInspectionInterface {

  /**
   * Set setting.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function setSetting($key, $value);

  /**
   * Set paging status.
   *
   * @param bool $status
   *   Set the paging status.
   *
   * @return $this
   */
  public function setPageEnabled($status = TRUE);

  /**
   * Check if paging is enabled.
   *
   * @return bool
   *   TRUE if paging is enabled.
   */
  public function isPageEnabled();

  /**
   * Get the page size.
   *
   * @return int
   *   The page size.
   */
  public function getPageSize();

  /**
   * Set page size.
   *
   * @param int $size
   *   The page size.
   */
  public function setPageSize($size);

  /**
   * Get the page limit.
   *
   * @return int
   *   The page limit.
   */
  public function getPageLimit();

  /**
   * Set page limit.
   *
   * @param int $limit
   *   The page limit.
   */
  public function setPageLimit($limit);

  /**
   * Prepare for fetching.
   *
   * @param int $page_number
   *   The request page number.
   * @param \Drupal\sync\Plugin\SyncDataItems $previous_data
   *   The request page number.
   *
   * @return mixed
   *   The results of the fetch.
   */
  public function doFetch($page_number = 0, SyncDataItems $previous_data = NULL);

  /**
   * Should fetcher act on another page.
   *
   * @param int $page_number
   *   The request page number.
   * @param \Drupal\sync\Plugin\SyncDataItems $previous_data
   *   The request page number.
   *
   * @return bool
   *   If TRUE, sync will continue until this returns false.
   */
  public function hasNextPage($page_number = 1, SyncDataItems $previous_data = NULL);

}
