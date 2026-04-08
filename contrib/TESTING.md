# Testing bbPatreon

## Test Suite Overview

| Suite | Type | Base class | What it tests |
|---|---|---|---|
| `tests/service/group_mapper_test.php` | Unit | `phpbb_test_case` | Tier map parsing, group ID deduplication, grace period skip |
| `tests/service/api_client_test.php` | Unit | `phpbb_test_case` | Error handling: no token, no campaign, no refresh token |
| `tests/event/listener_test.php` | Unit | `phpbb_test_case` | Subscribed events, language loading, provider filtering |
| `tests/controller/webhook_test.php` | Unit | `phpbb_test_case` | HMAC-MD5 signature validation, JSON:API payload parsing |
| `tests/dbal/migration_test.php` | Database | `phpbb_database_test_case` | Migration: table exists, config keys, column types |
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

- PHP versions: 8.1, 8.2, 8.3, 8.4
- Databases: MySQL, PostgreSQL, SQLite
- EPV (Extension Pre-Validator) runs as a separate job

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
