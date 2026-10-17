<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback\Form;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\pepsico_feedback\Entity\FeedbackSubmissionInterface;
use Drupal\pepsico_feedback\Event\FeedbackSubmittedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Public feedback form at /feedback.
 */
final class FeedbackForm extends FormBase {

  public const SETTINGS = 'pepsico_feedback.settings';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EmailValidatorInterface $emailValidator,
    private readonly EventDispatcherInterface $eventDispatcher,
    ConfigFactoryInterface $configFactory,
    MessengerInterface $messenger,
  ) {
    // FormBase traits already declare these properties, so use the setters.
    $this->setConfigFactory($configFactory);
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'pepsico_feedback_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form render array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);
    $maxLength = $this->getCommentsMaxLength();

    $form['#attributes']['class'][] = 'feedback-form';

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('Tell us what you think about the newsroom. All fields are required.') . '</p>',
    ];
    $form[FeedbackSubmissionInterface::FIELD_NAME] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#maxlength' => FeedbackSubmissionInterface::NAME_MAX_LENGTH,
      '#attributes' => ['autocomplete' => 'name'],
    ];
    $form[FeedbackSubmissionInterface::FIELD_EMAIL] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'email'],
    ];
    $form[FeedbackSubmissionInterface::FIELD_COMMENTS] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comments'),
      '#required' => TRUE,
      '#rows' => 6,
      '#maxlength' => $maxLength,
      '#description' => $this->t('Up to @count characters.', ['@count' => $maxLength]),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send feedback'),
    ];

    // Rebuild the cached form when the max length setting changes.
    CacheableMetadata::createFromRenderArray($form)
      ->addCacheableDependency($config)
      ->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $labels = [
      FeedbackSubmissionInterface::FIELD_NAME => $this->t('Name'),
      FeedbackSubmissionInterface::FIELD_EMAIL => $this->t('Email'),
      FeedbackSubmissionInterface::FIELD_COMMENTS => $this->t('Comments'),
    ];

    foreach ($labels as $field => $label) {
      $value = trim((string) $form_state->getValue($field));
      $form_state->setValue($field, $value);
      // #required lets whitespace-only input through, so check again here.
      if ($value === '') {
        $form_state->setErrorByName($field, $this->t('@field is required.', ['@field' => $label]));
      }
    }

    $email = $form_state->getValue(FeedbackSubmissionInterface::FIELD_EMAIL);
    if ($email !== '' && !$this->emailValidator->isValid($email)) {
      $form_state->setErrorByName(FeedbackSubmissionInterface::FIELD_EMAIL, $this->t('Enter a valid email address.'));
    }

    $name = $form_state->getValue(FeedbackSubmissionInterface::FIELD_NAME);
    if (mb_strlen($name) > FeedbackSubmissionInterface::NAME_MAX_LENGTH) {
      $form_state->setErrorByName(FeedbackSubmissionInterface::FIELD_NAME, $this->t('Name cannot be longer than @max characters.', ['@max' => FeedbackSubmissionInterface::NAME_MAX_LENGTH]));
    }

    $maxLength = $this->getCommentsMaxLength();
    $comments = $form_state->getValue(FeedbackSubmissionInterface::FIELD_COMMENTS);
    if (mb_strlen($comments) > $maxLength) {
      $form_state->setErrorByName(FeedbackSubmissionInterface::FIELD_COMMENTS, $this->t('Comments cannot be longer than @max characters.', ['@max' => $maxLength]));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = $form_state->getValue(FeedbackSubmissionInterface::FIELD_NAME);

    $submission = $this->entityTypeManager
      ->getStorage(FeedbackSubmissionInterface::ENTITY_TYPE_ID)
      ->create([
        FeedbackSubmissionInterface::FIELD_NAME => $name,
        FeedbackSubmissionInterface::FIELD_EMAIL => $form_state->getValue(FeedbackSubmissionInterface::FIELD_EMAIL),
        FeedbackSubmissionInterface::FIELD_COMMENTS => $form_state->getValue(FeedbackSubmissionInterface::FIELD_COMMENTS),
      ]);
    assert($submission instanceof FeedbackSubmissionInterface);
    $submission->save();

    $this->eventDispatcher->dispatch(new FeedbackSubmittedEvent($submission), FeedbackSubmittedEvent::NAME);

    $this->messenger()->addStatus($this->t('Thank you, @name. Your feedback has been received.', ['@name' => $name]));
    $form_state->setRedirect('pepsico_feedback.form');
  }

  /**
   * Gets the configured maximum length of the Comments field.
   */
  private function getCommentsMaxLength(): int {
    return (int) $this->config(self::SETTINGS)->get('comments_max_length');
  }

}
