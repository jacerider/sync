<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Sync Fetcher plugins.
 */
abstract class SyncFetcherBase extends PluginBase implements SyncFetcherInterface, ContainerFactoryPluginInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The current page number.
   *
   * @var int
   */
  protected $pageNumber;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger) {
    $configuration += $this->defaultSettings();
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger->get('sync');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    $definition = $this->getPluginDefinition();
    return (string) $definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getContext() {
    $context = [
      '%plugin_id' => $this->getPluginId(),
      '%plugin_label' => $this->label(),
    ];
    return $context;
  }

  /**
   * Provides default settings.
   */
  protected function defaultSettings() {
    return [
      'page_enabled' => FALSE,
      'page_size' => 0,
      'page_limit' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setSetting($key, $value) {
    $this->configuration[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($key) {
    return isset($this->configuration[$key]) ? $this->configuration[$key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setSettings(array $settings) {
    $this->configuration = $settings;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPageEnabled() {
    return !empty($this->configuration['page_enabled']);
  }

  /**
   * {@inheritdoc}
   */
  public function setPageEnabled($status = TRUE) {
    $this->configuration['page_enabled'] = $status;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageSize() {
    return $this->configuration['page_size'];
  }

  /**
   * {@inheritdoc}
   */
  public function setPageSize($size) {
    $this->configuration['page_size'] = $size;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageLimit() {
    return $this->configuration['page_limit'];
  }

  /**
   * Set the page number.
   */
  protected function setPageNumber($page_number) {
    $this->pageNumber = $page_number;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageNumber() {
    return $this->pageNumber;
  }

  /**
   * {@inheritdoc}
   */
  public function setPageLimit($limit) {
    $this->configuration['page_limit'] = $limit;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function doFetch($page_number = 1, SyncDataItems $previous_data = NULL) {
    if (!$previous_data) {
      $previous_data = new SyncDataItems();
    }
    $this->setPageNumber($page_number);
    return $this->fetch($page_number, $previous_data);
  }

  /**
   * {@inheritdoc}
   */
  public function hasNextPage($page_number = 1, SyncDataItems $previous_data = NULL) {
    // Is enabled?
    if (!$this->isPageEnabled()) {
      return FALSE;
    }
    if ($previous_data) {
      // If there are no items in our previous request, we are done.
      if (!$previous_data->hasItems()) {
        return FALSE;
      }
      $page_size = $this->getPageSize();
      // If we have a set page size and there are items but they are less than
      // our page size, we are done.
      if ($page_size && $page_size > $previous_data->count()) {
        return FALSE;
      }
    }
    return $this->getPageLimit() === 0 || $page_number < $this->getPageLimit();
  }

  /**
   * Build the request.
   */
  abstract protected function fetch($page_number, SyncDataItems $previous_data);

}
