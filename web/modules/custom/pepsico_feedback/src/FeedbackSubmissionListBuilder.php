<?php

declare(strict_types=1);

namespace Drupal\pepsico_feedback;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\pepsico_feedback\Entity\FeedbackSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists feedback submissions at /admin/content/feedback, newest first.
 *
 * The parent render() adds the entity type's list cache tag, so the page is
 * invalidated as soon as a new submission is saved.
 */
final class FeedbackSubmissionListBuilder extends EntityListBuilder {

  private const COMMENTS_PREVIEW_LENGTH = 120;

  /**
   * {@inheritdoc}
   */
  protected $limit = 25;

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    private readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get(DateFormatterInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityListQuery(): QueryInterface {
    return $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort(FeedbackSubmissionInterface::FIELD_CREATED, 'DESC')
      ->sort('id', 'DESC')
      ->pager($this->limit);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   The table header.
   */
  public function buildHeader(): array {
    // Submissions have no edit or delete routes, so no operations column.
    return [
      'name' => $this->t('Name'),
      'email' => $this->t('Email'),
      'comments' => $this->t('Comments'),
      'created' => $this->t('Submitted'),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   *   The table row.
   */
  public function buildRow(EntityInterface $entity): array {
    assert($entity instanceof FeedbackSubmissionInterface);
    return [
      'name' => $entity->getName(),
      'email' => $entity->getEmail(),
      'comments' => Unicode::truncate($entity->getComments(), self::COMMENTS_PREVIEW_LENGTH, TRUE, TRUE),
      'created' => $this->dateFormatter->format($entity->getCreatedTime(), 'short'),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No feedback has been submitted yet.');
    return $build;
  }

}
