<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wrapper methods for creating url redirects.
 */
trait SyncResourceRedirectTrait {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Create a redirect for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to create the redirect for.
   * @param string $from_url
   *   The relative URL that should be redirected.
   * @param string $status_code
   *   The redirect status code.
   */
  public function createRedirectForEntity(EntityInterface $entity, $from_url, $status_code = '301') {
    if (!$entity->isNew()) {
      $to_url = $entity->toUrl()->getInternalPath();
      $this->createRedirect($from_url, $to_url, $status_code, $entity->language()->getId());
    }
  }

  /**
   * Create a redirect.
   *
   * @param string $from_url
   *   The relative URL that should be redirected from.
   * @param string $to_url
   *   The relative URL that should be redirected to.
   * @param string $status_code
   *   The redirect status code.
   * @param string $language
   *   The language code.
   */
  public function createRedirect($from_url, $to_url, $status_code = '301', $language = 'en') {
    $url_parts = UrlHelper::parse($from_url);
    $storage = $this->entityTypeManager->getStorage('redirect');
    $params = [
      'redirect_source__path' => $url_parts['path'],
    ];
    if (!empty($url_parts['query'])) {
      $params['redirect_source__query'] = $url_parts['query'];
    }
    $redirect = $storage->loadByProperties($params);
    if (!$redirect) {
      $redirect = $storage->create([
        'redirect_source' => $from_url,
      ]);
    }
    else {
      $redirect = reset($redirect);
    }
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect->setRedirect($to_url);
    $redirect->setLanguage($language);
    $redirect->setStatusCode($status_code);
    $redirect->save();
  }

}
