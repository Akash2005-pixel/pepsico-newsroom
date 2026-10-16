<?php

declare(strict_types=1);

namespace Drupal\pepsico_news\Drush\Commands;

use Drupal\pepsico_news\Service\NewsArticleGenerator;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for PepsiCo News demo content.
 */
final class NewsGeneratorCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly NewsArticleGenerator $generator,
  ) {
    parent::__construct();
  }

  /**
   * Generates demo News Articles (safe to run repeatedly).
   */
  #[CLI\Command(name: 'pepsico:generate-news', aliases: ['pgn'])]
  #[CLI\Usage(name: 'drush pepsico:generate-news', description: 'Create any missing demo news articles.')]
  public function generateNews(): void {
    $result = $this->generator->generate();
    $this->io()->success(sprintf(
      'Created %d article(s), skipped %d existing.',
      $result['created'],
      $result['skipped'],
    ));
  }

}
