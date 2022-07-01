<?php

namespace Drupal\sync\Plugin\SyncParser;

use Drupal\Component\Utility\NestedArray;
use Drupal\sync\Plugin\SyncFetcherInterface;
use Drupal\sync\Plugin\SyncParserBase;

/**
 * Plugin implementation of the 'xml' sync parser.
 *
 * @SyncParser(
 *   id = "xml",
 *   label = @Translation("XML"),
 * )
 */
class Xml extends SyncParserBase {

  /**
   * Constructs a SyncParser object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $configuration += [
      'base_key' => [],
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function parse($data, SyncFetcherInterface $fetcher) {
    if (empty($data)) {
      return [];
    }
    $xml = simplexml_load_string($data);
    $base_key = $this->configuration['base_key'];
    $data = $this->xmlToArray($xml);
    if ($base_key) {
      if (!is_array($base_key)) {
        $base_key = [$base_key];
      }
      if ($new_data = NestedArray::getValue($data, $base_key)) {
        return $new_data;
      }
    }

    return $data;
  }

  /**
   * Recursively crawl through XML and return results as an array.
   *
   * @param \SimpleXmlElement $xml
   *   The XML element to crawl through.
   * @param array $arr
   *   The results.
   *
   * @return array
   *   The XML results as an array.
   */
  protected function xmlToArray(\SimpleXMLElement $xml, array $arr = []) {
    $iter = 0;
    foreach ($xml->children() as $b) {
      $a = $b->getName();
      if (!$b->children()) {
        $arr[$a] = trim($b[0]);
      }
      else {
        $arr[$a][$iter] = [];
        $arr[$a][$iter] = $this->xmlToArray($b, $arr[$a][$iter]);
        $iter++;
      }
    }
    $arr = $this->xmlNamespaceToXml($xml, $arr);
    return $arr;
  }

  /**
   * Recursively crawl through XML namespaces and return results as an array.
   *
   * @param \SimpleXmlElement $xml
   *   The XML element to crawl through.
   * @param array $arr
   *   The results.
   *
   * @return array
   *   The XML results as an array.
   */
  protected function xmlNamespaceToXml(\SimpleXMLElement $xml, array $arr = []) {
    $namespaces = $xml->getNamespaces(TRUE);
    if ($namespaces) {
      foreach ($namespaces as $namespace => $path) {
        $iter = 0;
        foreach ($xml->children($path) as $b) {
          $a = $b->getName();
          if (!$b->children($path)) {
            $arr[$a] = trim($b[0]);
          }
          else {
            $arr[$a] = [];
            if (!$b->children($path)) {
              $arr[$a] = trim($b[0]);
            }
            else {
              foreach ($b->children($path) as $bb) {
                $arr[$a][$iter] = [];
                $arr[$a][$iter] = $this->xmlToArray($bb, $arr[$a][$iter]);
                $iter++;
              }
            }
          }
        }
      }
    }
    return $arr;
  }

}
