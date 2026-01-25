<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Recipe Note entity.
 *
 * @ContentEntityType(
 *   id = "recipe_note",
 *   label = @Translation("Recipe Note"),
 *   label_collection = @Translation("Recipe Notes"),
 *   label_singular = @Translation("recipe note"),
 *   label_plural = @Translation("recipe notes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count recipe note",
 *     plural = "@count recipe notes"
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "access" = "Drupal\recipeboxx_recipe\Access\RecipeNoteAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\recipeboxx_recipe\Form\RecipeNoteForm",
 *       "add" = "Drupal\recipeboxx_recipe\Form\RecipeNoteForm",
 *       "edit" = "Drupal\recipeboxx_recipe\Form\RecipeNoteForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "recipeboxx_recipe_note",
 *   admin_permission = "administer recipe notes",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 * )
 */
class RecipeNote extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['recipe_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recipe'))
      ->setDescription(t('The recipe this note is for.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => ['recipe' => 'recipe'],
      ])
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['note_text'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Note'))
      ->setDescription(t('Your personal notes about this recipe.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 0,
        'settings' => [
          'rows' => 6,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['rating'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Personal Rating'))
      ->setDescription(t('Your personal rating (1-5 stars).'))
      ->setSetting('min', 1)
      ->setSetting('max', 5)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'number_integer',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the note was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the note was last edited.'));

    return $fields;
  }

  /**
   * Get the recipe this note is for.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The recipe node.
   */
  public function getRecipe() {
    return $this->get('recipe_id')->entity;
  }

  /**
   * Set the recipe this note is for.
   *
   * @param int $recipe_id
   *   The recipe node ID.
   *
   * @return $this
   */
  public function setRecipe($recipe_id) {
    $this->set('recipe_id', $recipe_id);
    return $this;
  }

  /**
   * Get the note text.
   *
   * @return string
   *   The note text.
   */
  public function getNoteText() {
    return $this->get('note_text')->value;
  }

  /**
   * Set the note text.
   *
   * @param string $note_text
   *   The note text.
   *
   * @return $this
   */
  public function setNoteText($note_text) {
    $this->set('note_text', $note_text);
    return $this;
  }

  /**
   * Get the personal rating.
   *
   * @return int|null
   *   The rating (1-5) or NULL.
   */
  public function getRating() {
    return $this->get('rating')->value;
  }

  /**
   * Set the personal rating.
   *
   * @param int $rating
   *   The rating (1-5).
   *
   * @return $this
   */
  public function setRating($rating) {
    $this->set('rating', $rating);
    return $this;
  }

}
