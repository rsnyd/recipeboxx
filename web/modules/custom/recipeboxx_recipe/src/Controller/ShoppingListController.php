<?php

namespace Drupal\recipeboxx_recipe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\recipeboxx_recipe\Service\ShoppingListService;
use Drupal\recipeboxx_recipe\Entity\ShoppingList;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for shopping list functionality.
 */
class ShoppingListController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The shopping list service.
   *
   * @var \Drupal\recipeboxx_recipe\Service\ShoppingListService
   */
  protected ShoppingListService $shoppingListService;

  /**
   * Constructs a ShoppingListController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\recipeboxx_recipe\Service\ShoppingListService $shopping_list_service
   *   The shopping list service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ShoppingListService $shopping_list_service) {
    $this->entityTypeManager = $entity_type_manager;
    $this->shoppingListService = $shopping_list_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('recipeboxx_recipe.shopping_list')
    );
  }

  /**
   * List all shopping lists for the current user.
   *
   * @return array
   *   A render array.
   */
  public function listShoppingLists(): array {
    $lists = $this->shoppingListService->getUserShoppingLists();

    $build = [
      '#theme' => 'recipeboxx_shopping_list_overview',
      '#lists' => $lists,
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['shopping_list_list'],
      ],
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Create New Shopping List'),
      '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.shopping_list_add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -10,
    ];

    $build['lists'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['shopping-lists-container']],
      '#weight' => 0,
    ];

    foreach ($lists as $list) {
      $items = $this->shoppingListService->getListItems($list);
      $total_items = count($items);
      $checked_items = count(array_filter($items, fn($item) => $item->isChecked()));

      $build['lists'][$list->id()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['shopping-list-summary']],
      ];

      $build['lists'][$list->id()]['title'] = [
        '#type' => 'link',
        '#title' => $list->getName(),
        '#url' => $list->toUrl(),
        '#attributes' => ['class' => ['shopping-list-title']],
      ];

      $build['lists'][$list->id()]['stats'] = [
        '#markup' => '<div class="shopping-list-stats">' .
                     $this->t('@checked of @total items', [
                       '@checked' => $checked_items,
                       '@total' => $total_items,
                     ]) . '</div>',
      ];

      $build['lists'][$list->id()]['date'] = [
        '#markup' => '<div class="shopping-list-date">' .
                     $this->t('Created @date', [
                       '@date' => \Drupal::service('date.formatter')->format($list->get('created')->value, 'medium'),
                     ]) . '</div>',
      ];
    }

    return $build;
  }

  /**
   * View a shopping list.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\ShoppingList $shopping_list
   *   The shopping list.
   *
   * @return array
   *   A render array.
   */
  public function viewShoppingList(ShoppingList $shopping_list): array {
    $items = $this->shoppingListService->getListItems($shopping_list, TRUE);

    $build = [
      '#theme' => 'recipeboxx_shopping_list',
      '#shopping_list' => $shopping_list,
      '#items' => $items,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/shopping-list',
        ],
      ],
    ];

    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['shopping-list-actions']],
      '#weight' => -10,
    ];

    $build['actions']['print'] = [
      '#type' => 'link',
      '#title' => $this->t('Print List'),
      '#url' => \Drupal\Core\Url::fromRoute('recipeboxx_recipe.shopping_list_print', [
        'shopping_list' => $shopping_list->id(),
      ]),
      '#attributes' => ['class' => ['button'], 'target' => '_blank'],
    ];

    $build['actions']['edit'] = [
      '#type' => 'link',
      '#title' => $this->t('Edit'),
      '#url' => $shopping_list->toUrl('edit-form'),
      '#attributes' => ['class' => ['button']],
    ];

    // Render items grouped by category.
    $build['items'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['shopping-list-items']],
      '#weight' => 0,
    ];

    foreach ($items as $category => $category_items) {
      if (empty($category_items)) {
        continue;
      }

      $build['items'][$category] = [
        '#type' => 'details',
        '#title' => $category,
        '#open' => TRUE,
        '#attributes' => ['class' => ['shopping-list-category']],
      ];

      $build['items'][$category]['list'] = [
        '#theme' => 'item_list',
        '#items' => [],
        '#attributes' => ['class' => ['shopping-list-category-items']],
      ];

      foreach ($category_items as $item) {
        $build['items'][$category]['list']['#items'][] = [
          '#markup' => '<label class="shopping-list-item ' . ($item->isChecked() ? 'checked' : '') . '">' .
                       '<input type="checkbox" class="item-checkbox" data-item-id="' . $item->id() . '" ' .
                       ($item->isChecked() ? 'checked' : '') . '> ' .
                       '<span class="item-text">' .
                       ($item->getQuantity() ? $item->getQuantity() . ' ' : '') .
                       $item->getItemText() .
                       '</span>' .
                       '</label>',
        ];
      }
    }

    return $build;
  }

  /**
   * Print view for a shopping list.
   *
   * @param \Drupal\recipeboxx_recipe\Entity\ShoppingList $shopping_list
   *   The shopping list.
   *
   * @return array
   *   A render array.
   */
  public function printShoppingList(ShoppingList $shopping_list): array {
    $items = $this->shoppingListService->getListItems($shopping_list, TRUE);

    $build = [
      '#theme' => 'recipeboxx_shopping_list_print',
      '#shopping_list' => $shopping_list,
      '#items' => $items,
      '#attached' => [
        'library' => [
          'recipeboxx_recipe/shopping-list-print',
        ],
      ],
    ];

    return $build;
  }

}
