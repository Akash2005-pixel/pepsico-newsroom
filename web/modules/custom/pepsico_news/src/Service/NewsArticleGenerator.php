<?php

declare(strict_types=1);

namespace Drupal\pepsico_news\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Creates demo News Article nodes in an idempotent way.
 *
 * All articles are fictional demo content and must never be presented as
 * real corporate announcements.
 */
final class NewsArticleGenerator {

  public const BUNDLE = 'news_article';
  public const FIELD_SUMMARY = 'field_news_summary';
  public const FIELD_PUBLISH_DATE = 'field_news_publish_date';
  public const FIELD_FEATURED = 'field_news_featured';
  public const FIELD_BODY = 'field_news_body';

  private const TEXT_FORMAT = 'basic_html';
  private const DEMO_NOTICE = '<p><em>Demo content created for a Drupal portfolio project. This is a fictional article and not an official announcement.</em></p>';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.channel.pepsico_news')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Creates any missing demo articles.
   *
   * @return array{created: int, skipped: int}
   *   Counts of created and already-existing articles.
   */
  public function generate(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $now = $this->time->getRequestTime();
    $created = 0;
    $skipped = 0;

    foreach ($this->getDefinitions() as $definition) {
      $existing = $storage->loadByProperties([
        'type' => self::BUNDLE,
        'title' => $definition['title'],
      ]);
      if ($existing) {
        $skipped++;
        continue;
      }

      $timestamp = $now - ($definition['days_ago'] * 86400);
      $storage->create([
        'type' => self::BUNDLE,
        'title' => $definition['title'],
        'uid' => 1,
        'status' => 1,
        'created' => $timestamp,
        self::FIELD_SUMMARY => $definition['summary'],
        self::FIELD_PUBLISH_DATE => gmdate('Y-m-d', $timestamp),
        self::FIELD_FEATURED => $definition['featured'] ? 1 : 0,
        self::FIELD_BODY => [
          'value' => $definition['body'] . self::DEMO_NOTICE,
          'format' => self::TEXT_FORMAT,
        ],
      ])->save();
      $created++;
    }

    $this->logger->info('Generated demo news: @created created, @skipped skipped.', [
      '@created' => $created,
      '@skipped' => $skipped,
    ]);

    return ['created' => $created, 'skipped' => $skipped];
  }

  /**
   * Demo article definitions.
   *
   * @return list<array{title: string, summary: string, body: string, days_ago: int, featured: bool}>
   *   Article definitions, newest first.
   */
  private function getDefinitions(): array {
    return [
      [
        'title' => 'Pepsi Launches Limited Summer Flavor Collection',
        'summary' => 'Three new seasonal flavors arrive in stores this month, with recyclable packaging and a campaign built around live music.',
        'body' => '<p>The limited collection brings three bold seasonal flavors to shelves, backed by a summer campaign celebrating music and community.</p><p>Every bottle uses recycled material, continuing the brand\'s packaging goals.</p>',
        'days_ago' => 2,
        'featured' => TRUE,
      ],
      [
        'title' => 'Lay\'s Expands Local Potato Sourcing Program',
        'summary' => 'More than a hundred new partner farms join the program, strengthening regional supply and reducing transport distance.',
        'body' => '<p>The expanded program connects local growers directly with production sites, supporting regional agriculture and shorter supply chains.</p><p>Farmers receive agronomy support focused on water efficiency.</p>',
        'days_ago' => 6,
        'featured' => FALSE,
      ],
      [
        'title' => 'Gatorade Opens Performance Lab for Young Athletes',
        'summary' => 'A new hydration science lab will support school sports programs with research, coaching resources and free workshops.',
        'body' => '<p>The lab brings sports scientists and coaches together to share practical hydration and recovery guidance with schools.</p><p>Workshops open to student athletes begin next quarter.</p>',
        'days_ago' => 11,
        'featured' => TRUE,
      ],
      [
        'title' => 'Quaker Introduces High-Fiber Breakfast Range',
        'summary' => 'A new oat-based range focuses on fiber, simple ingredients and quick preparation for busy mornings.',
        'body' => '<p>The range offers ready-in-minutes options built around whole-grain oats and short ingredient lists.</p><p>Availability begins with select retail partners.</p>',
        'days_ago' => 17,
        'featured' => FALSE,
      ],
      [
        'title' => 'Tropicana Pilots Water-Saving Orchard Practices',
        'summary' => 'A pilot across partner orchards tests precision irrigation to cut water use while protecting harvest quality.',
        'body' => '<p>Sensors and precision irrigation help growers apply water only where and when it is needed.</p><p>Results from the pilot will guide wider rollout across the supply base.</p>',
        'days_ago' => 24,
        'featured' => TRUE,
      ],
      [
        'title' => 'Community Grants Program Opens New Application Round',
        'summary' => 'Local organizations can now apply for funding for food access, youth skills and neighborhood sustainability projects.',
        'body' => '<p>The new round prioritizes projects that improve food access and create learning opportunities for young people.</p><p>Applications are open for six weeks.</p>',
        'days_ago' => 33,
        'featured' => FALSE,
      ],
      [
        'title' => 'Sustainability Report Highlights Packaging Progress',
        'summary' => 'The annual report outlines reductions in virgin plastic use and growth in recycled-content packaging across key markets.',
        'body' => '<p>The report details progress on packaging goals, renewable energy at manufacturing sites and responsible agriculture.</p><p>It also sets out priorities for the coming year.</p>',
        'days_ago' => 45,
        'featured' => TRUE,
      ],
      [
        'title' => 'Digital Innovation Hub Supports Faster Product Launches',
        'summary' => 'A new cross-functional hub brings data, design and engineering together to shorten the path from idea to shelf.',
        'body' => '<p>The hub unites teams working on analytics, consumer insight and digital experiences.</p><p>Early projects focus on faster pilots and smarter demand planning.</p>',
        'days_ago' => 60,
        'featured' => FALSE,
      ],
    ];
  }

}
