<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * A feedback submission sent through the public /feedback form.
 */
interface FeedbackSubmissionInterface extends ContentEntityInterface {

  public const ENTITY_TYPE_ID = 'pepsico_feedback_submission';
  public const FIELD_NAME = 'name';
  public const FIELD_EMAIL = 'email';
  public const FIELD_COMMENTS = 'comments';
  public const FIELD_CREATED = 'created';
  public const NAME_MAX_LENGTH = 128;

  /**
   * Gets the submitter's name.
   */
  public function getName(): string;

  /**
   * Gets the submitter's email address.
   */
  public function getEmail(): string;

  /**
   * Gets the feedback text.
   */
  public function getComments(): string;

  /**
   * Gets the submission timestamp.
   */
  public function getCreatedTime(): int;

}
