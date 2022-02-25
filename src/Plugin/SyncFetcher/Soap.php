<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 'soap' sync resource.
 *
 * @SyncFetcher(
 *   id = "soap",
 *   label = @Translation("Soap"),
 * )
 */
class Soap extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'url' => '',
      'options' => [],
      'params' => [],
      'resource_name' => NULL,
      'login' => NULL,
      'password' => NULL,
    ] + parent::defaultSettings();
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
  public function getResourceName() {
    return $this->configuration['resource_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function setResourceName($resource_name) {
    $this->configuration['resource_name'] = $resource_name;
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
  public function setQueryParameter($key, $value) {
    $this->configuration['query'][$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuth() {
    return !empty($this->configuration['login']) && !empty($this->configuration['password']) ? [
      'login' => $this->configuration['login'],
      'password' => $this->configuration['password'],
    ] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    $options = $this->configuration['options'];
    $options += $this->getAuth();
    $context = stream_context_create([
      'ssl' => [
        'verify_peer' => FALSE,
        'verify_peer_name' => FALSE,
        'allow_self_signed' => TRUE,
      ],
    ]);
    $options += [
      'stream_context' => $context,
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getParams() {
    return $this->configuration['params'];
  }

  /**
   * {@inheritdoc}
   */
  public function setParams($params) {
    $this->configuration['params'] = $params;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetParams() {
    return $this->setParams([]);
  }

  /**
   * {@inheritdoc}
   */
  public function addParam($key, $value) {
    $this->configuration['params'][$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $client = new \SoapClient($this->getUrl(), $this->getOptions());
    $data = [];
    if (!empty($this->getResourceName())) {
      $results = $client->__soapCall($this->getResourceName(), [
        'parameters' => $this->getParams(),
      ]);
      if (is_object($results) && isset($results->{$this->getResourceName() . 'Result'})) {
        $data = $results->{$this->getResourceName() . 'Result'};
      }
    }
    return $data;
  }

}
