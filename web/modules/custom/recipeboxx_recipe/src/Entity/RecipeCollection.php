<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Recipe Collection entity.
 *
 * @ContentEntityType(
 *   id = "recipe_collection",
 *   label = @Translation("Recipe Collection"),
 *   label_collection = @Translation("Recipe Collections"),
 *   label_singular = @Translation("recipe collection"),
 *   label_plural = @Translation("recipe collections"),
 *   label_count = @PluralTranslation(
 *     singular = "@count recipe collection",
 *     plural = "@count recipe collections",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\recipeboxx_recipe\Access\RecipeCollectionAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\recipeboxx_recipe\Form\RecipeCollectionForm",
 *       "add" = "Drupal\recipeboxx_recipe\Form\RecipeCollectionForm",
 *       "edit" = "Drupal\recipeboxx_recipe\Form\RecipeCollectionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "recipeboxx_collection",
 *   admin_permission = "administer recipe collections",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/collection/{recipe_collection}",
 *     "add-form" = "/collection/add",
 *     "edit-form" = "/collection/{recipe_collection}/edit",
 *     "delete-form" = "/collection/{recipe_collection}/delete",
 *     "collection" = "/my/collections",
 *   },
 * )
 */
class RecipeCollection extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName(string $name): self {
    $this->set('name', $name);
    return $this;
  }

  /**
   * Get the description.
   *
   * @return string|null
   *   The description.
   */
  public function getDescription(): ?string {
    return $this->get('description')->value;
  }

  /**
   * Set the description.
   *
   * @param string $description
   *   The description.
   *
   * @return $this
   */
  public function setDescription(string $description): self {
    $this->set('description', $description);
    return $this;
  }

  /**
   * Get the visibility setting.
   *
   * @return string
   *   The visibility (public or private).
   */
  public function getVisibility(): string {
    return $this->get('visibility')->value ?? 'private';
  }

  /**
   * Set the visibility.
   *
   * @param string $visibility
   *   The visibility.
   *
   * @return $this
   */
  public function setVisibility(string $visibility): self {
    $this->set('visibility', $visibility);
    return $this;
  }

  /**
   * Check if collection is public.
   *
   * @return bool
   *   TRUE if public.
   */
  public function isPublic(): bool {
    return $this->getVisibility() === 'public';
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    // Set owner to current user if not set.
    if (!$this->getOwnerId()) {
      $this->setOwnerId(\Drupal::currentUser()->id());
    }

    // Set created time.
    if ($this->isNew() && !$this->get('created')->value) {
      $this->set('created', \Drupal::time()->getRequestTime());
    }

    // Set changed time.
    $this->set('changed', \Drupal::time()->getRequestTime());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Collection Name'))
      ->setDescription(t('The name of the recipe collection.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of this collection.'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => -5,
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['visibility'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Visibility'))
      ->setDescription(t('Who can view this collection.'))
      ->setRequired(TRUE)
      ->setDefaultValue('private')
      ->setSetting('allowed_values', [
        'private' => 'Private (only me)',
        'public' => 'Public (anyone can view)',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Cover Image'))
      ->setDescription(t('An optional cover image for this collection.'))
      ->setSettings([
        'file_directory' => 'recipe-collections',
        'alt_field_required' => FALSE,
        'file_extensions' => 'png jpg jpeg gif',
        'max_filesize' => '5 MB',
      ])
      ->setDisplayOptions('form', [
        'type' => 'image_image',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the collection was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the collection was last edited.'));

    return $fields;
  }

}
