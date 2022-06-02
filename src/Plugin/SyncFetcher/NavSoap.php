<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;

/**
 * Plugin implementation of the 'nav_soap' sync resource.
 *
 * @SyncFetcher(
 *   id = "nav_soap",
 *   label = @Translation("Nav: Soap"),
 * )
 */
class NavSoap extends Soap {

  /**
   * The bookmark key value.
   *
   * @var string
   */
  protected $bookmarkKey = '';

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      // Example filters: [['Field' => 'Description', 'Criteria' => '*PIPE*']].
      'page_enabled' => TRUE,
      'page_size' => 100,
      'filters' => [],
      'resource_segment' => 'Page',
      'resource_function' => 'ReadMultiple',
      'resource_function_result' => 'ReadMultiple_Result',
      'bookmark_key' => 'Key',
    ] + parent::defaultSettings();
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
  public function addFilter($field, $criteria) {
    $this->configuration['filters'][$field] = [
      'Field' => $field,
      'Criteria' => $criteria,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceSegment() {
    return $this->configuration['resource_segment'];
  }

  /**
   * {@inheritdoc}
   */
  public function setResourceSegment($resource_segment) {
    $this->configuration['resource_segment'] = $resource_segment;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceFunction() {
    return $this->configuration['resource_function'];
  }

  /**
   * {@inheritdoc}
   */
  public function setResourceFunction($resource_function) {
    $this->configuration['resource_function'] = $resource_function;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceFunctionResult() {
    return $this->configuration['resource_function_result'];
  }

  /**
   * {@inheritdoc}
   */
  public function setResourceFunctionResult($resource_function_result) {
    $this->configuration['resource_function_result'] = $resource_function_result;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->configuration['url'] . '/' . $this->getResourceSegment() . '/' . $this->getResourceName();
  }

  /**
   * {@inheritdoc}
   */
  public function getParams() {
    $this->addParam('filter', array_values($this->getFilters()));
    $this->addParam('setSize', $this->getPageSize());
    if (!empty($this->bookmarkKey)) {
      $this->addParam('bookmarkKey', $this->bookmarkKey);
    }
    return parent::getParams();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $data = [];

    // Support paging.
    if ($previous_data->hasItems() && !empty($this->configuration['bookmark_key'])) {
      $item = $previous_data->last();
      $this->bookmarkKey = $item[$this->configuration['bookmark_key']];
    }

    $client = new \SoapClient($this->getUrl(), $this->getOptions());
    $function = $this->getResourceFunction();
    $function_result = $this->getResourceFunctionResult();
    $results = $client->{$function}($this->getParams());
    if ($function_result && is_object($results) && isset($results->{$function_result}->{$this->getResourceName()})) {
      $data = $results->{$function_result}->{$this->getResourceName()};
      if (!is_array($data)) {
        $data = [$data];
      }
    }

    return $data;
  }

}
