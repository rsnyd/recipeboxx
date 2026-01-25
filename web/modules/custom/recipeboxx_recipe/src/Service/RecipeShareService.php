<?php

namespace Drupal\recipeboxx_recipe\Service;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Service for recipe social media sharing functionality.
 */
class RecipeShareService {

  /**
   * Generate share URL for a social platform.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   * @param string $platform
   *   The platform: facebook, twitter, pinterest, email.
   *
   * @return string|null
   *   The share URL or NULL if platform not supported.
   */
  public function getShareUrl(NodeInterface $node, string $platform): ?string {
    if ($node->bundle() !== 'recipe') {
      return NULL;
    }

    $recipe_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    $title = $node->getTitle();
    $description = $this->getRecipeDescription($node);
    $image_url = $this->getRecipeImageUrl($node);

    switch ($platform) {
      case 'facebook':
        return 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($recipe_url);

      case 'twitter':
        $text = $title . ' - ' . $description;
        return 'https://twitter.com/intent/tweet?url=' . urlencode($recipe_url) . '&text=' . urlencode($text);

      case 'pinterest':
        if ($image_url) {
          return 'https://pinterest.com/pin/create/button/?url=' . urlencode($recipe_url) .
            '&media=' . urlencode($image_url) .
            '&description=' . urlencode($title);
        }
        return NULL;

      case 'email':
        $subject = 'Recipe: ' . $title;
        $body = "Check out this recipe: $title\n\n$recipe_url\n\n$description";
        return 'mailto:?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);

      case 'whatsapp':
        $text = "$title - $recipe_url";
        return 'https://wa.me/?text=' . urlencode($text);

      default:
        return NULL;
    }
  }

  /**
   * Get recipe description for sharing.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return string
   *   The description.
   */
  protected function getRecipeDescription(NodeInterface $node): string {
    if ($node->hasField('field_description') && !$node->get('field_description')->isEmpty()) {
      $description = $node->get('field_description')->value;
      return mb_substr(strip_tags($description), 0, 200);
    }

    // Fallback to ingredients count and time
    $parts = [];

    if ($node->hasField('field_servings') && !$node->get('field_servings')->isEmpty()) {
      $servings = $node->get('field_servings')->value;
      $parts[] = "Serves $servings";
    }

    if ($node->hasField('field_total_time') && !$node->get('field_total_time')->isEmpty()) {
      $time = $node->get('field_total_time')->value;
      $parts[] = "$time minutes";
    }

    return implode(' | ', $parts);
  }

  /**
   * Get recipe image URL for sharing.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return string|null
   *   The absolute image URL or NULL.
   */
  protected function getRecipeImageUrl(NodeInterface $node): ?string {
    if ($node->hasField('field_recipe_images') && !$node->get('field_recipe_images')->isEmpty()) {
      $image = $node->get('field_recipe_images')->first();
      if ($image && $image->entity) {
        $uri = $image->entity->getFileUri();
        $url = \Drupal::service('file_url_generator')->generateAbsoluteString($uri);
        return $url;
      }
    }

    return NULL;
  }

  /**
   * Generate Open Graph metadata for a recipe.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return array
   *   Array of Open Graph meta tags.
   */
  public function getOpenGraphMetadata(NodeInterface $node): array {
    if ($node->bundle() !== 'recipe') {
      return [];
    }

    $metadata = [
      'og:type' => 'article',
      'og:title' => $node->getTitle(),
      'og:url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'og:site_name' => \Drupal::config('system.site')->get('name'),
    ];

    // Add description
    $description = $this->getRecipeDescription($node);
    if ($description) {
      $metadata['og:description'] = $description;
    }

    // Add image
    $image_url = $this->getRecipeImageUrl($node);
    if ($image_url) {
      $metadata['og:image'] = $image_url;
      $metadata['og:image:alt'] = $node->getTitle();
    }

    // Add recipe-specific metadata
    if ($node->hasField('field_prep_time') && !$node->get('field_prep_time')->isEmpty()) {
      $metadata['recipe:prep_time'] = $node->get('field_prep_time')->value . ' minutes';
    }

    if ($node->hasField('field_cook_time') && !$node->get('field_cook_time')->isEmpty()) {
      $metadata['recipe:cook_time'] = $node->get('field_cook_time')->value . ' minutes';
    }

    if ($node->hasField('field_servings') && !$node->get('field_servings')->isEmpty()) {
      $metadata['recipe:serves'] = $node->get('field_servings')->value;
    }

    return $metadata;
  }

  /**
   * Get all available share platforms.
   *
   * @return array
   *   Array of platform info keyed by platform ID.
   */
  public function getSharePlatforms(): array {
    return [
      'facebook' => [
        'label' => 'Facebook',
        'icon' => 'facebook-square',
        'color' => '#3b5998',
      ],
      'twitter' => [
        'label' => 'Twitter',
        'icon' => 'twitter',
        'color' => '#1da1f2',
      ],
      'pinterest' => [
        'label' => 'Pinterest',
        'icon' => 'pinterest',
        'color' => '#bd081c',
      ],
      'whatsapp' => [
        'label' => 'WhatsApp',
        'icon' => 'whatsapp',
        'color' => '#25d366',
      ],
      'email' => [
        'label' => 'Email',
        'icon' => 'envelope',
        'color' => '#666',
      ],
    ];
  }

  /**
   * Generate copy-able share link.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The recipe node.
   *
   * @return string
   *   The absolute URL to the recipe.
   */
  public function getShareLink(NodeInterface $node): string {
    return $node->toUrl('canonical', ['absolute' => TRUE])->toString();
  }

}
