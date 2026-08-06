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
          // setsid puts the child in its own session/process group so it
          // survives the FPM worker exiting. Without it, hosts like Pantheon
          // tear down the child when the parent worker is recycled. Guarded
          // because setsid (from util-linux) isn't guaranteed on every host.
          // Redirecting stdin from /dev/null ensures PHP doesn't wait on it.
          $prefix = is_executable('/usr/bin/setsid') ? '/usr/bin/setsid ' : '';
          exec($prefix . 'sh -c ' . escapeshellarg($this->buildCronCommand($drush)) . ' > /dev/null 2>&1 < /dev/null &');
          return;
        }
      }
    }
    $this->cron->run();
  }

  /**
   * Build the command run in the background.
   *
   * Core cron is always run, because this endpoint is commonly a site's only
   * cron trigger. When the site has opted out of processing sync queues during
   * core cron, sync:cron is appended to pick them up instead: it drives the
   * queues directly and so is not bound by the per-queue cron budget.
   *
   * @param string $drush
   *   The path to the drush binary.
   *
   * @return string
   *   The shell command.
   */
  protected function buildCronCommand($drush) {
    $drush = escapeshellarg($drush);
    $command = $drush . ' cron';
    $config = \Drupal::config('sync.settings');
    if ($config->get('cron_queue') === FALSE) {
      $time_limit = (int) ($config->get('cron_queue_time') ?: 30);
      $command .= '; ' . $drush . ' sync:cron --time-limit=' . escapeshellarg((string) $time_limit);
    }
    return $command;
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
