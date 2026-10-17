<?php

declare(strict_types=1);

namespace Drupal\Tests\pepsico_news_stats\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\pepsico_news\Service\NewsArticleGenerator;
use Drupal\pepsico_news_stats\Service\NewsStatsCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the News Article statistics calculator.
 */
#[CoversClass(NewsStatsCalculator::class)]
#[Group('pepsico_news_stats')]
#[RunTestsInSeparateProcesses]
final class NewsStatsCalculatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'field',
    'node',
    'pepsico_news',
    'pepsico_news_stats',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    NodeType::create([
      'type' => NewsArticleGenerator::BUNDLE,
      'name' => 'News Article',
    ])->save();
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();

    FieldStorageConfig::create([
      'field_name' => NewsArticleGenerator::FIELD_FEATURED,
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => NewsArticleGenerator::FIELD_FEATURED,
      'entity_type' => 'node',
      'bundle' => NewsArticleGenerator::BUNDLE,
    ])->save();
  }

  /**
   * Counts only published News Articles, split by the featured flag.
   */
  public function testGetStats(): void {
    $calculator = $this->container->get(NewsStatsCalculator::class);
    $this->assertSame(['total' => 0, 'featured' => 0], $calculator->getStats());

    $this->createArticle(featured: TRUE);
    $this->createArticle(featured: TRUE);
    $this->createArticle(featured: FALSE);
    // Unpublished articles and other bundles must be ignored.
    $this->createArticle(featured: TRUE, status: NodeInterface::NOT_PUBLISHED);
    Node::create(['type' => 'page', 'title' => 'About', 'status' => 1])->save();

    $this->assertSame(['total' => 3, 'featured' => 2], $calculator->getStats());
  }

  /**
   * Creates a News Article node.
   */
  private function createArticle(bool $featured, int $status = NodeInterface::PUBLISHED): void {
    Node::create([
      'type' => NewsArticleGenerator::BUNDLE,
      'title' => $this->randomString(),
      'status' => $status,
      NewsArticleGenerator::FIELD_FEATURED => $featured ? 1 : 0,
    ])->save();
  }

}
