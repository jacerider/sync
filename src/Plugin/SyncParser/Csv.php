<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;

/**
 * Plugin implementation of the 'csv' sync parser.
 *
 * @SyncParser(
 *   id = "csv",
 *   label = @Translation("CSV"),
 * )
 */
class Csv extends SyncParserBase {

  /**
   * {@inheritdoc}
   */
  protected function defaultSettings() {
    return [
      'header' => TRUE,
      'delimiter' => ',',
      'remove_lines' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    // To use, composer require parsecsv/php-parsecsv.
    if (class_exists('\ParseCsv\Csv')) {
      if (!mb_detect_encoding((string) $data, 'UTF-8', TRUE) && class_exists('\UConverter')) {
        // Attempt to resolve issue when CSV files is incorrectly encoded as
        // UTF-8.
        // phpcs:ignore
        $data = \UConverter::transcode($data, 'ISO-8859-1', 'UTF8', ['to_subst' => '?']);
      }
      // phpcs:ignore
      $csv = new \ParseCsv\Csv();
      $csv->heading = !empty($this->configuration['header']);
      $csv->delimiter = $this->configuration['delimiter'];
      $csv->parse($data);
      return $csv->data;
    }

    // Fix CSVs that store blob data on multiple lines.
    if (!empty($this->configuration['remove_lines'])) {
      preg_match_all('/"(.*?)"/s', $data, $matches);
      if (!empty($matches[0])) {
        foreach ($matches[0] as $value) {
          $data = str_replace($value, str_replace(["\n", "\r"], '', $value), $data);
        }
      }
    }

    $use_header = $this->configuration['header'];
    $rows = array_filter(explode(PHP_EOL, $data));
    $csv = array_map(function ($row) {
      return str_getcsv($row, $this->configuration['delimiter']);
    }, $rows);
    $page_size = $fetcher->getPageSize();
    if ($page_size) {
      $fetcher->setPageEnabled(TRUE);
      if ($use_header) {
        $header = array_shift($csv);
      }
      $max = $page_size * $fetcher->getPageNumber();
      $min = $max - $page_size;
      $csv = array_slice($csv, $min, $page_size);
      if ($use_header) {
        $csv = array_merge([$header], $csv);
      }
    }
    if ($use_header) {
      $found = [];
      foreach ($csv[0] as $key => $value) {
        if (isset($found[$value])) {
          $csv[0][$key] .= ' ' . $found[$value];
        }
        $found[$value] = isset($found[$value]) ? $found[$value] + 1 : 1;
      }
      array_walk($csv, function (&$a) use ($csv) {
        $a = array_slice($a, 0, count($csv[0]));
        if (count($csv[0]) === count($a)) {
          $a = array_combine($csv[0], $a);
        }
      });
      array_shift($csv);
    }
    return $csv;
  }

}
