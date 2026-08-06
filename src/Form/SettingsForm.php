<?php

namespace Drupal\sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'sync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sync_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('sync.settings');
    $form['email_fail'] = [
      '#type' => 'textfield',
      '#title' => t('Email Failure'),
      '#description' => t('An email will be sent to the provided email addresses when a sync reports a failure.'),
      '#default_value' => $config->get('email_fail'),
    ];
    $form['log_verbose'] = [
      '#type' => 'checkbox',
      '#title' => t('Verbose Logging'),
      '#default_value' => $config->get('log_verbose'),
    ];
    $locked = \Drupal::service('sync.storage')->countLocked();
    $form['lock_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Allow locking synced entities'),
      '#description' => t('Adds a Lock action to entities that sync manages. A locked entity is never changed or deleted by a sync, so edits made here are kept. Only users with the "Lock and unlock synced entities" permission see it.'),
      '#default_value' => (bool) $config->get('lock_enabled'),
    ];
    if ($locked) {
      $form['lock_enabled']['#description'] .= ' ' . t('There @count currently locked.', [
        '@count' => \Drupal::translation()->formatPlural($locked, 'is 1 entity', 'are @count entities'),
      ]);
    }
    $form['cron'] = [
      '#type' => 'details',
      '#title' => t('Cron'),
      '#open' => $config->get('cron_queue') === FALSE || $config->get('cron_build') === FALSE,
    ];
    $form['cron']['intro'] = [
      '#markup' => '<p>' . t('A sync happens in two stages. First it is <em>started</em>: the data is downloaded and every record is added to a work queue. Then that queue is <em>worked through</em>, a few records at a time, until it is empty. The two settings below control whether cron does each stage.') . '</p>'
      . '<p>' . t('Leave both enabled unless something outside Drupal is running your syncs, such as a server cron job calling <code>drush sync:cron</code>. In that case turn both off, so that Drupal cron and the external job do not both try to run the same sync.') . '</p>',
    ];
    $form['cron']['cron_build'] = [
      '#type' => 'checkbox',
      '#title' => t('Let cron start scheduled syncs'),
      '#description' => t('Each time cron runs, any sync whose scheduled day and time have passed is started. Turn this off if something else starts your syncs; if both do it, a second copy of a sync can be started while the first is still running.'),
      '#default_value' => $config->get('cron_build') === NULL ? TRUE : $config->get('cron_build'),
    ];
    $form['cron']['cron_queue'] = [
      '#type' => 'checkbox',
      '#title' => t('Let cron work through the sync queue'),
      '#description' => t('Each time cron runs, it spends a short time processing queued records. Turn this off if something else processes the queue; if both do it, they take records from each other and each one slows down.'),
      '#default_value' => $config->get('cron_queue') === NULL ? TRUE : $config->get('cron_queue'),
    ];
    $form['cron']['cron_queue_time'] = [
      '#type' => 'number',
      '#title' => t('Seconds cron may spend on each sync'),
      '#description' => t('How long cron works through a single sync queue before moving on. Records left over are picked up the next time cron runs. This caps how much a large sync can get done per day, so if a sync never finishes, run it with <code>drush sync:run</code> instead of raising this.'),
      '#min' => 1,
      '#default_value' => $config->get('cron_queue_time') ?: 30,
      '#states' => [
        'visible' => [
          ':input[name="cron_queue"]' => ['checked' => TRUE],
        ],
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();
    // Turning locking off only hides the UI. Existing locks keep being
    // honoured, so nothing quietly starts overwriting an entity someone
    // deliberately protected - but there is then no way to clear them.
    if (!$values['lock_enabled'] && $this->config('sync.settings')->get('lock_enabled')) {
      $locked = \Drupal::service('sync.storage')->countLocked();
      if ($locked) {
        $this->messenger()->addWarning($this->formatPlural($locked,
          '1 entity is still locked and will continue to be skipped by sync. Re-enable locking if you need to unlock it.',
          '@count entities are still locked and will continue to be skipped by sync. Re-enable locking if you need to unlock them.'
        ));
      }
    }
    $this->config('sync.settings')
      ->set('email_fail', $values['email_fail'])
      ->set('lock_enabled', (bool) $values['lock_enabled'])
      ->set('log_verbose', $values['log_verbose'])
      ->set('cron_build', (bool) $values['cron_build'])
      ->set('cron_queue', (bool) $values['cron_queue'])
      ->set('cron_queue_time', (int) $values['cron_queue_time'])
      ->save();
    // Queue worker definitions are built by hook_queue_info_alter() and cached
    // in cache.discovery, so they have to be rebuilt for the change to apply.
    \Drupal::service('plugin.manager.queue_worker')->clearCachedDefinitions();
  }

}
