<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\sync\Plugin\SyncFetcherBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\sync\Plugin\SyncDataItems;
use PDO;

/**
 * Plugin implementation of the 'SQL' sync resource.
 *
 * @SyncFetcher(
 *   id = "sql",
 *   label = @Translation("SQL"),
 * )
 */
class Sql extends SyncFetcherBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The query.
   *
   * @var \Drupal\Core\Database\Query\Select
   */
  protected $query;

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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->database = Database::getConnection($this->configuration['target'], $this->configuration['key']);
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'target' => 'default',
      'key' => NULL,
      'page_size' => 500,
    ] + parent::defaultSettings();
  }

  /**
   * Get database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  public function getDatabase() {
    return $this->database;
  }

  /**
   * Set query.
   *
   * @param \Drupal\Core\Database\Query\Select $query
   *   The query to set.
   *
   * @return $this
   */
  public function setQuery(Select $query) {
    $this->query = $this;
    return $this;
  }

  /**
   * Get the query.
   *
   * @return \Drupal\Core\Database\Query\Select
   *   The query.
   */
  public function getQuery() {
    return isset($this->query) ? $this->query : NULL;
  }

  /**
   * Prepares and returns a SELECT query object.
   *
   * @param string $table
   *   The base table for this query, that is, the first table in the FROM
   *   clause. This table will also be used as the "base" table for query_alter
   *   hook implementations.
   * @param string $alias
   *   (optional) The alias of the base table of this query.
   * @param array $options
   *   An array of options on the query.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   An appropriate SelectQuery object for this database connection. Note that
   *   it may be a driver-specific subclass of SelectQuery, depending on the
   *   driver.
   */
  public function select($table, $alias = NULL, array $options = []) {
    $this->query = $this->database->select($table, $alias, $options);
    $this->query->range(0, 10);
    return $this->query;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $data = [];
    if ($this->query instanceof Select) {
      $length = $this->getPageSize();
      $start = ($page_number - 1) * $length;
      $this->query->range($start, $length);
      $data = $this->query->execute()->fetchAll(PDO::FETCH_ASSOC);
    }
    return $data;
  }

}
