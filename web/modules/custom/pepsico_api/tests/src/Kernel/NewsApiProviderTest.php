<?php

declare(strict_types=1);

namespace Drupal\Tests\pepsico_api\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\pepsico_api\Service\NewsApiProvider;
use Drupal\pepsico_news\Service\NewsArticleGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the News Article data exposed by the news API.
 */
#[CoversClass(NewsApiProvider::class)]
#[Group('pepsico_api')]
#[RunTestsInSeparateProcesses]
final class NewsApiProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'datetime',
    'field',
    'node',
    'pepsico_api',
    'pepsico_news',
    'system',
    'text',
    'user',
  ];

  /**
   * The provider under test.
   */
  private NewsApiProvider $provider;

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

    $fields = [
      NewsArticleGenerator::FIELD_SUMMARY => ['type' => 'string'],
      NewsArticleGenerator::FIELD_PUBLISH_DATE => [
        'type' => 'datetime',
        'settings' => ['datetime_type' => 'date'],
      ],
      NewsArticleGenerator::FIELD_FEATURED => ['type' => 'boolean'],
    ];
    foreach ($fields as $field_name => $storage) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
      ] + $storage)->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => NewsArticleGenerator::BUNDLE,
      ])->save();
    }

    $this->provider = $this->container->get(NewsApiProvider::class);
  }

  /**
   * Returns only published News Articles with exactly six whitelisted keys.
   */
  public function testPublishedArticlesAndFieldShape(): void {
    $featured = $this->createArticle('2026-03-01', featured: TRUE, summary: 'Featured summary');
    $regular = $this->createArticle('2026-02-01');
    // Newest and featured, so it would come first if it leaked.
    $unpublished = $this->createArticle('2026-04-01', featured: TRUE, status: NodeInterface::NOT_PUBLISHED);
    Node::create(['type' => 'page', 'title' => 'About', 'status' => 1])->save();

    $articles = $this->provider->getArticles(FALSE, new CacheableMetadata());

    $this->assertSame([(int) $featured->id(), (int) $regular->id()], array_column($articles, 'id'));
    $this->assertNotContains((int) $unpublished->id(), array_column($articles, 'id'));

    $url = $featured->toUrl('canonical', ['absolute' => TRUE])->toString();
    $this->assertStringEndsWith('/node/' . $featured->id(), $url);
    $this->assertSame([
      'id' => (int) $featured->id(),
      'title' => $featured->label(),
      'summary' => 'Featured summary',
      'publish_date' => '2026-03-01',
      'featured' => TRUE,
      'url' => $url,
    ], $articles[0]);
    foreach ($articles as $article) {
      $this->assertSame(['id', 'title', 'summary', 'publish_date', 'featured', 'url'], array_keys($article));
    }
    $this->assertFalse($articles[1]['featured']);
  }

  /**
   * The featured filter returns featured published articles only.
   */
  public function testFeaturedFilter(): void {
    $featured = $this->createArticle('2026-03-01', featured: TRUE);
    $this->createArticle('2026-03-02');
    $this->createArticle('2026-03-03', featured: TRUE, status: NodeInterface::NOT_PUBLISHED);

    $articles = $this->provider->getArticles(TRUE, new CacheableMetadata());

    $this->assertSame([(int) $featured->id()], array_column($articles, 'id'));
  }

  /**
   * Articles are sorted by publish date, newest first, then by newest ID.
   */
  public function testOrdering(): void {
    $january = $this->createArticle('2026-01-10');
    $march_first = $this->createArticle('2026-03-05');
    $march_second = $this->createArticle('2026-03-05');
    $december = $this->createArticle('2025-12-31');

    $articles = $this->provider->getArticles(FALSE, new CacheableMetadata());

    $this->assertSame([
      (int) $march_second->id(),
      (int) $march_first->id(),
      (int) $january->id(),
      (int) $december->id(),
    ], array_column($articles, 'id'));
  }

  /**
   * At most MAX_ITEMS articles are returned, and they are the newest ones.
   */
  public function testMaxItems(): void {
    $total = NewsApiProvider::MAX_ITEMS + 5;
    $ids = [];
    for ($day = 1; $day <= $total; $day++) {
      $date = (new \DateTimeImmutable('2026-01-01'))->modify('+' . ($day - 1) . ' days');
      $ids[] = (int) $this->createArticle($date->format('Y-m-d'))->id();
    }

    $articles = $this->provider->getArticles(FALSE, new CacheableMetadata());

    $this->assertCount(NewsApiProvider::MAX_ITEMS, $articles);
    $expected = array_slice(array_reverse($ids), 0, NewsApiProvider::MAX_ITEMS);
    $this->assertSame($expected, array_column($articles, 'id'));
  }

  /**
   * The result carries the list tag, entity tags and the expected contexts.
   */
  public function testCacheability(): void {
    // Even an empty list must be invalidated when the first article appears.
    $empty = new CacheableMetadata();
    $this->assertSame([], $this->provider->getArticles(FALSE, $empty));
    $this->assertSame([NewsApiProvider::CACHE_TAG], $empty->getCacheTags());
    $this->assertEqualsCanonicalizing(['user.node_grants:view'], $empty->getCacheContexts());

    $first = $this->createArticle('2026-03-01');
    $second = $this->createArticle('2026-02-01');
    $this->createArticle('2026-04-01', status: NodeInterface::NOT_PUBLISHED);

    $cacheability = new CacheableMetadata();
    $this->provider->getArticles(FALSE, $cacheability);

    $this->assertEqualsCanonicalizing([
      NewsApiProvider::CACHE_TAG,
      'node:' . $first->id(),
      'node:' . $second->id(),
    ], $cacheability->getCacheTags());
    $this->assertEqualsCanonicalizing(['url.site', 'user.node_grants:view'], $cacheability->getCacheContexts());
    $this->assertSame(Cache::PERMANENT, $cacheability->getCacheMaxAge());
  }

  /**
   * Creates and saves a News Article node.
   */
  private function createArticle(string $date, bool $featured = FALSE, int $status = NodeInterface::PUBLISHED, ?string $summary = NULL): NodeInterface {
    $node = Node::create([
      'type' => NewsArticleGenerator::BUNDLE,
      'title' => $this->randomMachineName(),
      'status' => $status,
      NewsArticleGenerator::FIELD_SUMMARY => $summary ?? $this->randomMachineName(),
      NewsArticleGenerator::FIELD_PUBLISH_DATE => $date,
      NewsArticleGenerator::FIELD_FEATURED => $featured ? 1 : 0,
    ]);
    $node->save();
    return $node;
  }

}
