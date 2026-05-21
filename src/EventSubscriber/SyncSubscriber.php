<?php

namespace Drupal\sync\EventSubscriber;

use Drupal\Core\CronInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A subscriber running cron after a response is sent.
 */
class SyncSubscriber implements EventSubscriberInterface {

  /**
   * The cron service.
   *
   * @var \Drupal\Core\CronInterface
   */
  protected $cron;

  /**
   * The application root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Constructs a new SyncSubscriber object.
   *
   * @param \Drupal\Core\CronInterface $cron
   *   The cron service.
   * @param string $app_root
   *   The application root path.
   */
  public function __construct(CronInterface $cron, string $app_root) {
    $this->cron = $cron;
    $this->appRoot = $app_root;
  }

  /**
   * Run the automated cron if enabled.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The Event to process.
   */
  public function onTerminate(TerminateEvent $event) {
    if ($event->getRequest()->attributes->get('_route') !== 'sync.cron') {
      return;
    }
    // Run cron in a background CLI process so the FPM worker is released
    // immediately rather than parked for the duration of queue draining.
    // Falls back to inline cron if exec is disabled (e.g. via php.ini
    // disable_functions) or the drush binary isn't where we expect it
    // (non-standard composer layout).
    if (function_exists('exec')) {
      // drupal/recommended-project (vendor at project root) is the common
      // modern layout. drupal/legacy-project (vendor inside docroot) is the
      // older one.
      foreach ([
        $this->appRoot . '/../vendor/bin/drush',
        $this->appRoot . '/vendor/bin/drush',
      ] as $drush) {
        if (is_executable($drush)) {
          exec(escapeshellarg($drush) . ' cron > /dev/null 2>&1 &');
          return;
        }
      }
    }
    $this->cron->run();
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::TERMINATE => [['onTerminate', 500]]];
  }

}
