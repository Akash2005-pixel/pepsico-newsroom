<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access rules for feedback submissions.
 *
 * Viewing needs the view or the admin permission, deleting needs the admin
 * permission, and nobody can edit a submission.
 */
final class FeedbackSubmissionAccessControlHandler extends EntityAccessControlHandler {

  public const PERMISSION_VIEW = 'view pepsico feedback submissions';
  public const PERMISSION_ADMINISTER = 'administer pepsico feedback';

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, [self::PERMISSION_VIEW, self::PERMISSION_ADMINISTER], 'OR'),
      'update' => AccessResult::forbidden('Feedback submissions are immutable.'),
      // Delete and anything else fall back to the admin permission.
      default => parent::checkAccess($entity, $operation, $account),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    // Submissions are created by the public form, not through entity forms.
    return AccessResult::neutral()->cachePerPermissions();
  }

}
