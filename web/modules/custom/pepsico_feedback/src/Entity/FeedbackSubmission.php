<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pepsico_feedback\FeedbackSubmissionAccessControlHandler;
use Drupal\views\EntityViewsData;

/**
 * Defines the feedback submission entity.
 *
 * Submissions are immutable records: they are created by the public form and
 * can only be viewed or deleted afterwards.
 */
#[ContentEntityType(
  id: FeedbackSubmissionInterface::ENTITY_TYPE_ID,
  label: new TranslatableMarkup('Feedback submission'),
  label_collection: new TranslatableMarkup('Feedback'),
  label_singular: new TranslatableMarkup('feedback submission'),
  label_plural: new TranslatableMarkup('feedback submissions'),
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => FeedbackSubmissionInterface::FIELD_NAME,
  ],
  handlers: [
    'access' => FeedbackSubmissionAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
  ],
  admin_permission: 'administer pepsico feedback',
  base_table: FeedbackSubmissionInterface::ENTITY_TYPE_ID,
  label_count: [
    'singular' => '@count feedback submission',
    'plural' => '@count feedback submissions',
  ],
)]
final class FeedbackSubmission extends ContentEntityBase implements FeedbackSubmissionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields[self::FIELD_NAME] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', self::NAME_MAX_LENGTH)
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 0]);

    $fields[self::FIELD_EMAIL] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Email'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 1]);

    $fields[self::FIELD_COMMENTS] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Comments'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'weight' => 2]);

    $fields[self::FIELD_CREATED] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Submitted'))
      ->setDescription(new TranslatableMarkup('The time the feedback was submitted.'))
      ->setDisplayOptions('view', ['label' => 'inline', 'weight' => 3]);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return (string) $this->get(self::FIELD_NAME)->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmail(): string {
    return (string) $this->get(self::FIELD_EMAIL)->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getComments(): string {
    return (string) $this->get(self::FIELD_COMMENTS)->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get(self::FIELD_CREATED)->value;
  }

}
