# Testing bbPatreon

## Test Suite Overview

| Suite | Type | Base class | What it tests |
|---|---|---|---|
| `tests/service/group_mapper_test.php` | Unit | `phpbb_test_case` | Tier→groups map parsing (1.3.0+: array per tier), group ID deduplication across multi-group tiers, grace period skip, unmapped-tier warn-not-demote (1.3.0+) |
| `tests/service/api_client_test.php` | Unit | `phpbb_test_case` | Error handling: no token, no campaign, no refresh token |
| `tests/service/patron_data_provider_test.php` | Unit | `TestCase` | Empty-result path, count query, rank-title batch lookup, group-name colour/translation formatting, currency formatting. Deliberately does NOT exercise the real-row happy path — see the file's own docblock on why |
| `tests/service/tier_data_provider_test.php` | Unit | `TestCase` | Published-tier mapping, empty result, subscribe-URL building (incl. no-campaign and URL-encoding cases), currency fallback |
| `tests/service/bbaccounts_recorder_test.php` | Unit | `phpbb_test_case` | Soft-coupling when bbAccounts is absent, credit filtering logic |
| `tests/event/listener_test.php` | Unit | `phpbb_test_case` | Subscribed events, language loading, provider filtering, OAuth-login sync, supporters nav-badge count delegation to `patron_data_provider` |
| `tests/controller/webhook_test.php` | Unit | `phpbb_test_case` | HMAC-MD5 signature validation, JSON:API payload parsing |
| `tests/controller/webhook_controller_test.php` | Unit | `TestCase` | `handle()` flow: no secret configured, no signature header, bad signature, response shape |
| `tests/controller/acp_controller_test.php` | Unit | `TestCase` | Paginated linked-users query (`sql_query_limit` vs plain `sql_query`), currency formatting, `set_page_url` |
| `tests/controller/patron_stats_acp_controller_test.php` | Unit | `TestCase` | Aggregate totals query (incl. NULL-row defaulting), per-tier breakdown query, currency formatting |
| `tests/controller/supporters_controller_test.php` | Unit | `TestCase` | 404 when disabled (and provider never called), template var mapping from `patron_data_provider` output |
| `tests/cron/sync_test.php` | Unit | `TestCase` | `is_runnable()`/`should_run()` guards, empty-members early return, skip-empty-user-id |
| `tests/cron/credit_idempotency_test.php` | Unit | `phpbb_test_case` | Two recorder runs in the same period don't double-credit (outbox pattern via `bbpatreon_credit_log`) |
| `tests/notification/patreon_linked_test.php` | Unit | `phpbb_test_case` | Notification type: `set_user_loader()`, `get_avatar()`, `get_title()` |
| `tests/oauth/patreon_test.php` | Unit | `TestCase` | PHPoAuthLib Patreon service adapter (authorize/token URLs, scopes, bearer auth method) |
| `tests/auth/oauth_service_test.php` | Unit | `TestCase` | phpBB OAuth service provider registration for Patreon |
| `tests/ext_test.php` | Unit | `TestCase` | Extension entry point / `is_enableable()` |
| `tests/dbal/migration_test.php` | Database | `TestCase` | v1_0_0: config keys declared, `patreon_tiers`/`patreon_sync` table schemas |
| `tests/dbal/supporters_migration_test.php` | Database | `TestCase` | v1_1_0: `show_public` column + config key declared |
| `tests/dbal/tier_group_migration_test.php` | Database | `TestCase` | v1_3_0/v1_3_1: `patreon_tier_groups` join table schema (composite PK), `patreon_tiers.group_id` drop, migration dependency chain |
| `tests/functional/acp_test.php` | Functional | `phpbb_functional_test_case` | ACP module loads, form fields present, action buttons |
| `tests/functional/ucp_test.php` | Functional | `phpbb_functional_test_case` | UCP module loads, link button, webhook route, callback route |

## Running Tests Locally

### Unit and database tests (fast, no server needed)

```bash
cd /path/to/phpbb
phpunit --configuration ext/avathar/bbpatreon/phpunit.xml.dist --testsuite "Extension Test Suite"
```

### Functional tests (requires test database and web server)

```bash
phpunit --configuration ext/avathar/bbpatreon/phpunit.xml.dist --testsuite "Extension Functional Tests"
```

### All tests

```bash
phpunit --configuration ext/avathar/bbpatreon/phpunit.xml.dist
```

## CI / GitHub Actions

Tests run automatically on push and pull request via the [phpbb-extensions/test-framework](https://github.com/phpbb-extensions/test-framework) reusable workflow:

- PHP versions: 8.1 (full DB/OS matrix), 8.2–8.4 (mysql:5.7 + postgres:14 only)
- Databases: MySQL (5.6/5.7/8.0, plus a MyISAM-storage-engine variant), MariaDB (10.1–10.5), PostgreSQL (9.5–14) — no SQLite
- A Windows job runs the PHP 8.1 suite on `windows-latest`
- Code sniffer, image ICC profile check, executable-file check, and EPV (Extension Pre-Validator) all run as steps within the single "PHP 8.1 - none" job (no real database, no functional tests) — not separate jobs

## Manual Testing

### OAuth Link Flow

1. Log in to phpBB as a test user
2. Go to UCP -> Patreon -> "Link your Patreon Account"
3. Authorize on Patreon (must be logged in as the patron, not the creator)
4. Verify: UCP shows Patreon ID, tier, status, pledge amount
5. Verify: user is added to the mapped phpBB group

### Webhook (requires public URL or ngrok)

```bash
SECRET="your_webhook_secret"
BODY='{"data":{"type":"member","id":"test-001","attributes":{"patron_status":"active_patron","currently_entitled_amount_cents":500},"relationships":{"currently_entitled_tiers":{"data":[{"id":"YOUR_TIER_ID","type":"tier"}]},"user":{"data":{"id":"PATREON_USER_ID","type":"user"}}}}}'
SIG=$(echo -n "$BODY" | openssl dgst -md5 -hmac "$SECRET" | awk '{print $2}')

curl -X POST https://yourforum.com/patreon/webhook \
  -H "Content-Type: application/json" \
  -H "X-Patreon-Event: members:pledge:create" \
  -H "X-Patreon-Signature: $SIG" \
  -d "$BODY"
```

### ACP Sync

1. Log in as admin, go to ACP -> Patreon Integration
2. Click "Sync Now" — should report members fetched and synced
3. Verify linked users table is populated

### Multi-Group Tier Mapping (1.3.0+)

1. ACP -> Patreon Integration -> Settings -> Tier Mapping: tick two phpBB groups for one tier, Submit
2. Trigger a sync for a patron on that tier (link, webhook, or "Sync Now")
3. Verify the patron is added to BOTH groups, and that unrelated patron groups they previously held are removed
4. If "Set tier group as default" is on, verify the patron's default group is the alphabetically-first of the two ticked groups
