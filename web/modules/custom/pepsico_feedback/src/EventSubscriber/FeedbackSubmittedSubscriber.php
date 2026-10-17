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
 * Only the submission id and name are logged: the email address and comments
 * stay in the entity so they are not copied into watchdog.
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
    $this->logger->info('Feedback #@id received from @name.', [
      '@id' => $event->submission->id(),
      '@name' => $event->submission->getName(),
    ]);
  }

}
