<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Utility\NestedArray;

/**
 * Custom ArrayObject implementation.
 *
 * The native ArrayObject is unnecessarily complicated.
 *
 * @ingroup utility
 */
class SyncDataItems implements \IteratorAggregate, \ArrayAccess, \Countable {

  /**
   * The array.
   *
   * @var \Drupal\sync\Plugin\SyncDataItem[]
   */
  protected $data;

  /**
   * Holds the original total count of data.
   *
   * @var int
   */
  protected $count;

  /**
   * Holds a boolean indicating if there is a next page.
   *
   * @var bool
   */
  protected $hasNextPage;

  /**
   * Array object constructor.
   *
   * @param array $data
   *   An array of arrays or SyncDataItem[].
   */
  public function __construct(array $data = []) {
    $this->setItems($data);
    $this->count = count($this->data);
  }

  /**
   * Set the items.
   *
   * @param array $data
   *   An array of arrays or SyncDataItem[].
   *
   * @return SyncDataItem[]
   *   An array of items.
   */
  public function setItems(array $data = []) {
    $this->data = [];
    foreach ($data as $key => $value) {
      if (!$value instanceof SyncDataItem) {
        if (!is_array($value)) {
          $value = [
            'value' => $value,
          ];
        }
        $value = new SyncDataItem($value);
      }
      $value->set('_sync_key', $key);
      $this->data[$key] = $value;
    }
    return $this->data;
  }

  /**
   * Returns whether the requested key exists.
   *
   * @param mixed $key
   *   A key.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function __isset($key) {
    return $this->offsetExists($key);
  }

  /**
   * Sets the value at the specified key to value.
   *
   * @param mixed $key
   *   A key.
   * @param mixed $value
   *   A value.
   */
  public function __set($key, $value) {
    $this->offsetSet($key, $value);
  }

  /**
   * Unsets the value at the specified key.
   *
   * @param mixed $key
   *   A key.
   */
  public function __unset($key) {
    $this->offsetUnset($key);
  }

  /**
   * Returns the value at the specified key by reference.
   *
   * @param mixed $key
   *   A key.
   *
   * @return mixed
   *   The stored value.
   */
  public function &__get($key) {
    $ret =& $this->offsetGet($key);
    return $ret;
  }

  /**
   * Returns the collection.
   *
   * @return \Drupal\sync\Plugin\SyncDataItem[]
   *   The array.
   */
  public function items() {
    return $this->data;
  }

  /**
   * Checks if there is a next page in the collection.
   *
   * @return bool
   *   TRUE if the collection has a next page.
   */
  public function hasNextPage() {
    return (bool) $this->hasNextPage;
  }

  /**
   * Sets the has next page flag.
   *
   * Once the collection query has been executed and we build the entity
   * collection, we now if there will be a next page with extra entities.
   *
   * @param bool $has_next_page
   *   TRUE if the collection has a next page.
   */
  public function setHasNextPage($has_next_page) {
    $this->hasNextPage = (bool) $has_next_page;
  }

  /**
   * Returns the collection as an array.
   *
   * @return array
   *   The array.
   */
  public function toArray() {
    $data = [];
    foreach ($this->data as $key => $item) {
      $data[$key] = $item->toArray();
    }
    return $data;
  }

  /**
   * Get the number of public properties in the ArrayObject.
   *
   * @return int
   *   The count.
   */
  #[\ReturnTypeWillChange]
  public function count() {
    return count($this->data);
  }

  /**
   * Get the first item.
   *
   * @return int
   *   The count.
   */
  public function first() {
    if ($this->count()) {
      return $this->offsetGet(key($this->data));
    }
    return NULL;
  }

  /**
   * Get the last item.
   *
   * @return int
   *   The count.
   */
  public function last() {
    if ($this->count()) {
      $keys = array_keys($this->data);
      return $this->offsetGet(end($keys));
    }
    return NULL;
  }

  /**
   * Get the original number of public properties in the ArrayObject.
   *
   * @return int
   *   The count.
   */
  public function getOriginalCount() {
    return $this->count;
  }

  /**
   * Check ArrayObject for results.
   *
   * @return int
   *   The count.
   */
  public function hasItems() {
    return !empty($this->count());
  }

  /**
   * Check ArrayObject for results.
   *
   * @return array
   *   The slice.
   */
  public function slice($offset, $length, $preserve_keys = FALSE) {
    $this->data = array_slice($this->data, $offset, $length, $preserve_keys);
    return $this->data;
  }

  /**
   * Remove an item from the collection.
   *
   * @param \Drupal\sync\Plugin\SyncDataItem $item
   *   The item to remove.
   *
   * @return $this
   */
  public function removeItem(SyncDataItem $item) {
    $this->offsetUnset($item->offsetGet('_sync_key'));
    return $this;
  }

  /**
   * Returns whether the requested key is empty.
   *
   * @param mixed $property
   *   A key.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function empty($property) {
    return empty($this->offsetGet($property));
  }

  /**
   * Returns whether the requested key exists.
   *
   * @param mixed $property
   *   A key.
   *
   * @return bool
   *   TRUE or FALSE
   */
  public function has($property) {
    return $this->offsetExists($property);
  }

  /**
   * Returns the value at the specified key.
   *
   * @param mixed $property
   *   A key.
   *
   * @return mixed
   *   The value.
   */
  public function &get($property) {
    return $this->offsetGet($property);
  }

  /**
   * Sets the value at the specified key to value.
   *
   * @param mixed $property
   *   A key.
   * @param mixed $value
   *   A value.
   */
  public function set($property, $value) {
    $this->offsetSet($property, $value);
  }

  /**
   * Unsets the value at the specified key.
   *
   * @param mixed $property
   *   A key.
   */
  public function unset($property) {
    $this->offsetUnset($property);
  }

  /**
   * Returns whether the requested key exists.
   *
   * @param mixed $property
   *   A key.
   *
   * @return bool
   *   TRUE or FALSE
   */
  #[\ReturnTypeWillChange]
  public function offsetExists($property) {
    $exists = NULL;
    NestedArray::getValue($this->data, (array) $property, $exists);
    return $exists;
  }

  /**
   * Returns the value at the specified key.
   *
   * @param mixed $property
   *   A key.
   *
   * @return mixed
   *   The value.
   */
  #[\ReturnTypeWillChange]
  public function &offsetGet($property) {
    $value = NULL;
    if (!$this->offsetExists($property)) {
      return $value;
    }
    $value = &NestedArray::getValue($this->data, (array) $property);
    return $value;
  }

  /**
   * Sets the value at the specified key to value.
   *
   * @param mixed $property
   *   A key.
   * @param mixed $value
   *   A value.
   */
  #[\ReturnTypeWillChange]
  public function offsetSet($property, $value) {
    NestedArray::setValue($this->data, (array) $property, $value, TRUE);
  }

  /**
   * Unsets the value at the specified key.
   *
   * @param mixed $property
   *   A key.
   */
  #[\ReturnTypeWillChange]
  public function offsetUnset($property) {
    NestedArray::unsetValue($this->data, (array) $property);
  }

  /**
   * Returns an iterator for entities.
   *
   * @return \ArrayIterator
   *   An \ArrayIterator instance
   */
  #[\ReturnTypeWillChange]
  public function getIterator() {
    return new \ArrayIterator($this->data);
  }

}
