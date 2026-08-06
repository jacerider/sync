<?php

namespace Drupal\sync\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sync\SyncStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for locking or unlocking a synced entity.
 *
 * A locked entity is skipped by every sync that would otherwise update it, and
 * is excluded from cleanup, so local edits to it survive.
 */
class SyncLockForm extends ConfirmFormBase {

  /**
   * The sync storage.
   *
   * @var \Drupal\sync\SyncStorageInterface
   */
  protected $syncStorage;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity being locked or unlocked.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Whether the entity is currently locked.
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * Constructs a SyncLockForm object.
   *
   * @param \Drupal\sync\SyncStorageInterface $sync_storage
   *   The sync storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(SyncStorageInterface $sync_storage, EntityTypeManagerInterface $entity_type_manager) {
    $this->syncStorage = $sync_storage;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sync.storage'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sync_lock_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sync_entity_type = NULL, $sync_entity_id = NULL) {
    $this->entity = $this->loadEntity($sync_entity_type, $sync_entity_id);
    $records = $this->syncStorage->loadByEntity($this->entity);
    if (!$records) {
      throw new NotFoundHttpException();
    }
    foreach ($records as $record) {
      if (!empty($record->locked)) {
        $this->locked = TRUE;
        break;
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->locked) {
      return $this->t('Allow sync to update %label again?', [
        '%label' => $this->entity->label(),
      ]);
    }
    return $this->t('Lock %label so sync cannot change it?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    if ($this->locked) {
      return $this->t('Sync will start updating this from the source data again on its next run, overwriting any changes made here.');
    }
    return $this->t('Sync will stop updating this from the source data, and will not delete it, until it is unlocked. Any changes made here will be kept.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->locked ? $this->t('Unlock') : $this->t('Lock');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->entity->hasLinkTemplate('canonical')) {
      return $this->entity->toUrl();
    }
    return new Url('sync.sync');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->syncStorage->setEntityLocked($this->entity, !$this->locked);
    if ($this->locked) {
      $this->messenger()->addStatus($this->t('%label is no longer locked and will be updated by the next sync.', [
        '%label' => $this->entity->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('%label is locked and will no longer be changed by sync.', [
        '%label' => $this->entity->label(),
      ]));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Load the entity named in the route.
   *
   * The entity type is a route parameter rather than an upcast entity, so that
   * one route can serve every entity type sync tracks.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $entity_id
   *   The entity id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function loadEntity($entity_type, $entity_id) {
    if (!$entity_type || $entity_id === NULL || !$this->entityTypeManager->hasDefinition($entity_type)) {
      throw new NotFoundHttpException();
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity) {
      throw new NotFoundHttpException();
    }
    return $entity;
  }

}
