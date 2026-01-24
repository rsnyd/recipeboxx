<?php

namespace Drupal\recipeboxx_recipe\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

/**
 * Access check for cloning recipes.
 */
class RecipeCloneAccessCheck implements AccessInterface {

  /**
   * Check access for cloning a recipe.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\node\NodeInterface|null $node
   *   The recipe node.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, NodeInterface $node = NULL) {
    // Must be a recipe node
    if (!$node || $node->bundle() !== 'recipe') {
      return AccessResult::forbidden('Not a recipe node');
    }

    // Must be public
    $visibility = $node->get('field_recipe_visibility')->value ?? 'private';
    if ($visibility !== 'public') {
      return AccessResult::forbidden('Recipe is not public');
    }

    // Cannot clone own recipe
    if ($node->getOwnerId() == $account->id()) {
      return AccessResult::forbidden('Cannot clone your own recipe');
    }

    // Must have permission
    if (!$account->hasPermission('clone public recipes')) {
      return AccessResult::forbidden('No permission to clone recipes');
    }

    return AccessResult::allowed();
  }

}
