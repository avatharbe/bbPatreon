# bbPatreon Test Suite

## Overview

The test suite covers three layers: **unit tests** (fast, no database), **database tests** (XML fixtures), and **functional tests** (full phpBB board with HTTP requests). Unit and database tests run in the "Extension Test Suite"; functional tests run separately in the "Extension Functional Tests" suite.

All tests use phpBB's test framework base classes (`phpbb_test_case`, `phpbb_database_test_case`, `phpbb_functional_test_case`) and are executed via the [phpbb-extensions/test-framework](https://github.com/phpbb-extensions/test-framework) GitHub Actions workflow.

---

## Unit Tests

Unit tests mock all dependencies and verify pure logic without touching a database or making HTTP requests. They run in milliseconds.

### `tests/service/group_mapper_test.php`

Tests the tier-to-group mapping logic that decides which phpBB groups a user should be added to or removed from.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_get_tier_group_map` | JSON config string is decoded into an associative array of tier_id => group_id | Every sync decision starts with this map; if decoding fails, no groups are ever assigned |
| `test_get_tier_group_map_empty` | Empty JSON `{}` returns an empty array, not an error | Normal state on a fresh install before the admin configures tiers |
| `test_get_tier_group_map_invalid_json` | Corrupt or non-JSON config values return an empty array | Prevents a fatal error if the config row is manually edited in the database |
| `test_get_all_patron_group_ids` | Extracts unique group IDs from the tier map | Used by `demote_from_all_patron_groups()` to know which groups to clean up |
| `test_get_all_patron_group_ids_deduplicates` | Two tiers mapping to the same group produce only one group ID | Without deduplication, `group_user_del()` would be called twice for the same group |
| `test_sync_skips_on_empty_map` | `sync_user_groups()` is a no-op when no tiers are mapped | Prevents DB queries and errors on a freshly installed extension |
| `test_sync_skips_demotion_during_grace_period` | Former patrons are not demoted when grace period > 0 | The nightly cron handles deferred demotion; immediate demotion would defeat the purpose of the grace period setting |

### `tests/service/api_client_test.php`

Tests the error-handling guard clauses in the Patreon API client that fire before any curl call is made.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_request_returns_error_when_no_token` | `request()` returns an error array when creator access token is empty | Prevents a curl call with an empty Authorization header, which Patreon would reject with 401 |
| `test_get_campaign_members_returns_empty_when_no_campaign` | `get_campaign_members()` returns `[]` when campaign ID is empty | Without a campaign ID the URL would be malformed (`/campaigns//members`) and Patreon would return 404 |
| `test_refresh_token_returns_false_when_no_refresh_token` | `refresh_token()` returns false when no refresh token is stored | Prevents an infinite retry loop: request() → 401 → refresh → fail → request() again |

### `tests/event/listener_test.php`

Tests the event listener that hooks into phpBB's language loading and OAuth login flow.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_subscribed_events` | Listener subscribes to `core.user_setup` and `core.oauth_login_after_check_if_provider_id_has_match` | If either subscription is missing, the extension silently stops working: no language keys load, or no group sync after OAuth |
| `test_load_language_on_setup` | Language file `common` is appended to `$lang_set_ext` for extension `avathar/bbpatreon` | Without this, all `PATREON_*` language keys render as raw key names in templates and log entries |
| `test_on_oauth_login_ignores_non_patreon` | OAuth logins via Google, Facebook, etc. do not trigger the Patreon API | Without this filter, every OAuth login on the board would trigger a slow, unnecessary Patreon API call |
| `test_on_oauth_login_skips_when_row_empty` | No sync attempt when `$row` is false (no linked user yet) | During first-time OAuth login, there's no user_id to assign groups to; syncing here would cause a null reference |

### `tests/controller/webhook_test.php`

Tests the HMAC signature validation and JSON:API payload parsing used by the webhook controller.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_hmac_signature_validation` | `hash_hmac('md5')` + `hash_equals()` correctly accepts matching signatures and rejects non-matching ones | Patreon signs webhooks with HMAC-MD5; using the wrong hash function or comparison method would reject all legitimate events or create a timing attack vulnerability |
| `test_hmac_signature_is_md5_hex` | The hex digest is exactly 32 lowercase hex characters | Patreon sends this format in the `X-Patreon-Signature` header; a format mismatch (e.g. binary vs hex) would fail every validation |
| `test_webhook_payload_parsing` | Extracts user ID, patron status, pledge amount, tier ID, and tier label from a full JSON:API payload | Patreon's JSON:API format nests data in relationships and included resources; incorrect path traversal silently produces null values |
| `test_webhook_payload_no_tiers` | Empty tiers array is handled gracefully, producing an empty tier_id string | When a patron cancels, Patreon sends empty tiers; a missing guard would throw an undefined-index error |

---

## Database Tests

Database tests use XML fixtures loaded into a real test database. They verify that the migration schema and config seeding are correct.

### `tests/dbal/migration_test.php`

Tests that the v1_0_0 migration correctly creates all required config keys.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_patreon_config_keys_exist` | All 9 `patreon_*` config keys are present in `phpbb_config` | The ACP, cron task, webhook controller, and group mapper all read these keys; a missing key causes undefined-index errors at runtime |

**Fixture:** `tests/dbal/fixtures/config.xml` — seeds the `phpbb_config` and `phpbb_patreon_sync` tables with the expected default values.

---

## Functional Tests

Functional tests run against a fully installed phpBB board with the extension enabled. They make real HTTP requests and assert on rendered HTML. These are slow but catch DI wiring errors, template syntax errors, and module registration problems that unit tests cannot detect.

### `tests/functional/acp_test.php`

Tests the ACP settings page as an admin user.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_acp_module_accessible` | The ACP page loads and contains the extension title | Exercises the full DI container build, template rendering, and language loading; catches service wiring errors in `services.yml` |
| `test_acp_has_credentials_fields` | Input fields for client_id, client_secret, creator_access_token, campaign_id are present | If a field is missing (template edit accident, undefined variable), the admin cannot configure the extension |
| `test_acp_has_action_buttons` | Submit, Sync Now, and Fetch Tiers buttons are all present | These are the primary admin actions; a missing button removes a key capability with no visible error |

### `tests/functional/ucp_test.php`

Tests the UCP Patreon page as a logged-in user.

| Test | What it verifies | Why it matters |
|---|---|---|
| `test_ucp_module_accessible` | The UCP page loads and contains the extension title | Catches UCP module registration failures (migration didn't run), DI errors, or template compilation errors |
| `test_ucp_shows_link_button_when_not_linked` | The "Link your Patreon Account" button and explanatory text are present | This is the entry point for the entire OAuth flow; if the button or text is missing, users cannot link their accounts |

---

## What is NOT tested (and why)

| Area | Reason |
|---|---|
| Real Patreon API calls | Cannot hit live API in CI; tested via manual QA and the webhook curl test in the user docs |
| OAuth redirect flow | Requires a real Patreon OAuth client and browser interaction |
| Cron task execution | Depends on API responses; the cron logic reuses the same `group_mapper` and `api_client` methods tested above |
| Notification delivery | phpBB's notification system is tested by phpBB core; we only call `add_notifications()` |
| `group_user_add()` / `group_user_del()` | phpBB core global functions; tested indirectly by functional tests and the manual QA flow |

---

## Running Tests

### CI (GitHub Actions)

Tests run automatically on push to `main`, `develop`, and `develop-*` branches via the [phpbb-extensions/test-framework](https://github.com/phpbb-extensions/test-framework) reusable workflow. Tested against PHP 8.1, 8.2, 8.3, and 8.4 with MySQL, PostgreSQL, and SQLite.

### Locally

```bash
# Unit + database tests (fast)
cd /path/to/phpbb
phpunit --configuration ext/avathar/bbpatreon/phpunit.xml.dist --testsuite "Extension Test Suite"

# Functional tests (requires test DB + web server)
phpunit --configuration ext/avathar/bbpatreon/phpunit.xml.dist --testsuite "Extension Functional Tests"

# All tests
phpunit --configuration ext/avathar/bbpatreon/phpunit.xml.dist
```
