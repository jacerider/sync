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
 *   id = "odata_v4",
 *   label = @Translation("OData v4"),
 * )
 */
class OdataV4 extends Http {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'page_enabled' => TRUE,
      'page_size' => 100,
      'page_key' => '$skip',
      'login' => NULL,
      'password' => NULL,
      'resource_name' => NULL,
      'filters' => [],
    ] + parent::defaultSettings();
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
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return $this->configuration['filters'];
  }

  /**
   * {@inheritdoc}
   */
  public function setFilters($filters) {
    $this->configuration['filters'] = $filters;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetFilters() {
    return $this->setFilters([]);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFilterCriteria($criteria) {
    if (is_bool($criteria)) {
      $criteria = $criteria ? 'true' : 'false';
    }
    elseif (is_string($criteria)) {
      $criteria = "'" . $criteria . "'";
    }
    elseif (is_int($criteria)) {
      $criteria = "'" . $criteria . "'";
    }
    return $criteria;
  }

  /**
   * {@inheritdoc}
   */
  public function addFilter($field, $criteria, $operator = 'eq') {
    $this->configuration['filters'][$field] = $field . ' ' . $operator . ' ' . $this->prepareFilterCriteria($criteria);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addRawFilter($field, $criteria, $operator = 'eq') {
    $this->configuration['filters'][$field] = $field . ' ' . $operator . ' ' . $criteria;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addOrFilter($field, array $criterias, $operator = 'eq') {
    $filter = [];
    foreach ($criterias as $criteria) {
      $filter[] = $field . ' ' . $operator . ' ' . $this->prepareFilterCriteria($criteria);
    }
    $this->configuration['filters'][$field] = '(' . implode(' or ', $filter) . ')';
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeFilter($field) {
    unset($this->configuration['filters'][$field]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setResourceSegment($resource_segment) {
    // Do nothing. This is just here to satisfy easy migration from NavSoap.
  }

  /**
   * {@inheritdoc}
   */
  public function setQueryParameter($key, $value) {
    if ($key === $this->configuration['page_key']) {
      $value = $this->configuration['page_size'] * ($value - 1);
    }
    $this->configuration['query'][$key] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    $url = $this->configuration['url'];
    if ($this->configuration['resource_name']) {
      $url .= '/' . $this->configuration['resource_name'];
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaders() {
    $headers = $this->configuration['headers'];
    if ($this->configuration['login'] && $this->configuration['password']) {
      $headers['Authorization'] = 'Basic ' . base64_encode($this->configuration['login'] . ':' . $this->configuration['password']);
    }
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery() {
    $query = parent::getQuery();
    if (!empty($this->configuration['filters'])) {
      $query['$filter'] = implode(' and ', $this->configuration['filters']);
    }
    $query['$top'] = $this->configuration['page_size'];
    return $query;
  }

}
