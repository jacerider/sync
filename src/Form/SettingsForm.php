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
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();
    $this->config('sync.settings')
      ->set('email_fail', $values['email_fail'])
      ->set('log_verbose', $values['log_verbose'])
      ->save();
  }

}
