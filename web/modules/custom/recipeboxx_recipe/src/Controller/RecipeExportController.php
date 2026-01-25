<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Service\RecipeExportService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for recipe export functionality.
 */
class RecipeExportController extends ControllerBase {

  /**
   * The recipe export service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\RecipeExportService
   */
  protected RecipeExportService $exportService;

  /**
   * Constructs a RecipeExportController object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\RecipeExportService $export_service
   *   The recipe export service.
   */
  public function __construct(RecipeExportService $export_service) {
    $this->exportService = $export_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipeboxx_recipe.export')
    );
  }

  /**
   * Display export options page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return array
   *   A render array.
   */
  public function exportOptions(NodeInterface $node): array {
    $formats = $this->exportService->getAvailableFormats();

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['recipe-export-options']],
    ];

    $build['title'] = [
      '#markup' => '<h2>' . $this->t('Export @recipe', ['@recipe' => $node->getTitle()]) . '</h2>',
    ];

    $build['formats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['export-formats']],
    ];

    foreach ($formats as $format_key => $format) {
      $route_name = 'recipeboxx_recipe.export_' . ($format_key === 'jsonld' ? 'jsonld' : $format_key);

      $build['formats'][$format_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['export-format-option']],
      ];

      $build['formats'][$format_key]['link'] = [
        '#type' => 'link',
        '#title' => $format['label'],
        '#url' => \Drupal\Core\Url::fromRoute($route_name, ['node' => $node->id()]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];

      $build['formats'][$format_key]['description'] = [
        '#markup' => '<p>' . $format['description'] . '</p>',
      ];
    }

    return $build;
  }

  /**
   * Export recipe as JSON.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON response.
   */
  public function exportJson(NodeInterface $node, Request $request): Response {
    $servings = $request->query->get('servings');
    $json = $this->exportService->exportToJson($node, $servings ? (int) $servings : NULL);

    $response = new Response($json);
    $response->headers->set('Content-Type', 'application/json');
    $response->headers->set('Content-Disposition', 'attachment; filename="recipe-' . $node->id() . '.json"');

    return $response;
  }

  /**
   * Export recipe as JSON-LD.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   JSON-LD response.
   */
  public function exportJsonLd(NodeInterface $node): Response {
    $jsonld = $this->exportService->exportToJsonLd($node);

    $response = new Response($jsonld);
    $response->headers->set('Content-Type', 'application/ld+json');
    $response->headers->set('Content-Disposition', 'attachment; filename="recipe-' . $node->id() . '-schema.json"');

    return $response;
  }

  /**
   * Export recipe card for printing.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   Render array for recipe card.
   */
  public function exportCard(NodeInterface $node, Request $request): array {
    $servings = $request->query->get('servings');

    return $this->exportService->generateRecipeCard($node, $servings ? (int) $servings : NULL);
  }

}
