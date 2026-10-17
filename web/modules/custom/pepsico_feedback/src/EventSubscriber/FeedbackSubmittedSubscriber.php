<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback\EventSubscriber;

use Drupal\pepsico_feedback\Event\FeedbackSubmittedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Logs each new feedback submission.
 *
 * Only the submission id is logged: the name, email address and comments are
 * personal data, so they stay in the entity and are not copied into watchdog.
 */
final class FeedbackSubmittedSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'logger.channel.pepsico_feedback')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [FeedbackSubmittedEvent::NAME => 'onSubmitted'];
  }

  /**
   * Writes a log entry for the submission.
   */
  public function onSubmitted(FeedbackSubmittedEvent $event): void {
    $this->logger->info('Feedback #@id received.', [
      '@id' => $event->submission->id(),
    ]);
  }

}
