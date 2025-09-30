<?php

namespace Drupal\sync\Plugin\SyncFetcher;

use Drupal\sync\Plugin\SyncDataItems;
use Drupal\sync\Plugin\SyncFetcherBase;
use Google\Service\Sheets;

/**
 * Plugin implementation of the 'google_sheet' sync resource.
 *
 * @SyncFetcher(
 *   id = "google_sheet",
 *   label = @Translation("Google Sheet"),
 * )
 */
class GoogleSheet extends SyncFetcherBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'credentials' => '',
      'id' => '',
      'name' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function fetch($page_number, SyncDataItems $previous_data) {
    $credentials = $this->configuration['credentials'];
    if (strpos($credentials, '://') === FALSE) {
      $credentials = \Drupal::root() . '/' . $credentials;
    }

    $client = new \Google_Client();
    $client->setApplicationName('Google Sheets API');
    $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
    $client->setAccessType('offline');
    $client->setAuthConfig($credentials);

    $service = new Sheets($client);
    $data = $service->spreadsheets_values->get($this->configuration['id'], $this->configuration['name'])->getValues();

    if (empty($data)) {
      return [];
    }

    // Remove and get first row as headers.
    $headers = array_shift($data);
    $result = [];

    foreach ($data as $row) {
      $rowData = [];
      foreach ($headers as $index => $header) {
        $rowData[trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9\-]/', '', str_replace(' ', '-', strtolower($header)))), '-')] = trim($row[$index] ?? '');
      }
      $result[] = $rowData;
    }

    return $result;
  }

}
