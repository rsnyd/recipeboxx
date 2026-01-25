<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Entity\MealPlan;
use Drupal\recipeboxx_recipe\Entity\ShoppingList;

/**
 * Service for meal planning business logic.
 */
class MealPlanService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The shopping list service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\ShoppingListService
   */
  protected ShoppingListService $shoppingListService;

  /**
   * Constructs a MealPlanService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\recipeboxx_recipe\Service\ShoppingListService $shopping_list_service
   *   The shopping list service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, ShoppingListService $shopping_list_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->shoppingListService = $shopping_list_service;
  }

  /**
   * Create a new meal plan.
   *
   * @param string|null $name
   *   The plan name (defaults to "Week of [date]").
   * @param int|null $start_date
   *   The start date timestamp (defaults to next Monday).
   * @param int|null $user_id
   *   The user ID (defaults to current user).
   *
   * @return \Drupal\recipeboxx_recipe\Entity\MealPlan
   *   The created meal plan.
   */
  public function createMealPlan(?string $name = NULL, ?int $start_date = NULL, ?int $user_id = NULL): MealPlan {
    if ($user_id === NULL) {
      $user_id = $this->currentUser->id();
    }

    if ($start_date === NULL) {
      $start_date = $this->getNextMonday();
    }

    if ($name === NULL) {
      $name = 'Week of ' . date('M j, Y', $start_date);
    }

    /** @var \Drupal\recipeboxx_recipe\Entity\MealPlan $plan */
    $plan = $this->entityTypeManager->getStorage('meal_plan')->create([
      'name' => $name,
      'uid' => $user_id,
      'start_date' => $start_date,
      'status' => 'active',
    ]);

    $plan->save();
    return $plan;
  }

  /**
   * Get the timestamp for next Monday.
   *
   * @param int|null $from_timestamp
   *   Optional timestamp to calculate from (defaults to now).
   *
   * @return int
   *   The timestamp for next Monday.
   */
  protected function getNextMonday(?int $from_timestamp = NULL): int {
    if ($from_timestamp === NULL) {
      $from_timestamp = \Drupal::time()->getRequestTime();
    }

    $date = new \DateTime('@' . $from_timestamp);
    $day_of_week = (int) $date->format('N'); // 1=Monday, 7=Sunday

    if ($day_of_week === 1) {
      // It's Monday, return start of today.
      $date->setTime(0, 0, 0);
      return $date->getTimestamp();
    }

    // Calculate days until next Monday.
    $days_until_monday = 8 - $day_of_week;
    $date->modify("+{$days_until_monday} days");
    $date->setTime(0, 0, 0);

    return $date->getTimestamp();
  }

  /**
   * Get the current week's meal plan for a user.
   *
   * @param int|null $user_id
   *   The user ID (defaults to current user).
   *
   * @return \Drupal\recipeboxx_recipe\Entity\MealPlan|null
   *   The current week's meal plan or NULL.
   */
  public function getCurrentWeek(?int $user_id = NULL): ?MealPlan {
    if ($user_id === NULL) {
      $user_id = $this->currentUser->id();
    }

    $current_monday = $this->getNextMonday();

    $storage = $this->entityTypeManager->getStorage('meal_plan');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $user_id)
      ->condition('start_date', $current_monday)
      ->condition('status', 'active')
      ->range(0, 1);

    $ids = $query->execute();

    if (!empty($ids)) {
      return $storage->load(reset($ids));
    }

    return NULL;
  }

  /**
   * Add a recipe to a meal plan.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\MealPlan $plan
   *   The meal plan.
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param int $day_of_week
   *   Day of week (0=Monday, 6=Sunday).
   * @param string $meal_type
   *   Meal type (breakfast, lunch, dinner, snack).
   * @param int|null $servings
   *   Number of servings (defaults to recipe servings).
   *
   * @return \Drupal\recipeboxx_recipe\Entity\MealPlanEntry
   *   The created entry.
   */
  public function addRecipeToMealPlan(MealPlan $plan, NodeInterface $recipe, int $day_of_week, string $meal_type, ?int $servings = NULL): object {
    if ($recipe->bundle() !== 'recipe') {
      throw new \InvalidArgumentException('Node must be a recipe.');
    }

    if ($day_of_week < 0 || $day_of_week > 6) {
      throw new \InvalidArgumentException('Day of week must be 0-6.');
    }

    if ($servings === NULL && $recipe->hasField('field_servings')) {
      $servings = $recipe->get('field_servings')->value ?? 4;
    }

    $entry = $this->entityTypeManager->getStorage('meal_plan_entry')->create([
      'plan_id' => $plan->id(),
      'recipe_id' => $recipe->id(),
      'day_of_week' => $day_of_week,
      'meal_type' => $meal_type,
      'servings' => $servings,
    ]);

    $entry->save();
    return $entry;
  }

  /**
   * Get entries for a meal plan.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\MealPlan $plan
   *   The meal plan.
   * @param bool $group_by_day
   *   Whether to group by day.
   *
   * @return array
   *   Array of meal plan entries.
   */
  public function getPlanEntries(MealPlan $plan, bool $group_by_day = FALSE): array {
    $entry_storage = $this->entityTypeManager->getStorage('meal_plan_entry');
    $entries = $entry_storage->loadByProperties(['plan_id' => $plan->id()]);

    if (!$group_by_day) {
      return $entries;
    }

    // Group by day of week.
    $grouped = [];
    for ($i = 0; $i <= 6; $i++) {
      $grouped[$i] = [
        'breakfast' => [],
        'lunch' => [],
        'dinner' => [],
        'snack' => [],
      ];
    }

    foreach ($entries as $entry) {
      $day = $entry->getDayOfWeek();
      $meal_type = $entry->getMealType();

      if (isset($grouped[$day][$meal_type])) {
        $grouped[$day][$meal_type][] = $entry;
      }
    }

    return $grouped;
  }

  /**
   * Generate a shopping list from a meal plan.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\MealPlan $plan
   *   The meal plan.
   * @param string|null $list_name
   *   Optional name for the shopping list.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\ShoppingList
   *   The generated shopping list.
   */
  public function generateShoppingList(MealPlan $plan, ?string $list_name = NULL): ShoppingList {
    if ($list_name === NULL) {
      $list_name = 'Shopping for ' . $plan->getName();
    }

    // Create new shopping list.
    $shopping_list = $this->shoppingListService->createShoppingList($list_name, $plan->getOwnerId());

    // Get all entries from the meal plan.
    $entries = $this->getPlanEntries($plan, FALSE);

    // Add each recipe to the shopping list.
    foreach ($entries as $entry) {
      $recipe = $entry->getRecipe();
      if ($recipe) {
        $this->shoppingListService->addRecipeToList($shopping_list, $recipe, FALSE);
      }
    }

    // Combine all duplicate ingredients.
    $this->shoppingListService->combineIngredients($shopping_list);

    return $shopping_list;
  }

  /**
   * Copy a meal plan to a new week.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\MealPlan $plan
   *   The source meal plan.
   * @param int $new_start_date
   *   The new start date.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\MealPlan
   *   The new meal plan.
   */
  public function copyMealPlan(MealPlan $plan, int $new_start_date): MealPlan {
    $new_name = 'Week of ' . date('M j, Y', $new_start_date);

    // Create new plan.
    $new_plan = $this->createMealPlan($new_name, $new_start_date, $plan->getOwnerId());

    // Copy all entries.
    $entries = $this->getPlanEntries($plan, FALSE);

    foreach ($entries as $entry) {
      $this->addRecipeToMealPlan(
        $new_plan,
        $entry->getRecipe(),
        $entry->getDayOfWeek(),
        $entry->getMealType(),
        $entry->getServings()
      );
    }

    return $new_plan;
  }

  /**
   * Get all meal plans for a user.
   *
   * @param int|null $user_id
   *   The user ID (defaults to current user).
   * @param bool $active_only
   *   Whether to return only active plans.
   *
   * @return array
   *   Array of meal plan entities.
   */
  public function getUserMealPlans(?int $user_id = NULL, bool $active_only = TRUE): array {
    if ($user_id === NULL) {
      $user_id = $this->currentUser->id();
    }

    $storage = $this->entityTypeManager->getStorage('meal_plan');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $user_id)
      ->sort('start_date', 'DESC');

    if ($active_only) {
      $query->condition('status', 'active');
    }

    $ids = $query->execute();
    return $storage->loadMultiple($ids);
  }

}
