<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Shopping List entity.
 *
 * @ContentEntityType(
 *   id = "shopping_list",
 *   label = @Translation("Shopping List"),
 *   label_collection = @Translation("Shopping Lists"),
 *   label_singular = @Translation("shopping list"),
 *   label_plural = @Translation("shopping lists"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "access" = "Drupal\recipeboxx_recipe\Access\ShoppingListAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\recipeboxx_recipe\Form\ShoppingListForm",
 *       "add" = "Drupal\recipeboxx_recipe\Form\ShoppingListForm",
 *       "edit" = "Drupal\recipeboxx_recipe\Form\ShoppingListForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "recipeboxx_shopping_list",
 *   admin_permission = "administer shopping lists",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "canonical" = "/shopping-list/{shopping_list}",
 *     "edit-form" = "/shopping-list/{shopping_list}/edit",
 *     "delete-form" = "/shopping-list/{shopping_list}/delete",
 *   },
 * )
 */
class ShoppingList extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('List Name'))
      ->setDescription(t('The name of the shopping list.'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDescription(t('Whether the shopping list is active.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the list was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the list was last edited.'));

    return $fields;
  }

  /**
   * Get the list name.
   *
   * @return string
   *   The list name.
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * Set the list name.
   *
   * @param string $name
   *   The list name.
   *
   * @return $this
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * Check if the list is active.
   *
   * @return bool
   *   TRUE if active.
   */
  public function isActive() {
    return (bool) $this->get('status')->value;
  }

  /**
   * Set the active status.
   *
   * @param bool $status
   *   The status.
   *
   * @return $this
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

}
