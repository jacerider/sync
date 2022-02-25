<?php

namespace Drupal\sync;

use Drupal\Core\Queue\SuspendQueueException;

/**
 * Exception class to throw to indicate that an item should not be synced.
 */
class SyncFailException extends SuspendQueueException {}
