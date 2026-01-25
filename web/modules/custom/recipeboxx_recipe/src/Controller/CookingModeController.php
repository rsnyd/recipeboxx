<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\recipeboxx_recipe\Service\RecipeScalingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for cooking mode interface.
 */
class CookingModeController extends ControllerBase {

  /**
   * The recipe scaling service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\RecipeScalingService
   */
  protected RecipeScalingService $scalingService;

  /**
   * Constructs a CookingModeController object.
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
   * Display cooking mode interface.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   A render array.
   */
  public function cookingMode(NodeInterface $node, Request $request): array {
    // Get scaling parameter if provided.
    $servings = $request->query->get('servings');
    $scaled_data = NULL;

    if ($servings && is_numeric($servings)) {
      $scaled_data = $this->scalingService->scaleRecipe($node, (int) $servings);
    }

    // Parse instructions into steps.
    $instructions_text = $node->hasField('field_instructions') ?
      $node->get('field_instructions')->value : '';

    $steps = $this->parseInstructionsIntoSteps($instructions_text);

    // Extract times from steps.
    $steps_with_times = $this->extractTimesFromSteps($steps);

    $build = [
      '#theme' => 'recipeboxx_recipe_cooking_mode',
      '#node' => $node,
      '#steps' => $steps_with_times,
      '#scaled_data' => $scaled_data,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/cooking-mode',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Parse instructions into individual steps.
   *
   * @param string $instructions_text
   *   The instructions text.
   *
   * @return array
   *   Array of step strings.
   */
  protected function parseInstructionsIntoSteps(string $instructions_text): array {
    // Strip HTML tags.
    $plain_text = strip_tags($instructions_text);

    // Try to split by numbers (1. 2. 3.) or by paragraphs.
    $steps = [];

    // Check if instructions use numbered format.
    if (preg_match_all('/\d+\.\s*([^\n\d]+(?:\n(?!\d+\.)[^\n\d]+)*)/', $plain_text, $matches)) {
      $steps = array_map('trim', $matches[1]);
    }
    else {
      // Split by double newlines or periods followed by newline.
      $steps = preg_split('/\n\n+|\.\s*\n/', $plain_text);
      $steps = array_filter(array_map('trim', $steps));
    }

    // Re-index array.
    return array_values($steps);
  }

  /**
   * Extract timing information from steps.
   *
   * @param array $steps
   *   Array of step strings.
   *
   * @return array
   *   Array of steps with extracted timing data.
   */
  protected function extractTimesFromSteps(array $steps): array {
    $steps_with_times = [];

    foreach ($steps as $index => $step) {
      $step_data = [
        'text' => $step,
        'number' => $index + 1,
        'timers' => [],
      ];

      // Extract timing patterns.
      // Patterns: "20 minutes", "1 hour", "2-3 hours", "10 to 15 minutes"
      $time_pattern = '/(\d+(?:\s*-\s*\d+|\s+to\s+\d+)?)\s*(minutes?|mins?|hours?|hrs?|seconds?|secs?)/i';

      if (preg_match_all($time_pattern, $step, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          $duration_text = $match[1];
          $unit = strtolower($match[2]);

          // Parse duration (handle ranges).
          if (preg_match('/(\d+)\s*(?:-|to)\s*(\d+)/', $duration_text, $range)) {
            $duration = (int) $range[2]; // Use max of range.
          }
          else {
            $duration = (int) $duration_text;
          }

          // Convert to minutes.
          $minutes = $this->convertToMinutes($duration, $unit);

          if ($minutes > 0) {
            $step_data['timers'][] = [
              'duration' => $minutes,
              'label' => $match[0],
              'seconds' => $minutes * 60,
            ];
          }
        }
      }

      $steps_with_times[] = $step_data;
    }

    return $steps_with_times;
  }

  /**
   * Convert duration to minutes.
   *
   * @param int $duration
   *   The duration value.
   * @param string $unit
   *   The time unit.
   *
   * @return int
   *   Duration in minutes.
   */
  protected function convertToMinutes(int $duration, string $unit): int {
    $unit_lower = strtolower($unit);

    if (str_starts_with($unit_lower, 'hour') || str_starts_with($unit_lower, 'hr')) {
      return $duration * 60;
    }

    if (str_starts_with($unit_lower, 'second') || str_starts_with($unit_lower, 'sec')) {
      return (int) ceil($duration / 60);
    }

    // Default to minutes.
    return $duration;
  }

}
