<?php

namespace Drupal\recipeboxx_recipe\Access;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Recipe Collection entity.
 */
class RecipeCollectionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\recipeboxx_recipe\Entity\RecipeCollection $entity */

    // Admin permission bypasses all checks.
    if ($account->hasPermission('administer recipe collections')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        // Public collections can be viewed by anyone with permission.
        if ($entity->isPublic() && $account->hasPermission('view public collections')) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
        }

        // Owner can always view their own collections.
        if ($entity->getOwnerId() == $account->id() && $account->hasPermission('create recipe collections')) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }

        return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();

      case 'update':
      case 'delete':
        // Only owner can edit/delete.
        if ($entity->getOwnerId() == $account->id() && $account->hasPermission('edit own recipe collections')) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }

        return AccessResult::forbidden()->cachePerPermissions()->cachePerUser();

      default:
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create recipe collections');
  }

}
