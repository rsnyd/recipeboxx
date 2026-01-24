<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Service for cloning public recipes.
 */
class RecipeCloneService {

  /**
   * Clone a public recipe to user's collection.
   *
   * @param \Drupal\node\NodeInterface $sourceRecipe
   *   The source recipe to clone.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to clone to.
   *
   * @return \Drupal\node\NodeInterface
   *   The cloned recipe.
   */
  public function cloneRecipe(NodeInterface $sourceRecipe, AccountInterface $account): NodeInterface {
    // Create duplicate with new ownership
    $clonedRecipe = $sourceRecipe->createDuplicate();

    // Set new owner
    $clonedRecipe->setOwnerId($account->id());

    // Set as private by default
    $clonedRecipe->set('field_recipe_visibility', 'private');

    // Reference original recipe
    if ($clonedRecipe->hasField('field_original_recipe')) {
      $clonedRecipe->set('field_original_recipe', $sourceRecipe->id());
    }

    // Clear clone count (it's the source's count)
    if ($clonedRecipe->hasField('field_clone_count')) {
      $clonedRecipe->set('field_clone_count', 0);
    }

    $clonedRecipe->save();

    // Increment clone count on original
    $this->incrementCloneCount($sourceRecipe);

    return $clonedRecipe;
  }

  /**
   * Increment the clone count on a recipe.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe to increment.
   */
  private function incrementCloneCount(NodeInterface $recipe): void {
    if ($recipe->hasField('field_clone_count')) {
      $count = $recipe->get('field_clone_count')->value ?? 0;
      $recipe->set('field_clone_count', $count + 1);
      $recipe->save();
    }
  }

}
