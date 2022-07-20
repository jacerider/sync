<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;

/**
 * Plugin implementation of the 'ftp' sync resource.
 *
 * @SyncFetcher(
 *   id = "ftp",
 *   label = @Translation("FTP"),
 * )
 */
class Ftp extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'server' => '',
      'server_port' => 21,
      'username' => '',
      'password' => '',
      'filename' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $data = NULL;
    $ftp = ftp_connect($this->configuration['server'], $this->configuration['server_port']);
    if (!$ftp) {
      $message = t('Could not connect to FTP server.');
      \Drupal::messenger()->addError($message);
      throw new \Exception($message);
    }
    if (!ftp_login($ftp, $this->configuration['username'], $this->configuration['password'])) {
      $message = t('Could not log in to FTP server.');
      \Drupal::messenger()->addError($message);
      throw new \Exception($message);
    }
    $temp = fopen('php://temp', 'r+');
    ftp_pasv($ftp, TRUE);
    if (ftp_fget($ftp, $temp, $this->configuration['filename'], FTP_BINARY, 0)) {
      rewind($temp);
      $data = stream_get_contents($temp);
    }
    else {
      $message = t('Could not get file from FTP server.');
      \Drupal::messenger()->addError($message);
      throw new \Exception($message);
    }
    return $data;
  }

}
