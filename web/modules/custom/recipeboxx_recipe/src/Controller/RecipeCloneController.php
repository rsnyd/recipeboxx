<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Service\RecipeCloneService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for cloning recipes.
 */
class RecipeCloneController extends ControllerBase {

  /**
   * Constructs a RecipeCloneController object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\RecipeCloneService $cloneService
   *   The clone service.
   */
  public function __construct(
    private RecipeCloneService $cloneService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipeboxx_recipe.clone_service')
    );
  }

  /**
   * Clone a recipe.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node to clone.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the cloned recipe.
   */
  public function clone(NodeInterface $node): RedirectResponse {
    try {
      $clonedRecipe = $this->cloneService->cloneRecipe($node, $this->currentUser());

      $this->messenger()->addStatus($this->t('Recipe "@title" has been cloned to your collection!', [
        '@title' => $node->label(),
      ]));

      // Redirect to the cloned recipe
      return new RedirectResponse($clonedRecipe->toUrl()->toString());
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while cloning the recipe: @message', [
        '@message' => $e->getMessage(),
      ]));

      // Redirect back to the original recipe
      return new RedirectResponse($node->toUrl()->toString());
    }
  }

}
