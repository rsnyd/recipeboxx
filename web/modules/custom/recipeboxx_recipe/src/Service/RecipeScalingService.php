<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\node\NodeInterface;
use Moontoast\Math\BigNumber;

/**
 * Service for scaling recipe quantities.
 *
 * Uses fraction math to accurately scale ingredient quantities
 * while maintaining proper fraction display (1/2, 1 1/2, etc.).
 */
class RecipeScalingService {

  /**
   * The ingredient parser service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\IngredientParserService
   */
  protected IngredientParserService $ingredientParser;

  /**
   * Ingredients that should not be scaled or need special handling.
   *
   * @var array
   */
  protected array $noScaleIngredients = [
    'salt',
    'pepper',
    'water',
    'oil',
    'butter',
    'garlic',
    'onion',
  ];

  /**
   * Constructs a RecipeScalingService object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\IngredientParserService $ingredient_parser
   *   The ingredient parser service.
   */
  public function __construct(IngredientParserService $ingredient_parser) {
    $this->ingredientParser = $ingredient_parser;
  }

  /**
   * Scale a recipe to a new number of servings.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param int $new_servings
   *   The desired number of servings.
   *
   * @return array
   *   Array containing scaled ingredients and metadata.
   */
  public function scaleRecipe(NodeInterface $recipe, int $new_servings): array {
    $original_servings = $recipe->hasField('field_servings') ?
      (int) $recipe->get('field_servings')->value : 4;

    if ($original_servings <= 0) {
      $original_servings = 4;
    }

    $multiplier = $new_servings / $original_servings;

    $ingredients_text = $recipe->hasField('field_ingredients') ?
      $recipe->get('field_ingredients')->value : '';

    $ingredient_lines = array_filter(explode("\n", $ingredients_text));
    $scaled_ingredients = [];

    foreach ($ingredient_lines as $line) {
      $scaled_ingredients[] = $this->scaleIngredientLine($line, $multiplier);
    }

    return [
      'original_servings' => $original_servings,
      'new_servings' => $new_servings,
      'multiplier' => $multiplier,
      'ingredients' => $scaled_ingredients,
      'instructions' => $this->scaleInstructions($recipe, $multiplier),
    ];
  }

  /**
   * Scale a single ingredient line.
   *
   * @param string $ingredient_line
   *   The ingredient line to scale.
   * @param float $multiplier
   *   The scaling multiplier.
   *
   * @return string
   *   The scaled ingredient line.
   */
  public function scaleIngredientLine(string $ingredient_line, float $multiplier): string {
    $parsed = $this->ingredientParser->parseIngredient($ingredient_line);

    // If no quantity, return as-is.
    if (empty($parsed['quantity'])) {
      return $ingredient_line;
    }

    // Check if ingredient should not be scaled.
    if ($this->shouldNotScale($parsed['ingredient'])) {
      return $ingredient_line . ' (adjust to taste)';
    }

    // Scale the quantity.
    $scaled_quantity = $this->scaleQuantity($parsed['quantity'], $multiplier);

    // Build the scaled ingredient line.
    $parts = [];

    if (!empty($scaled_quantity)) {
      $parts[] = $scaled_quantity;
    }

    if (!empty($parsed['unit'])) {
      $parts[] = $parsed['unit'];
    }

    if (!empty($parsed['ingredient'])) {
      $parts[] = $parsed['ingredient'];
    }

    if (!empty($parsed['preparation'])) {
      $parts[] = ', ' . $parsed['preparation'];
    }

    return implode(' ', $parts);
  }

  /**
   * Scale a quantity value.
   *
   * @param mixed $quantity
   *   The quantity to scale (string or float).
   * @param float $multiplier
   *   The scaling multiplier.
   *
   * @return string
   *   The scaled quantity as a fraction string.
   */
  public function scaleQuantity($quantity, float $multiplier): string {
    // Convert to decimal.
    $decimal_quantity = is_numeric($quantity) ?
      (float) $quantity :
      $this->ingredientParser->convertQuantityToDecimal($quantity);

    // Scale using precise math.
    $quantity_bn = new BigNumber($decimal_quantity);
    $multiplier_bn = new BigNumber($multiplier);
    $scaled = $quantity_bn->multiply($multiplier_bn);

    $scaled_float = (float) $scaled->getValue();

    // Convert back to fraction for display.
    return $this->decimalToFraction($scaled_float);
  }

  /**
   * Convert a decimal to a fraction string.
   *
   * @param float $decimal
   *   The decimal value.
   * @param int $max_denominator
   *   Maximum denominator for fraction simplification.
   *
   * @return string
   *   Fraction string (e.g., "1 1/2", "2/3", "3").
   */
  public function decimalToFraction(float $decimal, int $max_denominator = 16): string {
    // Handle very small values.
    if ($decimal < 0.0625) {
      return 'pinch';
    }

    // Round to 2 decimal places.
    $decimal = round($decimal, 2);

    // Get whole number part.
    $whole = floor($decimal);
    $fraction_part = $decimal - $whole;

    // If no fractional part, return whole number.
    if ($fraction_part < 0.01) {
      return (string) (int) $whole;
    }

    // Find best fraction approximation.
    $best_numerator = 1;
    $best_denominator = 1;
    $best_error = abs($fraction_part - 1);

    // Common denominators used in cooking: 2, 3, 4, 8, 16.
    $common_denominators = [2, 3, 4, 8, 16];

    foreach ($common_denominators as $denominator) {
      if ($denominator > $max_denominator) {
        continue;
      }

      $numerator = round($fraction_part * $denominator);

      // Simplify the fraction.
      $gcd = $this->gcd($numerator, $denominator);
      $simple_numerator = $numerator / $gcd;
      $simple_denominator = $denominator / $gcd;

      $error = abs($fraction_part - ($numerator / $denominator));

      if ($error < $best_error) {
        $best_error = $error;
        $best_numerator = $simple_numerator;
        $best_denominator = $simple_denominator;
      }
    }

    // Build fraction string.
    $parts = [];

    if ($whole > 0) {
      $parts[] = (int) $whole;
    }

    if ($best_numerator > 0 && $best_denominator > 1) {
      $parts[] = $best_numerator . '/' . $best_denominator;
    }

    return implode(' ', $parts);
  }

  /**
   * Calculate greatest common divisor.
   *
   * @param int $a
   *   First number.
   * @param int $b
   *   Second number.
   *
   * @return int
   *   The GCD.
   */
  protected function gcd(int $a, int $b): int {
    while ($b != 0) {
      $temp = $b;
      $b = $a % $b;
      $a = $temp;
    }
    return abs($a);
  }

  /**
   * Check if an ingredient should not be scaled.
   *
   * @param string $ingredient
   *   The ingredient name.
   *
   * @return bool
   *   TRUE if ingredient should not be scaled.
   */
  protected function shouldNotScale(string $ingredient): bool {
    $ingredient_lower = strtolower($ingredient);

    foreach ($this->noScaleIngredients as $no_scale) {
      if (strpos($ingredient_lower, $no_scale) !== FALSE) {
        // Only suggest adjustment if multiplier is significant.
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Scale cooking instructions with time adjustments.
   *
   * @param \Drupal\node\NodeInterface $recipe
   *   The recipe node.
   * @param float $multiplier
   *   The scaling multiplier.
   *
   * @return string
   *   The instructions with scaling notes.
   */
  protected function scaleInstructions(NodeInterface $recipe, float $multiplier): string {
    $instructions = $recipe->hasField('field_instructions') ?
      $recipe->get('field_instructions')->value : '';

    // Add scaling note if significant change.
    if ($multiplier < 0.75 || $multiplier > 1.5) {
      $note = "\n\n<strong>Scaling Note:</strong> ";

      if ($multiplier > 1.5) {
        $note .= "Cooking times may need to be increased by 10-25%. ";
        $note .= "Check for doneness before removing from heat.";
      }
      else {
        $note .= "Cooking times may need to be decreased by 10-25%. ";
        $note .= "Monitor closely to avoid overcooking.";
      }

      return $instructions . $note;
    }

    return $instructions;
  }

  /**
   * Get scaling suggestions based on original servings.
   *
   * @param int $original_servings
   *   The original number of servings.
   *
   * @return array
   *   Array of suggested serving sizes.
   */
  public function getScalingSuggestions(int $original_servings): array {
    $suggestions = [];

    // Half recipe.
    if ($original_servings >= 4) {
      $suggestions[] = (int) ($original_servings / 2);
    }

    // Original.
    $suggestions[] = $original_servings;

    // Double.
    $suggestions[] = $original_servings * 2;

    // Triple (if reasonable).
    if ($original_servings <= 8) {
      $suggestions[] = $original_servings * 3;
    }

    // Common serving sizes.
    $common = [2, 4, 6, 8, 12];
    foreach ($common as $size) {
      if (!in_array($size, $suggestions) && $size != $original_servings) {
        $suggestions[] = $size;
      }
    }

    sort($suggestions);

    return array_values(array_unique($suggestions));
  }

  /**
   * Calculate ingredient cost scaling.
   *
   * @param float $original_cost
   *   Original recipe cost.
   * @param float $multiplier
   *   Scaling multiplier.
   *
   * @return float
   *   Scaled cost.
   */
  public function scaleCost(float $original_cost, float $multiplier): float {
    $cost_bn = new BigNumber($original_cost);
    $multiplier_bn = new BigNumber($multiplier);
    $scaled = $cost_bn->multiply($multiplier_bn);

    return round((float) $scaled->getValue(), 2);
  }

}
