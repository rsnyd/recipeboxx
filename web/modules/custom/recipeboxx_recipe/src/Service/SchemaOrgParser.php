<?php

namespace Drupal\recipeboxx_recipe\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for parsing recipe data from Schema.org/JSON-LD markup.
 */
class SchemaOrgParser {

  /**
   * Constructs a SchemaOrgParser object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   */
  public function __construct(
    private ClientInterface $httpClient,
  ) {}

  /**
   * Parse recipe from Schema.org JSON-LD.
   *
   * @param string $url
   *   The URL to parse.
   *
   * @return array|null
   *   Parsed recipe data or NULL if parsing failed.
   */
  public function parseUrl(string $url): ?array {
    try {
      $response = $this->httpClient->request('GET', $url);
      $html = (string) $response->getBody();

      // Extract JSON-LD from HTML
      $jsonLd = $this->extractJsonLd($html);

      if ($jsonLd && isset($jsonLd['@type']) && $jsonLd['@type'] === 'Recipe') {
        return $this->mapRecipeData($jsonLd, $url);
      }

      return NULL;
    }
    catch (GuzzleException $e) {
      \Drupal::logger('recipeboxx_recipe')->error('Schema.org parsing failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Extract JSON-LD from HTML.
   *
   * @param string $html
   *   The HTML content.
   *
   * @return array|null
   *   Parsed JSON-LD data or NULL.
   */
  private function extractJsonLd(string $html): ?array {
    // Parse HTML and find script tags with type="application/ld+json"
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $matches);

    foreach ($matches[1] as $json) {
      $data = json_decode($json, TRUE);
      if (isset($data['@type']) && $data['@type'] === 'Recipe') {
        return $data;
      }
      // Handle @graph structure
      if (isset($data['@graph'])) {
        foreach ($data['@graph'] as $item) {
          if (isset($item['@type']) && $item['@type'] === 'Recipe') {
            return $item;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Map JSON-LD data to recipe fields.
   *
   * @param array $jsonLd
   *   The JSON-LD data.
   * @param string $sourceUrl
   *   The source URL.
   *
   * @return array
   *   Mapped recipe data.
   */
  private function mapRecipeData(array $jsonLd, string $sourceUrl): array {
    return [
      'title' => $jsonLd['name'] ?? '',
      'body' => $this->parseInstructions($jsonLd['recipeInstructions'] ?? []),
      'ingredients' => $this->parseIngredients($jsonLd['recipeIngredient'] ?? []),
      'prep_time' => $this->parseIso8601Duration($jsonLd['prepTime'] ?? NULL),
      'cook_time' => $this->parseIso8601Duration($jsonLd['cookTime'] ?? NULL),
      'servings' => $this->parseServings($jsonLd['recipeYield'] ?? NULL),
      'calories' => $this->extractCalories($jsonLd['nutrition'] ?? []),
      'images' => $this->extractImages($jsonLd['image'] ?? []),
      'author' => $this->extractAuthor($jsonLd['author'] ?? NULL),
      'source_url' => $sourceUrl,
      'cuisine' => $jsonLd['recipeCuisine'] ?? NULL,
      'category' => $jsonLd['recipeCategory'] ?? NULL,
    ];
  }

  /**
   * Parse ingredients from array or string.
   *
   * @param mixed $ingredients
   *   Ingredients data.
   *
   * @return string
   *   One ingredient per line.
   */
  private function parseIngredients($ingredients): string {
    if (is_array($ingredients)) {
      return implode("\n", $ingredients);
    }
    return (string) $ingredients;
  }

  /**
   * Parse instructions from various formats.
   *
   * @param mixed $instructions
   *   Instructions data.
   *
   * @return string
   *   Formatted instructions.
   */
  private function parseInstructions($instructions): string {
    if (is_string($instructions)) {
      return $instructions;
    }

    if (is_array($instructions)) {
      $steps = [];
      foreach ($instructions as $step) {
        if (is_string($step)) {
          $steps[] = $step;
        }
        elseif (isset($step['text'])) {
          $steps[] = $step['text'];
        }
      }
      return implode("\n\n", $steps);
    }

    return '';
  }

  /**
   * Parse ISO 8601 duration to minutes.
   *
   * @param string|null $duration
   *   ISO 8601 duration string (e.g., "PT30M").
   *
   * @return int|null
   *   Minutes or NULL.
   */
  private function parseIso8601Duration(?string $duration): ?int {
    if (!$duration) {
      return NULL;
    }

    // Parse ISO 8601 duration (PT#H#M)
    $minutes = 0;
    if (preg_match('/PT(\d+)H/', $duration, $hours)) {
      $minutes += (int) $hours[1] * 60;
    }
    if (preg_match('/PT(?:\d+H)?(\d+)M/', $duration, $mins)) {
      $minutes += (int) $mins[1];
    }

    return $minutes > 0 ? $minutes : NULL;
  }

  /**
   * Parse servings from string or number.
   *
   * @param mixed $yield
   *   Yield data.
   *
   * @return int|null
   *   Number of servings.
   */
  private function parseServings($yield): ?int {
    if (is_numeric($yield)) {
      return (int) $yield;
    }

    if (is_string($yield)) {
      // Extract number from string like "4 servings" or "Makes 6"
      if (preg_match('/(\d+)/', $yield, $matches)) {
        return (int) $matches[1];
      }
    }

    return NULL;
  }

  /**
   * Extract calories from nutrition data.
   *
   * @param array $nutrition
   *   Nutrition data.
   *
   * @return int|null
   *   Calories or NULL.
   */
  private function extractCalories(array $nutrition): ?int {
    if (isset($nutrition['calories'])) {
      // Extract number from strings like "350 calories"
      if (is_numeric($nutrition['calories'])) {
        return (int) $nutrition['calories'];
      }
      if (preg_match('/(\d+)/', $nutrition['calories'], $matches)) {
        return (int) $matches[1];
      }
    }

    return NULL;
  }

  /**
   * Extract image URLs.
   *
   * @param mixed $image
   *   Image data.
   *
   * @return array
   *   Array of image URLs.
   */
  private function extractImages($image): array {
    $images = [];

    if (is_string($image)) {
      $images[] = $image;
    }
    elseif (is_array($image)) {
      foreach ($image as $img) {
        if (is_string($img)) {
          $images[] = $img;
        }
        elseif (isset($img['url'])) {
          $images[] = $img['url'];
        }
      }
    }

    return $images;
  }

  /**
   * Extract author name.
   *
   * @param mixed $author
   *   Author data.
   *
   * @return string|null
   *   Author name or NULL.
   */
  private function extractAuthor($author): ?string {
    if (is_string($author)) {
      return $author;
    }

    if (is_array($author) && isset($author['name'])) {
      return $author['name'];
    }

    return NULL;
  }

}
