<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Shopping List Item entity.
 *
 * @ContentEntityType(
 *   id = "shopping_list_item",
 *   label = @Translation("Shopping List Item"),
 *   label_collection = @Translation("Shopping List Items"),
 *   label_singular = @Translation("shopping list item"),
 *   label_plural = @Translation("shopping list items"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "recipeboxx_shopping_list_item",
 *   admin_permission = "administer shopping lists",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class ShoppingListItem extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['list_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Shopping List'))
      ->setDescription(t('The shopping list this item belongs to.'))
      ->setSetting('target_type', 'shopping_list')
      ->setRequired(TRUE);

    $fields['recipe_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recipe'))
      ->setDescription(t('The recipe this item came from (if any).'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => ['recipe' => 'recipe'],
      ]);

    $fields['item_text'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Item'))
      ->setDescription(t('The shopping list item text.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
      ]);

    $fields['quantity'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Quantity'))
      ->setDescription(t('The quantity needed.'))
      ->setSettings([
        'max_length' => 100,
      ]);

    $fields['category'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Category'))
      ->setDescription(t('The category (e.g., Produce, Dairy).'))
      ->setSettings([
        'max_length' => 50,
      ]);

    $fields['checked'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Checked'))
      ->setDescription(t('Whether this item is checked off.'))
      ->setDefaultValue(FALSE);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('The weight for ordering.'))
      ->setDefaultValue(0);

    $fields['notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Additional notes about this item.'));

    return $fields;
  }

  /**
   * Get the shopping list.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\ShoppingList|null
   *   The shopping list entity.
   */
  public function getShoppingList() {
    return $this->get('list_id')->entity;
  }

  /**
   * Get the recipe.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The recipe node.
   */
  public function getRecipe() {
    return $this->get('recipe_id')->entity;
  }

  /**
   * Get the item text.
   *
   * @return string
   *   The item text.
   */
  public function getItemText() {
    return $this->get('item_text')->value;
  }

  /**
   * Get the quantity.
   *
   * @return string|null
   *   The quantity.
   */
  public function getQuantity() {
    return $this->get('quantity')->value;
  }

  /**
   * Get the category.
   *
   * @return string|null
   *   The category.
   */
  public function getCategory() {
    return $this->get('category')->value;
  }

  /**
   * Check if the item is checked.
   *
   * @return bool
   *   TRUE if checked.
   */
  public function isChecked() {
    return (bool) $this->get('checked')->value;
  }

  /**
   * Set checked status.
   *
   * @param bool $checked
   *   The checked status.
   *
   * @return $this
   */
  public function setChecked($checked) {
    $this->set('checked', $checked);
    return $this;
  }

}
