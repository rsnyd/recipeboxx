<?php

namespace Drupal\recipeboxx_recipe\Service;

/**
 * Service for parsing ingredient strings.
 *
 * Extracts quantities, units, and ingredient names from text.
 * Critical foundation for shopping lists, meal planning, and recipe scaling.
 */
class IngredientParserService {

  /**
   * Common cooking units.
   */
  const UNITS = [
    // Volume
    'cup', 'cups', 'c',
    'tablespoon', 'tablespoons', 'tbsp', 'tbs', 'T',
    'teaspoon', 'teaspoons', 'tsp', 't',
    'fluid ounce', 'fluid ounces', 'fl oz', 'fl. oz.',
    'pint', 'pints', 'pt',
    'quart', 'quarts', 'qt',
    'gallon', 'gallons', 'gal',
    'milliliter', 'milliliters', 'ml',
    'liter', 'liters', 'l',
    // Weight
    'pound', 'pounds', 'lb', 'lbs',
    'ounce', 'ounces', 'oz',
    'gram', 'grams', 'g',
    'kilogram', 'kilograms', 'kg',
    // Other
    'pinch', 'pinches',
    'dash', 'dashes',
    'clove', 'cloves',
    'slice', 'slices',
    'piece', 'pieces',
    'can', 'cans',
    'package', 'packages', 'pkg',
    'box', 'boxes',
    'bunch', 'bunches',
    'head', 'heads',
    'stalk', 'stalks',
    'sprig', 'sprigs',
    'leaf', 'leaves',
    'whole',
    'large', 'medium', 'small',
  ];

  /**
   * Parse an ingredient string into components.
   *
   * @param string $ingredient_text
   *   The ingredient text (e.g., "2 cups flour").
   *
   * @return array
   *   Array with keys: quantity, unit, ingredient, original.
   */
  public function parseIngredient(string $ingredient_text): array {
    $original = trim($ingredient_text);

    // Default structure.
    $parsed = [
      'quantity' => NULL,
      'quantity_min' => NULL,
      'quantity_max' => NULL,
      'unit' => '',
      'ingredient' => $original,
      'original' => $original,
      'preparation' => '',
    ];

    if (empty($original)) {
      return $parsed;
    }

    $text = $original;

    // Extract quantity (including fractions and ranges).
    $quantity_pattern = '/^(\d+(?:\s+\d+\/\d+|\.\d+)?(?:\s*-\s*\d+(?:\s+\d+\/\d+|\.\d+)?)?|\d+\/\d+)/';
    if (preg_match($quantity_pattern, $text, $matches)) {
      $quantity_str = trim($matches[1]);
      $parsed['quantity'] = $this->convertQuantityToDecimal($quantity_str);

      // Handle ranges (e.g., "2-3 cups").
      if (strpos($quantity_str, '-') !== FALSE) {
        [$min, $max] = explode('-', $quantity_str);
        $parsed['quantity_min'] = $this->convertQuantityToDecimal(trim($min));
        $parsed['quantity_max'] = $this->convertQuantityToDecimal(trim($max));
        $parsed['quantity'] = ($parsed['quantity_min'] + $parsed['quantity_max']) / 2;
      }

      $text = trim(substr($text, strlen($matches[0])));
    }

    // Extract unit.
    $unit_pattern = '/^(' . implode('|', array_map('preg_quote', self::UNITS)) . ')\.?\s+/i';
    if (preg_match($unit_pattern, $text, $matches)) {
      $parsed['unit'] = strtolower(trim($matches[1], '.'));
      $text = trim(substr($text, strlen($matches[0])));
    }

    // Extract preparation instructions (comma-separated).
    if (preg_match('/^([^,]+),\s*(.+)$/', $text, $matches)) {
      $parsed['ingredient'] = trim($matches[1]);
      $parsed['preparation'] = trim($matches[2]);
    }
    else {
      $parsed['ingredient'] = trim($text);
    }

    return $parsed;
  }

  /**
   * Convert quantity string to decimal.
   *
   * Handles fractions like "1 1/2", "1/2", "2.5".
   *
   * @param string $quantity_str
   *   The quantity string.
   *
   * @return float
   *   The decimal value.
   */
  public function convertQuantityToDecimal(string $quantity_str): float {
    $quantity_str = trim($quantity_str);

    // Handle mixed fractions (e.g., "1 1/2").
    if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $quantity_str, $matches)) {
      $whole = (int) $matches[1];
      $numerator = (int) $matches[2];
      $denominator = (int) $matches[3];
      return $whole + ($numerator / $denominator);
    }

    // Handle simple fractions (e.g., "1/2").
    if (preg_match('/^(\d+)\/(\d+)$/', $quantity_str, $matches)) {
      $numerator = (int) $matches[1];
      $denominator = (int) $matches[2];
      return $numerator / $denominator;
    }

    // Handle decimals and whole numbers.
    return (float) $quantity_str;
  }

  /**
   * Convert decimal quantity to fraction string.
   *
   * @param float $decimal
   *   The decimal value.
   * @param int $precision
   *   Precision for fraction matching.
   *
   * @return string
   *   The fraction string (e.g., "1 1/2", "3/4").
   */
  public function convertDecimalToFraction(float $decimal, int $precision = 8): string {
    if ($decimal == 0) {
      return '0';
    }

    $whole = floor($decimal);
    $fraction = $decimal - $whole;

    if ($fraction == 0) {
      return (string) $whole;
    }

    // Common fractions for cooking.
    $common_fractions = [
      0.125 => '1/8',
      0.25 => '1/4',
      0.333 => '1/3',
      0.5 => '1/2',
      0.666 => '2/3',
      0.75 => '3/4',
    ];

    foreach ($common_fractions as $decimal_val => $fraction_str) {
      if (abs($fraction - $decimal_val) < 0.01) {
        return $whole > 0 ? "$whole $fraction_str" : $fraction_str;
      }
    }

    // Fallback to decimal.
    return number_format($decimal, 2);
  }

  /**
   * Normalize unit to standard form.
   *
   * @param string $unit
   *   The unit to normalize.
   *
   * @return string
   *   The normalized unit.
   */
  public function normalizeUnit(string $unit): string {
    $unit = strtolower(trim($unit));

    $unit_map = [
      'c' => 'cup',
      'tbsp' => 'tablespoon',
      'tbs' => 'tablespoon',
      't' => 'teaspoon',
      'tsp' => 'teaspoon',
      'lb' => 'pound',
      'lbs' => 'pound',
      'oz' => 'ounce',
      'fl oz' => 'fluid ounce',
      'fl. oz.' => 'fluid ounce',
      'pt' => 'pint',
      'qt' => 'quart',
      'gal' => 'gallon',
      'ml' => 'milliliter',
      'l' => 'liter',
      'g' => 'gram',
      'kg' => 'kilogram',
    ];

    if (isset($unit_map[$unit])) {
      return $unit_map[$unit];
    }

    // Remove plural 's' for normalization.
    if (substr($unit, -1) === 's' && strlen($unit) > 3) {
      $singular = substr($unit, 0, -1);
      if (in_array($singular, self::UNITS)) {
        return $singular;
      }
    }

    return $unit;
  }

  /**
   * Scale an ingredient quantity.
   *
   * @param array $parsed_ingredient
   *   Parsed ingredient from parseIngredient().
   * @param float $multiplier
   *   The scaling multiplier.
   *
   * @return array
   *   Scaled ingredient data.
   */
  public function scaleIngredient(array $parsed_ingredient, float $multiplier): array {
    $scaled = $parsed_ingredient;

    if ($parsed_ingredient['quantity'] !== NULL) {
      $scaled['quantity'] = $parsed_ingredient['quantity'] * $multiplier;

      if ($parsed_ingredient['quantity_min'] !== NULL) {
        $scaled['quantity_min'] = $parsed_ingredient['quantity_min'] * $multiplier;
      }

      if ($parsed_ingredient['quantity_max'] !== NULL) {
        $scaled['quantity_max'] = $parsed_ingredient['quantity_max'] * $multiplier;
      }
    }

    return $scaled;
  }

  /**
   * Format parsed ingredient back to text.
   *
   * @param array $parsed_ingredient
   *   Parsed ingredient data.
   * @param bool $use_fractions
   *   Whether to convert decimals to fractions.
   *
   * @return string
   *   Formatted ingredient text.
   */
  public function formatIngredient(array $parsed_ingredient, bool $use_fractions = TRUE): string {
    $parts = [];

    if ($parsed_ingredient['quantity'] !== NULL) {
      if ($use_fractions) {
        $parts[] = $this->convertDecimalToFraction($parsed_ingredient['quantity']);
      }
      else {
        $parts[] = number_format($parsed_ingredient['quantity'], 2);
      }
    }

    if (!empty($parsed_ingredient['unit'])) {
      $parts[] = $parsed_ingredient['unit'];
    }

    $parts[] = $parsed_ingredient['ingredient'];

    if (!empty($parsed_ingredient['preparation'])) {
      return implode(' ', $parts) . ', ' . $parsed_ingredient['preparation'];
    }

    return implode(' ', $parts);
  }

  /**
   * Combine duplicate ingredients.
   *
   * @param array $ingredients
   *   Array of parsed ingredients.
   *
   * @return array
   *   Combined ingredients with totaled quantities.
   */
  public function combineIngredients(array $ingredients): array {
    $combined = [];

    foreach ($ingredients as $ingredient) {
      $key = $this->generateIngredientKey($ingredient);

      if (!isset($combined[$key])) {
        $combined[$key] = $ingredient;
      }
      else {
        // Same ingredient, add quantities.
        if ($ingredient['quantity'] !== NULL && $combined[$key]['quantity'] !== NULL) {
          $combined[$key]['quantity'] += $ingredient['quantity'];
        }
      }
    }

    return array_values($combined);
  }

  /**
   * Generate unique key for ingredient matching.
   *
   * @param array $ingredient
   *   Parsed ingredient.
   *
   * @return string
   *   Unique key.
   */
  protected function generateIngredientKey(array $ingredient): string {
    $unit = $this->normalizeUnit($ingredient['unit']);
    $name = strtolower(trim($ingredient['ingredient']));
    return $unit . '::' . $name;
  }

}
