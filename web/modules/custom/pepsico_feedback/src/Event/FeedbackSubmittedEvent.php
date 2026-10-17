<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\pepsico_feedback\Entity\FeedbackSubmissionInterface;

/**
 * Dispatched after a feedback submission has been saved.
 */
final class FeedbackSubmittedEvent extends Event {

  public const NAME = 'pepsico_feedback.submitted';

  public function __construct(
    public readonly FeedbackSubmissionInterface $submission,
  ) {}

}
