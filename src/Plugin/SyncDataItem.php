<?php

namespace Drupal\sync\Plugin;

use Drupal\Component\Utility\NestedArray;
use Traversable;

/**
 * Custom ArrayObject implementation.
 *
 * The native ArrayObject is unnecessarily complicated.
 *
 * @ingroup utility
 */
class SyncDataItem implements \IteratorAggregate, \ArrayAccess, \Countable {

  /**
   * The array.
   *
   * @var array
   */
  protected $data;

  /**
   * Array object constructor.
   *
   * @param array $data
   *   An array.
   */
  public function __construct(array $data = []) {
    $this->data = $data;
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
  public function __isset($property) {
    return $this->offsetExists($property);
  }

  /**
   * Sets the value at the specified key to value.
   *
   * @param mixed $property
   *   A key.
   * @param mixed $value
   *   A value.
   */
  public function __set($property, $value) {
    $this->offsetSet($property, $value);
  }

  /**
   * Unsets the value at the specified key.
   *
   * @param mixed $property
   *   A key.
   */
  public function __unset($property) {
    $this->offsetUnset($property);
  }

  /**
   * Returns the value at the specified key by reference.
   *
   * @param mixed $property
   *   A key.
   *
   * @return mixed
   *   The stored value.
   */
  public function &__get($property) {
    $ret =& $this->offsetGet($property);
    return $ret;
  }

  /**
   * Returns the data as an array.
   *
   * @return array
   *   The array.
   */
  public function toArray() {
    return $this->data;
  }

  /**
   * Get the number of public properties in the ArrayObject.
   *
   * @return int
   *   The count.
   */
  public function count(): int {
    return count($this->data);
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
  public function offsetExists($property): bool {
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
  public function &offsetGet($property): mixed {
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
  public function offsetSet($property, $value): void {
    if ($value instanceof SyncDataItems || $value instanceof SyncDataItem) {
      $value = $value->toArray();
    }
    NestedArray::setValue($this->data, (array) $property, $value, TRUE);
  }

  /**
   * Unsets the value at the specified key.
   *
   * @param mixed $property
   *   A key.
   */
  public function offsetUnset($property): void {
    NestedArray::unsetValue($this->data, (array) $property);
  }

  /**
   * Returns an iterator for entities.
   *
   * @return \ArrayIterator
   *   An \ArrayIterator instance
   */
  public function getIterator(): Traversable {
    return new \ArrayIterator($this->data);
  }

}
