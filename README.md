# PepsiCo Newsroom

A PepsiCo-themed corporate newsroom built on Drupal 11.4 with a custom theme,
custom modules, a REST API secured with OAuth2, and a feedback system.

## Stack
- Drupal 11.4.x, PHP 8.3, MySQL (via DDEV)
- Custom theme: `pepsico_newsroom` (Single Directory Components)
- Custom modules: `pepsico_core`, `pepsico_news`, `pepsico_news_stats`,
  `pepsico_feedback`, `pepsico_api`

## Fresh clone (new machine)
Verified by a clean-clone test on 2026-10-17 (Drupal 11.4.10, DDEV v1.25.4).

```bash
git clone git@github.com:Akash2005-pixel/pepsico-newsroom.git
cd pepsico-newsroom
ddev start
ddev composer install
ddev drush site:install --existing-config -y   # imports config/sync
ddev drush pepsico:generate-news               # 8 fictional sample articles
mkdir -p keys
ddev drush simple-oauth:generate-keys /var/www/html/keys
ddev drush pepsico:api-client                  # copy the secret now
ddev launch
```

- `site:install` prints the admin password once. To log in later, run
  `ddev drush uli`.
- The keys and the client secret are created per environment and never
  committed; see [News API (OAuth2)](#news-api-oauth2).
- To run a second copy next to an existing one, give it its own DDEV project
  name before `ddev start`, for example `name: pepsico-newsroom-clean` in
  `.ddev/config.local.yaml`. DDEV's generated `.ddev/.gitignore` ignores that
  file once DDEV has run in the folder.

## Workflow
- `main`: stable, always demoable. `develop`: integration.
- Feature branches per task, merged with `--no-ff`.
- Conventional commits (`feat`, `fix`, `chore`, `docs`, `test`).
- Configuration lives in `config/sync` (`ddev drush cex -y` / `cim -y`).

## News API (OAuth2)

`GET /api/news` returns the published News Articles as JSON. It is a custom
REST resource (`pepsico_api`) protected with OAuth2 through
[Simple OAuth](https://www.drupal.org/project/simple_oauth), using the
`client_credentials` grant for machine-to-machine access.

### How the API is protected
- **OAuth2 only.** The REST resource config (`rest.resource.pepsico_api_news`)
  allows the `oauth2` authentication provider and nothing else: no cookie, no
  basic auth. A logged-in browser session, even an administrator's, gets
  `403 The used authentication method is not allowed on this route`.
- **One role, one permission.** Tokens act as the dedicated account
  `api_client_news_demo`, which has only the `api_consumer` role. That role
  holds exactly one permission, `restful get pepsico_api_news`. The
  `news_read` scope maps to this role and is limited to `client_credentials`.
- **The client account cannot sign in.** It has no password and no email
  address, so the login form always fails and no reset link can be sent. It
  stays active because Simple OAuth rejects tokens of blocked users.
- **Short-lived tokens.** Access tokens expire after 300 seconds.
- **Data whitelist.** Only published articles, at most 50, newest first, with
  six fields: `id`, `title`, `summary`, `publish_date`, `featured`, `url`.
  `?featured=1` returns featured articles only; any value other than `0` or `1`
  returns `400`.
- **Cacheable.** Responses carry the `node_list:news_article` cache tag and vary
  by `featured` and by the consumer's permissions, so a cached `200` is never
  served to an anonymous request.

### Keys (per environment, never committed)
Simple OAuth signs tokens with an RSA key pair stored in `keys/` at the project
root: outside the web root (`web/`) and ignored by Git (`/keys/`, `*.key`,
`*.pem` in `.gitignore`). Every environment generates its own pair, and keys
are never committed, copied between environments or shared.

```bash
mkdir -p keys
ddev drush simple-oauth:generate-keys /var/www/html/keys
git status --short   # must not list anything under keys/
```

The paths are set in config (`simple_oauth.settings`: `../keys/public.key`,
`../keys/private.key`, relative to `web/`). The generator writes both files
with mode `0600`.

> **Warning:** running the generator again silently **replaces** both keys.
> Every token issued until then stops working at once. Do it only to rotate
> the keys on purpose.

### Demo client
```bash
ddev drush pepsico:api-client
```
This creates the confidential client `news_demo` (grant `client_credentials`,
scope `news_read`) and its account, then prints the client secret **once**.
The secret is generated with a CSPRNG and stored only as a password hash on the
Consumer entity; it is never written to config, state, logs or files. Copy it
into a password manager straight away.

- Running the command again refuses (exit code 1): the client already exists.
- `ddev drush pepsico:api-client --rotate-secret` issues a new secret, revokes
  all existing tokens of the client and prints the new secret once. The old
  secret stops working immediately.
- Lost the secret? It cannot be recovered; run
  `ddev drush pepsico:api-client --rotate-secret` to issue a new one.

Run the command in your own terminal, not through tools that keep a transcript.

### Calling the API with curl
DDEV serves the site with a local certificate from mkcert. Under WSL, pass the
mkcert root CA to curl explicitly:

```bash
CA="$(mkcert -CAROOT)/rootCA.pem"
API=https://pepsico-newsroom.ddev.site
```

1. Without a token: expect **403**.
   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' --cacert "$CA" "$API/api/news"
   ```
2. Read the secret without echoing it or saving it to shell history, then
   request a token: expect **200** with an `access_token`.
   ```bash
   read -rs SECRET   # paste the secret, press Enter
   TOKEN=$(curl -s --cacert "$CA" -X POST "$API/oauth/token" \
     -d grant_type=client_credentials -d client_id=news_demo \
     --data-urlencode "client_secret=$SECRET" -d scope=news_read \
     | python3 -c 'import sys, json; print(json.load(sys.stdin)["access_token"])')
   ```
   A wrong secret returns **401** `invalid_client`.
3. With the token: expect **200** and `{"data":[...],"count":8}` with the
   sample content.
   ```bash
   curl -s --cacert "$CA" -H "Authorization: Bearer $TOKEN" "$API/api/news" | python3 -m json.tool
   ```
4. Featured articles only: expect **200**.
   ```bash
   curl -s --cacert "$CA" -H "Authorization: Bearer $TOKEN" "$API/api/news?featured=1" | python3 -m json.tool
   ```
5. Invalid filter value: expect **400**.
   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' --cacert "$CA" -H "Authorization: Bearer $TOKEN" "$API/api/news?featured=true"
   ```
6. Invalid or expired token: expect **401**.
   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' --cacert "$CA" -H "Authorization: Bearer not-a-real-token" "$API/api/news"
   ```
7. Only JSON is served: `?_format=xml` returns **406**. Clear the variables
   when you are done:
   ```bash
   unset SECRET TOKEN
   ```

### Tests
Both suites run inside the DDEV web container against SQLite in `/tmp`, never
the site database. Test output goes to `web/sites/simpletest/`, which is
ignored by Git.

Kernel test (`NewsApiProvider`: published only, featured filter, field shape,
ordering, 50-item cap, cache metadata):
```bash
ddev exec 'SIMPLETEST_DB=sqlite://localhost//tmp/pepsico_api_kernel.sqlite vendor/bin/phpunit -c web/core web/modules/custom/pepsico_api/tests/src/Kernel'
```

Functional test (the full OAuth flow over HTTP; generates its own throwaway key
pair in `/tmp` and its own client, never the real `keys/` or `news_demo`):
```bash
mkdir -p web/sites/simpletest/browser_output
ddev exec 'SIMPLETEST_BASE_URL=http://web SIMPLETEST_DB=sqlite://localhost//tmp/pepsico_api_functional.sqlite BROWSERTEST_OUTPUT_DIRECTORY=/var/www/html/web/sites/simpletest/browser_output vendor/bin/phpunit -c web/core web/modules/custom/pepsico_api/tests/src/Functional'
```
`SIMPLETEST_BASE_URL=http://web` is the web server as seen from inside the
container. Deprecation notices in the output come from Simple OAuth and
Consumers, not from the custom modules.
