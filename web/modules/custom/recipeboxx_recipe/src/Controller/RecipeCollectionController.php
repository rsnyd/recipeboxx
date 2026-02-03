<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Service\RecipeCollectionService;
use Drupal\recipeboxx_recipe\Entity\RecipeCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for recipe collection functionality.
 */
class RecipeCollectionController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The recipe collection service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\RecipeCollectionService
   */
  protected RecipeCollectionService $collectionService;

  /**
   * Constructs a RecipeCollectionController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\recipeboxx_recipe\Service\RecipeCollectionService $collection_service
   *   The recipe collection service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RecipeCollectionService $collection_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->collectionService = $collection_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('recipeboxx_recipe.collection')
    );
  }

  /**
   * List user's collections.
   *
   * @return array
   *   A render array.
   */
  public function listCollections(): array {
    $collections = $this->collectionService->getUserCollections();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['recipe-collections-page']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collections-header']],
    ];

    $build['header']['title'] = [
      '#markup' => '<h1>' . $this->t('My Recipe Collections') . '</h1>',
    ];

    $build['header']['add'] = [
      '#type' => 'link',
      '#title' => $this->t('Create New Collection'),
      '#url' => \Drupal\Core\Url::fromRoute('entity.recipe_collection.add_form'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    if (empty($collections)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('You have not created any collections yet.') . '</p>',
      ];

      return $build;
    }

    $build['collections'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collections-grid']],
    ];

    foreach ($collections as $collection) {
      $recipes = $this->collectionService->getCollectionRecipes($collection, TRUE);
      $recipe_count = count($recipes);

      $build['collections'][$collection->id()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['collection-card']],
      ];

      if ($collection->hasField('image') && !$collection->get('image')->isEmpty()) {
        $image = $collection->get('image')->entity;
        if ($image) {
          $build['collections'][$collection->id()]['image'] = [
            '#theme' => 'image_style',
            '#style_name' => 'medium',
            '#uri' => $image->getFileUri(),
            '#alt' => $collection->getName(),
          ];
        }
      }

      $build['collections'][$collection->id()]['title'] = [
        '#type' => 'link',
        '#title' => $collection->getName(),
        '#url' => $collection->toUrl(),
        '#attributes' => ['class' => ['collection-title']],
      ];

      if ($collection->getDescription()) {
        $build['collections'][$collection->id()]['description'] = [
          '#markup' => '<div class="collection-description">' .
                       \Drupal\Component\Utility\Xss::filterAdmin($collection->getDescription()) .
                       '</div>',
        ];
      }

      $build['collections'][$collection->id()]['meta'] = [
        '#markup' => '<div class="collection-meta">' .
                     $this->formatPlural($recipe_count, '1 recipe', '@count recipes') .
                     ' &bull; ' .
                     ($collection->isPublic() ? $this->t('Public') : $this->t('Private')) .
                     '</div>',
      ];

      $build['collections'][$collection->id()]['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['collection-actions']],
      ];

      $build['collections'][$collection->id()]['actions']['view'] = [
        '#type' => 'link',
        '#title' => $this->t('View'),
        '#url' => $collection->toUrl(),
        '#attributes' => ['class' => ['button', 'button--small']],
      ];

      $build['collections'][$collection->id()]['actions']['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => $collection->toUrl('edit-form'),
        '#attributes' => ['class' => ['button', 'button--small']],
      ];
    }

    $build['#cache'] = [
      'contexts' => ['user'],
      'tags' => ['recipe_collection_list'],
    ];

    return $build;
  }

  /**
   * View a collection.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $recipe_collection
   *   The collection.
   *
   * @return array
   *   A render array.
   */
  public function viewCollection(RecipeCollection $recipe_collection): array {
    $recipes = $this->collectionService->getCollectionRecipes($recipe_collection, TRUE);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['recipe-collection-view']],
    ];

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collection-header']],
    ];

    $build['header']['title'] = [
      '#markup' => '<h1>' . $recipe_collection->getName() . '</h1>',
    ];

    if ($recipe_collection->getDescription()) {
      $build['header']['description'] = [
        '#markup' => '<div class="collection-description">' .
                     \Drupal\Component\Utility\Xss::filterAdmin($recipe_collection->getDescription()) .
                     '</div>',
      ];
    }

    $build['header']['meta'] = [
      '#markup' => '<div class="collection-meta">' .
                   $this->formatPlural(count($recipes), '1 recipe', '@count recipes') .
                   ' &bull; ' .
                   ($recipe_collection->isPublic() ? $this->t('Public') : $this->t('Private')) .
                   '</div>',
    ];

    // Show action buttons if user is owner.
    if ($recipe_collection->getOwnerId() == $this->currentUser()->id()) {
      $build['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['collection-actions']],
      ];

      $build['actions']['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit Collection'),
        '#url' => $recipe_collection->toUrl('edit-form'),
        '#attributes' => ['class' => ['button']],
      ];

      $build['actions']['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete Collection'),
        '#url' => $recipe_collection->toUrl('delete-form'),
        '#attributes' => ['class' => ['button', 'button--danger']],
      ];
    }

    if (empty($recipes)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('This collection does not have any recipes yet.') . '</p>',
      ];

      return $build;
    }

    $build['recipes'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collection-recipes']],
    ];

    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    foreach ($recipes as $recipe) {
      $build['recipes'][] = $view_builder->view($recipe, 'teaser');
    }

    $build['#cache'] = [
      'tags' => ['recipe_collection:' . $recipe_collection->id()],
    ];

    return $build;
  }

  /**
   * Browse public collections.
   *
   * @return array
   *   A render array.
   */
  public function browsePublicCollections(): array {
    $collections = $this->collectionService->getPublicCollections(100);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['public-collections-page']],
    ];

    $build['header'] = [
      '#markup' => '<h1>' . $this->t('Public Recipe Collections') . '</h1>',
    ];

    if (empty($collections)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No public collections available.') . '</p>',
      ];

      return $build;
    }

    $build['collections'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['collections-grid']],
    ];

    foreach ($collections as $collection) {
      $recipes = $this->collectionService->getCollectionRecipes($collection);
      $recipe_count = count($recipes);

      $build['collections'][$collection->id()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['collection-card']],
      ];

      $build['collections'][$collection->id()]['title'] = [
        '#type' => 'link',
        '#title' => $collection->getName(),
        '#url' => $collection->toUrl(),
        '#attributes' => ['class' => ['collection-title']],
      ];

      if ($collection->getDescription()) {
        $build['collections'][$collection->id()]['description'] = [
          '#markup' => '<div class="collection-description">' .
                       \Drupal\Component\Utility\Xss::filterAdmin($collection->getDescription()) .
                       '</div>',
        ];
      }

      $build['collections'][$collection->id()]['meta'] = [
        '#markup' => '<div class="collection-meta">' .
                     $this->formatPlural($recipe_count, '1 recipe', '@count recipes') .
                     ' &bull; ' .
                     $this->t('by @author', [
                       '@author' => $collection->getOwner()->getDisplayName(),
                     ]) .
                     '</div>',
      ];
    }

    $build['#cache'] = [
      'tags' => ['recipe_collection_list'],
      'max-age' => 3600,
    ];

    return $build;
  }

  /**
   * Add recipe to collection (AJAX).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function addToCollection(NodeInterface $node, Request $request): JsonResponse {
    $collection_id = $request->request->get('collection_id');

    if (!$collection_id) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Collection ID is required.')->render(),
      ], 400);
    }

    $collection = $this->entityTypeManager->getStorage('recipe_collection')->load($collection_id);

    if (!$collection) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Collection not found.')->render(),
      ], 404);
    }

    // Check access.
    if (!$collection->access('update')) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Access denied.')->render(),
      ], 403);
    }

    try {
      $this->collectionService->addRecipeToCollection($collection, $node);

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Recipe added to collection.')->render(),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 400);
    }
  }

  /**
   * Remove recipe from collection (AJAX).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param \Drupal\recipeboxx_recipe\Entity\RecipeCollection $recipe_collection
   *   The collection.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function removeFromCollection(NodeInterface $node, RecipeCollection $recipe_collection): JsonResponse {
    // Check access.
    if (!$recipe_collection->access('update')) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Access denied.')->render(),
      ], 403);
    }

    $removed = $this->collectionService->removeRecipeFromCollection($recipe_collection, $node);

    if ($removed) {
      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Recipe removed from collection.')->render(),
      ]);
    }

    return new JsonResponse([
      'status' => 'error',
      'message' => $this->t('Recipe was not in this collection.')->render(),
    ], 400);
  }

}
