<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\node\NodeInterface;

/**
 * Defines the Collection Item entity.
 *
 * @ContentEntityType(
 *   id = "collection_item",
 *   label = @Translation("Collection Item"),
 *   label_collection = @Translation("Collection Items"),
 *   label_singular = @Translation("collection item"),
 *   label_plural = @Translation("collection items"),
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "recipeboxx_collection_item",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class CollectionItem extends ContentEntityBase {

  /**
   * Get the collection.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\RecipeCollection|null
   *   The collection entity.
   */
  public function getCollection(): ?RecipeCollection {
    return $this->get('collection_id')->entity;
  }

  /**
   * Get the recipe.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The recipe node.
   */
  public function getRecipe(): ?NodeInterface {
    return $this->get('recipe_id')->entity;
  }

  /**
   * Get the weight.
   *
   * @return int
   *   The weight.
   */
  public function getWeight(): int {
    return (int) $this->get('weight')->value;
  }

  /**
   * Set the weight.
   *
   * @param int $weight
   *   The weight.
   *
   * @return $this
   */
  public function setWeight(int $weight): self {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    if ($this->isNew() && !$this->get('added')->value) {
      $this->set('added', \Drupal::time()->getRequestTime());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['collection_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Collection'))
      ->setSetting('target_type', 'recipe_collection')
      ->setRequired(TRUE);

    $fields['recipe_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recipe'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', ['target_bundles' => ['recipe' => 'recipe']])
      ->setRequired(TRUE);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The weight for ordering within the collection.'))
      ->setDefaultValue(0);

    $fields['added'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Added'))
      ->setDescription(t('The time the recipe was added to the collection.'));

    return $fields;
  }

}
