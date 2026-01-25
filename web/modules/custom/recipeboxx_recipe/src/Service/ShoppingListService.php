<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Entity\ShoppingList;

/**
 * Service for shopping list business logic.
 */
class ShoppingListService {

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
   * The ingredient parser service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\IngredientParserService
   */
  protected IngredientParserService $ingredientParser;

  /**
   * Constructs a ShoppingListService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\recipeboxx_recipe\Service\IngredientParserService $ingredient_parser
   *   The ingredient parser service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, IngredientParserService $ingredient_parser) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->ingredientParser = $ingredient_parser;
  }

  /**
   * Create a new shopping list.
   *
   * @param string $name
   *   The list name.
   * @param int|null $user_id
   *   The user ID (defaults to current user).
   *
   * @return \Drupal\recipeboxx_recipe\Entity\ShoppingList
   *   The created shopping list.
   */
  public function createShoppingList(string $name, ?int $user_id = NULL): ShoppingList {
    if ($user_id === NULL) {
      $user_id = $this->currentUser->id();
    }

    /** @var \Drupal\recipeboxx_recipe\Entity\ShoppingList $list */
    $list = $this->entityTypeManager->getStorage('shopping_list')->create([
      'name' => $name,
      'uid' => $user_id,
      'status' => TRUE,
    ]);

    $list->save();
    return $list;
  }

  /**
   * Add a recipe to a shopping list.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\ShoppingList $list
   *   The shopping list.
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param bool $combine
   *   Whether to combine with existing ingredients.
   *
   * @return int
   *   Number of items added.
   */
  public function addRecipeToList(ShoppingList $list, NodeInterface $recipe, bool $combine = TRUE): int {
    if ($recipe->bundle() !== 'recipe') {
      return 0;
    }

    // Get ingredients from recipe.
    if (!$recipe->hasField('field_ingredients') || $recipe->get('field_ingredients')->isEmpty()) {
      return 0;
    }

    $ingredients_text = $recipe->get('field_ingredients')->value;
    $ingredient_lines = array_filter(array_map('trim', explode("\n", $ingredients_text)));

    $added_count = 0;

    foreach ($ingredient_lines as $ingredient_line) {
      $parsed = $this->ingredientParser->parseIngredient($ingredient_line);

      // Create shopping list item.
      $item_data = [
        'list_id' => $list->id(),
        'recipe_id' => $recipe->id(),
        'item_text' => $parsed['ingredient'],
        'quantity' => $parsed['quantity'] !== NULL ? $this->ingredientParser->convertDecimalToFraction($parsed['quantity']) : '',
        'category' => $this->categorizeIngredient($parsed['ingredient']),
        'checked' => FALSE,
        'weight' => 0,
      ];

      if (!empty($parsed['unit'])) {
        $item_data['item_text'] = $parsed['ingredient'];
        $item_data['quantity'] = ($parsed['quantity'] !== NULL ? $this->ingredientParser->convertDecimalToFraction($parsed['quantity']) . ' ' : '') . $parsed['unit'];
      }

      $this->entityTypeManager->getStorage('shopping_list_item')->create($item_data)->save();
      $added_count++;
    }

    // Combine duplicate ingredients if requested.
    if ($combine) {
      $this->combineIngredients($list);
    }

    return $added_count;
  }

  /**
   * Combine duplicate ingredients in a shopping list.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\ShoppingList $list
   *   The shopping list.
   *
   * @return int
   *   Number of items after combining.
   */
  public function combineIngredients(ShoppingList $list): int {
    $item_storage = $this->entityTypeManager->getStorage('shopping_list_item');

    // Load all items for this list.
    $items = $item_storage->loadByProperties(['list_id' => $list->id()]);

    $combined = [];

    foreach ($items as $item) {
      $key = strtolower(trim($item->getItemText()));

      if (!isset($combined[$key])) {
        $combined[$key] = $item;
      }
      else {
        // Merge quantities if both are numeric.
        $existing_qty = $combined[$key]->getQuantity();
        $new_qty = $item->getQuantity();

        if ($existing_qty && $new_qty) {
          // Try to add quantities.
          $parsed_existing = $this->ingredientParser->parseIngredient($existing_qty . ' item');
          $parsed_new = $this->ingredientParser->parseIngredient($new_qty . ' item');

          if ($parsed_existing['quantity'] !== NULL && $parsed_new['quantity'] !== NULL) {
            $total = $parsed_existing['quantity'] + $parsed_new['quantity'];
            $combined[$key]->set('quantity', $this->ingredientParser->convertDecimalToFraction($total));
            $combined[$key]->save();
          }
        }

        // Delete the duplicate item.
        $item->delete();
      }
    }

    return count($combined);
  }

  /**
   * Categorize an ingredient.
   *
   * @param string $ingredient
   *   The ingredient name.
   *
   * @return string
   *   The category.
   */
  protected function categorizeIngredient(string $ingredient): string {
    $ingredient_lower = strtolower($ingredient);

    $categories = [
      'Produce' => ['lettuce', 'tomato', 'onion', 'garlic', 'carrot', 'celery', 'pepper', 'apple', 'banana', 'lemon', 'lime', 'potato', 'spinach', 'broccoli', 'cucumber'],
      'Dairy' => ['milk', 'cheese', 'butter', 'cream', 'yogurt', 'sour cream'],
      'Meat' => ['chicken', 'beef', 'pork', 'turkey', 'bacon', 'sausage', 'ham'],
      'Seafood' => ['fish', 'salmon', 'tuna', 'shrimp', 'crab', 'lobster'],
      'Pantry' => ['flour', 'sugar', 'salt', 'pepper', 'oil', 'vinegar', 'rice', 'pasta', 'beans', 'spice'],
      'Bakery' => ['bread', 'rolls', 'bagel', 'tortilla'],
      'Frozen' => ['frozen'],
      'Beverages' => ['juice', 'soda', 'water', 'wine', 'beer'],
    ];

    foreach ($categories as $category => $keywords) {
      foreach ($keywords as $keyword) {
        if (strpos($ingredient_lower, $keyword) !== FALSE) {
          return $category;
        }
      }
    }

    return 'Other';
  }

  /**
   * Get all shopping lists for a user.
   *
   * @param int|null $user_id
   *   The user ID (defaults to current user).
   * @param bool $active_only
   *   Whether to return only active lists.
   *
   * @return array
   *   Array of shopping list entities.
   */
  public function getUserShoppingLists(?int $user_id = NULL, bool $active_only = TRUE): array {
    if ($user_id === NULL) {
      $user_id = $this->currentUser->id();
    }

    $storage = $this->entityTypeManager->getStorage('shopping_list');
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('uid', $user_id)
      ->sort('created', 'DESC');

    if ($active_only) {
      $query->condition('status', TRUE);
    }

    $ids = $query->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Get items for a shopping list.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\ShoppingList $list
   *   The shopping list.
   * @param bool $group_by_category
   *   Whether to group items by category.
   *
   * @return array
   *   Array of shopping list items.
   */
  public function getListItems(ShoppingList $list, bool $group_by_category = FALSE): array {
    $item_storage = $this->entityTypeManager->getStorage('shopping_list_item');
    $items = $item_storage->loadByProperties(['list_id' => $list->id()]);

    if (!$group_by_category) {
      return $items;
    }

    // Group by category.
    $grouped = [];
    foreach ($items as $item) {
      $category = $item->getCategory() ?: 'Other';
      $grouped[$category][] = $item;
    }

    return $grouped;
  }

}
