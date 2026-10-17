<?php

declare(strict_types=1);

namespace Drupal\Tests\pepsico_feedback\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\pepsico_feedback\Entity\FeedbackSubmissionInterface;
use Drupal\pepsico_feedback\Event\FeedbackSubmittedEvent;
use Drupal\pepsico_feedback\FeedbackSubmissionAccessControlHandler;
use Drupal\pepsico_feedback\Form\FeedbackForm;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the feedback form validation, storage and listing access.
 */
#[CoversClass(FeedbackForm::class)]
#[Group('pepsico_feedback')]
#[RunTestsInSeparateProcesses]
final class FeedbackFormTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'pepsico_feedback',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema(FeedbackSubmissionInterface::ENTITY_TYPE_ID);
    $this->installConfig(['pepsico_feedback']);
  }

  /**
   * Empty and whitespace-only values fail on every field.
   */
  public function testRequiredFields(): void {
    foreach (['', '   '] as $value) {
      $errors = $this->submit(['name' => $value, 'email' => $value, 'comments' => $value]);
      $this->assertEqualsCanonicalizing(
        [
          FeedbackSubmissionInterface::FIELD_NAME,
          FeedbackSubmissionInterface::FIELD_EMAIL,
          FeedbackSubmissionInterface::FIELD_COMMENTS,
        ],
        array_keys($errors),
        sprintf('Errors for value "%s".', $value),
      );
    }
    $this->assertSame(0, $this->countSubmissions());
  }

  /**
   * An invalid email address is rejected server side.
   */
  public function testInvalidEmail(): void {
    $errors = $this->submit($this->validValues(['email' => 'not-an-email']));
    $this->assertSame([FeedbackSubmissionInterface::FIELD_EMAIL], array_keys($errors));
    $this->assertSame(0, $this->countSubmissions());
  }

  /**
   * Comments longer than the configured maximum are rejected.
   */
  public function testCommentsMaxLength(): void {
    $errors = $this->submit($this->validValues(['comments' => str_repeat('a', 2001)]));
    $this->assertSame([FeedbackSubmissionInterface::FIELD_COMMENTS], array_keys($errors));

    // The limit comes from config, not a hard-coded value.
    $this->config(FeedbackForm::SETTINGS)->set('comments_max_length', 10)->save();
    $errors = $this->submit($this->validValues(['comments' => 'Eleven char']));
    $this->assertSame([FeedbackSubmissionInterface::FIELD_COMMENTS], array_keys($errors));

    $this->assertSame(0, $this->countSubmissions());
  }

  /**
   * A valid submission is stored, announced and confirmed.
   */
  public function testValidSubmission(): void {
    $events = [];
    $this->container->get('event_dispatcher')->addListener(
      FeedbackSubmittedEvent::NAME,
      static function (FeedbackSubmittedEvent $event) use (&$events): void {
        $events[] = $event;
      },
    );

    $errors = $this->submit($this->validValues(['name' => '  Jordan Lee  ']));
    $this->assertSame([], $errors);

    $storage = $this->container->get('entity_type.manager')->getStorage(FeedbackSubmissionInterface::ENTITY_TYPE_ID);
    $submissions = $storage->loadMultiple();
    $this->assertCount(1, $submissions);
    $submission = reset($submissions);
    $this->assertInstanceOf(FeedbackSubmissionInterface::class, $submission);
    $this->assertSame('Jordan Lee', $submission->getName());
    $this->assertSame('jordan@example.com', $submission->getEmail());
    $this->assertSame('Great newsroom.', $submission->getComments());
    $this->assertGreaterThan(0, $submission->getCreatedTime());

    $this->assertCount(1, $events);
    $this->assertSame($submission->id(), $events[0]->submission->id());

    $status = $this->container->get('messenger')->messagesByType('status');
    $this->assertCount(1, $status);
    $this->assertStringContainsString('Thank you, Jordan Lee.', (string) $status[0]);
  }

  /**
   * Only users with a feedback permission can open the listing.
   */
  public function testListingAccess(): void {
    $this->container->get('router.builder')->rebuild();
    $accessManager = $this->container->get('access_manager');
    $route = 'entity.pepsico_feedback_submission.collection';

    $this->assertFalse($accessManager->checkNamedRoute($route, [], User::getAnonymousUser()));
    $this->assertFalse($accessManager->checkNamedRoute($route, [], $this->createUser([])));
    $this->assertTrue($accessManager->checkNamedRoute($route, [], $this->createUser([FeedbackSubmissionAccessControlHandler::PERMISSION_VIEW])));
    $this->assertTrue($accessManager->checkNamedRoute($route, [], $this->createUser([FeedbackSubmissionAccessControlHandler::PERMISSION_ADMINISTER])));
  }

  /**
   * Submits the feedback form programmatically.
   *
   * @param array<string, string> $values
   *   Submitted values keyed by field name.
   *
   * @return array<string, string>
   *   Validation errors keyed by element name.
   */
  private function submit(array $values): array {
    $formState = (new FormState())->setValues($values);
    $this->container->get('form_builder')->submitForm(FeedbackForm::class, $formState);
    return array_map('strval', $formState->getErrors());
  }

  /**
   * Returns valid form values with optional overrides.
   *
   * @param array<string, string> $overrides
   *   Values to replace.
   *
   * @return array<string, string>
   *   Form values.
   */
  private function validValues(array $overrides = []): array {
    return $overrides + [
      FeedbackSubmissionInterface::FIELD_NAME => 'Jordan Lee',
      FeedbackSubmissionInterface::FIELD_EMAIL => 'jordan@example.com',
      FeedbackSubmissionInterface::FIELD_COMMENTS => 'Great newsroom.',
    ];
  }

  /**
   * Counts stored submissions.
   */
  private function countSubmissions(): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage(FeedbackSubmissionInterface::ENTITY_TYPE_ID)
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
