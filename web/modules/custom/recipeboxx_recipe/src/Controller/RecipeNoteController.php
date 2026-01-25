<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for recipe note functionality.
 */
class RecipeNoteController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a RecipeNoteController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * View user's note for a recipe.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return array
   *   A render array.
   */
  public function viewNote(NodeInterface $node): array {
    $current_user = $this->currentUser();

    // Find existing note for this user and recipe.
    $note_storage = $this->entityTypeManager->getStorage('recipe_note');
    $notes = $note_storage->loadByProperties([
      'uid' => $current_user->id(),
      'recipe_id' => $node->id(),
    ]);

    $note = !empty($notes) ? reset($notes) : NULL;

    $build = [];

    if ($note) {
      // Display existing note.
      $view_builder = $this->entityTypeManager->getViewBuilder('recipe_note');
      $build['note'] = $view_builder->view($note);

      $build['edit_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit Note'),
        '#url' => $note->toUrl('edit-form'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }
    else {
      // Show message and link to add note.
      $build['message'] = [
        '#markup' => '<p>' . $this->t('You have not added a note for this recipe yet.') . '</p>',
      ];

      $build['add_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Note'),
        '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.note_add', ['node' => $node->id()]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];
    }

    $build['back_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to Recipe'),
      '#url' => $node->toUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $build;
  }

}
