<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sync\Plugin\SyncFetcherBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\sync\Plugin\SyncDataItems;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Plugin implementation of the 'http' sync resource.
 *
 * @SyncFetcher(
 *   id = "http",
 *   label = @Translation("HTTP"),
 * )
 */
class Http extends SyncFetcherBase implements ContainerFactoryPluginInterface {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a SyncFetcher object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle HTTP client.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'url' => '',
      'query' => [],
      'headers' => [],
      'as_content' => TRUE,
      'page_key' => 'page',
      'timeout' => 30,
    ] + parent::defaultSettings();
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
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->configuration['url'];
  }

  /**
   * {@inheritdoc}
   */
  public function setUrl($url) {
    $this->configuration['url'] = $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery() {
    return $this->configuration['query'];
  }

  /**
   * {@inheritdoc}
   */
  public function setQuery($query) {
    $this->configuration['query'] = $query;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryParameter($key) {
    return isset($this->configuration['query'][$key]) ? $this->configuration['query'][$key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueryParameter($key, $value) {
    $this->configuration['query'][$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaders() {
    return $this->configuration['headers'];
  }

  /**
   * {@inheritdoc}
   */
  public function setHeaders($header) {
    $this->configuration['headers'] = $header;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaderParameter($key) {
    return isset($this->configuration['headers'][$key]) ? $this->configuration['headers'][$key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeaderParameter($key, $value) {
    $this->configuration['headers'][$key] = $value;
  }
  
  /**
   * Float describing the total timeout of the request in seconds.
   * Use 0 to wait indefinitely (the default GuzzleHttp behavior).
   * Drupal's default is 30.
   *
   * @see \Drupal\Core\Http\ClientFactory::fromOptions()
   *
   * @return float
   */
  public function getTimeout() {
    return $this->configuration['timeout'];
  }

  /**
   * Set Http client timeout.
   *
   * Drupal default is 30. Use 0 for indefinite timeout.
   *
   * @param float $timeout
   *
   * @return \Drupal\sync\Plugin\SyncFetcher\Http
   */
  public function setTimeout($timeout = 30) {
    $this->configuration['timeout'] = $timeout;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    $options = [];
    $options['query'] = $this->getQuery();
    $options['headers'] = $this->getHeaders();
    $options['timeout'] = $this->getTimeout();
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    if ($this->isPageEnabled()) {
      $this->setQueryParameter($this->configuration['page_key'], $page_number);
    }
    $data = $this->httpClient->request('GET', $this->configuration['url'], $this->getOptions())->getBody();
    if ($this->configuration['as_content']) {
      $data = $data->getContents();
    }
    return $data;
  }

}
