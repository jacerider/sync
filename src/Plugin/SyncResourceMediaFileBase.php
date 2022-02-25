<?php

namespace Drupal\sync\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\sync\SyncFailException;

/**
 * A base resource used for creating files.
 *
 * @ExampleSyncResource(
 *   id = "my_module",
 *   label = @Translation("My Module"),
 *   client = "my_client",
 *   no_ui = true,
 *   entity_type = "media",
 *   bundle = "image",
 * )
 */
abstract class SyncResourceMediaFileBase extends SyncResourceFileBase {

  /**
   * The field that will store the file entity reference.
   *
   * @var string
   */
  protected $mediaFieldName = 'field_media_image';

  /**
   * The label of the media entity.
   *
   * @var string
   */
  protected $mediaEntityLabel;

  /**
   * Get media field where the file entity reference will be stored.
   *
   * @return string
   *   The field name.
   */
  protected function getMediaFieldname(SyncDataItem $item) {
    return $this->mediaFieldName;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMediaEntityLabel(SyncDataItem $item) {
    return $this->mediaEntityLabel;
  }

  /**
   * Set the entity label.
   *
   * @param string $label
   *   The label.
   */
  public function setMediaEntityLabel($label) {
    $this->mediaEntityLabel = $label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function processItem(EntityInterface $entity, SyncDataItem $item) {
    $this->processItemAsMedia($entity, $item);
  }

  /**
   * {@inheritdoc}
   */
  protected function processItemAsMedia(MediaInterface $entity, SyncDataItem $item) {
    $field_name = $this->getMediaFieldname($item);
    if (!$entity->hasField($field_name)) {
      throw new SyncFailException('The media entity does not have a field name ' . $field_name);
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $this->syncEntityProvider->getOrNew($this->id($item), 'file', 'file');
    $this->processItemAsFile($file, $item);
    if ($file->isNew()) {
      $file->save();
    }
    else {
      $this->cleanFile($file);
    }

    $entity_label = $this->getMediaEntityLabel($item);
    $label = !empty($entity_label) ? $entity_label : $file->getFilename();
    $entity->setName($label);
    $entity->get($field_name)->setValue([
      'target_id' => $file->id(),
      'alt' => $label,
    ]);
  }

  /**
   * Flush image file.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   */
  protected function cleanFile(FileInterface $file) {
    image_path_flush($file->getFileUri());
  }

}
