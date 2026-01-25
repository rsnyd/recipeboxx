<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Meal Plan entity.
 *
 * @ContentEntityType(
 *   id = "meal_plan",
 *   label = @Translation("Meal Plan"),
 *   label_collection = @Translation("Meal Plans"),
 *   label_singular = @Translation("meal plan"),
 *   label_plural = @Translation("meal plans"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "access" = "Drupal\recipeboxx_recipe\Access\MealPlanAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\recipeboxx_recipe\Form\MealPlanForm",
 *       "add" = "Drupal\recipeboxx_recipe\Form\MealPlanForm",
 *       "edit" = "Drupal\recipeboxx_recipe\Form\MealPlanForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "recipeboxx_meal_plan",
 *   admin_permission = "administer meal plans",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "canonical" = "/meal-plan/{meal_plan}",
 *     "edit-form" = "/meal-plan/{meal_plan}/edit",
 *     "delete-form" = "/meal-plan/{meal_plan}/delete",
 *   },
 * )
 */
class MealPlan extends ContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plan Name'))
      ->setDescription(t('The name of the meal plan.'))
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

    $fields['start_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Start Date'))
      ->setDescription(t('The start date of the week (Monday).'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Status'))
      ->setDescription(t('The meal plan status.'))
      ->setDefaultValue('active')
      ->setSettings([
        'max_length' => 20,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the plan was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the plan was last edited.'));

    return $fields;
  }

  /**
   * Get the plan name.
   *
   * @return string
   *   The plan name.
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * Get the start date.
   *
   * @return int
   *   The start date timestamp.
   */
  public function getStartDate() {
    return $this->get('start_date')->value;
  }

  /**
   * Get the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * Check if the plan is active.
   *
   * @return bool
   *   TRUE if active.
   */
  public function isActive() {
    return $this->get('status')->value === 'active';
  }

}
