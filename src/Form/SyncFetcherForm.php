<?php

namespace Drupal\sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sync\Plugin\SyncFetcherFormInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sync\Plugin\SyncFetcherManager;
use Drupal\sync\Plugin\SyncResourceManager;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class SyncForm.
 */
class SyncFetcherForm extends FormBase {

  /**
   * Drupal\sync\Plugin\SyncResourceManager definition.
   *
   * @var \Drupal\sync\Plugin\SyncResourceManager
   */
  protected $syncResourceManager;

  /**
   * The sync fetcher manager.
   *
   * @var \Drupal\sync\Plugin\SyncFetcherManager
   */
  protected $syncFetcherManager;

  /**
   * The current resource plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The sync resource.
   *
   * @var \Drupal\sync\Plugin\SyncResourceInterface
   */
  protected $resource;

  /**
   * The sync fetcher.
   *
   * @var \Drupal\sync\Plugin\SyncFetcherFormInterface
   */
  protected $fetcher;

  /**
   * Constructs a new SyncFetcherForm object.
   */
  public function __construct(SyncResourceManager $plugin_id_manager_sync_resource, SyncFetcherManager $sync_fetcher_manager) {
    $this->syncResourceManager = $plugin_id_manager_sync_resource;
    $this->syncFetcherManager = $sync_fetcher_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.sync_resource'),
      $container->get('plugin.manager.sync_fetcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sync_fetcher_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL) {
    $this->pluginId = $plugin_id;
    /** @var \Drupal\sync\Plugin\SyncResourceInterface $resource */
    $resource = $this->syncResourceManager->getResource($this->pluginId);
    if (empty($resource)) {
      throw new NotFoundHttpException('Plugin does not exist.');
    }
    $fetcher = $resource->getFetcher();
    if (!$fetcher instanceof SyncFetcherFormInterface) {
      throw new NotFoundHttpException('Resource does not use a fetcher that supports a form.');
    }
    $form = $fetcher->buildForm($form, $form_state, $resource);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\sync\Plugin\SyncResourceInterface $resource */
    $resource = $this->syncResourceManager->getResource($this->pluginId);
    /** @var \Drupal\sync\Plugin\SyncFetcherFormInterface $fetcher */
    $fetcher = $resource->getFetcher();
    $fetcher->validateForm($form, $form_state, $resource);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\sync\Plugin\SyncResourceInterface $resource */
    $resource = $this->syncResourceManager->getResource($this->pluginId);
    /** @var \Drupal\sync\Plugin\SyncFetcherFormInterface $fetcher */
    $fetcher = $resource->getFetcher();
    $fetcher->submitForm($form, $form_state, $resource);
    if (!\Drupal::request()->query->get('debug')) {
      $form_state->setRedirect('sync.sync');
    }
  }

}
