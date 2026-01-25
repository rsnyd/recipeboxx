<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Entity\RecipeCollection;
use Drupal\recipeboxx_recipe\Entity\CollectionItem;

/**
 * Service for managing recipe collections.
 */
class RecipeCollectionService {

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
   * Constructs a RecipeCollectionService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * Create a new recipe collection.
   *
   * @param string $name
   *   The collection name.
   * @param int|null $uid
   *   The owner user ID.
   * @param array $properties
   *   Additional properties.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\RecipeCollection
   *   The created collection.
   */
  public function createCollection(string $name, ?int $uid = NULL, array $properties = []): RecipeCollection {
    if ($uid === NULL) {
      $uid = $this->currentUser->id();
    }

    $collection = $this->entityTypeManager->getStorage('recipe_collection')->create([
      'name' => $name,
      'uid' => $uid,
    ] + $properties);

    $collection->save();

    return $collection;
  }

  /**
   * Add a recipe to a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $collection
   *   The collection.
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe to add.
   * @param int|null $weight
   *   Optional weight for ordering.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\CollectionItem
   *   The created collection item.
   *
   * @throws \Exception
   *   If recipe is already in collection.
   */
  public function addRecipeToCollection(RecipeCollection $collection, NodeInterface $recipe, ?int $weight = NULL): CollectionItem {
    // Check if already exists.
    if ($this->recipeInCollection($collection, $recipe)) {
      throw new \Exception('Recipe is already in this collection.');
    }

    // Get next weight if not specified.
    if ($weight === NULL) {
      $weight = $this->getNextWeight($collection);
    }

    $item = $this->entityTypeManager->getStorage('collection_item')->create([
      'collection_id' => $collection->id(),
      'recipe_id' => $recipe->id(),
      'weight' => $weight,
    ]);

    $item->save();

    return $item;
  }

  /**
   * Remove a recipe from a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $collection
   *   The collection.
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe to remove.
   *
   * @return bool
   *   TRUE if removed, FALSE if not found.
   */
  public function removeRecipeFromCollection(RecipeCollection $collection, NodeInterface $recipe): bool {
    $items = $this->entityTypeManager->getStorage('collection_item')->loadByProperties([
      'collection_id' => $collection->id(),
      'recipe_id' => $recipe->id(),
    ]);

    if (empty($items)) {
      return FALSE;
    }

    foreach ($items as $item) {
      $item->delete();
    }

    return TRUE;
  }

  /**
   * Check if a recipe is in a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $collection
   *   The collection.
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe.
   *
   * @return bool
   *   TRUE if recipe is in collection.
   */
  public function recipeInCollection(RecipeCollection $collection, NodeInterface $recipe): bool {
    $items = $this->entityTypeManager->getStorage('collection_item')->loadByProperties([
      'collection_id' => $collection->id(),
      'recipe_id' => $recipe->id(),
    ]);

    return !empty($items);
  }

  /**
   * Get all recipes in a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $collection
   *   The collection.
   * @param bool $load_recipes
   *   Whether to load full recipe nodes.
   *
   * @return array
   *   Array of collection items or recipes.
   */
  public function getCollectionRecipes(RecipeCollection $collection, bool $load_recipes = FALSE): array {
    $items = $this->entityTypeManager->getStorage('collection_item')->loadByProperties([
      'collection_id' => $collection->id(),
    ]);

    // Sort by weight.
    usort($items, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    if ($load_recipes) {
      return array_filter(array_map(function ($item) {
        return $item->getRecipe();
      }, $items));
    }

    return $items;
  }

  /**
   * Get all collections for a user.
   *
   * @param int|null $uid
   *   The user ID. Defaults to current user.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\RecipeCollection[]
   *   Array of collections.
   */
  public function getUserCollections(?int $uid = NULL): array {
    if ($uid === NULL) {
      $uid = $this->currentUser->id();
    }

    return $this->entityTypeManager->getStorage('recipe_collection')->loadByProperties([
      'uid' => $uid,
    ]);
  }

  /**
   * Get public collections.
   *
   * @param int $limit
   *   Number of collections to return.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\RecipeCollection[]
   *   Array of public collections.
   */
  public function getPublicCollections(int $limit = 50): array {
    $query = $this->entityTypeManager->getStorage('recipe_collection')->getQuery();
    $query->condition('visibility', 'public');
    $query->sort('changed', 'DESC');
    $query->range(0, $limit);
    $query->accessCheck(TRUE);

    $ids = $query->execute();

    return $this->entityTypeManager->getStorage('recipe_collection')->loadMultiple($ids);
  }

  /**
   * Get collections containing a recipe.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe.
   * @param int|null $uid
   *   Optional user ID to filter by.
   *
   * @return \Drupal\recipeboxx_recipe\Entity\RecipeCollection[]
   *   Array of collections.
   */
  public function getCollectionsForRecipe(NodeInterface $recipe, ?int $uid = NULL): array {
    $query = $this->entityTypeManager->getStorage('collection_item')->getQuery();
    $query->condition('recipe_id', $recipe->id());
    $query->accessCheck(FALSE);

    $item_ids = $query->execute();

    if (empty($item_ids)) {
      return [];
    }

    $items = $this->entityTypeManager->getStorage('collection_item')->loadMultiple($item_ids);

    $collection_ids = [];
    foreach ($items as $item) {
      $collection_ids[] = $item->get('collection_id')->target_id;
    }

    $collection_ids = array_unique($collection_ids);

    $query = $this->entityTypeManager->getStorage('recipe_collection')->getQuery();
    $query->condition('id', $collection_ids, 'IN');

    if ($uid !== NULL) {
      $query->condition('uid', $uid);
    }

    $query->accessCheck(TRUE);

    $ids = $query->execute();

    return $this->entityTypeManager->getStorage('recipe_collection')->loadMultiple($ids);
  }

  /**
   * Get next weight for a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $collection
   *   The collection.
   *
   * @return int
   *   The next weight value.
   */
  protected function getNextWeight(RecipeCollection $collection): int {
    $query = $this->entityTypeManager->getStorage('collection_item')->getQuery();
    $query->condition('collection_id', $collection->id());
    $query->sort('weight', 'DESC');
    $query->range(0, 1);
    $query->accessCheck(FALSE);

    $ids = $query->execute();

    if (empty($ids)) {
      return 0;
    }

    $item = $this->entityTypeManager->getStorage('collection_item')->load(reset($ids));

    return $item->getWeight() + 1;
  }

  /**
   * Reorder items in a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $collection
   *   The collection.
   * @param array $item_order
   *   Array of item IDs in new order.
   */
  public function reorderCollection(RecipeCollection $collection, array $item_order): void {
    foreach ($item_order as $weight => $item_id) {
      $item = $this->entityTypeManager->getStorage('collection_item')->load($item_id);
      if ($item && $item->get('collection_id')->target_id == $collection->id()) {
        $item->setWeight($weight);
        $item->save();
      }
    }
  }

}
