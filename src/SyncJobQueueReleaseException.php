<?php

namespace Drupal\sync;

/**
 * Exception class to throw to indicate a queue item should not be deleted.
 *
 * The item queued will be released so that it can be run again.
 */
class SyncJobQueueReleaseException extends \Exception {}
