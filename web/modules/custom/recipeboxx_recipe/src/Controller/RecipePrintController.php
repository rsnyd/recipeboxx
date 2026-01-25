<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for generating print views of recipes.
 */
class RecipePrintController extends ControllerBase {

  /**
   * Constructs a RecipePrintController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(
    private RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * Generates a print-friendly view of a recipe.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node to print.
   *
   * @return array
   *   Render array for the print view.
   */
  public function printView(NodeInterface $node) {
    // Get selected sections from query parameter
    $request = $this->requestStack->getCurrentRequest();
    $sections_param = $request->query->get('sections', '');
    $selected_sections = $sections_param ? explode(',', $sections_param) : [];

    // Build render array for print view
    $build = [
      '#theme' => 'recipeboxx_recipe_print',
      '#node' => $node,
      '#selected_sections' => $selected_sections,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/print',
        ],
      ],
    ];

    return $build;
  }

}
