<?php

namespace Drupal\sync;

/**
 * Exception class to throw to indicate in item should be ignored.
 *
 * The item queued item will be removed and no messages will be shown.
 */
class SyncIgnoreException extends \Exception {}
