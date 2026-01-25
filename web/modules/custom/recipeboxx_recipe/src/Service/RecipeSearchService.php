<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Service for programmatic recipe search functionality.
 */
class RecipeSearchService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a RecipeSearchService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Search for recipes by keyword.
   *
   * @param string $keyword
   *   The search keyword.
   * @param array $filters
   *   Optional filters (cuisine, dietary, category, etc.).
   * @param int $limit
   *   Maximum number of results to return.
   * @param int $offset
   *   Offset for pagination.
   *
   * @return array
   *   Array of search results with recipe node IDs and metadata.
   */
  public function searchRecipes(string $keyword = '', array $filters = [], int $limit = 50, int $offset = 0): array {
    try {
      // Get the Search API index.
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load('recipes');

      if (!$index) {
        return ['results' => [], 'total' => 0];
      }

      // Create query.
      $query = $index->query();

      // Add keyword search if provided.
      if (!empty($keyword)) {
        $query->keys($keyword);
      }

      // Apply filters.
      if (!empty($filters['cuisine'])) {
        $query->addCondition('field_cuisine', $filters['cuisine']);
      }
      if (!empty($filters['dietary'])) {
        $query->addCondition('field_dietary_restrictions', $filters['dietary'], 'IN');
      }
      if (!empty($filters['category'])) {
        $query->addCondition('field_recipe_category', $filters['category'], 'IN');
      }
      if (!empty($filters['prep_time_max'])) {
        $query->addCondition('field_prep_time', $filters['prep_time_max'], '<=');
      }
      if (!empty($filters['cook_time_max'])) {
        $query->addCondition('field_cook_time', $filters['cook_time_max'], '<=');
      }

      // Set range and sort.
      $query->range($offset, $limit);
      $query->sort('search_api_relevance', 'DESC');

      // Execute query.
      $results = $query->execute();
      $result_items = $results->getResultItems();

      $recipe_results = [];
      foreach ($result_items as $item) {
        $recipe_results[] = [
          'nid' => $item->getId(),
          'score' => $item->getScore(),
        ];
      }

      return [
        'results' => $recipe_results,
        'total' => $results->getResultCount(),
      ];

    }
    catch (\Exception $e) {
      \Drupal::logger('recipeboxx_recipe')->error('Search error: @message', ['@message' => $e->getMessage()]);
      return ['results' => [], 'total' => 0];
    }
  }

  /**
   * Get facet data for search filters.
   *
   * @param string $facet_field
   *   The field to get facet counts for.
   * @param string $keyword
   *   Optional search keyword to filter facets.
   *
   * @return array
   *   Array of facet values and counts.
   */
  public function getFacets(string $facet_field, string $keyword = ''): array {
    try {
      $index = $this->entityTypeManager
        ->getStorage('search_api_index')
        ->load('recipes');

      if (!$index) {
        return [];
      }

      $query = $index->query();

      if (!empty($keyword)) {
        $query->keys($keyword);
      }

      // Get facets using Search API's facet support.
      $query->setOption('search_api_facets', [
        $facet_field => [
          'field' => $facet_field,
          'limit' => 50,
          'operator' => 'and',
          'min_count' => 1,
        ],
      ]);

      $results = $query->execute();
      $facets = $results->getExtraData('search_api_facets', []);

      return $facets[$facet_field] ?? [];

    }
    catch (\Exception $e) {
      \Drupal::logger('recipeboxx_recipe')->error('Facet error: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Get popular/trending recipes.
   *
   * @param int $limit
   *   Number of recipes to return.
   *
   * @return array
   *   Array of node IDs.
   */
  public function getPopularRecipes(int $limit = 10): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', 1)
      ->sort('field_made_count', 'DESC')
      ->range(0, $limit);

    $nids = $query->execute();
    return array_values($nids);
  }

  /**
   * Get recently added recipes.
   *
   * @param int $limit
   *   Number of recipes to return.
   *
   * @return array
   *   Array of node IDs.
   */
  public function getRecentRecipes(int $limit = 10): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit);

    $nids = $query->execute();
    return array_values($nids);
  }

  /**
   * Get highest rated recipes.
   *
   * @param int $limit
   *   Number of recipes to return.
   *
   * @return array
   *   Array of node IDs.
   */
  public function getTopRatedRecipes(int $limit = 10): array {
    $query = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', 1)
      ->condition('field_rating_count', 5, '>=')
      ->sort('field_rating_average', 'DESC')
      ->range(0, $limit);

    $nids = $query->execute();
    return array_values($nids);
  }

}
