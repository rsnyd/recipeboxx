<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Recipe Note edit forms.
 */
class RecipeNoteForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\recipeboxx_recipe\Entity\RecipeNote $entity */
    $entity = $this->entity;

    // If recipe_id is set in route parameters, set it on the entity.
    if (!$entity->id() && $recipe_id = \Drupal::routeMatch()->getParameter('node')) {
      if (is_object($recipe_id)) {
        $recipe_id = $recipe_id->id();
      }
      $entity->setRecipe($recipe_id);
      $form['recipe_id']['#access'] = FALSE;
    }

    $form['#attributes']['class'][] = 'recipe-note-form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created your note for %recipe.', [
          '%recipe' => $entity->getRecipe()->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Updated your note for %recipe.', [
          '%recipe' => $entity->getRecipe()->label(),
        ]));
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $entity->getRecipe()->id()]);

    return $status;
  }

}
