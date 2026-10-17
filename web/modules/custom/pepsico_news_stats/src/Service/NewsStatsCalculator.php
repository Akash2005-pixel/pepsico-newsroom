<?php

declare(strict_types=1);

namespace Drupal\pepsico_news_stats\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeInterface;
use Drupal\pepsico_news\Service\NewsArticleGenerator;

/**
 * Counts published News Articles for the statistics report.
 */
final class NewsStatsCalculator {

  /**
   * Cache tag invalidated whenever any News Article is saved or deleted.
   */
  public const CACHE_TAG = 'node_list:' . NewsArticleGenerator::BUNDLE;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the published News Article counts.
   *
   * @return array{total: int, featured: int}
   *   Total published articles and how many of them are featured.
   */
  public function getStats(): array {
    return [
      'total' => (int) $this->baseQuery()->count()->execute(),
      'featured' => (int) $this->baseQuery()
        ->condition(NewsArticleGenerator::FIELD_FEATURED, 1)
        ->count()
        ->execute(),
    ];
  }

  /**
   * Builds a query for published News Articles.
   */
  private function baseQuery(): QueryInterface {
    // Access checks are skipped on purpose: the report only exposes aggregate
    // counts and the route itself is protected by a dedicated permission.
    return $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', NewsArticleGenerator::BUNDLE)
      ->condition('status', NodeInterface::PUBLISHED);
  }

}
