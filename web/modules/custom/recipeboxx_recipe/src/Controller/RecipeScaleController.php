<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Service\RecipeScalingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for recipe scaling functionality.
 */
class RecipeScaleController extends ControllerBase {

  /**
   * The recipe scaling service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\RecipeScalingService
   */
  protected RecipeScalingService $scalingService;

  /**
   * Constructs a RecipeScaleController object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\RecipeScalingService $scaling_service
   *   The recipe scaling service.
   */
  public function __construct(RecipeScalingService $scaling_service) {
    $this->scalingService = $scaling_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipeboxx_recipe.scaling')
    );
  }

  /**
   * Scale a recipe (AJAX endpoint).
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with scaled recipe data.
   */
  public function scale(NodeInterface $node, Request $request): JsonResponse {
    $servings = $request->request->get('servings') ?? $request->query->get('servings');

    if (!$servings || !is_numeric($servings) || $servings < 1) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Invalid servings value.')->render(),
      ], 400);
    }

    try {
      $scaled_data = $this->scalingService->scaleRecipe($node, (int) $servings);

      return new JsonResponse([
        'status' => 'success',
        'data' => $scaled_data,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

}
