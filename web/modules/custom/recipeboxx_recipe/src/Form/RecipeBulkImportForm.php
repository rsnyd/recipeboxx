<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\recipeboxx_recipe\Service\RecipeScraperService;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk importing recipes from multiple URLs.
 */
class RecipeBulkImportForm extends FormBase {

  /**
   * Constructs a RecipeBulkImportForm object.
   *
   * @param \Drupal\recipeboxx_recipe\Service\RecipeScraperService $recipeScraper
   *   The recipe scraper service.
   */
  public function __construct(
    private RecipeScraperService $recipeScraper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recipeboxx_recipe.scraper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipeboxx_recipe_bulk_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Recipe URLs'),
      '#description' => $this->t('Enter one recipe URL per line. All recipes will be imported sequentially.'),
      '#required' => TRUE,
      '#rows' => 15,
      '#placeholder' => "https://www.allrecipes.com/recipe/...\nhttps://www.foodnetwork.com/recipes/...\nhttps://www.myrecipes.com/recipe/...",
    ];

    $form['visibility'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default Visibility'),
      '#description' => $this->t('Set the default visibility for all imported recipes.'),
      '#options' => [
        'private' => $this->t('Private (only visible to me)'),
        'public' => $this->t('Public (visible to all users)'),
      ],
      '#default_value' => 'private',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import All Recipes'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $urls_text = $form_state->getValue('urls');
    $urls = array_filter(array_map('trim', explode("\n", $urls_text)));

    if (empty($urls)) {
      $form_state->setErrorByName('urls', $this->t('Please enter at least one recipe URL.'));
      return;
    }

    // Validate each URL
    foreach ($urls as $url) {
      if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $form_state->setErrorByName('urls', $this->t('Invalid URL: @url', ['@url' => $url]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $urls_text = $form_state->getValue('urls');
    $urls = array_filter(array_map('trim', explode("\n", $urls_text)));
    $visibility = $form_state->getValue('visibility');

    $batch = [
      'title' => $this->t('Importing Recipes'),
      'operations' => [],
      'finished' => '\Drupal\recipeboxx_recipe\Form\RecipeBulkImportForm::batchFinished',
      'progress_message' => $this->t('Imported @current of @total recipes.'),
    ];

    foreach ($urls as $url) {
      $batch['operations'][] = [
        '\Drupal\recipeboxx_recipe\Form\RecipeBulkImportForm::batchImportRecipe',
        [$url, $visibility, $this->currentUser()->id()],
      ];
    }

    batch_set($batch);
  }

  /**
   * Batch operation: Import a single recipe.
   *
   * @param string $url
   *   The recipe URL.
   * @param string $visibility
   *   The visibility setting.
   * @param int $uid
   *   The user ID.
   * @param array $context
   *   The batch context.
   */
  public static function batchImportRecipe($url, $visibility, $uid, &$context) {
    try {
      $scraper = \Drupal::service('recipeboxx_recipe.scraper');
      $recipeData = $scraper->importFromUrl($url);

      if (!$recipeData) {
        $context['results']['failed'][] = [
          'url' => $url,
          'error' => 'Could not extract recipe data',
        ];
        return;
      }

      // Create node from scraped data
      $node = Node::create([
        'type' => 'recipe',
        'title' => $recipeData['title'] ?? 'Untitled Recipe',
        'body' => [
          'value' => $recipeData['body'] ?? $recipeData['instructions'] ?? '',
          'format' => 'basic_html',
        ],
        'field_ingredients' => $recipeData['ingredients'] ?? '',
        'field_prep_time' => $recipeData['prep_time'] ?? NULL,
        'field_cook_time' => $recipeData['cook_time'] ?? NULL,
        'field_servings' => $recipeData['servings'] ?? NULL,
        'field_calories' => $recipeData['calories'] ?? NULL,
        'field_source_url' => !empty($recipeData['source_url']) ? ['uri' => $recipeData['source_url']] : NULL,
        'field_source_author' => $recipeData['author'] ?? '',
        'field_recipe_visibility' => $visibility,
        'uid' => $uid,
        'status' => 1,
      ]);

      $node->save();

      $context['results']['success'][] = [
        'title' => $node->label(),
        'url' => $url,
        'nid' => $node->id(),
      ];

      $context['message'] = t('Imported: @title', ['@title' => $node->label()]);
    }
    catch (\Exception $e) {
      $context['results']['failed'][] = [
        'url' => $url,
        'error' => $e->getMessage(),
      ];
      \Drupal::logger('recipeboxx_recipe')->error('Bulk import failed for @url: @error', [
        '@url' => $url,
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   The results array.
   * @param array $operations
   *   The operations that were performed.
   */
  public static function batchFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $success_count = count($results['success'] ?? []);
      $failed_count = count($results['failed'] ?? []);

      if ($success_count > 0) {
        $messenger->addStatus(t('Successfully imported @count recipes!', [
          '@count' => $success_count,
        ]));
      }

      if ($failed_count > 0) {
        $messenger->addWarning(t('Failed to import @count recipes. See logs for details.', [
          '@count' => $failed_count,
        ]));

        // Show first few failures
        $failures_to_show = array_slice($results['failed'], 0, 5);
        foreach ($failures_to_show as $failure) {
          $messenger->addError(t('Failed: @url - @error', [
            '@url' => $failure['url'],
            '@error' => $failure['error'],
          ]));
        }

        if ($failed_count > 5) {
          $messenger->addError(t('... and @more more failures.', [
            '@more' => $failed_count - 5,
          ]));
        }
      }
    }
    else {
      $messenger->addError(t('Bulk import encountered an error.'));
    }
  }

}
