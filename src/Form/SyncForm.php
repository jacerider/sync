<?php

namespace Drupal\sync\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sync\Plugin\SyncResourceManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\sync\Plugin\SyncFetcherFormInterface;

/**
 * Class SyncForm.
 */
class SyncForm extends FormBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle information manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The sync resource manager.
   *
   * @var \Drupal\sync\Plugin\SyncResourceManager
   */
  protected $syncResourceManager;

  /**
   * Constructs a new AuthorizeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The bundle information manager.
   * @param \Drupal\sync\Plugin\SyncResourceManager $sync_resource_manager
   *   The resource manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info, SyncResourceManager $sync_resource_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->syncResourceManager = $sync_resource_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.sync_resource')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request_time = \Drupal::time()->getCurrentTime();
    $date_formatter = \Drupal::service('date.formatter');
    $account = $this->currentUser();
    $manager = $account->hasPermission('manage sync');

    $definitions = $this->syncResourceManager->getActiveDefinitions();
    $form['table'] = [
      '#type' => 'table',
      '#header' => [
        'label' => $this->t('Name'),
        'cleanup' => $this->t('Cleanup'),
        'times' => $this->t('Schedule'),
        'next' => $this->t('Next Run'),
        'last' => $this->t('Last Run'),
        'actions' => $this->t('Actions'),
        'log' => $this->t('Log'),
      ],
    ];
    if (!$manager) {
      unset($form['table']['#header']['cleanup']);
    }
    $has_cron = FALSE;
    foreach ($definitions as $definition) {
      if (!empty($definition['no_ui'])) {
        continue;
      }
      $next = $this->syncResourceManager->getNextCronTime($definition);
      $computed = !empty($definition['computed']);
      $enable = $this->syncResourceManager->access($definition, $account);
      if (!$enable && !$this->syncResourceManager->access($definition, $account, 'view')) {
        continue;
      }
      /** @var \Drupal\sync\Plugin\SyncResourceInterface $resource */
      $resource = $this->syncResourceManager->getResource($definition['id']);
      $as_form = $resource->getFetcher() instanceof SyncFetcherFormInterface;
      $context = [
        '@label' => $definition['label'],
        '@id' => $definition['id'],
        '@entity' => '-',
        '@bundle' => '-',
      ];
      if ($definition['entity_type']) {
        $entity_definition = $this->entityTypeManager->getDefinition($definition['entity_type']);
        $bundle_definitions = $this->bundleInfo->getBundleInfo($definition['entity_type']);
        $context = [
          '@entity' => $entity_definition->getLabel(),
          '@bundle' => isset($bundle_definitions[$definition['bundle']]) ? $bundle_definitions[$definition['bundle']]['label'] : 'None Specified',
        ] + $context;
      }
      $row = [];
      $row['label']['#markup'] = $this->t('<strong>@label</strong> <small>(@id)</small><br><small>Entity: @entity<br>Bundle: @bundle</small>', $context);
      if ($manager) {
        $row['clean']['#markup'] = '<small>' . (!empty($definition['cleanup']) ? $this->t('Yes') : $this->t('No')) . '</small>';
      }
      $row['times']['#markup'] = '-';
      $row['next']['#markup'] = '-';
      if (($times = $this->syncResourceManager->getCronTimes($definition)) && ($days = $this->syncResourceManager->getCronDays($definition))) {
        $row['times']['#markup'] = '<em>' . implode(', ', array_map(function ($time) {
          return date('g:ia', strtotime($time));
        }, $times)) . '</em><br><small>' . implode(', ', array_map(function ($time) {
          return date('D', strtotime($time));
        }, $days)) . '</small>';
      }
      if ($next) {
        $has_cron = TRUE;
        if ($next < $request_time) {
          $row['next']['#markup'] = '<small>Next Run</small>';
        }
        else {
          $row['next']['#markup'] = '<small>' . (!$computed ? $date_formatter->format($next) : '-') . '</small>';
        }
      }
      $last_run_start = $this->syncResourceManager->getLastRunStart($definition);
      $last_run_end = $this->syncResourceManager->getLastRunEnd($definition);
      $row['last']['#markup'] = $this->t('<small>Start: %start<br>Finish: %end</small>', [
        '%start' => $last_run_start ? $date_formatter->format($last_run_start) : '-',
        '%end' => $last_run_end ? $date_formatter->format($last_run_end) : '-',
      ]);
      $row['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'style' => 'white-space: nowrap;',
        ],
      ];
      $action = ['::sync'];
      if ($as_form) {
        $action = ['::asForm'];
      }
      $row['actions']['run'] = [
        '#type' => 'submit',
        '#name' => 'action_' . $definition['id'],
        '#button_type' => 'primary',
        '#value' => (string) $this->t('Sync'),
        '#disabled' => !$enable,
        '#plugin_id' => $definition['id'],
        '#submit' => $action,
      ];
      if (!empty($definition['reset']) && $account->hasPermission('sync reset') && SyncResourceManager::getLastRunStart($definition)) {
        $row['actions']['reset'] = [
          '#type' => 'submit',
          '#name' => 'reset_' . $definition['id'],
          '#value' => (string) $this->t('Reset', ['@label' => $definition['label']]),
          '#disabled' => !$enable,
          '#plugin_id' => $definition['id'],
          '#submit' => ['::reset'],
        ];
      }
      $row['log'] = [
        '#type' => 'link',
        '#title' => $this->t('Log'),
        '#url' => Url::fromRoute('sync.log', ['plugin_id' => $definition['id']]),
      ];
      if ($account->hasPermission('sync debug')) {
        $form['table']['#header']['devel'] = $this->t('Debug');
        $action = ['::debug'];
        if ($as_form) {
          $action = ['::asFormDebug'];
        }
        $row['devel'] = [
          '#type' => 'submit',
          '#name' => 'debug_' . $definition['id'],
          '#value' => (string) $this->t('Debug', ['@label' => $definition['label']]),
          '#plugin_id' => $definition['id'],
          '#submit' => $action,
        ];
      }
      $form['table'][$definition['id']] = $row;
    }
    foreach (Element::children($form['table']) as $key) {
      $row = &$form['table'][$key];
      if (!$has_cron) {
        unset($form['table']['#header']['times'], $form['table']['#header']['next']);
        unset($row['times'], $row['next']);
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function sync(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#plugin_id'];
    $this->syncResourceManager->createInstance($plugin_id)->runAsBatch();
  }

  /**
   * {@inheritdoc}
   */
  public function asForm(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#plugin_id'];
    $form_state->setRedirect('sync.fetcher.form', [
      'plugin_id' => $plugin_id,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function reset(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#plugin_id'];
    $this->syncResourceManager->createInstance($plugin_id)->resetLastRun();
  }

  /**
   * {@inheritdoc}
   */
  public function debug(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#plugin_id'];
    $this->syncResourceManager->createInstance($plugin_id)->debug();
  }

  /**
   * {@inheritdoc}
   */
  public function asFormDebug(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $plugin_id = $trigger['#plugin_id'];
    $form_state->setRedirect('sync.fetcher.form', [
      'plugin_id' => $plugin_id,
      'debug' => TRUE,
    ]);
  }

}
