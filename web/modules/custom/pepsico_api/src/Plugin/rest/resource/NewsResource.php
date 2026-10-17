<?php

declare(strict_types=1);

namespace Drupal\pepsico_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pepsico_api\Service\NewsApiProvider;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Exposes published News Articles as JSON at /api/news.
 *
 * Only GET is available because the plugin implements only get(). Access is
 * controlled by the generated "restful get pepsico_api_news" permission, and
 * the accepted authentication providers are set in the REST resource config.
 */
#[RestResource(
  id: 'pepsico_api_news',
  label: new TranslatableMarkup('PepsiCo news articles'),
  uri_paths: [
    'canonical' => '/api/news',
  ],
)]
final class NewsResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * Query parameter that restricts the list to featured articles.
   */
  public const FEATURED_PARAMETER = 'featured';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    private readonly NewsApiProvider $newsProvider,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get(NewsApiProvider::class),
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The published News Articles with their cacheability metadata.
   *
   * @throws \Drupal\Core\Http\Exception\CacheableBadRequestHttpException
   *   When the featured parameter is not "0" or "1".
   */
  public function get(Request $request): ResourceResponse {
    // The response varies by the featured filter and by the consumer's
    // permissions; the provider adds the entity tags and URL metadata.
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['url.query_args:' . self::FEATURED_PARAMETER, 'user.permissions']);

    $articles = $this->newsProvider->getArticles($this->isFeaturedOnly($request, $cacheability), $cacheability);

    $response = new ResourceResponse([
      'data' => $articles,
      'count' => count($articles),
    ]);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Reads the featured parameter as a strict boolean.
   *
   * Only "1" and "0" are accepted; a missing parameter means all articles.
   */
  private function isFeaturedOnly(Request $request, CacheableMetadata $cacheability): bool {
    $value = $request->query->get(self::FEATURED_PARAMETER);
    return match ($value) {
      NULL, '0' => FALSE,
      '1' => TRUE,
      default => throw new CacheableBadRequestHttpException($cacheability, 'The "featured" query parameter must be 0 or 1.'),
    };
  }

}
