<?php

declare(strict_types=1);

namespace Drupal\Tests\pepsico_api\Functional;

use Drupal\Component\Utility\Crypt;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\pepsico_api\Plugin\rest\resource\NewsResource;
use Drupal\pepsico_news\Service\NewsArticleGenerator;
use Drupal\simple_oauth\Service\KeyGenerator;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests OAuth2 protection and output of the /api/news endpoint over HTTP.
 *
 * The test generates its own throwaway key pair and OAuth client, so it never
 * reads the site's real keys or the demo client's secret.
 */
#[CoversClass(NewsResource::class)]
#[Group('pepsico_api')]
#[RunTestsInSeparateProcesses]
final class NewsApiOAuthTest extends BrowserTestBase {

  private const CLIENT_ID = 'news_test_client';
  private const ENDPOINT = '/api/news';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_page_cache',
    'page_cache',
    'pepsico_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Temporary directory holding the throwaway key pair.
   */
  private string $keyDirectory;

  /**
   * The client secret generated for this test run.
   */
  private string $clientSecret;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createNewsArticleBundle();
    $this->createKeyPair();
    $this->createClient();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->keyDirectory) && is_dir($this->keyDirectory)) {
      foreach (glob($this->keyDirectory . '/*.key') as $file) {
        unlink($file);
      }
      rmdir($this->keyDirectory);
    }
    parent::tearDown();
  }

  /**
   * Covers anonymous, invalid-token and valid-token access and the output.
   */
  public function testTokenAccessAndOutput(): void {
    $featured = $this->createArticle('Featured story', '2026-03-01', TRUE);
    $regular = $this->createArticle('Regular story', '2026-02-01', FALSE);
    $unpublished = $this->createArticle('Unpublished story', '2026-04-01', TRUE, NodeInterface::NOT_PUBLISHED);

    // No token: the anonymous user lacks the REST permission.
    $this->assertSame(403, $this->getNews()->getStatusCode());

    // A malformed token is rejected by the oauth2 provider.
    $this->assertSame(401, $this->getNews(token: 'not-a-real-token')->getStatusCode());

    // A valid token returns exactly the published articles, newest first.
    $token = $this->requestToken();
    $response = $this->getNews(token: $token);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
    $this->assertSame([
      'data' => [
        $this->expectedItem($featured),
        $this->expectedItem($regular),
      ],
      'count' => 2,
    ], $this->decode($response));
    $this->assertStringNotContainsString('Unpublished story', (string) $response->getBody());
    $this->assertNotContains((int) $unpublished->id(), array_column($this->decode($response)['data'], 'id'));

    // A second token request is served from the dynamic page cache.
    $this->assertSame('HIT', $this->getNews(token: $token)->getHeaderLine('X-Drupal-Dynamic-Cache'));

    // Cache leak check: the cached 200 must never reach an anonymous request,
    // and a cached anonymous 403 must never reach a token request.
    $this->assertSame(403, $this->getNews()->getStatusCode());
    $this->assertSame(403, $this->getNews()->getStatusCode());
    $this->assertSame(200, $this->getNews(token: $token)->getStatusCode());

    // Only JSON is configured for the resource.
    $this->assertSame(406, $this->getNews(['_format' => 'xml'], $token)->getStatusCode());

    // The featured filter is a strict boolean.
    $featured_only = $this->getNews([NewsResource::FEATURED_PARAMETER => '1'], $token);
    $this->assertSame(200, $featured_only->getStatusCode());
    $this->assertSame([(int) $featured->id()], array_column($this->decode($featured_only)['data'], 'id'));
    $this->assertSame(400, $this->getNews([NewsResource::FEATURED_PARAMETER => 'true'], $token)->getStatusCode());
  }

  /**
   * A cookie session is refused, even for an administrator.
   */
  public function testCookieSessionIsRefused(): void {
    $this->createArticle('Published story', '2026-03-01', FALSE);
    $admin = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    $response = $this->getNews(options: [RequestOptions::COOKIES => $this->getSessionCookies()]);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertStringContainsString('authentication method is not allowed', (string) $response->getBody());
  }

  /**
   * Creates the News Article bundle and the fields the API exposes.
   */
  private function createNewsArticleBundle(): void {
    NodeType::create([
      'type' => NewsArticleGenerator::BUNDLE,
      'name' => 'News Article',
    ])->save();

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
  }

  /**
   * Writes a throwaway key pair to a temporary directory and configures it.
   */
  private function createKeyPair(): void {
    $this->keyDirectory = sys_get_temp_dir() . '/pepsico_api_test_keys_' . $this->randomMachineName();
    $this->assertStringStartsNotWith(dirname(DRUPAL_ROOT), $this->keyDirectory, 'Test keys must live outside the project.');
    mkdir($this->keyDirectory, 0700);

    foreach (KeyGenerator::generateKeys() as $name => $key) {
      $path = $this->keyDirectory . '/' . $name . '.key';
      file_put_contents($path, $key);
      chmod($path, 0600);
    }

    $this->config('simple_oauth.settings')
      ->set('public_key', $this->keyDirectory . '/public.key')
      ->set('private_key', $this->keyDirectory . '/private.key')
      ->save();
  }

  /**
   * Creates a client_credentials consumer acting as an api_consumer account.
   */
  private function createClient(): void {
    $user = User::create([
      'name' => 'api_client_test',
      'status' => 1,
      'roles' => ['api_consumer'],
    ]);
    $user->save();

    $this->clientSecret = Crypt::randomBytesBase64(32);
    Consumer::create([
      'client_id' => self::CLIENT_ID,
      'label' => 'Test client',
      'secret' => $this->clientSecret,
      'confidential' => TRUE,
      'third_party' => FALSE,
      'grant_types' => ['client_credentials'],
      'scopes' => ['news_read'],
      'user_id' => $user->id(),
      'access_token_expiration' => 300,
    ])->save();
  }

  /**
   * Obtains an access token with the client_credentials grant.
   */
  private function requestToken(): string {
    $response = $this->getHttpClient()->request('POST', Url::fromRoute('oauth2_token.token')->setAbsolute()->toString(), [
      RequestOptions::FORM_PARAMS => [
        'grant_type' => 'client_credentials',
        'client_id' => self::CLIENT_ID,
        'client_secret' => $this->clientSecret,
        'scope' => 'news_read',
      ],
      RequestOptions::HTTP_ERRORS => FALSE,
    ]);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $token = $this->decode($response)['access_token'] ?? '';
    $this->assertNotSame('', $token);
    return $token;
  }

  /**
   * Sends a GET request to the news endpoint.
   */
  private function getNews(array $query = [], ?string $token = NULL, array $options = []): ResponseInterface {
    $url = Url::fromUserInput(self::ENDPOINT, [
      'query' => $query + ['_format' => 'json'],
      'absolute' => TRUE,
    ])->toString();
    if ($token !== NULL) {
      $options[RequestOptions::HEADERS]['Authorization'] = 'Bearer ' . $token;
    }
    return $this->getHttpClient()->request('GET', $url, $options + [RequestOptions::HTTP_ERRORS => FALSE]);
  }

  /**
   * Builds the API item expected for an article.
   */
  private function expectedItem(NodeInterface $node): array {
    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'summary' => $node->get(NewsArticleGenerator::FIELD_SUMMARY)->value,
      'publish_date' => $node->get(NewsArticleGenerator::FIELD_PUBLISH_DATE)->value,
      'featured' => (bool) $node->get(NewsArticleGenerator::FIELD_FEATURED)->value,
      'url' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
  }

  /**
   * Creates and saves a News Article node.
   */
  private function createArticle(string $title, string $date, bool $featured, int $status = NodeInterface::PUBLISHED): NodeInterface {
    $node = Node::create([
      'type' => NewsArticleGenerator::BUNDLE,
      'title' => $title,
      'status' => $status,
      NewsArticleGenerator::FIELD_SUMMARY => $title . ' summary',
      NewsArticleGenerator::FIELD_PUBLISH_DATE => $date,
      NewsArticleGenerator::FIELD_FEATURED => $featured ? 1 : 0,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Decodes a JSON response body.
   */
  private function decode(ResponseInterface $response): array {
    return json_decode((string) $response->getBody(), TRUE, flags: JSON_THROW_ON_ERROR);
  }

}
