<?php

namespace Drupal\recipeboxx_recipe\Hook;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

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

  /**
   * Implements hook_node_view().
   *
   * Add print link, share buttons, and nutrition label to recipe nodes.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if ($node->bundle() === 'recipe' && $view_mode === 'full') {
      $build['print_link'] = [
        '#type' => 'link',
        '#title' => t('Print Recipe'),
        '#url' => Url::fromRoute('recipeboxx_recipe.print_options', ['node' => $node->id()]),
        '#attributes' => [
          'class' => ['button', 'button--primary', 'use-ajax', 'recipe-print-trigger'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 500,
            'dialogClass' => 'recipe-print-dialog',
          ]),
        ],
        '#attached' => [
          'library' => [
            'core/drupal.dialog.ajax',
            'recipeboxx_recipe/print-trigger',
          ],
        ],
        '#weight' => 100,
      ];

      // Add share buttons.
      $share_service = \Drupal::service('recipeboxx_recipe.share');
      $platforms = $share_service->getSharePlatforms();

      $build['share_buttons'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['recipe-share-buttons']],
        '#weight' => 101,
      ];

      foreach ($platforms as $platform_id => $platform_info) {
        $share_url = $share_service->getShareUrl($node, $platform_id);
        if ($share_url) {
          $build['share_buttons'][$platform_id] = [
            '#type' => 'link',
            '#title' => $platform_info['label'],
            '#url' => Url::fromUri($share_url),
            '#attributes' => [
              'class' => ['share-button', 'share-button--' . $platform_id],
              'target' => '_blank',
              'rel' => 'noopener noreferrer',
            ],
          ];
        }
      }

      // Add "I made this" button.
      $made_it_controller = \Drupal::service('class_resolver')->getInstanceFromDefinition(
        'Drupal\recipeboxx_recipe\Controller\RecipeMadeItController'
      );
      $user_made_it = $made_it_controller->userHasMadeRecipe($node->id());
      $made_count = $node->get('field_made_count')->value ?? 0;

      $build['made_it_button'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['recipe-made-it-container']],
        '#weight' => 45,
      ];

      $build['made_it_button']['button'] = [
        '#type' => 'link',
        '#title' => $user_made_it ? t('I made this ✓') : t('I made this'),
        '#url' => Url::fromRoute('recipeboxx_recipe.made_it_toggle', ['node' => $node->id()]),
        '#attributes' => [
          'class' => ['button', 'recipe-made-it-toggle', $user_made_it ? 'active' : ''],
          'data-recipe-id' => $node->id(),
        ],
      ];

      if ($made_count > 0) {
        $build['made_it_button']['count'] = [
          '#markup' => '<span class="made-it-count">' . \Drupal::translation()->formatPlural(
            $made_count,
            '1 person made this',
            '@count people made this'
          ) . '</span>',
        ];
      }
    }
  }

  /**
   * Implements hook_comment_insert().
   *
   * Update recipe rating when a review is posted.
   */
  #[Hook('comment_insert')]
  public function commentInsert($comment): void {
    if ($comment->bundle() === 'recipe_review') {
      $this->updateRecipeRating($comment->getCommentedEntity());
    }
  }

  /**
   * Implements hook_comment_update().
   *
   * Update recipe rating when a review is edited.
   */
  #[Hook('comment_update')]
  public function commentUpdate($comment): void {
    if ($comment->bundle() === 'recipe_review') {
      $this->updateRecipeRating($comment->getCommentedEntity());
    }
  }

  /**
   * Implements hook_comment_delete().
   *
   * Update recipe rating when a review is deleted.
   */
  #[Hook('comment_delete')]
  public function commentDelete($comment): void {
    if ($comment->bundle() === 'recipe_review') {
      $this->updateRecipeRating($comment->getCommentedEntity());
    }
  }

  /**
   * Update recipe average rating and count.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   */
  protected function updateRecipeRating(NodeInterface $node): void {
    if ($node->bundle() !== 'recipe') {
      return;
    }

    // Get all reviews for this recipe.
    $comment_storage = \Drupal::entityTypeManager()->getStorage('comment');
    $query = $comment_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_id', $node->id())
      ->condition('entity_type', 'node')
      ->condition('comment_type', 'recipe_review')
      ->condition('status', 1);

    $comment_ids = $query->execute();

    if (empty($comment_ids)) {
      $node->set('field_rating_average', 0);
      $node->set('field_rating_count', 0);
      $node->save();
      return;
    }

    $comments = $comment_storage->loadMultiple($comment_ids);
    $total_rating = 0;
    $count = 0;

    foreach ($comments as $comment) {
      if ($comment->hasField('field_rating') && !$comment->get('field_rating')->isEmpty()) {
        $total_rating += $comment->get('field_rating')->value;
        $count++;
      }
    }

    if ($count > 0) {
      $average = round($total_rating / $count, 2);
      $node->set('field_rating_average', $average);
      $node->set('field_rating_count', $count);
      $node->save();
    }
  }

}
