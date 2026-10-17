<?php

declare(strict_types=1);

namespace Drupal\pepsico_api\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\pepsico_news\Service\NewsArticleGenerator;

/**
 * Builds the public representation of published News Articles for the API.
 *
 * Only a fixed whitelist of fields is exposed. Every entity and URL that
 * contributes to the result is added to the given cacheability metadata, so
 * the caller can attach it to a cacheable response.
 */
final class NewsApiProvider {

  /**
   * Cache tag invalidated whenever any News Article is saved or deleted.
   */
  public const CACHE_TAG = 'node_list:' . NewsArticleGenerator::BUNDLE;

  /**
   * Maximum number of articles returned in one response.
   */
  public const MAX_ITEMS = 50;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns published News Articles, newest first.
   *
   * @param bool $featuredOnly
   *   Whether to return only featured articles.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cache tags and contexts the result depends on.
   *
   * @return list<array{id: int, title: string, summary: ?string, publish_date: ?string, featured: bool, url: string}>
   *   The normalized articles.
   */
  public function getArticles(bool $featuredOnly, CacheableMetadata $cacheability): array {
    // The list changes whenever any News Article changes, and the
    // access-checked query depends on the node grants of the current user.
    $cacheability->addCacheTags([self::CACHE_TAG]);
    $cacheability->addCacheContexts(['user.node_grants:view']);

    $storage = $this->entityTypeManager->getStorage('node');
    // The explicit status condition guarantees unpublished articles are never
    // returned, independently of the access check and of the consumer's role.
    $query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', NewsArticleGenerator::BUNDLE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort(NewsArticleGenerator::FIELD_PUBLISH_DATE, 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, self::MAX_ITEMS);
    if ($featuredOnly) {
      $query->condition(NewsArticleGenerator::FIELD_FEATURED, 1);
    }

    $articles = [];
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($storage->loadMultiple($query->execute()) as $node) {
      $articles[] = $this->normalize($node, $cacheability);
    }
    return $articles;
  }

  /**
   * Converts a News Article into its API representation.
   *
   * @return array{id: int, title: string, summary: ?string, publish_date: ?string, featured: bool, url: string}
   *   The whitelisted fields of the article.
   */
  private function normalize(NodeInterface $node, CacheableMetadata $cacheability): array {
    $cacheability->addCacheableDependency($node);

    // Collect the URL's own metadata (for example the url.site context of an
    // absolute URL); a REST response that drops it fails with a "leaked
    // metadata" exception.
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString(TRUE);
    $cacheability->addCacheableDependency($url);

    return [
      'id' => (int) $node->id(),
      'title' => (string) $node->label(),
      'summary' => $node->get(NewsArticleGenerator::FIELD_SUMMARY)->value,
      'publish_date' => $node->get(NewsArticleGenerator::FIELD_PUBLISH_DATE)->value,
      'featured' => (bool) $node->get(NewsArticleGenerator::FIELD_FEATURED)->value,
      'url' => $url->getGeneratedUrl(),
    ];
  }

}
