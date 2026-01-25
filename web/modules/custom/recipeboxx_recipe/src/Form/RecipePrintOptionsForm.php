<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

/**
 * Form for selecting which sections to include when printing a recipe.
 */
class RecipePrintOptionsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipeboxx_recipe_print_options_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    // Store node ID for submit handler
    $form['node_id'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    // Informational message about always-included sections
    $form['required_info'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>Always included:</strong> Title, Ingredients, Instructions</p>',
    ];

    // Optional sections checkboxes
    $form['sections'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Include these sections:'),
      '#options' => [
        'timing' => $this->t('Timing Information (Prep/Cook/Total Time)'),
        'nutrition' => $this->t('Nutrition Facts (Calories, Protein, Carbs, Fat, Fiber)'),
        'source' => $this->t('Source Information (Author, URL, Site)'),
        'categories' => $this->t('Categories (Cuisine, Meal Type, Dietary Restrictions, Tags)'),
        'images' => $this->t('Recipe Images'),
      ],
      '#default_value' => ['timing', 'images'], // Sensible defaults
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate Print View'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node_id = $form_state->getValue('node_id');
    $sections = array_filter($form_state->getValue('sections'));

    // Build URL with sections as query parameters
    $url = Url::fromRoute('recipeboxx_recipe.print_view', [
      'node' => $node_id,
    ], [
      'query' => ['sections' => implode(',', array_keys($sections))],
    ]);

    // Redirect will be intercepted by JavaScript to open in new window
    $form_state->setRedirectUrl($url);
  }

}
