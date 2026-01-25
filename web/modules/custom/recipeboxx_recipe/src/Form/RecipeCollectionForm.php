<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for recipe collection add and edit forms.
 */
class RecipeCollectionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\recipeboxx_recipe\Entity\RecipeCollection $entity */
    $entity = $this->entity;

    $form['#title'] = $entity->isNew() ?
      $this->t('Create Recipe Collection') :
      $this->t('Edit @name', ['@name' => $entity->getName()]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\recipeboxx_recipe\Entity\RecipeCollection $entity */
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    $messenger = $this->messenger();

    if ($status == SAVED_NEW) {
      $messenger->addStatus($this->t('Created collection %name.', [
        '%name' => $entity->getName(),
      ]));
    }
    else {
      $messenger->addStatus($this->t('Updated collection %name.', [
        '%name' => $entity->getName(),
      ]));
    }

    $form_state->setRedirect('entity.recipe_collection.canonical', [
      'recipe_collection' => $entity->id(),
    ]);

    return $status;
  }

}
