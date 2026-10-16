# PepsiCo Newsroom

A PepsiCo-themed corporate newsroom built on Drupal 11.4 with a custom theme,
custom modules, a REST API secured with OAuth2, and a feedback system.

## Stack
- Drupal 11.4.x, PHP 8.3, MySQL (via DDEV)
- Custom theme: `pepsico_newsroom` (Single Directory Components)
- Custom modules: `pepsico_core`, `pepsico_news`, `pepsico_news_stats`,
  `pepsico_feedback`, `pepsico_api`

## Local setup (new machine)
```bash
git clone git@github.com:Akash2005-pixel/pepsico-newsroom.git
cd pepsico-newsroom
ddev start
ddev composer install
ddev drush site:install --existing-config -y
ddev launch
```

## Workflow
- `main`: stable, always demoable. `develop`: integration.
- Feature branches per task, merged with `--no-ff`.
- Conventional commits (`feat`, `fix`, `chore`, `docs`, `test`).
- Configuration lives in `config/sync` (`ddev drush cex -y` / `cim -y`).
