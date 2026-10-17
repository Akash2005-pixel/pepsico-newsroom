# Interview demo script

A 10 to 15 minute walkthrough of the PepsiCo Newsroom in assessment order.
All content is fictional demo content.

## Before the interview (5 minutes, not part of the demo)

```bash
ddev start
ddev drush cim -y            # expect: no changes
ddev drush pepsico:generate-news   # expect: "Created 0 article(s), skipped 8 existing."
ddev drush uli               # one-time admin login link
```

- Open https://pepsico-newsroom.ddev.site in one browser window (anonymous)
  and the `uli` link in a private window (admin).
- Have a terminal ready with the API client secret in a password manager
  (see README, "Demo client"). Never paste it into a shared screen or chat.
- Open the project in the editor.

| # | Task | Time |
|---|------|------|
| 1 | News Article, sample data, /news | 3 min |
| 2 | News statistics module | 1.5 min |
| 3 | Feedback form | 2 min |
| 4 | Theme | 2 min |
| 6 | REST API with OAuth2 | 3 min |
| 7 | Git | 1.5 min |

---

## Task 1: News Article content type, sample data, /news

**Show**
1. Admin: `/admin/structure/types/manage/news_article/fields`
   - Expect: Summary (`field_news_summary`, plain text), Publish Date
     (`field_news_publish_date`, date only), Featured (`field_news_featured`,
     boolean), Body (`field_news_body`, formatted). Title is the node title.
2. Terminal: `ddev drush pepsico:generate-news`
   - Expect: `Created 0 article(s), skipped 8 existing.` Running it again
     never creates duplicates.
3. Browser: `/news` (also the front page)
   - Expect: 8 article cards, newest first, 9 per page.
   - Exposed filters: **Search Title** (contains), **Featured**,
     **Published Between** (date range).
   - Exposed sorts: **Publish Date** or **Title**, ascending or descending.
   - Try Featured = True: 4 articles. A filter with no results shows
     "No news articles match your filters."

**Files**
- `config/sync/node.type.news_article.yml`, `config/sync/field.*.news_article.*`
- `config/sync/views.view.news.yml`
- `web/modules/custom/pepsico_news/src/Service/NewsArticleGenerator.php`
- `web/modules/custom/pepsico_news/src/Drush/Commands/NewsGeneratorCommands.php`

**Drupal concepts**
- *Entities and config management:* the content type and fields are config
  entities exported to `config/sync`, so `drush cim` rebuilds the whole site
  from Git. A fresh clone gets them with `site:install --existing-config`.
- *Services and DI:* `NewsArticleGenerator` is an autowired service. The
  Drush 13 command gets it by constructor injection (`AutowireTrait`), and
  field names live in class constants.

---

## Task 2: Custom module `pepsico_news_stats`

The module is named `pepsico_news_stats` (project prefix `pepsico_`).

**Show**
1. Admin: `/admin/news-stats` (also under Reports > News statistics)
   - Expect: a table with **Published news articles: 8** and
     **Featured news articles: 4**.
2. Anonymous window: `/admin/news-stats`
   - Expect: Access denied.
3. Optional: unpublish one article as admin, reload the report. The count
   drops at once with no cache clear.

**Files**
- `web/modules/custom/pepsico_news_stats/pepsico_news_stats.routing.yml`
- `web/modules/custom/pepsico_news_stats/pepsico_news_stats.permissions.yml`
- `web/modules/custom/pepsico_news_stats/src/Controller/NewsStatsController.php`
- `web/modules/custom/pepsico_news_stats/src/Service/NewsStatsCalculator.php`

**Drupal concepts**
- *Routing and permissions:* the route maps the path to a controller and
  requires the custom permission `view pepsico news stats`.
- *Services and DI:* the controller gets the `NewsStatsCalculator` service by
  constructor injection. The service runs entity queries for published News
  Articles.
- *Cache tags:* the render array carries `node_list:news_article`, so Drupal
  invalidates the cached page whenever a News Article is saved or deleted.

---

## Task 3: Feedback form at /feedback

**Show**
1. Anonymous: `/feedback`
   - Expect: Name, Email and Comments fields, all required, and a
     "Send feedback" button.
2. Submit with empty fields: the browser blocks it (HTML5 `required`).
   Enter a single space in each field and submit: the server rejects it with
   "Name is required." and so on, because the form trims values before
   checking.
3. Enter `not-an-email` in Email: rejected as an invalid email address.
4. Submit valid values.
   - Expect: green message "Thank you, <name>. Your feedback has been
     received." and an empty form.
5. Admin: `/admin/content/feedback`
   - Expect: the submission in the listing. Anonymous users get Access denied.
6. Admin: `/admin/reports/dblog`
   - Expect: "Feedback #<id> received." Only the id is logged, not the name
     or email address.

**Files**
- `web/modules/custom/pepsico_feedback/src/Form/FeedbackForm.php`
- `web/modules/custom/pepsico_feedback/src/Entity/FeedbackSubmission.php`
- `web/modules/custom/pepsico_feedback/src/Event/FeedbackSubmittedEvent.php`
- `web/modules/custom/pepsico_feedback/src/EventSubscriber/FeedbackSubmittedSubscriber.php`
- `web/modules/custom/pepsico_feedback/config/install/pepsico_feedback.settings.yml`
- `web/modules/custom/pepsico_feedback/tests/src/Kernel/FeedbackFormTest.php`

**Drupal concepts**
- *Forms:* Form API with `buildForm`, `validateForm` (server-side required
  and email checks with the core email validator) and `submitForm`
  (stores the entity, shows a messenger status message).
- *Entities:* each submission is a `pepsico_feedback_submission` content
  entity with its own access control handler and list builder.
- *Events:* the form dispatches `FeedbackSubmittedEvent`; a subscriber logs it.
  Other features can react to a submission without changing the form.
- *Config management:* the Comments max length (2000) lives in
  `pepsico_feedback.settings`, not in code.

Kernel test (SQLite in `/tmp`, never the site database):
```bash
ddev exec 'SIMPLETEST_DB=sqlite://localhost//tmp/pepsico_feedback_kernel.sqlite vendor/bin/phpunit -c web/core web/modules/custom/pepsico_feedback'
```

---

## Task 4: Theme customization

**Show**
1. `/news`
   - Expect: the hero banner ("The PepsiCo Newsroom", "Browse latest news"),
     then cards with publish date, title, summary and a **Featured** badge on
     featured articles.
2. Open one featured article.
   - Expect: Featured badge, publish date, summary, formatted body and a
     "Back to all news" link.
3. Status messages (from Task 3) use the theme's colors.

**Files**
- `web/themes/custom/pepsico_newsroom/components/news-card/` (`news-card.component.yml`, `.twig`, `.css`)
- `web/themes/custom/pepsico_newsroom/templates/node/node--news-article--teaser.html.twig`
- `web/themes/custom/pepsico_newsroom/templates/node/node--news-article--full.html.twig`
- `web/themes/custom/pepsico_newsroom/css/tokens.css`
- `web/modules/custom/pepsico_core/src/Plugin/Block/NewsroomHeroBlock.php`

**Drupal concepts**
- *Single Directory Components:* the news card is a component with a props
  schema, Twig and CSS in one folder. The teaser template only maps node
  fields to props.
- *Plugins:* the hero is a Block plugin declared with a PHP attribute,
  with its own settings form, and renders the `pepsico_core:hero` component.
- *Design tokens:* all colors are CSS custom properties in `tokens.css`; no
  other file hard-codes colors.

---

## Task 6: Custom REST endpoint with OAuth2

Run the curl steps from the README ("Calling the API with curl"). Short form:

```bash
CA="$(mkcert -CAROOT)/rootCA.pem"
API=https://pepsico-newsroom.ddev.site
curl -s -o /dev/null -w '%{http_code}\n' --cacert "$CA" "$API/api/news"   # 403
read -rs SECRET   # paste the secret, press Enter
TOKEN=$(curl -s --cacert "$CA" -X POST "$API/oauth/token" \
  -d grant_type=client_credentials -d client_id=news_demo \
  --data-urlencode "client_secret=$SECRET" -d scope=news_read \
  | python3 -c 'import sys, json; print(json.load(sys.stdin)["access_token"])')
curl -s --cacert "$CA" -H "Authorization: Bearer $TOKEN" "$API/api/news" | python3 -m json.tool
curl -s --cacert "$CA" -H "Authorization: Bearer $TOKEN" "$API/api/news?featured=1" | python3 -m json.tool
curl -s -o /dev/null -w '%{http_code}\n' --cacert "$CA" -H "Authorization: Bearer $TOKEN" "$API/api/news?featured=true"   # 400
unset SECRET TOKEN
```

**Expected**
- No token: **403**.
- Token request: **200** with an `access_token` (valid for 300 seconds).
- With token: **200**, `{"data":[...],"count":8}`; each item has `id`, `title`,
  `summary`, `publish_date`, `featured`, `url`.
- `?featured=1`: only the 4 featured articles. `?featured=true`: **400**.
- Admin browser at `/api/news?_format=json`: **403** "The used authentication
  method is not allowed on this route", even for an administrator.

**Files**
- `web/modules/custom/pepsico_api/src/Plugin/rest/resource/NewsResource.php`
- `web/modules/custom/pepsico_api/src/Service/NewsApiProvider.php`
- `web/modules/custom/pepsico_api/config/install/rest.resource.pepsico_api_news.yml`
- `web/modules/custom/pepsico_api/config/install/user.role.api_consumer.yml`
- `web/modules/custom/pepsico_api/config/install/simple_oauth.oauth2_scope.news_read.yml`
- `web/modules/custom/pepsico_api/src/Drush/Commands/ApiClientCommands.php`

**Drupal concepts**
- *Plugins:* `NewsResource` is a REST resource plugin (`#[RestResource]`);
  only `get()` exists, so only GET is served.
- *Config management and permissions:* the resource config allows only the
  `oauth2` provider and JSON. The `news_read` scope maps to the `api_consumer`
  role, which holds exactly `restful get pepsico_api_news`.
- *Cache tags:* responses carry `node_list:news_article` and vary by the
  `featured` query argument and the user's permissions.

Tests: kernel test for `NewsApiProvider` and a functional test of the whole
OAuth flow; commands in the README, "Tests".

---

## Task 7: Git

```bash
git log --oneline --graph -25
git branch -a
git tag -n
git show --stat 08c0d541
```

**Expected**
- `main` (always demoable, tagged releases), `develop` (integration), and one
  branch per task: `feature/news-content-type`, `feature/news-stats`,
  `feature/feedback-form`, `feature/news-article-page`, `feature/news-api`,
  plus `fix/feedback-hardening`, `fix/menu-home-duplicate`,
  `refactor/theme-tokens`, `feature/readme-fresh-clone`.
- Every feature merged into `develop` with `--no-ff`, so the graph shows each
  task as its own bubble.
- Tags `v0.1.0` to `v0.6.0`, one per milestone, each with a message.
- Conventional commits with a scope, one logical change each, for example
  `feat(api): restrict news resource to oauth2 authentication`.
- Secrets are never committed: `/keys/`, `*.key`, `*.pem` and
  `settings.ddev.php` are in `.gitignore`.

**Drupal concept**
- *Config management:* all site configuration is in `config/sync`, so Git
  holds the full site, not just code. `ddev drush cim -y` reports no changes
  on a clean checkout.

---

## If asked

**Why are the OAuth keys in `keys/` and not in `web/`?**
Anything under `web/` can be served by the web server. `keys/` is next to it,
outside the web root, ignored by Git, and the generator writes the files with
mode `0600`. Every environment generates its own pair; keys are never copied
or shared. Regenerating them invalidates all tokens at once.

**Why is the client secret shown only once?**
`pepsico:api-client` generates 256 random bits, prints the secret once and
stores only a hash on the Consumer entity. Nothing is written to config,
state, logs or files. Running the command again refuses; a lost secret is
replaced with `--rotate-secret`, which also revokes the client's tokens.

**Why a role with only one permission?**
Least privilege. Tokens act as `api_client_news_demo`, which has only the
`api_consumer` role, and that role holds only `restful get pepsico_api_news`.
A leaked token can read published news for at most 300 seconds and nothing
else. The account has no password or email, so it cannot log in.

**Why are cookie sessions refused?**
The REST resource config lists only `oauth2`. If cookies were accepted, any
logged-in browser could call the API, and a cross-site request could use the
session. Refusing them keeps one clear way in: a bearer token.

**What would you add next?**
Images and image styles, more tests (functional tests for the views and
theme), phpcs and phpstan in CI.

**How did you use AI tools?**
I used Claude Code as a pair programmer in the terminal. The project rules
live in `CLAUDE.md` (environment, Git rules, coding conventions, testing
rules), so every session followed the same standards. Commits it helped write
carry a `Co-Authored-By` trailer, so the history shows it openly. I set the
order of the work and reviewed each change before committing it.
Anything that creates secrets (the OAuth keys and client) I ran in my own
terminal so the secret never went into a transcript. AI tools did not replace
the checks: config import, kernel and functional tests, and a clean-clone
install verify the result.
