<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;
use Drupal\sync\Plugin\SyncFetcherFormInterface;
use Drupal\sync\Plugin\SyncResourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'file' sync resource.
 *
 * @SyncFetcher(
 *   id = "file_upload",
 *   label = @Translation("File Upload"),
 * )
 */
class FileUpload extends SyncFetcherBase implements SyncFetcherFormInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a SyncResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'path' => '',
      'file_field_title' => 'CSV File',
      'extentions' => ['csv'],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $filepath = $this->configuration['path'];
    if (strpos($this->configuration['path'], '://') === FALSE) {
      $filepath = \Drupal::root() . '/' . $filepath;
    }
    if (!file_exists($filepath)) {
      $message = t('The file %filepath could not be found.', [
        '%filepath' => $filepath,
      ]);
      \Drupal::messenger()->addError($message);
      throw new \Exception($message);
    }
    return file_get_contents($filepath);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, SyncResourceInterface $resource) {
    $validators = [
      'file_validate_extensions' => $this->configuration['extentions'],
    ];
    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->configuration['file_field_title'],
      '#description' => [
        '#theme' => 'file_upload_help',
        '#upload_validators' => $validators,
      ],
      '#upload_validators' => $validators,
      '#upload_location' => 'private://sync/upload/',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state, SyncResourceInterface $resource) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface $form_state, SyncResourceInterface $resource) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')->load($form_state->getValue(['file', 0]));
    if ($file) {
      $resource->getFetcher()->setSetting('path', $file->getFileUri());
      $resource->runAsBatch();
      $file->delete();
    }
  }

}
