<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\recipeboxx_recipe\Service\MealPlanService;
use Drupal\recipeboxx_recipe\Entity\MealPlan;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for meal plan functionality.
 */
class MealPlanController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The meal plan service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\MealPlanService
   */
  protected MealPlanService $mealPlanService;

  /**
   * Constructs a MealPlanController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\recipeboxx_recipe\Service\MealPlanService $meal_plan_service
   *   The meal plan service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MealPlanService $meal_plan_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mealPlanService = $meal_plan_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('recipeboxx_recipe.meal_plan')
    );
  }

  /**
   * View the current week's meal plan.
   *
   * @return array
   *   A render array.
   */
  public function currentWeek(): array {
    $current_plan = $this->mealPlanService->getCurrentWeek();

    if (!$current_plan) {
      // No plan exists, create one.
      $current_plan = $this->mealPlanService->createMealPlan();
      $this->messenger()->addStatus($this->t('Created a new meal plan for this week.'));
    }

    return $this->viewMealPlan($current_plan);
  }

  /**
   * List all meal plans.
   *
   * @return array
   *   A render array.
   */
  public function listMealPlans(): array {
    $plans = $this->mealPlanService->getUserMealPlans();

    $build = [
      '#theme' => 'recipeboxx_meal_plan_overview',
      '#plans' => $plans,
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['meal_plan_list'],
      ],
    ];

    $build['current_week'] = [
      '#type' => 'link',
      '#title' => $this->t('View Current Week'),
      '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.meal_plan_current'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -10,
    ];

    $build['plans_list'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['meal-plans-container']],
      '#weight' => 0,
    ];

    foreach ($plans as $plan) {
      $entries = $this->mealPlanService->getPlanEntries($plan);
      $recipe_count = count($entries);

      $build['plans_list'][$plan->id()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['meal-plan-summary']],
      ];

      $build['plans_list'][$plan->id()]['title'] = [
        '#type' => 'link',
        '#title' => $plan->getName(),
        '#url' => $plan->toUrl(),
        '#attributes' => ['class' => ['meal-plan-title']],
      ];

      $build['plans_list'][$plan->id()]['stats'] = [
        '#markup' => '<div class="meal-plan-stats">' .
                     $this->formatPlural($recipe_count, '1 recipe', '@count recipes') .
                     '</div>',
      ];

      $build['plans_list'][$plan->id()]['date'] = [
        '#markup' => '<div class="meal-plan-date">' .
                     $this->t('Week of @date', [
                       '@date' => date('M j, Y', $plan->getStartDate()),
                     ]) . '</div>',
      ];
    }

    return $build;
  }

  /**
   * View a meal plan.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\MealPlan $meal_plan
   *   The meal plan.
   *
   * @return array
   *   A render array.
   */
  public function viewMealPlan(MealPlan $meal_plan): array {
    $entries = $this->mealPlanService->getPlanEntries($meal_plan, TRUE);

    $build = [
      '#theme' => 'recipeboxx_meal_plan_week',
      '#meal_plan' => $meal_plan,
      '#entries' => $entries,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/meal-planner',
        ],
      ],
    ];

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['meal-plan-actions']],
      '#weight' => -10,
    ];

    $build['actions']['generate_list'] = [
      '#type' => 'link',
      '#title' => $this->t('Generate Shopping List'),
      '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.meal_plan_generate_list', [
        'meal_plan' => $meal_plan->id(),
      ]),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $build['actions']['copy'] = [
      '#type' => 'link',
      '#title' => $this->t('Copy to Another Week'),
      '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.meal_plan_copy', [
        'meal_plan' => $meal_plan->id(),
      ]),
      '#attributes' => ['class' => ['button']],
    ];

    // Build week grid.
    $build['week_grid'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['meal-plan-week-grid'],
        'data-plan-id' => $meal_plan->id(),
      ],
      '#weight' => 0,
    ];

    $days = [
      0 => 'Monday',
      1 => 'Tuesday',
      2 => 'Wednesday',
      3 => 'Thursday',
      4 => 'Friday',
      5 => 'Saturday',
      6 => 'Sunday',
    ];

    $meal_types = ['breakfast', 'lunch', 'dinner', 'snack'];

    foreach ($days as $day_num => $day_name) {
      $build['week_grid'][$day_num] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['meal-plan-day'],
          'data-day' => $day_num,
        ],
      ];

      $build['week_grid'][$day_num]['header'] = [
        '#markup' => '<h3 class="day-header">' . $day_name . '</h3>',
      ];

      foreach ($meal_types as $meal_type) {
        $build['week_grid'][$day_num][$meal_type] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['meal-slot', 'meal-slot--' . $meal_type],
            'data-day' => $day_num,
            'data-meal-type' => $meal_type,
          ],
        ];

        $build['week_grid'][$day_num][$meal_type]['label'] = [
          '#markup' => '<div class="meal-label">' . ucfirst($meal_type) . '</div>',
        ];

        $build['week_grid'][$day_num][$meal_type]['recipes'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['meal-recipes']],
        ];

        // Add recipes for this slot.
        if (!empty($entries[$day_num][$meal_type])) {
          foreach ($entries[$day_num][$meal_type] as $entry) {
            $recipe = $entry->getRecipe();
            if ($recipe) {
              $view_builder = $this->entityTypeManager->getViewBuilder('node');
              $build['week_grid'][$day_num][$meal_type]['recipes'][] = [
                '#type' => 'container',
                '#attributes' => [
                  'class' => ['meal-recipe-card'],
                  'data-entry-id' => $entry->id(),
                  'draggable' => 'true',
                ],
                'content' => $view_builder->view($recipe, 'teaser'),
              ];
            }
          }
        }
        else {
          $build['week_grid'][$day_num][$meal_type]['recipes']['empty'] = [
            '#markup' => '<div class="meal-empty">Click to add recipe</div>',
          ];
        }
      }
    }

    return $build;
  }

  /**
   * Generate shopping list from meal plan.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\MealPlan $meal_plan
   *   The meal plan.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with redirect URL.
   */
  public function generateShoppingList(MealPlan $meal_plan): JsonResponse {
    try {
      $shopping_list = $this->mealPlanService->generateShoppingList($meal_plan);

      $this->messenger()->addStatus($this->t('Shopping list "@name" created from meal plan.', [
        '@name' => $shopping_list->getName(),
      ]));

      return new JsonResponse([
        'status' => 'success',
        'redirect' => $shopping_list->toUrl()->toString(),
        'message' => $this->t('Shopping list created.')->render(),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
      ], 500);
    }
  }

}
