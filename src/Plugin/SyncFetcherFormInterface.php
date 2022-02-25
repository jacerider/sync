<?php

namespace Drupal\sync\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * Defines an interface for Sync Resource plugins.
 */
interface SyncFetcherFormInterface extends SyncFetcherInterface {

  /**
   * Build a fetcher form.
   *
   * @param array $form
   *   The initial form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\sync\Plugin\SyncResourceInterface $resource
   *   The resource.
   *
   * @return array
   *   Return the form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state, SyncResourceInterface $resource);

  /**
   * Validate a fetcher form.
   *
   * @param array $form
   *   The initial form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\sync\Plugin\SyncResourceInterface $resource
   *   The resource.
   */
  public function validateForm(array $form, FormStateInterface $form_state, SyncResourceInterface $resource);

  /**
   * Submit a fetcher form.
   *
   * @param array $form
   *   The initial form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\sync\Plugin\SyncResourceInterface $resource
   *   The resource.
   */
  public function submitForm(array $form, FormStateInterface $form_state, SyncResourceInterface $resource);

}
