<?php

namespace Drupal\recipeboxx_recipe\Hook;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for Recipe Box module.
 */
class RecipeBoxHooks {

  /**
   * Implements hook_node_grants().
   *
   * Defines access grants for the current user.
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    $grants = [];

    if ($account->isAuthenticated()) {
      // Grant access to own recipes
      $grants['recipeboxx_author'][] = $account->id();

      // Grant access to all public recipes for viewing
      if ($operation === 'view') {
        $grants['recipeboxx_public'][] = 1;
      }
    }

    return $grants;
  }

  /**
   * Implements hook_node_access_records().
   *
   * Define access records for a recipe node.
   */
  #[Hook('node_access_records')]
  public function nodeAccessRecords(NodeInterface $node): array {
    $grants = [];

    if ($node->bundle() !== 'recipe') {
      return $grants;
    }

    $visibility = $node->get('field_recipe_visibility')->value ?? 'private';

    // Author always has full access
    $grants[] = [
      'realm' => 'recipeboxx_author',
      'gid' => $node->getOwnerId(),
      'grant_view' => 1,
      'grant_update' => 1,
      'grant_delete' => 1,
    ];

    // Public recipes viewable by all authenticated users
    if ($visibility === 'public' && $node->isPublished()) {
      $grants[] = [
        'realm' => 'recipeboxx_public',
        'gid' => 1,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ];
    }

    return $grants;
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for node.
   *
   * Calculate computed fields before saving.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() === 'recipe') {
      // Calculate total time if prep and cook times are set
      if ($node->hasField('field_prep_time') && $node->hasField('field_cook_time') && $node->hasField('field_total_time')) {
        $prep = $node->get('field_prep_time')->value ?? 0;
        $cook = $node->get('field_cook_time')->value ?? 0;
        $node->set('field_total_time', $prep + $cook);
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node.
   *
   * Rebuild access grants when visibility changes.
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    if ($node->bundle() === 'recipe') {
      // Rebuild node access if visibility changed
      if (isset($node->original)) {
        $original_visibility = $node->original->get('field_recipe_visibility')->value ?? 'private';
        $new_visibility = $node->get('field_recipe_visibility')->value ?? 'private';

        if ($original_visibility !== $new_visibility) {
          \Drupal::service('node.grant_storage')->write($node, [], NULL, FALSE);
        }
      }
    }
  }

}
