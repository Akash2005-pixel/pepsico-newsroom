# PepsiCo Newsroom (Drupal 11.4 demo for an interview)

Fictional-content newsroom demo. Never use real PepsiCo announcements, logos or packaging.

## Environment
- Windows + WSL2 Ubuntu, Docker Engine, DDEV. Run everything through `ddev`
  (`ddev drush ...`, `ddev composer ...`). Zero-cost toolchain only.
- Site: https://pepsico-newsroom.ddev.site. Drupal core must stay at 11.4.10 or later.

## Git rules
- Branches: `main` (always demoable), `develop` (integration), one `feature/<name>` per task.
- Merge features into `develop` with `--no-ff`. Tag releases on `main` (v0.x.0).
- Conventional commits: `feat(scope):`, `fix(scope):`, `chore(scope):`, `docs:`, `test:`.
- Small commits, one logical change each. Never force-push `main` or `develop`.
- Never commit secrets (OAuth keys, .env, settings.ddev.php).

## Config management
- All configuration lives in `config/sync`. After changing config: `ddev drush cex -y`.
- Verify reproducibility with `ddev drush cim -y` (expect "no changes").

## Code conventions
- Custom modules in `web/modules/custom/`, theme in `web/themes/custom/pepsico_newsroom/`.
- Prefix: `pepsico_` for modules and theme; `field_news_*` for News Article fields.
- Modules: `pepsico_core`, `pepsico_news`, plus the ones still to build.
- PHP 8.3, `declare(strict_types=1)`, constructor dependency injection and autowiring,
  never `\Drupal::service()` in classes. Drupal coding standards (phpcs).
- Field and bundle names live in constants (see `NewsArticleGenerator`).
- Theme: Single Directory Components under `components/`, design tokens in `css/tokens.css`,
  no hard-coded colors outside the tokens file.
- Drush 13: commands use attributes and live in `src/Drush/Commands/`.
- `drush config:set` cannot take negative numbers as values: use positive weights.

## Content model
- Bundle `news_article`: `title`, `field_news_summary` (plain), `field_news_publish_date`
  (date only), `field_news_featured` (boolean), `field_news_body` (formatted long text).
- Sample data: `ddev drush pepsico:generate-news` (idempotent, 8 fictional articles).

## Done so far
- Task 1 (content type, sample data, /news view with exposed filters and sorting)
- Task 4 mostly done (custom theme, news-card component, hero block, full article page)
- Front page is /news

## Remaining work (priority order)
1. Merge `feature/news-article-page` into `develop`, tag.
2. Task 2: `pepsico_news_stats`, route `/admin/news-stats`, controller + service,
   permission, cache tags, shows total and featured counts.
3. Task 3: `pepsico_feedback`, form at `/feedback` (Name, Email, Comments), required and
   email validation, success message, custom entity to store submissions.
4. Task 6: `pepsico_api`, REST resource exposing news data, protected with OAuth2
   (Simple OAuth).
5. Images (AI-generated, in `assets-source/`), image styles, hero background.
6. Tests (PHPUnit), phpcs/phpstan clean, README, demo script.
7. Stretch: event subscriber, Drupal AI (local provider only), Canvas.
