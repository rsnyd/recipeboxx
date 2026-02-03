<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\recipeboxx_recipe\Service\RecipeSearchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for recipe search functionality.
 */
class RecipeSearchController extends ControllerBase {

  /**
   * The recipe search service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\RecipeSearchService
   */
  protected RecipeSearchService $searchService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a RecipeSearchController object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\RecipeSearchService $search_service
   *   The recipe search service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RecipeSearchService $search_service, EntityTypeManagerInterface $entity_type_manager) {
    $this->searchService = $search_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipeboxx_recipe.search'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the recipe search page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   A render array.
   */
  public function searchPage(Request $request): array {
    $keyword = $request->query->get('keyword', '');
    $page = $request->query->get('page', 0);
    $per_page = 20;

    // Build filters from query parameters.
    $filters = [];
    if ($cuisine = $request->query->get('cuisine')) {
      $filters['cuisine'] = $cuisine;
    }
    if ($dietary = $request->query->get('dietary')) {
      $filters['dietary'] = is_array($dietary) ? $dietary : [$dietary];
    }
    if ($category = $request->query->get('category')) {
      $filters['category'] = is_array($category) ? $category : [$category];
    }
    if ($prep_time = $request->query->get('prep_time_max')) {
      $filters['prep_time_max'] = (int) $prep_time;
    }
    if ($cook_time = $request->query->get('cook_time_max')) {
      $filters['cook_time_max'] = (int) $cook_time;
    }

    // Execute search.
    $search_results = $this->searchService->searchRecipes(
      $keyword,
      $filters,
      $per_page,
      $page * $per_page
    );

    // Load recipe nodes.
    $recipe_nodes = [];
    if (!empty($search_results['results'])) {
      $nids = array_column($search_results['results'], 'nid');
      $recipe_nodes = $this->entityTypeManager
        ->getStorage('node')
        ->loadMultiple($nids);
    }

    // Build render array.
    $build = [
      '#theme' => 'recipeboxx_recipe_search_page',
      '#keyword' => $keyword,
      '#filters' => $filters,
      '#results' => $recipe_nodes,
      '#total' => $search_results['total'],
      '#page' => $page,
      '#per_page' => $per_page,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/search',
        ],
      ],
    ];

    // Add search form.
    $build['search_form'] = $this->formBuilder()->getForm('Drupal\recipeboxx_recipe\Form\RecipeSearchForm');

    // Add facets for filtering.
    $build['facets'] = $this->buildFacets($keyword);

    // Add results view.
    $build['results'] = [
      '#theme' => 'recipeboxx_recipe_search_results',
      '#recipes' => $recipe_nodes,
      '#keyword' => $keyword,
    ];

    // Add pager.
    if ($search_results['total'] > $per_page) {
      $build['pager'] = [
        '#type' => 'pager',
        '#quantity' => 5,
      ];
    }

    return $build;
  }

  /**
   * Build facet blocks for filtering.
   *
   * @param string $keyword
   *   Current search keyword.
   *
   * @return array
   *   Render array of facets.
   */
  protected function buildFacets(string $keyword): array {
    $facets = [
      '#type' => 'container',
      '#attributes' => ['class' => ['recipe-search-facets']],
    ];

    // Get facet data for common fields.
    $facet_fields = [
      'field_cuisine' => $this->t('Cuisine'),
      'field_dietary_restrictions' => $this->t('Dietary'),
      'field_recipe_category' => $this->t('Category'),
    ];

    foreach ($facet_fields as $field => $label) {
      $facet_data = $this->searchService->getFacets($field, $keyword);

      if (!empty($facet_data)) {
        $facets[$field] = [
          '#theme' => 'item_list',
          '#title' => $label,
          '#items' => $this->formatFacetItems($facet_data, $field),
          '#attributes' => ['class' => ['facet-' . str_replace('_', '-', $field)]],
        ];
      }
    }

    return $facets;
  }

  /**
   * Format facet items as links.
   *
   * @param array $facet_data
   *   Raw facet data from search service.
   * @param string $field
   *   The field name.
   *
   * @return array
   *   Array of formatted facet items.
   */
  protected function formatFacetItems(array $facet_data, string $field): array {
    $items = [];

    foreach ($facet_data as $value => $count) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('@value (@count)', [
          '@value' => $value,
          '@count' => $count,
        ]),
        '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.search', [], [
          'query' => [
            str_replace('field_', '', $field) => $value,
          ],
        ]),
        '#attributes' => ['class' => ['facet-item']],
      ];
    }

    return $items;
  }

  /**
   * Displays the popular recipes page.
   *
   * @return array
   *   A render array.
   */
  public function popularRecipes(): array {
    $nids = $this->searchService->getPopularRecipes(20);
    $recipes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);

    return [
      '#theme' => 'recipeboxx_recipe_list',
      '#recipes' => $recipes,
      '#title' => $this->t('Popular Recipes'),
      '#cache' => [
        'max-age' => 3600,
        'tags' => ['node_list:recipe'],
      ],
    ];
  }

  /**
   * Displays the recent recipes page.
   *
   * @return array
   *   A render array.
   */
  public function recentRecipes(): array {
    $nids = $this->searchService->getRecentRecipes(20);
    $recipes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);

    return [
      '#theme' => 'recipeboxx_recipe_list',
      '#recipes' => $recipes,
      '#title' => $this->t('Recent Recipes'),
      '#cache' => [
        'max-age' => 1800,
        'tags' => ['node_list:recipe'],
      ],
    ];
  }

  /**
   * Displays the top rated recipes page.
   *
   * @return array
   *   A render array.
   */
  public function topRatedRecipes(): array {
    $nids = $this->searchService->getTopRatedRecipes(20);
    $recipes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);

    return [
      '#theme' => 'recipeboxx_recipe_list',
      '#recipes' => $recipes,
      '#title' => $this->t('Top Rated Recipes'),
      '#cache' => [
        'max-age' => 3600,
        'tags' => ['node_list:recipe', 'comment_list'],
      ],
    ];
  }

  /**
   * Page title callback for search page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   The page title.
   */
  public function searchPageTitle(Request $request): string {
    $keyword = $request->query->get('keyword', '');

    if (!empty($keyword)) {
      return $this->t('Search results for "@keyword"', ['@keyword' => $keyword]);
    }

    return $this->t('Search Recipes');
  }

}
