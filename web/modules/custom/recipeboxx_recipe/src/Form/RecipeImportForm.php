<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\recipeboxx_recipe\Service\RecipeScraperService;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for importing recipes from URLs.
 */
class RecipeImportForm extends FormBase {

  /**
   * Constructs a RecipeImportForm object.
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
    return 'recipeboxx_recipe_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Recipe URL'),
      '#description' => $this->t('Enter a URL from any recipe website. We will try to extract the recipe automatically using Schema.org or AI.'),
      '#required' => TRUE,
      '#placeholder' => 'https://www.example.com/recipe',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Recipe'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('url');

    try {
      $recipeData = $this->recipeScraper->importFromUrl($url);

      if (!$recipeData) {
        $this->messenger()->addError($this->t('Could not extract recipe from this URL. The site may not have structured recipe data. Please try manually creating the recipe.'));
        return;
      }

      // Create node from scraped data
      $node = $this->createRecipeNode($recipeData);
      $node->save();

      $this->messenger()->addStatus($this->t('Recipe "@title" has been imported successfully! Please review and modify as needed.', [
        '@title' => $node->label(),
      ]));

      // Redirect to edit form so user can review/modify
      $form_state->setRedirect('entity.node.edit_form', ['node' => $node->id()]);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while importing: @message', [
        '@message' => $e->getMessage(),
      ]));
      \Drupal::logger('recipeboxx_recipe')->error('Import error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Create a recipe node from scraped data.
   *
   * @param array $data
   *   The scraped recipe data.
   *
   * @return \Drupal\node\Entity\Node
   *   The created node.
   */
  private function createRecipeNode(array $data) {
    $node = Node::create([
      'type' => 'recipe',
      'title' => $data['title'] ?? 'Untitled Recipe',
      'body' => [
        'value' => $data['body'] ?? $data['instructions'] ?? '',
        'format' => 'basic_html',
      ],
      'field_ingredients' => $data['ingredients'] ?? '',
      'field_prep_time' => $data['prep_time'] ?? NULL,
      'field_cook_time' => $data['cook_time'] ?? NULL,
      'field_servings' => $data['servings'] ?? NULL,
      'field_calories' => $data['calories'] ?? NULL,
      'field_source_url' => !empty($data['source_url']) ? ['uri' => $data['source_url']] : NULL,
      'field_source_author' => $data['author'] ?? '',
      'field_recipe_visibility' => 'private', // Default to private
      'uid' => \Drupal::currentUser()->id(),
      'status' => 1,
    ]);

    // Process images (download and create media entities)
    if (!empty($data['images'])) {
      $this->processImages($node, $data['images']);
    }

    return $node;
  }

  /**
   * Download images and create media entities.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The recipe node.
   * @param array $imageUrls
   *   Array of image URLs.
   */
  private function processImages($node, array $imageUrls): void {
    $media_ids = [];

    foreach ($imageUrls as $imageUrl) {
      try {
        // Download image
        $imageData = file_get_contents($imageUrl);
        if (!$imageData) {
          continue;
        }

        // Get filename from URL
        $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
        if (!$filename) {
          $filename = 'recipe_image_' . time() . '.jpg';
        }

        // Save file
        $file = File::create([
          'uri' => 'public://recipe_images/' . $filename,
          'status' => 1,
        ]);
        file_put_contents($file->getFileUri(), $imageData);
        $file->save();

        // Create media entity
        $media = Media::create([
          'bundle' => 'image',
          'uid' => \Drupal::currentUser()->id(),
          'field_media_image' => [
            'target_id' => $file->id(),
            'alt' => $node->label(),
          ],
        ]);
        $media->setName($node->label() . ' - Image');
        $media->setPublished()->save();

        $media_ids[] = ['target_id' => $media->id()];
      }
      catch (\Exception $e) {
        \Drupal::logger('recipeboxx_recipe')->warning('Failed to download image @url: @message', [
          '@url' => $imageUrl,
          '@message' => $e->getMessage(),
        ]);
      }
    }

    if (!empty($media_ids)) {
      $node->set('field_recipe_images', $media_ids);
    }
  }

}
