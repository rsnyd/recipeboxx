<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for "I made this" functionality.
 */
class RecipeMadeItController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs a RecipeMadeItController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Toggle "I made this" for a recipe.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with status.
   */
  public function toggle(NodeInterface $node): JsonResponse {
    if ($node->bundle() !== 'recipe') {
      return new JsonResponse(['error' => 'Invalid recipe'], 400);
    }

    $current_user = $this->currentUser();

    if (!$current_user->isAuthenticated()) {
      return new JsonResponse(['error' => 'Must be logged in'], 403);
    }

    $recipe_id = $node->id();
    $user_id = $current_user->id();

    // Check if already marked.
    $exists = $this->database->select('recipeboxx_recipe_made', 'rm')
      ->fields('rm', ['recipe_id'])
      ->condition('recipe_id', $recipe_id)
      ->condition('user_id', $user_id)
      ->execute()
      ->fetchField();

    if ($exists) {
      // Remove the mark.
      $this->database->delete('recipeboxx_recipe_made')
        ->condition('recipe_id', $recipe_id)
        ->condition('user_id', $user_id)
        ->execute();

      // Decrement count on node.
      if ($node->hasField('field_made_count')) {
        $current_count = $node->get('field_made_count')->value ?? 0;
        $node->set('field_made_count', max(0, $current_count - 1));
        $node->save();
      }

      return new JsonResponse([
        'status' => 'removed',
        'count' => $node->get('field_made_count')->value ?? 0,
        'message' => $this->t('Removed from "I made this"')->render(),
      ]);
    }
    else {
      // Add the mark.
      $this->database->insert('recipeboxx_recipe_made')
        ->fields([
          'recipe_id' => $recipe_id,
          'user_id' => $user_id,
          'created' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();

      // Increment count on node.
      if ($node->hasField('field_made_count')) {
        $current_count = $node->get('field_made_count')->value ?? 0;
        $node->set('field_made_count', $current_count + 1);
        $node->save();
      }

      return new JsonResponse([
        'status' => 'added',
        'count' => $node->get('field_made_count')->value ?? 0,
        'message' => $this->t('Added to "I made this"')->render(),
      ]);
    }
  }

  /**
   * Check if current user has marked a recipe as "made it".
   *
   * @param int $recipe_id
   *   The recipe node ID.
   * @param int|null $user_id
   *   The user ID (defaults to current user).
   *
   * @return bool
   *   TRUE if user has made this recipe.
   */
  public function userHasMadeRecipe(int $recipe_id, ?int $user_id = NULL): bool {
    if ($user_id === NULL) {
      $user_id = $this->currentUser()->id();
    }

    if (!$user_id) {
      return FALSE;
    }

    $exists = $this->database->select('recipeboxx_recipe_made', 'rm')
      ->fields('rm', ['recipe_id'])
      ->condition('recipe_id', $recipe_id)
      ->condition('user_id', $user_id)
      ->execute()
      ->fetchField();

    return (bool) $exists;
  }

}
