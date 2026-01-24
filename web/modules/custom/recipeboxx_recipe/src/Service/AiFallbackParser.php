<?php

namespace Drupal\recipeboxx_recipe\Service;

use OpenAI;

/**
 * Service for parsing recipe data using AI when Schema.org fails.
 */
class AiFallbackParser {

  /**
   * OpenAI client.
   *
   * @var \OpenAI\Client|null
   */
  private ?object $client = NULL;

  /**
   * Constructs an AiFallbackParser object.
   */
  public function __construct() {
    $api_key = \Drupal::config('recipeboxx_recipe.settings')->get('openai_api_key');
    if ($api_key) {
      $this->client = OpenAI::client($api_key);
    }
  }

  /**
   * Parse recipe using AI when Schema.org fails.
   *
   * @param string $url
   *   The source URL.
   * @param string $html
   *   The HTML content.
   *
   * @return array|null
   *   Parsed recipe data or NULL if parsing failed.
   */
  public function parseUrl(string $url, string $html): ?array {
    if (!$this->client) {
      \Drupal::logger('recipeboxx_recipe')->warning('OpenAI API key not configured. Cannot use AI fallback parser.');
      return NULL;
    }

    try {
      $prompt = $this->buildPrompt($html);

      $result = $this->client->chat()->create([
        'model' => 'gpt-4',
        'messages' => [
          [
            'role' => 'system',
            'content' => 'You are a recipe extraction expert. Extract recipe data from the provided text and return it as JSON. If you cannot find a specific field, omit it or use null.',
          ],
          [
            'role' => 'user',
            'content' => $prompt,
          ],
        ],
        'response_format' => ['type' => 'json_object'],
      ]);

      $recipeData = json_decode($result->choices[0]->message->content, TRUE);
      if ($recipeData) {
        $recipeData['source_url'] = $url;
        return $recipeData;
      }

      return NULL;
    }
    catch (\Exception $e) {
      \Drupal::logger('recipeboxx_recipe')->error('AI parsing failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Build prompt for AI extraction.
   *
   * @param string $html
   *   The HTML content.
   *
   * @return string
   *   The prompt text.
   */
  private function buildPrompt(string $html): string {
    // Strip HTML to plain text, limit size
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = substr($text, 0, 8000); // Limit for API

    return "Extract the recipe from this webpage text and return JSON with these fields:\n" .
      "{\n" .
      '  "title": "Recipe title",\n' .
      '  "ingredients": "One ingredient per line",\n' .
      '  "body": "Step by step instructions",\n' .
      '  "prep_time": 30,  // in minutes\n' .
      '  "cook_time": 45,  // in minutes\n' .
      '  "servings": 4,  // number of servings\n' .
      '  "calories": 350,  // per serving\n' .
      '  "author": "Author name",\n' .
      '  "cuisine": "Cuisine type",\n' .
      '  "category": "Meal category"\n' .
      "}\n\n" .
      "Webpage text:\n" .
      $text;
  }

}
