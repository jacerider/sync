<?php

namespace Drupal\sync;

/**
 * Exception class to throw to indicate that an item should not be synced.
 *
 * This should only be used when a resource does not use cleanup.
 */
class SyncSkipWithoutSaveException extends SyncSkipException {}
