<?php

namespace Drupal\recipeboxx_recipe\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;

/**
 * Provides a form for uploading recipe photos.
 */
class RecipePhotoUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recipeboxx_recipe_photo_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    if (!$node || $node->bundle() !== 'recipe') {
      return $form;
    }

    $form_state->set('node', $node);

    $form['#attributes'] = ['class' => ['recipe-photo-upload-form']];

    $form['photo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Photo'),
      '#description' => $this->t('Allowed types: JPG, PNG, GIF. Maximum file size: 5MB.'),
      '#upload_location' => 'public://recipe-photos/' . $node->id(),
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png gif'],
        'file_validate_size' => [5 * 1024 * 1024], // 5MB
      ],
      '#required' => TRUE,
    ];

    $form['alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alt Text'),
      '#description' => $this->t('Alternative text for accessibility.'),
      '#default_value' => $node->getTitle(),
      '#maxlength' => 255,
    ];

    $form['caption'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Caption'),
      '#description' => $this->t('Optional caption for the photo.'),
      '#maxlength' => 255,
    ];

    $form['set_as_main'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set as main recipe image'),
      '#default_value' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload Photo'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $node->toUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $form_state->get('node');
    $fid = $form_state->getValue(['photo', 0]);

    if ($fid && $node) {
      $file = File::load($fid);

      if ($file) {
        // Make file permanent.
        $file->setPermanent();
        $file->save();

        // Add to node's image field.
        if ($node->hasField('field_recipe_images')) {
          $current_images = $node->get('field_recipe_images')->getValue();

          $new_image = [
            'target_id' => $file->id(),
            'alt' => $form_state->getValue('alt'),
            'title' => $form_state->getValue('caption'),
          ];

          // If set as main, prepend to images array.
          if ($form_state->getValue('set_as_main')) {
            array_unshift($current_images, $new_image);
          }
          else {
            $current_images[] = $new_image;
          }

          $node->set('field_recipe_images', $current_images);
          $node->save();

          $this->messenger()->addStatus($this->t('Photo uploaded successfully.'));
        }
        else {
          $this->messenger()->addWarning($this->t('Recipe images field not found. Photo was uploaded but not attached.'));
        }
      }
    }

    $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
  }

}
