<?php

declare(strict_types=1);

namespace Drupal\pepsico_news_stats\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\pepsico_news_stats\Service\NewsStatsCalculator;

/**
 * Renders the News Article statistics report.
 */
final class NewsStatsController extends ControllerBase {

  public function __construct(
    private readonly NewsStatsCalculator $calculator,
  ) {}

  /**
   * Builds the /admin/news-stats page.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function build(): array {
    $stats = $this->calculator->getStats();

    return [
      '#type' => 'table',
      '#header' => [$this->t('Statistic'), $this->t('Count')],
      '#rows' => [
        [$this->t('Published news articles'), $stats['total']],
        [$this->t('Featured news articles'), $stats['featured']],
      ],
      '#cache' => [
        'tags' => [NewsStatsCalculator::CACHE_TAG],
        'contexts' => ['user.permissions'],
      ],
    ];
  }

}
