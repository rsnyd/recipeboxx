<?php

namespace Drupal\recipeboxx_recipe\Service;

/**
 * Service for nutrition calculations and label generation.
 */
class NutritionCalculatorService {

  /**
   * Daily value percentages for nutrition label (based on 2000 calorie diet).
   */
  const DAILY_VALUES = [
    'total_fat' => 78,
    'saturated_fat' => 20,
    'cholesterol' => 300,
    'sodium' => 2300,
    'total_carbs' => 275,
    'fiber' => 28,
    'protein' => 50,
    'vitamin_d' => 20,
    'calcium' => 1300,
    'iron' => 18,
    'potassium' => 4700,
  ];

  /**
   * Calculate daily value percentage.
   *
   * @param string $nutrient
   *   The nutrient name.
   * @param float $amount
   *   The amount in the recipe.
   * @param string $unit
   *   The unit of measurement (g, mg, mcg).
   *
   * @return int
   *   Percentage of daily value.
   */
  public function calculateDailyValue(string $nutrient, float $amount, string $unit = 'g'): int {
    if (!isset(self::DAILY_VALUES[$nutrient])) {
      return 0;
    }

    // Convert to standard unit (grams for most, mg for some).
    $standard_amount = $this->convertToStandardUnit($amount, $unit);
    $daily_value = self::DAILY_VALUES[$nutrient];

    // Calculate percentage.
    $percentage = ($standard_amount / $daily_value) * 100;

    return (int) round($percentage);
  }

  /**
   * Convert nutrient amount to standard unit.
   *
   * @param float $amount
   *   The amount.
   * @param string $unit
   *   The unit (g, mg, mcg, etc.).
   *
   * @return float
   *   Amount in standard unit.
   */
  protected function convertToStandardUnit(float $amount, string $unit): float {
    switch (strtolower($unit)) {
      case 'mg':
        return $amount / 1000;

      case 'mcg':
      case 'µg':
        return $amount / 1000000;

      case 'g':
      default:
        return $amount;
    }
  }

  /**
   * Calculate per-serving nutrition from total recipe nutrition.
   *
   * @param array $nutrition
   *   Total nutrition data for the recipe.
   * @param int $servings
   *   Number of servings.
   *
   * @return array
   *   Per-serving nutrition data.
   */
  public function calculatePerServing(array $nutrition, int $servings): array {
    if ($servings <= 0) {
      return $nutrition;
    }

    $per_serving = [];
    foreach ($nutrition as $key => $value) {
      if (is_numeric($value)) {
        $per_serving[$key] = round($value / $servings, 1);
      }
      else {
        $per_serving[$key] = $value;
      }
    }

    return $per_serving;
  }

  /**
   * Build nutrition label data with daily values.
   *
   * @param array $nutrition
   *   Nutrition data from recipe.
   *
   * @return array
   *   Structured nutrition label data with daily value percentages.
   */
  public function buildNutritionLabel(array $nutrition): array {
    $label = [
      'calories' => $nutrition['calories'] ?? 0,
      'calories_from_fat' => $nutrition['calories_from_fat'] ?? 0,
      'nutrients' => [],
    ];

    // Main nutrients with daily values.
    $main_nutrients = [
      'total_fat' => ['label' => 'Total Fat', 'unit' => 'g'],
      'saturated_fat' => ['label' => 'Saturated Fat', 'unit' => 'g', 'indent' => TRUE],
      'trans_fat' => ['label' => 'Trans Fat', 'unit' => 'g', 'indent' => TRUE, 'no_dv' => TRUE],
      'cholesterol' => ['label' => 'Cholesterol', 'unit' => 'mg'],
      'sodium' => ['label' => 'Sodium', 'unit' => 'mg'],
      'total_carbs' => ['label' => 'Total Carbohydrate', 'unit' => 'g'],
      'fiber' => ['label' => 'Dietary Fiber', 'unit' => 'g', 'indent' => TRUE],
      'sugars' => ['label' => 'Total Sugars', 'unit' => 'g', 'indent' => TRUE, 'no_dv' => TRUE],
      'protein' => ['label' => 'Protein', 'unit' => 'g'],
    ];

    foreach ($main_nutrients as $key => $config) {
      if (isset($nutrition[$key])) {
        $amount = $nutrition[$key];
        $daily_value = NULL;

        if (empty($config['no_dv'])) {
          $daily_value = $this->calculateDailyValue($key, $amount, $config['unit']);
        }

        $label['nutrients'][] = [
          'label' => $config['label'],
          'amount' => $amount,
          'unit' => $config['unit'],
          'daily_value' => $daily_value,
          'indent' => $config['indent'] ?? FALSE,
        ];
      }
    }

    // Vitamins and minerals.
    $label['vitamins_minerals'] = [];
    $vitamins = [
      'vitamin_d' => ['label' => 'Vitamin D', 'unit' => 'mcg'],
      'calcium' => ['label' => 'Calcium', 'unit' => 'mg'],
      'iron' => ['label' => 'Iron', 'unit' => 'mg'],
      'potassium' => ['label' => 'Potassium', 'unit' => 'mg'],
    ];

    foreach ($vitamins as $key => $config) {
      if (isset($nutrition[$key]) && $nutrition[$key] > 0) {
        $amount = $nutrition[$key];
        $daily_value = $this->calculateDailyValue($key, $amount, $config['unit']);

        $label['vitamins_minerals'][] = [
          'label' => $config['label'],
          'amount' => $amount,
          'unit' => $config['unit'],
          'daily_value' => $daily_value,
        ];
      }
    }

    return $label;
  }

  /**
   * Scale nutrition data for different serving sizes.
   *
   * @param array $nutrition
   *   Original nutrition data.
   * @param int $original_servings
   *   Original number of servings.
   * @param int $new_servings
   *   New number of servings.
   *
   * @return array
   *   Scaled nutrition data.
   */
  public function scaleNutrition(array $nutrition, int $original_servings, int $new_servings): array {
    if ($original_servings <= 0 || $new_servings <= 0) {
      return $nutrition;
    }

    $multiplier = $new_servings / $original_servings;
    $scaled = [];

    foreach ($nutrition as $key => $value) {
      if (is_numeric($value)) {
        $scaled[$key] = round($value * $multiplier, 1);
      }
      else {
        $scaled[$key] = $value;
      }
    }

    return $scaled;
  }

}
