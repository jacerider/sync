<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP as SFTPClient;

/**
 * Plugin implementation of the 'sftp' sync resource.
 *
 * @SyncFetcher(
 *   id = "sftp",
 *   label = @Translation("SFTP"),
 * )
 */
class Sftp extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'server' => '',
      'server_port' => 22,
      'username' => '',
      'password' => '',
      'key_path' => '',
      'filename' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $sftp = new SFTPClient($this->configuration['server'], $this->configuration['server_port']);

    $username = $this->configuration['username'];
    $key_path = $this->configuration['key_path'];

    // Resolve relative paths from the Drupal app root.
    if (!empty($key_path) && $key_path[0] !== '/') {
      $key_path = \Drupal::root() . '/' . $key_path;
      $key_path = realpath($key_path) ?: $key_path;
    }

    if (!empty($this->configuration['key_path'])) {
      if (!file_exists($key_path)) {
        $message = t('SFTP key file not found: @path', ['@path' => $key_path]);
        \Drupal::messenger()->addError($message);
        throw new \Exception($message);
      }
      $key = PublicKeyLoader::load(file_get_contents($key_path), $this->configuration['password'] ?: FALSE);
      if (!$sftp->login($username, $key)) {
        $message = t('Could not log in to SFTP server with key authentication.');
        \Drupal::messenger()->addError($message);
        throw new \Exception($message);
      }
    }
    else {
      if (!$sftp->login($username, $this->configuration['password'])) {
        $message = t('Could not log in to SFTP server.');
        \Drupal::messenger()->addError($message);
        throw new \Exception($message);
      }
    }

    $data = $sftp->get($this->configuration['filename']);
    if ($data === FALSE) {
      $message = t('Could not get file from SFTP server.');
      \Drupal::messenger()->addError($message);
      throw new \Exception($message);
    }

    return $data;
  }

}
