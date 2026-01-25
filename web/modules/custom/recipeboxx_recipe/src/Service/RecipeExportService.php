<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for exporting recipes to various formats.
 */
class RecipeExportService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The recipe scaling service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\RecipeScalingService
   */
  protected RecipeScalingService $scalingService;

  /**
   * Constructs a RecipeExportService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\recipeboxx_recipe\Service\RecipeScalingService $scaling_service
   *   The recipe scaling service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
    RecipeScalingService $scaling_service
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->loggerFactory = $logger_factory;
    $this->scalingService = $scaling_service;
  }

  /**
   * Export recipe to JSON format.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param int|null $servings
   *   Optional scaled servings.
   *
   * @return string
   *   JSON string.
   */
  public function exportToJson(NodeInterface $recipe, ?int $servings = NULL): string {
    $data = $this->buildRecipeData($recipe, $servings);

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Export recipe to Schema.org JSON-LD format.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   *
   * @return string
   *   JSON-LD string.
   */
  public function exportToJsonLd(NodeInterface $recipe): string {
    $data = [
      '@context' => 'https://schema.org',
      '@type' => 'Recipe',
      'name' => $recipe->getTitle(),
    ];

    if ($recipe->hasField('field_description') && !$recipe->get('field_description')->isEmpty()) {
      $data['description'] = $recipe->get('field_description')->value;
    }

    if ($recipe->hasField('field_image') && !$recipe->get('field_image')->isEmpty()) {
      $image = $recipe->get('field_image')->entity;
      if ($image) {
        $data['image'] = file_create_url($image->getFileUri());
      }
    }

    if ($recipe->hasField('field_prep_time') && !$recipe->get('field_prep_time')->isEmpty()) {
      $data['prepTime'] = 'PT' . $recipe->get('field_prep_time')->value . 'M';
    }

    if ($recipe->hasField('field_cook_time') && !$recipe->get('field_cook_time')->isEmpty()) {
      $data['cookTime'] = 'PT' . $recipe->get('field_cook_time')->value . 'M';
    }

    if ($recipe->hasField('field_servings') && !$recipe->get('field_servings')->isEmpty()) {
      $data['recipeYield'] = $recipe->get('field_servings')->value;
    }

    if ($recipe->hasField('field_ingredients') && !$recipe->get('field_ingredients')->isEmpty()) {
      $ingredients = explode("\n", $recipe->get('field_ingredients')->value);
      $data['recipeIngredient'] = array_filter(array_map('trim', $ingredients));
    }

    if ($recipe->hasField('field_instructions') && !$recipe->get('field_instructions')->isEmpty()) {
      $instructions = strip_tags($recipe->get('field_instructions')->value);
      $steps = array_filter(explode("\n", $instructions));
      $data['recipeInstructions'] = array_map(function ($step, $index) {
        return [
          '@type' => 'HowToStep',
          'position' => $index + 1,
          'text' => trim($step),
        ];
      }, $steps, array_keys($steps));
    }

    if ($recipe->hasField('field_cuisine') && !$recipe->get('field_cuisine')->isEmpty()) {
      $data['recipeCuisine'] = $recipe->get('field_cuisine')->entity?->getName();
    }

    if ($recipe->hasField('field_category') && !$recipe->get('field_category')->isEmpty()) {
      $data['recipeCategory'] = $recipe->get('field_category')->entity?->getName();
    }

    // Add nutrition information if available.
    $nutrition = $this->buildNutritionData($recipe);
    if (!empty($nutrition)) {
      $data['nutrition'] = [
        '@type' => 'NutritionInformation',
      ];

      if (isset($nutrition['calories'])) {
        $data['nutrition']['calories'] = $nutrition['calories'] . ' calories';
      }
      if (isset($nutrition['fat'])) {
        $data['nutrition']['fatContent'] = $nutrition['fat'] . ' g';
      }
      if (isset($nutrition['carbs'])) {
        $data['nutrition']['carbohydrateContent'] = $nutrition['carbs'] . ' g';
      }
      if (isset($nutrition['protein'])) {
        $data['nutrition']['proteinContent'] = $nutrition['protein'] . ' g';
      }
    }

    $data['author'] = [
      '@type' => 'Person',
      'name' => $recipe->getOwner()->getDisplayName(),
    ];

    $data['datePublished'] = date('c', $recipe->getCreatedTime());
    $data['dateModified'] = date('c', $recipe->getChangedTime());

    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  /**
   * Build recipe data array.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param int|null $servings
   *   Optional scaled servings.
   *
   * @return array
   *   Recipe data array.
   */
  protected function buildRecipeData(NodeInterface $recipe, ?int $servings = NULL): array {
    $data = [
      'title' => $recipe->getTitle(),
      'id' => $recipe->id(),
      'created' => date('Y-m-d H:i:s', $recipe->getCreatedTime()),
      'updated' => date('Y-m-d H:i:s', $recipe->getChangedTime()),
    ];

    // Get scaled data if servings specified.
    if ($servings) {
      $scaled = $this->scalingService->scaleRecipe($recipe, $servings);
      $data['servings'] = $servings;
      $data['scaled_from'] = $scaled['original_servings'];
      $data['ingredients'] = $scaled['ingredients'];
      $data['instructions'] = strip_tags($scaled['instructions']);
    }
    else {
      // Export original data.
      if ($recipe->hasField('field_servings')) {
        $data['servings'] = $recipe->get('field_servings')->value;
      }

      if ($recipe->hasField('field_ingredients')) {
        $ingredients = explode("\n", $recipe->get('field_ingredients')->value);
        $data['ingredients'] = array_filter(array_map('trim', $ingredients));
      }

      if ($recipe->hasField('field_instructions')) {
        $data['instructions'] = strip_tags($recipe->get('field_instructions')->value);
      }
    }

    // Add other fields.
    if ($recipe->hasField('field_description')) {
      $data['description'] = $recipe->get('field_description')->value;
    }

    if ($recipe->hasField('field_prep_time')) {
      $data['prep_time_minutes'] = $recipe->get('field_prep_time')->value;
    }

    if ($recipe->hasField('field_cook_time')) {
      $data['cook_time_minutes'] = $recipe->get('field_cook_time')->value;
    }

    if ($recipe->hasField('field_cuisine')) {
      $data['cuisine'] = $recipe->get('field_cuisine')->entity?->getName();
    }

    if ($recipe->hasField('field_category')) {
      $data['category'] = $recipe->get('field_category')->entity?->getName();
    }

    if ($recipe->hasField('field_dietary_tags')) {
      $tags = [];
      foreach ($recipe->get('field_dietary_tags') as $tag) {
        $tags[] = $tag->entity?->getName();
      }
      $data['dietary_tags'] = $tags;
    }

    // Add nutrition.
    $data['nutrition'] = $this->buildNutritionData($recipe);

    // Add metadata.
    if ($recipe->hasField('field_source_url')) {
      $data['source_url'] = $recipe->get('field_source_url')->uri;
    }

    if ($recipe->hasField('field_rating_average')) {
      $data['rating_average'] = $recipe->get('field_rating_average')->value;
      $data['rating_count'] = $recipe->get('field_rating_count')->value;
    }

    return $data;
  }

  /**
   * Build nutrition data array.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   *
   * @return array
   *   Nutrition data.
   */
  protected function buildNutritionData(NodeInterface $recipe): array {
    $nutrition = [];

    $nutrition_fields = [
      'field_nutrition_calories' => 'calories',
      'field_nutrition_fat' => 'fat',
      'field_nutrition_saturated_fat' => 'saturated_fat',
      'field_nutrition_cholesterol' => 'cholesterol',
      'field_nutrition_sodium' => 'sodium',
      'field_nutrition_carbs' => 'carbs',
      'field_nutrition_fiber' => 'fiber',
      'field_nutrition_sugars' => 'sugars',
      'field_nutrition_protein' => 'protein',
    ];

    foreach ($nutrition_fields as $field_name => $key) {
      if ($recipe->hasField($field_name) && !$recipe->get($field_name)->isEmpty()) {
        $nutrition[$key] = $recipe->get($field_name)->value;
      }
    }

    return $nutrition;
  }

  /**
   * Generate recipe card HTML for printing.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param int|null $servings
   *   Optional scaled servings.
   *
   * @return array
   *   Render array for recipe card.
   */
  public function generateRecipeCard(NodeInterface $recipe, ?int $servings = NULL): array {
    $scaled_data = NULL;
    if ($servings) {
      $scaled_data = $this->scalingService->scaleRecipe($recipe, $servings);
    }

    return [
      '#theme' => 'recipeboxx_recipe_card',
      '#node' => $recipe,
      '#scaled_data' => $scaled_data,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/recipe-card',
        ],
      ],
    ];
  }

  /**
   * Get available export formats.
   *
   * @return array
   *   Array of format definitions.
   */
  public function getAvailableFormats(): array {
    return [
      'json' => [
        'label' => 'JSON',
        'description' => 'Export recipe data as JSON for backup or import into other systems.',
        'mime_type' => 'application/json',
        'extension' => 'json',
      ],
      'jsonld' => [
        'label' => 'JSON-LD (Schema.org)',
        'description' => 'Export in Schema.org format for embedding on websites.',
        'mime_type' => 'application/ld+json',
        'extension' => 'json',
      ],
      'pdf' => [
        'label' => 'PDF',
        'description' => 'Export as printable PDF document.',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
      ],
      'card' => [
        'label' => 'Recipe Card (4x6)',
        'description' => 'Print as a 4x6 recipe card.',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
      ],
    ];
  }

}
