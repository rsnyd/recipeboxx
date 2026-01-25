<?php

namespace Drupal\recipeboxx_recipe\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Meal Plan Entry entity.
 *
 * @ContentEntityType(
 *   id = "meal_plan_entry",
 *   label = @Translation("Meal Plan Entry"),
 *   label_collection = @Translation("Meal Plan Entries"),
 *   label_singular = @Translation("meal plan entry"),
 *   label_plural = @Translation("meal plan entries"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "recipeboxx_meal_plan_entry",
 *   admin_permission = "administer meal plans",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class MealPlanEntry extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['plan_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Meal Plan'))
      ->setDescription(t('The meal plan this entry belongs to.'))
      ->setSetting('target_type', 'meal_plan')
      ->setRequired(TRUE);

    $fields['recipe_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recipe'))
      ->setDescription(t('The recipe for this meal.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setSetting('handler_settings', [
        'target_bundles' => ['recipe' => 'recipe'],
      ])
      ->setRequired(TRUE);

    $fields['day_of_week'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Day of Week'))
      ->setDescription(t('Day of week (0=Monday, 6=Sunday).'))
      ->setRequired(TRUE)
      ->setSetting('min', 0)
      ->setSetting('max', 6);

    $fields['meal_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Meal Type'))
      ->setDescription(t('Type of meal (breakfast, lunch, dinner, snack).'))
      ->setRequired(TRUE)
      ->setSettings([
        'max_length' => 20,
      ]);

    $fields['servings'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Servings'))
      ->setDescription(t('Number of servings to prepare.'))
      ->setSetting('min', 1);

    $fields['notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Notes about this meal.'));

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight'))
      ->setDescription(t('Weight for ordering within a day/meal type.'))
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * Get the meal plan.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\MealPlan|null
   *   The meal plan entity.
   */
  public function getMealPlan() {
    return $this->get('plan_id')->entity;
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
   * Get the day of week.
   *
   * @return int
   *   Day of week (0-6).
   */
  public function getDayOfWeek() {
    return $this->get('day_of_week')->value;
  }

  /**
   * Get the meal type.
   *
   * @return string
   *   The meal type.
   */
  public function getMealType() {
    return $this->get('meal_type')->value;
  }

  /**
   * Get the servings.
   *
   * @return int|null
   *   The servings.
   */
  public function getServings() {
    return $this->get('servings')->value;
  }

  /**
   * Get the day name.
   *
   * @return string
   *   The day name.
   */
  public function getDayName() {
    $days = [
      0 => 'Monday',
      1 => 'Tuesday',
      2 => 'Wednesday',
      3 => 'Thursday',
      4 => 'Friday',
      5 => 'Saturday',
      6 => 'Sunday',
    ];

    return $days[$this->getDayOfWeek()] ?? 'Unknown';
  }

}
