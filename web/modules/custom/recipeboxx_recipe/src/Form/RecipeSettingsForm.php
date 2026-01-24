<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Recipe Box settings.
 */
class RecipeSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipeboxx_recipe_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['recipeboxx_recipe.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('recipeboxx_recipe.settings');

    $form['openai'] = [
      '#type' => 'details',
      '#title' => $this->t('OpenAI Settings'),
      '#description' => $this->t('Configure OpenAI for AI-powered recipe import fallback.'),
      '#open' => TRUE,
    ];

    $form['openai']['openai_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenAI API Key'),
      '#description' => $this->t('Enter your OpenAI API key to enable AI-powered recipe scraping for sites without Schema.org markup. Get your API key from https://platform.openai.com/api-keys'),
      '#default_value' => $config->get('openai_api_key'),
      '#maxlength' => 200,
    ];

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default Settings'),
      '#description' => $this->t('Configure default values for new recipes.'),
      '#open' => TRUE,
    ];

    $form['defaults']['default_visibility'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default Recipe Visibility'),
      '#description' => $this->t('Choose the default visibility setting for new recipes.'),
      '#options' => [
        'private' => $this->t('Private (only visible to the author)'),
        'public' => $this->t('Public (visible to all users)'),
      ],
      '#default_value' => $config->get('default_visibility') ?? 'private',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('recipeboxx_recipe.settings')
      ->set('openai_api_key', $form_state->getValue('openai_api_key'))
      ->set('default_visibility', $form_state->getValue('default_visibility'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
