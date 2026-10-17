# PepsiCo Newsroom (Drupal 11.4 demo for an interview)

Fictional-content newsroom demo. Never use real PepsiCo announcements, logos or packaging.

## Environment
- Windows + WSL2 Ubuntu, Docker Desktop with WSL integration (free for personal use),
  DDEV. Run everything through `ddev`
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
- Released v0.3.1 (full News Article page)
- Task 2: `pepsico_news_stats` at `/admin/news-stats` (permission `view pepsico news stats`,
  published total and featured counts, cache tag `node_list:news_article`, link under Reports)
- Task 3: `pepsico_feedback`, public form at `/feedback` storing `pepsico_feedback_submission`
  entities; listing at `/admin/content/feedback` (permission
  `view pepsico feedback submissions`, administrators only); comments max length in
  `pepsico_feedback.settings`; `FeedbackSubmittedEvent` + logging subscriber; kernel test.
- Theme overrides `status-messages.html.twig` to add `messages--{type}` classes.
- Task 6: `pepsico_api`, `GET /api/news` (REST plugin `pepsico_api_news`, JSON only),
  Simple OAuth 6.1 `client_credentials`. Resource config allows only the `oauth2` provider
  (cookie sessions get 403). Role `api_consumer` holds exactly `restful get pepsico_api_news`;
  scope `news_read` maps to it. Published articles only, max 50, six fields, `?featured=0|1`
  (anything else 400), cache tag `node_list:news_article`. Demo client `news_demo` via
  `ddev drush pepsico:api-client` (`--rotate-secret`); the user runs it, never Claude, so
  the secret stays out of transcripts. Keys in `<project>/keys/` (gitignored), generated per
  environment with `simple-oauth:generate-keys`. Kernel + functional tests. README has curl.
- Gotcha: the `restful get` permission exists only once the REST plugin and its resource
  config exist, and `Role` silently drops unknown permissions. Config that grants it must
  depend on the resource config; verify with `config:get user.role.<role> permissions`.

## Testing
- Tests never use the site database: always SQLite in the container's `/tmp`.
- Kernel tests: `ddev exec 'SIMPLETEST_DB=sqlite://localhost//tmp/<module>_kernel.sqlite vendor/bin/phpunit -c web/core web/modules/custom/<module>'`
- `ddev drush entity:updates` no longer exists in Drush 13; use `ddev drush updatedb:status`.
- `pepsico_api` kernel test (SQLite in `/tmp`, not the site database):
  `ddev exec 'SIMPLETEST_DB=sqlite://localhost//tmp/pepsico_api_kernel.sqlite vendor/bin/phpunit -c web/core web/modules/custom/pepsico_api/tests/src/Kernel'`
- `pepsico_api` functional test (needs `web/sites/simpletest/browser_output`, gitignored):
  `ddev exec 'SIMPLETEST_BASE_URL=http://web SIMPLETEST_DB=sqlite://localhost//tmp/pepsico_api_functional.sqlite BROWSERTEST_OUTPUT_DIRECTORY=/var/www/html/web/sites/simpletest/browser_output vendor/bin/phpunit -c web/core web/modules/custom/pepsico_api/tests/src/Functional'`
  It creates its own throwaway keys and client. Deprecations come from Simple OAuth/Consumers.

## Remaining work (priority order)
1. Images (AI-generated, in `assets-source/`), image styles, hero background.
2. Tests (PHPUnit), phpcs/phpstan clean, README, demo script.
3. Stretch: Drupal AI (local provider only), Canvas.
