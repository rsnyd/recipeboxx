<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a recipe search form.
 */
class RecipeSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipeboxx_recipe_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();

    $form['#attributes'] = ['class' => ['recipe-search-form']];

    $form['keyword'] = [
      '#type' => 'search',
      '#title' => $this->t('Search'),
      '#placeholder' => $this->t('Search recipes...'),
      '#default_value' => $request->query->get('keyword', ''),
      '#size' => 40,
    ];

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => !empty($request->query->all()),
    ];

    $form['filters']['cuisine'] = [
      '#type' => 'select',
      '#title' => $this->t('Cuisine'),
      '#options' => [
        '' => $this->t('- Any -'),
        'Italian' => $this->t('Italian'),
        'Mexican' => $this->t('Mexican'),
        'Chinese' => $this->t('Chinese'),
        'Indian' => $this->t('Indian'),
        'Japanese' => $this->t('Japanese'),
        'Thai' => $this->t('Thai'),
        'French' => $this->t('French'),
        'American' => $this->t('American'),
        'Mediterranean' => $this->t('Mediterranean'),
        'Other' => $this->t('Other'),
      ],
      '#default_value' => $request->query->get('cuisine', ''),
    ];

    $form['filters']['dietary'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Dietary Restrictions'),
      '#options' => [
        'vegetarian' => $this->t('Vegetarian'),
        'vegan' => $this->t('Vegan'),
        'gluten_free' => $this->t('Gluten Free'),
        'dairy_free' => $this->t('Dairy Free'),
        'nut_free' => $this->t('Nut Free'),
        'low_carb' => $this->t('Low Carb'),
        'keto' => $this->t('Keto'),
      ],
      '#default_value' => $request->query->get('dietary', []),
    ];

    $form['filters']['prep_time_max'] = [
      '#type' => 'select',
      '#title' => $this->t('Prep Time (max)'),
      '#options' => [
        '' => $this->t('- Any -'),
        '15' => $this->t('15 minutes'),
        '30' => $this->t('30 minutes'),
        '60' => $this->t('1 hour'),
        '120' => $this->t('2 hours'),
      ],
      '#default_value' => $request->query->get('prep_time_max', ''),
    ];

    $form['filters']['cook_time_max'] = [
      '#type' => 'select',
      '#title' => $this->t('Cook Time (max)'),
      '#options' => [
        '' => $this->t('- Any -'),
        '15' => $this->t('15 minutes'),
        '30' => $this->t('30 minutes'),
        '60' => $this->t('1 hour'),
        '120' => $this->t('2 hours'),
      ],
      '#default_value' => $request->query->get('cook_time_max', ''),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
    ];

    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Clear'),
      '#url' => Url::fromRoute('recipeboxx_recipe.search'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Build query parameters from form values.
    $query = [];

    if ($keyword = $form_state->getValue('keyword')) {
      $query['keyword'] = $keyword;
    }

    if ($cuisine = $form_state->getValue('cuisine')) {
      $query['cuisine'] = $cuisine;
    }

    if ($dietary = array_filter($form_state->getValue('dietary'))) {
      $query['dietary'] = array_values($dietary);
    }

    if ($prep_time = $form_state->getValue('prep_time_max')) {
      $query['prep_time_max'] = $prep_time;
    }

    if ($cook_time = $form_state->getValue('cook_time_max')) {
      $query['cook_time_max'] = $cook_time;
    }

    // Redirect to search page with query parameters.
    $form_state->setRedirect('recipeboxx_recipe.search', [], ['query' => $query]);
  }

}
