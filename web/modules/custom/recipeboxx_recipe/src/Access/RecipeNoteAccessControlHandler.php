<?php

namespace Drupal\recipeboxx_recipe\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Recipe Note entity.
 */
class RecipeNoteAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\recipeboxx_recipe\Entity\RecipeNote $entity */

    switch ($operation) {
      case 'view':
        // Users can only view their own notes.
        if ($account->id() == $entity->getOwnerId()) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($entity);

      case 'update':
      case 'delete':
        // Users can only edit/delete their own notes.
        if ($account->id() == $entity->getOwnerId()) {
          return AccessResult::allowed()->cachePerUser()->addCacheableDependency($entity);
        }
        return AccessResult::forbidden()->cachePerUser()->addCacheableDependency($entity);

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // All authenticated users can create recipe notes.
    return AccessResult::allowedIfHasPermission($account, 'create recipe notes');
  }

}
