<?php

namespace Drupal\recipeboxx_recipe\Service;

/**
 * Service for importing recipes from URLs using Schema.org or AI fallback.
 */
class RecipeScraperService {

  /**
   * Constructs a RecipeScraperService object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\SchemaOrgParser $schemaParser
   *   The Schema.org parser.
   * @param \Drupal\recipeboxx_recipe\Service\AiFallbackParser $aiParser
   *   The AI fallback parser.
   */
  public function __construct(
    private SchemaOrgParser $schemaParser,
    private AiFallbackParser $aiParser,
  ) {}

  /**
   * Import recipe from URL using Schema.org or AI fallback.
   *
   * @param string $url
   *   The URL to import from.
   *
   * @return array|null
   *   Recipe data or NULL if import failed.
   */
  public function importFromUrl(string $url): ?array {
    // Try Schema.org first
    $recipeData = $this->schemaParser->parseUrl($url);

    if ($recipeData) {
      \Drupal::logger('recipeboxx_recipe')->info('Recipe imported via Schema.org from @url', [
        '@url' => $url,
      ]);
      return $recipeData;
    }

    // Fallback to AI
    try {
      $response = \Drupal::httpClient()->request('GET', $url);
      $html = (string) $response->getBody();

      $recipeData = $this->aiParser->parseUrl($url, $html);

      if ($recipeData) {
        \Drupal::logger('recipeboxx_recipe')->info('Recipe imported via AI from @url', [
          '@url' => $url,
        ]);
        return $recipeData;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('recipeboxx_recipe')->error('Failed to fetch URL for AI parsing: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

}
