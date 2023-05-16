<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sync\SyncClientManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\sync\SyncSkipException;
use Drupal\sync\SyncFailException;
use Drupal\sync\SyncEntityProviderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sync\SyncStorageInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\sync\SyncIgnoreException;
use Drupal\sync\SyncJobQueueReleaseException;
use Psr\Log\LogLevel;

/**
 * Base class for Sync Resource plugins.
 */
abstract class SyncResourceBase extends PluginBase implements SyncResourceInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The queue manager.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The sync client manager.
   *
   * @var \Drupal\sync\SyncClientManagerInterface
   */
  protected $syncClientManager;

  /**
   * The sync fetcher manager.
   *
   * @var \Drupal\sync\Plugin\SyncFetcherManager
   */
  protected $syncFetcherManager;

  /**
   * The sync parser manager.
   *
   * @var \Drupal\sync\Plugin\SyncParserManager
   */
  protected $syncParserManager;

  /**
   * The sync storage.
   *
   * @var \Drupal\sync\SyncStorageInterface
   */
  protected $syncStorage;

  /**
   * The sync entity provider.
   *
   * @var \Drupal\sync\SyncEntityProviderInterface
   */
  protected $syncEntityProvider;

  /**
   * If run should 'run' event when data is empty.
   *
   * @var bool
   */
  protected $runOnEmpty = FALSE;

  /**
   * The maximum number of items to show when debugging.
   *
   * @var int
   */
  protected $maxDebug = 100;

  /**
   * The queue id.
   *
   * @var string
   */
  protected $queueId;

  /**
   * Cached context for currently processed item.
   *
   * @var array
   */
  protected $itemContext = [];

  /**
   * Constructs a SyncResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state storage service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The item queue object.
   * @param \Drupal\sync\SyncClientManagerInterface $sync_client_manager
   *   The sync client manager.
   * @param \Drupal\sync\Plugin\SyncFetcherManager $sync_fetcher_manager
   *   The sync fetcher manager.
   * @param \Drupal\sync\Plugin\SyncParserManager $sync_parser_manager
   *   The sync parser manager.
   * @param \Drupal\sync\SyncStorageInterface $sync_storage
   *   The sync storage.
   * @param \Drupal\sync\SyncEntityProviderInterface $sync_entity_provider
   *   The sync entity provider.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LoggerChannelFactoryInterface $logger, QueueInterface $queue, SyncClientManagerInterface $sync_client_manager, SyncFetcherManager $sync_fetcher_manager, SyncParserManager $sync_parser_manager, SyncStorageInterface $sync_storage, SyncEntityProviderInterface $sync_entity_provider) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->logger = $logger->get('sync_' . $plugin_id);
    $this->queue = $queue;
    $this->syncClientManager = $sync_client_manager;
    $this->syncFetcherManager = $sync_fetcher_manager;
    $this->syncParserManager = $sync_parser_manager;
    $this->syncStorage = $sync_storage;
    $this->syncEntityProvider = $sync_entity_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('state'),
      $container->get('logger.factory'),
      $container->get('queue')->get('sync_' . $plugin_id),
      $container->get('plugin.manager.sync_client'),
      $container->get('plugin.manager.sync_fetcher'),
      $container->get('plugin.manager.sync_parser'),
      $container->get('sync.storage'),
      $container->get('sync.entity_provider')
    );
  }

  /**
   * The value to use as the unique ID.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The item about to be processed.
   *
   * @return string
   *   The value to use as the unique ID.
   */
  abstract protected function id(SyncDataItem $item);

  /**
   * {@inheritdoc}
   */
  public function label() {
    $definition = $this->getPluginDefinition();
    return (string) $definition['label'];
  }

  /**
   * Get the entity type id.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType() {
    $definition = $this->getPluginDefinition();
    return $definition['entity_type'];
  }

  /**
   * Get the entity bundle.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The data about to be processed.
   *
   * @return string
   *   The bundle.
   */
  public function getBundle(SyncDataItem $item = NULL) {
    $definition = $this->getPluginDefinition();
    return !empty($definition['bundle']) ? $definition['bundle'] : $this->getEntityType();
  }

  /**
   * Get initial values.
   *
   * The initial values are used when loading/creating the entity that will be
   * used during the import process. If this is the first time the entity has
   * been processed, these values can be used to match an un-synced entity with
   * a pre-existing entity.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The data about to be processed.
   *
   * @return array
   *   The initial values.
   */
  public function getInitialValues(SyncDataItem $item) {
    return [];
  }

  /**
   * The sync group.
   *
   * Used to segment sync data that uses the same ID. Records a seperate
   * changed timestamp for each id => group so that sync providers can
   * manage their own data without overlap. Typically this is handled
   * automatically and can be ignored.
   *
   * @return string
   *   The sync group.
   */
  public function getGroup() {
    return $this->getPluginId();
  }

  /**
   * Determine if sync should run even if there are no results.
   */
  protected function shouldRunOnEmpty() {
    return $this->runOnEmpty;
  }

  /**
   * Determine if sync uses cleanup.
   */
  public function usesCleanup() {
    $definition = $this->getPluginDefinition();
    return $definition['cleanup'] == TRUE;
  }

  /**
   * Determine if sync uses reset.
   *
   * Reset will reset the last run date/time and can be used with resources that
   * track last run to dermine which items to act on to reset and sync all.
   */
  public function usesReset() {
    $definition = $this->getPluginDefinition();
    return $definition['reset'] == TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() {
    if (!isset($this->client)) {
      $this->client = $this->syncClientManager->getDefinition($this->getPluginDefinition()['client']);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function getFetcher() {
    if (!isset($this->fetcher)) {
      $client = $this->getClient();
      $this->fetcher = $this->syncFetcherManager->createInstance($client['fetcher'], $client['fetcher_settings']);
      $this->alterFetcher($this->fetcher);
    }
    return $this->fetcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getParser() {
    if (!isset($this->parser)) {
      $client = $this->getClient();
      $this->parser = $this->syncParserManager->createInstance($client['parser'], $client['parser_settings']);
      $this->alterParser($this->parser);
    }
    return $this->parser;
  }

  /**
   * Provides oportuniity to alter the fetcher.
   *
   * @param \Drupal\sync\Plugin\SyncFetcherInterface $fetcher
   *   The sync fetcher plugin.
   */
  protected function alterFetcher(SyncFetcherInterface $fetcher) {}

  /**
   * Provides oportuniity to alter the parser.
   *
   * @param \Drupal\sync\Plugin\SyncParserInterface $parser
   *   The sync parser plugin.
   */
  protected function alterParser(SyncParserInterface $parser) {}

  /**
   * Prepare the raw data before it is added to SyncDataItems.
   *
   * @param array $data
   *   The data items collection.
   *
   * @return array
   *   The data.
   */
  protected function prepareData(array $data) {
    return $data;
  }

  /**
   * Alter raw data before it has been processed.
   *
   * @param \Drupal\sync\Plugin\SyncDataItems $data
   *   The data items collection.
   */
  protected function alterItems(SyncDataItems $data) {}

  /**
   * Alter raw data item before it has been processed.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $data
   *   The data items collection.
   */
  protected function alterItem(SyncDataItem $data) {}

  /**
   * {@inheritdoc}
   */
  public function build(array $context = []) {
    $context += $this->getContext();
    $this->log(LogLevel::DEBUG, '%plugin_label: Start', $this->getContext());
    if ($this->queue->numberOfItems() > 0) {
      // When running as cron, we need to make sure any items still remaining in
      // the queue are processed before we start a new sync. We need to make
      // doEnd and doCleanup are removed as they will be added back in by the
      // new jobs.
      if (!empty($context['%sync_as_cron'])) {
        while ($item = $this->queue->claimItem()) {
          if (!in_array($item->data['op'], ['doEnd', 'doCleanup'])) {
            $items[] = $item;
          }
          $this->queue->deleteItem($item);
        }
        foreach ($items as $item) {
          $this->queue->createItem($item->data);
        }
      }
      // When we use cleanup or we are running as batch, we reset the queue.
      elseif ($this->usesCleanup() || !empty($context['%sync_as_batch'])) {
        // Only clean the queue if we use cleanup.
        $this->queue->deleteQueue();
      }
    }
    $this->setStartTime();
    $this->buildJobs($context);
    return $this;
  }

  /**
   * Build data and add to job queue.
   *
   * @param array $context
   *   Additional context.
   */
  protected function buildJobs(array $context) {
    $context = NestedArray::mergeDeep($this->getContext(), $context);
    $this->log(LogLevel::DEBUG, '%plugin_label: Build Data', $context);
    try {
      $data = $this->fetchData(NULL, $context);
      if ($data->hasItems() || $this->shouldRunOnEmpty()) {
        $this->queueStart($context);
        $this->queueData($data, $context);
        if ($data->hasNextPage()) {
          $this->queuePage($data, $context);
        }
        else {
          $this->queueEnd($context);
        }
      }
      else {
        $this->queueStart($context);
        $this->queueEnd($context);
      }
    }
    catch (SyncIgnoreException $e) {
      // Do nothing.
    }
    catch (SyncSkipException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
    }
    catch (SyncFailException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Add job that handles sync start.
   *
   * @param array $context
   *   Additional context.
   */
  protected function queueStart(array $context = []) {
    $context = NestedArray::mergeDeep($this->getContext(), $context);
    $this->log(LogLevel::DEBUG, '%plugin_label: Add Job: Queue Start', $context);
    $this->queue->createItem([
      'plugin_id' => $this->getPluginId(),
      'op' => 'doStart',
      'data' => $context,
    ]);
  }

  /**
   * Given items, create jobs.
   *
   * @param \Drupal\sync\Plugin\SyncDataItems $items
   *   The data used on the previous request. Used when paging.
   * @param array $context
   *   Additional context.
   */
  protected function queueData(SyncDataItems $items, array $context = []) {
    $context = NestedArray::mergeDeep($this->getContext(), $context);
    $this->log(LogLevel::DEBUG, '%plugin_label: Add Job: Queue Data', $context);
    foreach ($items->items() as $item) {
      $this->queue->createItem([
        'plugin_id' => $this->getPluginId(),
        'op' => 'doProcess',
        'data' => [
          'item' => $item,
          'context' => $context,
        ],
      ]);
    }
  }

  /**
   * Add a page batch item.
   *
   * @param \Drupal\sync\Plugin\SyncDataItems $items
   *   The data used on the previous request. Used when paging.
   * @param array $context
   *   Additional context.
   */
  protected function queuePage(SyncDataItems $items, array $context = []) {
    $context = NestedArray::mergeDeep($this->getContext(), $context);
    $this->log(LogLevel::DEBUG, '%plugin_label: Add Job: Queue Page', $context);
    $context['%page'] = isset($context['%page']) ? $context['%page'] + 1 : 2;
    $context['%fetcher_settings'] = $this->getFetcher()->getSettings();
    $this->queue->createItem([
      'plugin_id' => $this->getPluginId(),
      'op' => 'doPage',
      'data' => [
        'items' => $items,
        'context' => $context,
      ],
    ]);
  }

  /**
   * Add a sub-resource queue.
   *
   * @param string $resource_id
   *   The resource id.
   * @param array $fetcher_settings
   *   The fetcher settings.
   */
  protected function queueSubResource($resource_id, array $fetcher_settings = []) {
    $this->log(LogLevel::DEBUG, '%plugin_label: Add Job: Queue Sub Resource', $this->getContext());
    $context = [
      '%sync_as_batch' => !empty($this->itemContext['%sync_as_batch']),
      '%parent_plugin_id' => $this->itemContext['%parent_plugin_id'] ?? $this->getPluginId(),
      '%fetcher_settings' => $fetcher_settings,
    ];
    $this->queue->createItem([
      'plugin_id' => $this->getPluginId(),
      'op' => 'doSubResource',
      'data' => [
        'resource_id' => $resource_id,
        'context' => $context,
      ],
    ]);
  }

  /**
   * Create a job that will finish the sync.
   *
   * If cleanup is enabled, this will offload final job creation to the
   * doCleanup job runner.
   *
   * @param array $context
   *   Additional context.
   */
  protected function queueEnd(array $context = []) {
    $context = NestedArray::mergeDeep($this->getContext(), $context);
    $this->log(LogLevel::DEBUG, '%plugin_label: Add Job: Queue End', $context);
    if ($this->usesCleanup()) {
      $this->queue->createItem([
        'plugin_id' => $this->getPluginId(),
        'op' => 'doCleanup',
        'data' => $context,
      ]);
    }
    else {
      $this->queue->createItem([
        'plugin_id' => $this->getPluginId(),
        'op' => 'doEnd',
        'data' => $context,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function runJobs() {
    $this->log(LogLevel::DEBUG, '%plugin_label: Run Jobs', $this->getContext());
    while ($this->queue->numberOfItems() > 0) {
      $this->runJob();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function runJob() {
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('sync_' . $this->pluginId);
    $item = $this->queue->claimItem();
    if ($item) {
      try {
        $queue_worker->processItem($item->data);
        $this->queue->deleteItem($item);
      }
      catch (SyncIgnoreException $e) {
        $this->queue->deleteItem($item);
      }
      catch (SyncSkipException $e) {
        $this->queue->deleteItem($item);
      }
      catch (SyncFailException $e) {
        $this->queue->deleteItem($item);
      }
      catch (SyncJobQueueReleaseException $e) {
        $this->queue->releaseItem($item);
      }
      catch (\Exception $e) {
        $this->queue->deleteItem($item);
      }
    }
    return $this;
  }

  /**
   * Run jobs as a batch.
   */
  public function runAsBatch() {
    if (\Drupal::request()->query->get('debug')) {
      $this->debug();
      return;
    }
    $this->build([
      '%sync_as_batch' => TRUE,
    ]);
    $this->runBatch();
  }

  /**
   * Run jobs as a batch.
   */
  public function runAsSubResource(array $context) {
    $this->buildJobs($context);
  }

  /**
   * Take each item and build a batch.
   *
   * @param string $item_callback
   *   The item callback that will be run by the batch to process an item.
   * @param string $title
   *   The title of the batch.
   */
  protected function runBatch($item_callback = 'runBatchProxy', $title = 'Importing Data') {
    $item_count = $this->queue->numberOfItems();
    if ($item_count) {
      $class_name = get_class($this);
      $batch = [
        'title' => t('Batch: %title', ['%title' => $title]),
        'operations' => [],
        'init_message' => t('Commencing'),
        'progress_message' => t('Please do not close this window until import is complete. Task @current out of @total.'),
        'error_message' => t('An error occurred during processing'),
      ];
      for ($i = 0; $i < $item_count; $i++) {
        $batch['operations'][] = [
          [$class_name, $item_callback],
          [$this->pluginId],
        ];
      }
      batch_set($batch);
    }
  }

  /**
   * Finishes an "execute tasks" batch.
   *
   * @param string $plugin_id
   *   The plugin id.
   * @param array $context
   *   Additional context that can be passed to the build.
   */
  public static function runBatchProxy($plugin_id, array &$context) {
    \Drupal::service('plugin.manager.sync_resource')->createInstance($plugin_id)->runJob();
  }

  /**
   * The job called at the start of a sync.
   *
   * @param array $context
   *   Additional context that can be passed to the build.
   */
  public function doStart(array $context) {
    $this->log(LogLevel::INFO, '%plugin_label: Run Job: Start', $context);
    $this->resetProcessCount('success')
      ->resetProcessCount('skip')
      ->resetProcessCount('fail');
    \Drupal::service('plugin.manager.sync_resource')->setLastRunStart($this->pluginDefinition, $this->getStartTime());
  }

  /**
   * {@inheritdoc}
   */
  public function manualProcessMultiple(array $items) {
    $results = [];
    foreach ($items as $item) {
      if ($result = $this->manualProcess($item)) {
        $results[] = $result;
      }
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function manualProcess(SyncDataItem $extend_item = NULL) {
    try {
      $results = [];
      $data = $this->fetchData();
      foreach ($data as $item) {
        /** @var \Drupal\sync\Plugin\SyncDataItem $item */
        if ($extend_item) {
          foreach ($extend_item as $key => $value) {
            $item->set($key, $value);
          }
        }
        $result = $this->doProcess([
          'item' => $item,
          'context' => $this->getContext(),
          '%sync_as_job' => FALSE,
        ]);
        if ($result) {
          $results[] = $result;
        }
      }
      return $results;
    }
    catch (SyncIgnoreException $e) {
      return $results;
    }
    catch (SyncSkipException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
      return $results;
    }
    catch (SyncFailException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
      return $results;
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
      return $results;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function manualProcessItem(SyncDataItem $item) {
    try {
      return $this->doProcess([
        'item' => $item,
        'context' => $this->getContext(),
        '%sync_as_job' => FALSE,
      ]);
    }
    catch (SyncIgnoreException $e) {
      return;
    }
    catch (SyncSkipException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
    }
    catch (SyncFailException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * The job called for each item of a sync.
   *
   * @param array $data
   *   Is an associative array containing
   *   ['item' => SyncDataItem, 'context' => []].
   */
  public function doProcess(array $data) {
    /** @var \Drupal\sync\Plugin\SyncDataItem $item */
    $entity = NULL;
    $item = $data['item'];
    $context = $data['context'];
    try {
      $this->prepareItem($item);
      $id = $this->id($item);
      $context['%id'] = $id;
      $this->itemContext = $context;
      $entity = $this->loadEntity($item);
      if ($entity) {
        if ($this->accessEntity($entity)) {
          $this->processItem($entity, $item);
          $success = $this->saveItem($entity, $item);
          $context['%bundle'] = $entity->getEntityTypeId();
          $context['%entity_id'] = $entity->id();
          $this->incrementProcessCount('success');
          switch ($success) {
            case SAVED_NEW:
              $this->log(LogLevel::INFO, '%plugin_label: NEW: %id -> %entity_type:%entity_id', $context);
              break;

            case SAVED_UPDATED:
              $this->log(LogLevel::INFO, '%plugin_label: UPDATE: %id -> %entity_type:%entity_id', $context);
              break;

            default:
              $this->log(LogLevel::INFO, '%plugin_label: SUCCESS: %id -> %entity_type:%entity_id', $context);
              break;
          }
          return $entity;
        }
        else {
          throw new SyncSkipException('Entity was prevented from being synced.');
        }
      }
      else {
        throw new SyncFailException('Entity could not be loaded.');
      }
    }
    catch (SyncIgnoreException $e) {
      if ($entity && !$entity->isNew()) {
        $this->syncStorage->saveEntity($entity);
      }
      switch ($e->getCode()) {
        case 0:
          $this->incrementProcessCount('skip');
          break;

        case 1:
          $this->incrementProcessCount('success');
          break;

        case 2:
          $this->incrementProcessCount('fail');
          break;
      }
      if (empty($data['%sync_as_job'])) {
        throw new SyncIgnoreException($e->getMessage());
      }
    }
    catch (SyncSkipException $e) {
      if ($entity && !$entity->isNew()) {
        $this->syncStorage->saveEntity($entity);
      }
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::WARNING, '%plugin_label: %id: Process Item Skip: %error', $context);
      $this->incrementProcessCount('skip');
      if (empty($data['%sync_as_job'])) {
        throw new SyncSkipException($e->getMessage());
      }
    }
    catch (SyncFailException $e) {
      if ($entity && !$entity->isNew()) {
        $this->syncStorage->saveEntity($entity);
      }
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '%plugin_label: %id: Process Item Fail: %error', $context);
      $this->incrementProcessCount('fail');
      if (empty($data['%sync_as_job'])) {
        throw new SyncFailException($e->getMessage());
      }
    }
    catch (\Exception $e) {
      if ($entity && !$entity->isNew()) {
        $this->syncStorage->saveEntity($entity);
      }
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '%plugin_label: %id: Process Item Error: %error', $context);
      $this->incrementProcessCount('fail');
      if (empty($data['%sync_as_job'])) {
        throw new \Exception($e->getMessage());
      }
    }
  }

  /**
   * The job called for each page of a sync.
   *
   * @param array $data
   *   Is an associative array containing
   *   ['items' => SyncDataItems, 'context' => []].
   */
  public function doPage(array $data) {
    $context = [
      '@success' => $this->getProcessCount('success'),
      '@skip' => $this->getProcessCount('skip'),
      '@fail' => $this->getProcessCount('fail'),
    ] + $data['context'];
    try {
      $this->log(LogLevel::INFO, '%plugin_label: Fetching [Page: %page | Success: @success | Skipped: @skip | Failed: @fail]', $context);
      $this->getFetcher()->setSettings($context['%fetcher_settings']);
      $data = $this->fetchData($data['items'], $context);
      if ($data->hasItems()) {
        $this->queueData($data, $context);
        if ($data->hasNextPage()) {
          $this->queuePage($data, $context);
        }
        else {
          $this->queueEnd($context);
        }
      }
      else {
        $this->queueEnd($context);
      }
      if (!empty($context['%sync_as_batch'])) {
        $this->runBatch();
      }
    }
    catch (SyncIgnoreException $e) {
      // Do nothing.
    }
    catch (SyncSkipException $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::WARNING, '%plugin_label: Page %page Skip: %error', $context);
      if (!empty($context['%sync_as_batch'])) {
        \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
      }
    }
    catch (SyncFailException $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '%plugin_label: Page %page Fail: %error', $context);
      if (!empty($context['%sync_as_batch'])) {
        \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
      }
    }
    catch (\Exception $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '%plugin_label: Page %page Error: %error', $context);
      if (!empty($context['%sync_as_batch'])) {
        \Drupal::messenger()->addMessage($e->getMessage(), 'error');
      }
      if ($data['%sync_as_job']) {
        // When a page request fails, we need to release the job so that it
        // will be run again.
        throw new SyncJobQueueReleaseException();
      }
    }
  }

  /**
   * The job called for each sub-resource of a sync.
   *
   * @param array $data
   *   Is an associative array containing
   *   ['items' => SyncDataItems, 'context' => []].
   */
  public function doSubResource(array $data) {
    $plugin_id = $data['resource_id'];
    $context = NestedArray::mergeDeep($this->getContext(), $data['context']);
    /** @var \Drupal\sync\Plugin\SyncResourceInterface $instance */
    $instance = \Drupal::service('plugin.manager.sync_resource')->createInstance($plugin_id);
    $instance->runAsSubResource($context);
  }

  /**
   * The job called to determinie if cleanup is ncessary.
   *
   * @param array $context
   *   Additional context that can be passed to the build.
   */
  public function doCleanup(array $context) {
    $this->log(LogLevel::DEBUG, '%plugin_label: Run Job: Cleanup', $context);
    try {
      $query = $this->syncStorage->getDataQuery($this->getGroup());
      $this->cleanupQueryAlter($query, $context);
      $results = $query->execute()->fetchAll();
      if (!empty($results)) {
        foreach ($results as $sync) {
          $this->queue->createItem([
            'plugin_id' => $this->getPluginId(),
            'op' => 'doClean',
            'data' => [
              'sync' => (array) $sync,
              'context' => $context,
            ],
          ]);
        }
      }
      $this->queue->createItem([
        'plugin_id' => $this->getPluginId(),
        'op' => 'doEnd',
        'data' => $context,
      ]);
      if (!empty($context['%sync_as_batch'])) {
        $this->runBatch();
      }
    }
    catch (\Exception $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '[Sync Cleanup: %plugin_label] FAIL: %entity_type:%bundle with data %data. Error: %error', $context);
      if ($context['%sync_as_job']) {
        throw new \Exception($e->getMessage());
      }
    }
  }

  /**
   * The job called for cleaning up an item.
   *
   * @param array $data
   *   Is an associative array containing
   *   ['sync' => [], 'context' => []].
   */
  public function doClean(array $data) {
    $sync = $data['sync'];
    $context = $data['context'];
    $context['%id'] = $sync['id'];
    $context['%entity_id'] = $sync['entity_id'];
    $context['%entity_type'] = $sync['entity_type'];
    $this->log(LogLevel::INFO, '%plugin_label: Clean: %id -> %entity_type:%entity_id', $context);
    try {
      $entity = $this->syncStorage->loadEntity($sync['id'], $sync['entity_type']);
      if ($entity) {
        $context['%bundle'] = $entity->getEntityTypeId();
        $context['%entity_id'] = $entity->id();
        if ($this->accessEntity($entity)) {
          $this->cleanItem($entity, $sync, $context);
          $remaining = $this->queue->numberOfItems();
          if ($remaining % 50 === 1) {
            $this->log(LogLevel::INFO, '%plugin_label: Cleaning [Remaining: @remaining | Success: @success | Skipped: @skip | Failed: @fail]', $context + [
              '@remaining' => $remaining,
              '@success' => $this->getProcessCount('success'),
              '@skip' => $this->getProcessCount('skip'),
              '@fail' => $this->getProcessCount('fail'),
            ]);
          }
        }
        else {
          throw new SyncSkipException('Entity was prevented from being cleaned.');
        }
      }
      else {
        throw new SyncFailException('Entity could not be loaded.');
      }
    }
    catch (SyncIgnoreException $e) {
      if ($data['%sync_as_job']) {
        throw new SyncIgnoreException($e->getMessage());
      }
    }
    catch (SyncSkipException $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::WARNING, '%plugin_label: Clean Item Skip: %error', $context);
      if ($data['%sync_as_job']) {
        throw new SyncSkipException($e->getMessage());
      }
    }
    catch (SyncFailException $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '%plugin_label: Clean Item Fail: %error', $context);
      if ($data['%sync_as_job']) {
        throw new SyncFailException($e->getMessage());
      }
    }
    catch (\Exception $e) {
      $context['%error'] = $e->getMessage();
      $this->log(LogLevel::ERROR, '%plugin_label: Clean Item Error: %error', $context);
      if ($data['%sync_as_job']) {
        throw new \Exception($e->getMessage());
      }
    }
  }

  /**
   * Job called at the end of a sync.
   *
   * @param array $context
   *   Additional context that can be passed to the build.
   */
  public function doEnd(array $context) {
    $this->log(LogLevel::DEBUG, '%plugin_label: Run Job: End', $context);
    $context += [
      '%success' => $this->getProcessCount('success'),
      '%skip' => $this->getProcessCount('skip'),
      '%fail' => $this->getProcessCount('fail'),
    ];
    if (!empty($context['%parent_plugin_id'])) {
      // We are processing this as part of a parent. We will reply on the parent
      // process.
      return;
    }
    if ($this->queue->numberOfItems() > 1) {
      // Additional items have been added to the queue. Continue processing.
      $this->queueEnd($context);
      if (!empty($context['%sync_as_batch'])) {
        $this->runBatch();
      }
      return;
    }
    $this->resetProcessCount('success')
      ->resetProcessCount('skip')
      ->resetProcessCount('fail');
    $this->log(LogLevel::NOTICE, '%plugin_label: Completed [Success: %success, Skip: %skip, Fail: %fail]', [
      '%plugin_label' => (string) $context['%plugin_label'],
      '%success' => (int) $context['%success'],
      '%skip' => (int) $context['%skip'],
      '%fail' => (int) $context['%fail'],
    ]);
    \Drupal::service('plugin.manager.sync_resource')->setLastRunEnd($this->pluginDefinition);
    if (!empty($context['%fail'])) {
      $email_fail = $this->getErrorEmail();
      if ($email_fail) {
        /** @var \Drupal\Core\Mail\MailManagerInterface $mail_manager */
        $mail_manager = \Drupal::service('plugin.manager.mail');
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $context['%subject'] = t('Sync Failed: %plugin_label', $context, ['langcode' => $langcode]);
        $message = t('The %plugin_label sync had %fail failures. [Success: %success, Skip: %skip, Fail: %fail]', $context, ['langcode' => $langcode]);
        $url = Url::fromRoute('sync.log', ['plugin_id' => $context['%plugin_id']])->setAbsolute(TRUE)->toString();
        $context['%message'] = strip_tags($message . "\n\n" . $url);
        $mail_manager->mail('sync', 'end_fail', $email_fail, $langcode, $context);
      }
    }
  }

  /**
   * Get the error email.
   *
   * @return string
   *   The email address.
   */
  protected function getErrorEmail() {
    return \Drupal::config('sync.settings')->get('email_fail');
  }

  /**
   * Load or create the entity to be processed.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The data about to be processed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity about to be processed.
   */
  public function loadEntity(SyncDataItem $item) {
    $entity_type = $this->getEntityType();
    $bundle = $this->getBundle($item);
    if ($entity_type && $bundle) {
      return $this->syncEntityProvider->getOrNew($this->id($item), $entity_type, $bundle, $this->getInitialValues($item), $this->getGroup());
    }
    return NULL;
  }

  /**
   * Check if entity should be able to be modified via sync.
   *
   * @return bool
   *   If TRUE, entity will be synced.
   */
  public function accessEntity(EntityInterface $entity) {
    return empty($entity->syncIsLocked);
  }

  /**
   * Prepare an item for processing.
   *
   * Adding/removing/altering data on a per-item level can be done here.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The data about to be processed.
   */
  protected function prepareItem(SyncDataItem $item) {}

  /**
   * Extending classes should provide the item process method.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The data about to be processed.
   */
  protected function processItem(EntityInterface $entity, SyncDataItem $item) {}

  /**
   * Save the entity and store record in sync table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to process.
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The data about to be processed.
   *
   * @return bool
   *   A bool representing success.
   */
  protected function saveItem(EntityInterface $entity, SyncDataItem $item) {
    return $entity->save();
  }

  /**
   * Will clean an entity from the site.
   *
   * By default, the entity is deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be cleaned.
   * @param array $sync
   *   The sync database values. Includes id, group, changed, entity_type,
   *   entity_id, locked.
   * @param array $context
   *   Additional context that can be passed to the build.
   */
  protected function cleanItem(EntityInterface $entity, array $sync, array $context) {
    $status = $entity->delete();
    if ($status) {
      $this->log(LogLevel::INFO, '%plugin_label: Clean: %id -> %entity_type:%entity_id', $context);
    }
  }

  /**
   * Allow altering of cleanup query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query.
   * @param array $context
   *   Additional context that can be passed to the build.
   */
  protected function cleanupQueryAlter(SelectInterface $query, array $context) {
    $query->condition('sync_data.changed', $this->getStartTime(), '<');
    $query->condition('sync.locked', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function fetchData(SyncDataItems $previous_data = NULL, array $context = []) {
    if (!empty($context['%parent_plugin_id'])) {
      // Use parent plugin for queue and logger.
      $this->queue = \Drupal::service('queue')->get('sync_' . $context['%parent_plugin_id']);
      $this->logger = \Drupal::service('logger.factory')->get('sync_' . $context['%parent_plugin_id']);
    }
    if (!empty($context['%fetcher_settings'])) {
      // Use fetcher settings if supplied.
      $this->getFetcher()->setSettings(NestedArray::mergeDeep($this->getFetcher()->getSettings(), $context['%fetcher_settings']));
    }
    $fetcher = $this->getFetcher();
    $page = $context['%page'] ?? 1;
    $data = $fetcher->doFetch($page, $previous_data);
    $data = $this->getParser()->doParse($data, $fetcher);
    $data = $this->prepareData($data);
    $data = new SyncDataItems($data);
    $data->setHasNextPage($fetcher->hasNextPage($page, $data));
    $this->alterItems($data);
    foreach ($data as $item) {
      try {
        $this->alterItem($item);
      }
      catch (SyncSkipException $e) {
        $data->removeItem($item);
        \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
      }
    }
    return $data;
  }

  /**
   * Debug a single request.
   */
  public function debug() {
    try {
      $data = $this->fetchData();
      if ($data->hasItems() && $data->count() > $this->maxDebug) {
        $data->slice(0, $this->maxDebug);
      }
      foreach ($data as &$item) {
        try {
          $this->prepareItem($item);
        }
        catch (SyncIgnoreException $e) {
          // Do nothing.
        }
        catch (SyncSkipException $e) {
          \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
        }
        catch (SyncFailException $e) {
          \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
        }
        catch (\Exception $e) {
          \Drupal::messenger()->addMessage($e->getMessage(), 'error');
        }
      }
      if (\Drupal::service('module_handler')->moduleExists('kint')) {
        ksm($data->toArray());
      }
      elseif (function_exists('kint')) {
        ksm($data->toArray());
      }
      else {
        \Drupal::messenger()->addMessage('<pre>' . print_r($data->toArray(), TRUE) . '</pre>');
      }
    }
    catch (SyncIgnoreException $e) {
      // Do nothing.
    }
    catch (SyncSkipException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
    }
    catch (SyncFailException $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'warning');
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Get the initial context.
   */
  protected function getContext() {
    $context = [
      '%plugin_id' => $this->getPluginId(),
      '%plugin_label' => $this->label(),
      '%entity_type' => $this->getEntityType(),
      '%id' => 'na',
      '%bundle' => 'na',
      '%page' => 1,
    ];
    return $context;
  }

  /**
   * Get the state key.
   *
   * @return string
   *   The state key.
   */
  protected function getStateKey() {
    return 'sync.resource.' . $this->pluginId;
  }

  /**
   * Get the start time.
   */
  protected function getStartTime() {
    return $this->state->get($this->getStateKey() . '.start');
  }

  /**
   * Set the start time.
   */
  protected function setStartTime() {
    $start = \Drupal::time()->getRequestTime();
    $this->state->set($this->getStateKey() . '.start', $start);
    return $this;
  }

  /**
   * Set the queue count.
   */
  protected function getQueueCount() {
    $count = $this->state->get($this->getStateKey() . '.queue');
    return $count ? $count : 1;
  }

  /**
   * Increment the queue count.
   */
  protected function incrementQueueCount() {
    $count = $this->getQueueCount();
    $this->state->set($this->getStateKey() . '.queue', $count + 1);
    return $this;
  }

  /**
   * Decrease the queue count.
   */
  protected function decrementQueueCount() {
    $count = $this->getQueueCount();
    $this->state->set($this->getStateKey() . '.queue', $count - 1);
    return $this;
  }

  /**
   * Reset the page count.
   */
  protected function resetQueueCount() {
    $this->state->delete($this->getStateKey() . '.queue');
    return $this;
  }

  /**
   * Set the process count.
   */
  protected function getProcessCount($type = 'success') {
    $count = $this->state->get($this->getStateKey() . '.process.' . $type);
    return $count ? (int) $count : 0;
  }

  /**
   * Increment the process count.
   */
  protected function incrementProcessCount($type = 'success') {
    $count = $this->getProcessCount($type);
    $this->state->set($this->getStateKey() . '.process.' . $type, $count + 1);
    return $this;
  }

  /**
   * Reset the process count.
   */
  protected function resetProcessCount($type = 'success') {
    $this->state->delete($this->getStateKey() . '.process.' . $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function resetLastRun() {
    SyncResourceManager::resetLastRun($this->getPluginDefinition());
  }

  /**
   * Log event.
   *
   * Runtime errors that do not require immediate action but should typically
   * be logged and monitored.
   *
   * @param mixed $level
   *   The log level.
   * @param string $message
   *   The log message.
   * @param array $context
   *   The log context.
   */
  protected function log($level, $message, array $context = []) {
    $log_to_db = FALSE;
    $log_to_messages = FALSE;
    $log_to_drush = FALSE;
    foreach ($context as &$value) {
      if (is_array($value)) {
        $value = json_encode($value);
      }
    }
    switch ($level) {
      case LogLevel::EMERGENCY:
      case LogLevel::ALERT:
      case LogLevel::CRITICAL:
      case LogLevel::ERROR:
        $message_level = 'error';
        $log_to_db = TRUE;
        $log_to_messages = TRUE;
        $log_to_drush = FALSE;
        break;

      case LogLevel::WARNING:
        $message_level = 'warning';
        $log_to_db = TRUE;
        $log_to_messages = TRUE;
        $log_to_drush = FALSE;
        break;

      case LogLevel::NOTICE:
        $message_level = 'status';
        $log_to_db = TRUE;
        $log_to_messages = TRUE;
        $log_to_drush = TRUE;
        break;

      case LogLevel::INFO:
        $message_level = 'status';
        $log_to_db = $this->verboseLog();
        $log_to_drush = TRUE;
        break;

      case LogLevel::DEBUG:
        $message_level = 'status';
        $log_to_db = $this->verboseLog();
        $log_to_drush = $this->verboseLog();
        break;
    }
    if ($log_to_db) {
      if (!empty($context['%parent_plugin_id'])) {
        $context['channel'] = 'sync_' . $context['%parent_plugin_id'];
      }
      $this->logger->log($level, $message, $context);
    }
    if ($log_to_messages && !empty($context['%sync_as_batch'])) {
      \Drupal::messenger()->addMessage(new FormattableMarkup($message, $context), $message_level);
    }
    if ($log_to_drush) {
      $this->cliLog(new FormattableMarkup($message, $context), [], $level);
    }
  }

  /**
   * Log a message if called during drush operations.
   */
  protected function cliLog($string, array $args = [], $type = 'info') {
    if (PHP_SAPI === 'cli') {
      $red = "\033[31;40m\033[1m%s\033[0m";
      $yellow = "\033[1;33;40m\033[1m%s\033[0m";
      $green = "\033[1;32;40m\033[1m%s\033[0m";
      switch ($type) {
        case LogLevel::EMERGENCY:
        case LogLevel::ALERT:
        case LogLevel::CRITICAL:
        case LogLevel::ERROR:
          $color = $red;
          break;

        case LogLevel::WARNING:
          $color = $yellow;
          break;

        case LogLevel::NOTICE:
          $color = $green;
          break;

        default:
          $color = "%s";
          break;
      }
      $message = strip_tags(sprintf($color, dt($string, $args)));
      fwrite(STDOUT, $message . "\n");
    }
  }

  /**
   * Should verbose logging be used.
   *
   * @return bool
   *   TRUE if verbose logging should be used.
   */
  protected function verboseLog() {
    if (!isset($this->verboseLog)) {
      $this->verboseLog = \Drupal::config('sync.settings')->get('log_verbose');
    }
    return $this->verboseLog;
  }

}
