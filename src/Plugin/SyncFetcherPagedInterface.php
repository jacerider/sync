<?php

namespace Drupal\sync\Plugin;

/**
 * Defines an interface for Sync Resource plugins.
 */
interface SyncFetcherPagedInterface extends SyncFetcherInterface {

  /**
   * Get the number of items per page.
   *
   * @return int
   *   The number of items per page.
   */
  public function getSize();

  /**
   * Called when paging is enabled.
   *
   * @return array|null
   *   Should return results of paged fetch. If null or empty array, paging will
   *   end.
   */
  public function fetchPage($previous_data, $page);

}
