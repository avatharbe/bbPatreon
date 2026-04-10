# phpBB Patreon Integration — Architecture

## Overview

A phpBB extension that integrates Patreon patron status with forum membership. Existing phpBB users link their Patreon account via OAuth, and are automatically assigned to phpBB usergroups based on their Patreon tier. Pledge changes are kept live via Patreon webhooks and a nightly cron reconciliation.

**Forum:** avathar.be/forum
**Target phpBB version:** 3.3.x
**Extension namespace:** `avathar/bbpatreon`

---

## Goals

- Allow existing phpBB users to link their Patreon account from their UCP (User Control Panel)
- Automatically assign phpBB usergroups based on Patreon tier
- React to pledge changes (create, update, delete) in near-real-time via webhooks
- Provide an ACP panel to map Patreon tier IDs to phpBB group IDs, with auto-fetch from the API
- Nightly cron as a safety-net reconciliation against the Patreon members API
- Notify admins and moderators when a user links their Patreon account

## Non-Goals

- Creating new phpBB accounts from Patreon (out of scope — users already have accounts)
- Writing content to Patreon (API is read-only for campaign management)
- Supporting Patreon API v1 (deprecated)

---

## Integration Architecture

```
┌─────────────────────────────────────────────────────────┐
│                     Patreon Platform                    │
│   Creator Page  │  Patron OAuth  │  Webhooks  │  API v2 │
└────────┬────────┴───────┬────────┴─────┬──────┴────┬────┘
         │                │              │           │
         │ (manual setup) │ OAuth flow   │ events    │ GET /members
         │                │              │           │
┌────────▼────────────────▼──────────────▼───────────▼────┐
│                avathar.be/forum (phpBB)                  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │              avathar/bbpatreon Extension          │   │
│  │                                                  │   │
│  │  OAuth Service   Webhook Controller   Cron Task  │   │
│  │       │                 │                 │      │   │
│  │       ▼                 ▼                 │      │   │
│  │  UCP Controller   Group Mapper  ◄─────────┘      │   │
│  │  (link/unlink)    (tier → group)                  │   │
│  │       │                 │                         │   │
│  │       ▼                 │                         │   │
│  │  Notification           │                         │   │
│  │  (admin/mod alert)      │                         │   │
│  └──────────────────────────────────────────────────┘   │
│                                                          │
│  phpBB Core Tables          Extension Tables             │
│  ├── phpbb_users            ├── phpbb_patreon_sync       │
│  ├── phpbb_groups           └── (ACP config in          │
│  ├── phpbb_user_group            phpbb_config)           │
│  ├── phpbb_oauth_accounts                               │
│  └── phpbb_oauth_tokens                                 │
└──────────────────────────────────────────────────────────┘
```

---

## Data Model

### Reusing phpBB's Built-in OAuth Tables

phpBB's existing OAuth system maintains:

```sql
phpbb_oauth_accounts (
    user_id          INT,        -- phpBB user
    provider         VARCHAR,    -- 'patreon'
    oauth_provider_id VARCHAR    -- Patreon user ID (stable, used as foreign key)
)

phpbb_oauth_tokens (...)         -- PHPoAuthLib token storage, managed by phpBB
phpbb_oauth_states (...)         -- OAuth state/CSRF tokens, managed by phpBB
```

The `phpbb_oauth_accounts` row is written by the UCP controller after a successful OAuth callback. The token/state tables are managed by PHPoAuthLib via phpBB's `token_storage` class.

### Custom Sync Table

Stores Patreon-specific state that phpBB's OAuth table does not track:

```sql
CREATE TABLE phpbb_patreon_sync (
    patreon_user_id     VARCHAR(64)  NOT NULL,   -- FK to phpbb_oauth_accounts.oauth_provider_id
    tier_id             VARCHAR(64)  DEFAULT '',  -- Patreon tier ID (empty = no active tier)
    tier_label          VARCHAR(100) DEFAULT '',  -- Human-readable, for ACP display
    pledge_status       VARCHAR(20)  DEFAULT 'pending_link',
                                                  -- active_patron | declined_patron |
                                                  -- former_patron | pending_link
    pledge_cents        INT UNSIGNED DEFAULT 0,   -- current pledge amount in cents
    last_webhook_at     INT UNSIGNED DEFAULT 0,   -- unix timestamp of last webhook event
    last_synced_at      INT UNSIGNED DEFAULT 0,   -- unix timestamp of last cron/manual sync
    PRIMARY KEY (patreon_user_id)
);
```

> **Note:** phpBB DBAL does not support ENUM or DATETIME; the implementation uses VARCHAR(20) for pledge_status and TIMESTAMP (unsigned int) for dates.

### ACP Configuration (stored in phpbb_config)

```
patreon_client_id
patreon_client_secret
patreon_creator_access_token
patreon_creator_refresh_token
patreon_campaign_id
patreon_webhook_secret
patreon_grace_period_days     -- days before demotion after pledge:delete (default: 0)
patreon_last_cron_sync        -- unix timestamp of last cron run
auth_oauth_patreon_key        -- synced copy of patreon_client_id (phpBB OAuth convention)
auth_oauth_patreon_secret     -- synced copy of patreon_client_secret (phpBB OAuth convention)
```

### ACP Configuration (stored in phpbb_config_text)

These values can exceed the 255-character limit of `phpbb_config.config_value`, so they use `config_text` instead:

```
patreon_tier_group_map        -- JSON: {"tier_id_1": group_id, "tier_id_2": group_id}
patreon_tier_labels           -- JSON: {"tier_id_1": "Tier Name", ...} (cached from API)
```

---

## Extension File Structure

```
ext/avathar/bbpatreon/
│
├── composer.json                        # Extension metadata
├── ext.php                              # Extension base class
│                                        # is_enableable() checks PHP 3.3+ and curl
│                                        # enable/disable/purge steps for notification type
│
├── config/
│   ├── parameters.yml                   # Table name: %avathar.bbpatreon.tables.patreon_sync%
│   ├── routing.yml                      # Routes: /patreon/webhook (POST), /patreon/callback (GET)
│   └── services.yml                     # All DI service definitions
│
├── oauth/
│   └── patreon.php                      # PHPoAuthLib service class
│                                        # extends \OAuth\OAuth2\Service\AbstractService
│                                        # defines Patreon OAuth2 endpoints and scopes
│                                        # overrides getAuthorizationMethod() → HEADER_BEARER
│
├── auth/
│   └── provider/
│       └── oauth/
│           └── service/
│               └── patreon.php          # phpBB OAuth service
│                                        # extends \phpbb\auth\provider\oauth\service\base
│                                        # get_external_service_class() → oauth\patreon
│                                        # perform_auth_login() → returns Patreon user ID
│
├── controller/
│   ├── webhook.php                      # Patreon webhook receiver (POST /patreon/webhook)
│   │                                    # validates X-Patreon-Signature (HMAC-MD5)
│   │                                    # handles: members:pledge:create/update/delete
│   │                                    # always returns 200 OK (prevents retry storms)
│   │
│   ├── callback.php                     # OAuth callback (GET /patreon/callback)
│   │                                    # Patreon redirects here after user authorises
│   │                                    # forwards ?code= to UCP module for processing
│   │
│   ├── acp_controller.php              # ACP settings page logic
│   │                                    # save settings, fetch campaign ID, fetch tiers,
│   │                                    # register/check/test webhook, manual sync,
│   │                                    # linked users table
│   │
│   └── ucp_controller.php              # UCP Patreon page logic
│                                        # handles OAuth redirect + callback processing
│                                        # link/unlink account, display status
│                                        # fires patreon_linked notification on link
│
├── event/
│   └── listener.php                     # Listens on phpBB events
│                                        # core.user_setup → load language
│                                        # core.oauth_login_after_check_if_provider_id_has_match
│                                        #   → fetch tier, upsert sync, call group_mapper
│
├── cron/
│   └── task/
│       └── sync.php                     # Nightly reconciliation (every 24h)
│                                        # GET /campaigns/{id}/members (paginated)
│                                        # upsert all sync rows, fix group discrepancies
│                                        # enforce grace period demotions
│                                        # mark orphaned members as former_patron
│
├── service/
│   ├── api_client.php                   # Patreon API v2 client (curl-based)
│   │                                    # Authorization: Bearer {creator_access_token}
│   │                                    # User-Agent: Avathar Forum - Patreon Sync
│   │                                    # auto-refresh on 401 via refresh_token()
│   │                                    # methods: request(), get_campaign_members(),
│   │                                    #          register_webhook(), refresh_token()
│   │
│   └── group_mapper.php                 # Resolves tier_id → phpBB group_id from config
│                                        # promotes via group_user_add()
│                                        # demotes via group_user_del()
│                                        # handles grace period (skips demotion, cron enforces)
│                                        # handles tier changes (remove old, add new)
│
├── notification/
│   └── type/
│       └── patreon_linked.php           # Notification sent to admins/moderators
│                                        # when a user links their Patreon account
│                                        # shows username and tier
│
├── migrations/
│   └── v1_0_0_initial.php              # Creates phpbb_patreon_sync table
│                                        # Adds config and config_text keys
│                                        # Registers ACP module (under ACP_CAT_DOT_MODS)
│                                        # Registers UCP module
│
├── acp/
│   ├── main_info.php                    # ACP module metadata (mode: settings)
│   └── main_module.php                  # ACP module class → delegates to acp_controller
│
├── ucp/
│   ├── main_info.php                    # UCP module metadata (mode: settings)
│   └── main_module.php                  # UCP module class → delegates to ucp_controller
│
├── adm/style/
│   └── acp_bbpatreon_body.html          # ACP template (Twig)
│                                        # - Overview panel (collapsible)
│                                        # - API credentials fieldset
│                                        # - Webhook fieldset with URL, secret, register/check/test
│                                        # - Tier mapping table with Fetch Tiers button
│                                        # - Linked users table
│                                        # - Submit + Sync Now buttons
│
├── styles/prosilver/template/
│   └── ucp_bbpatreon_body.html          # UCP template (Twig)
│                                        # - Linked: shows tier, status, pledge, unlink button
│                                        # - Not linked: shows link button (form POST)
│
├── language/{en,nl,de,fr,es,pt}/
│   ├── common.php                       # OAuth provider title, notifications, log entries
│   ├── info_acp_bbpatreon.php           # ACP labels, help text, status messages
│   └── info_ucp_bbpatreon.php           # UCP labels, status messages
│
├── tests/                               # PHPUnit test suite (see tests/tests.md)
├── docs/                                # User documentation
└── contrib/                             # This architecture document
```

---

## Component Details

### OAuth Flow

The extension handles the OAuth flow itself rather than using phpBB's built-in `ucp_auth_link` module (which requires `auth_method = oauth`). This allows it to work alongside the default `db` auth method.

**Flow:**
```
1. User logs into forum normally (phpBB username/password)
2. User visits UCP → Patreon tab
3. Clicks "Link your Patreon Account" (form POST)
4. UCP controller creates PHPoAuthLib service, redirects to Patreon
5. User authorises on Patreon
6. Patreon redirects to: /patreon/callback?code=...
7. Callback controller forwards code to UCP module
8. UCP controller exchanges code for token via PHPoAuthLib
9. Calls /api/oauth2/v2/identity to get Patreon user ID
10. Inserts phpbb_oauth_accounts record
11. Fetches campaign members via api_client (creator token)
12. Upserts phpbb_patreon_sync row
13. Calls group_mapper to assign phpBB group
14. Sends notification to admins/moderators
15. User returned to UCP showing linked status and current tier
```

**Redirect URI** (must be set in Patreon API client settings):
`https://yourforum.com/patreon/callback`

### PHPoAuthLib Service (`oauth/patreon.php`)

Custom OAuth2 service class for the `lusitanian/oauth` library bundled with phpBB 3.3. Required because Patreon is not a built-in PHPoAuthLib provider.

- Authorize URL: `https://www.patreon.com/oauth2/authorize`
- Token URL: `https://www.patreon.com/api/oauth2/token`
- Base API URI: `https://www.patreon.com/api/oauth2/v2/`
- Authorization method: `HEADER_BEARER` (overrides default `HEADER_OAUTH`)
- Scope constants: `SCOPE_IDENTITY`, `SCOPE_IDENTITY_EMAIL`, `SCOPE_CAMPAIGNS`, `SCOPE_CAMPAIGNS_MEMBERS`

### phpBB OAuth Service (`auth/provider/oauth/service/patreon.php`)

Registered as `auth.provider.oauth.service.patreon` in the DI container (this exact ID is required by phpBB's `get_service_name()` convention). Returns the custom PHPoAuthLib class via `get_external_service_class()`.

### Webhook Controller (`controller/webhook.php`)

Public endpoint: `https://yourforum.com/patreon/webhook`

**Signature validation:**
```php
$signature = $_SERVER['HTTP_X_PATREON_SIGNATURE'];
$body      = file_get_contents('php://input');
$expected  = hash_hmac('md5', $body, $config['patreon_webhook_secret']);
if (!hash_equals($expected, $signature)) { /* log and return 200 */ }
```

**Always returns 200 OK** — even on validation failure or unknown events. Patreon retries on non-200 with exponential backoff, which would cause a retry storm on persistent failures.

| Trigger | Action |
|---|---|
| `members:pledge:create` | Upsert sync row, assign group |
| `members:pledge:update` | Re-evaluate tier, adjust group |
| `members:pledge:delete` | Set status to former_patron; demote immediately if grace=0, otherwise cron handles it |

### API Client (`service/api_client.php`)

Curl-based wrapper (not PHPoAuthLib — the creator token is a server-side config value, not tied to a user's OAuth session). Always sets:
```
Authorization: Bearer {creator_access_token}
User-Agent: Avathar Forum - Patreon Sync
```

On 401, automatically calls `refresh_token()` and retries once.

### Group Mapper (`service/group_mapper.php`)

Reads `patreon_tier_group_map` config (JSON) to resolve `tier_id → phpbb_group_id`.

- **Promotion:** `group_user_add()` — adds user to tier group
- **Demotion:** `group_user_del()` — removes from all tracked patron groups
- **Tier change:** remove from old group, add to new
- **Grace period:** when status is `former_patron`/`declined_patron` and grace_period > 0, demotion is skipped; the nightly cron enforces it by checking `last_webhook_at + grace_days < now()`

### Cron Task (`cron/task/sync.php`)

Runs every 24 hours. Full reconciliation:

1. `GET /api/oauth2/v2/campaigns/{id}/members` (paginated, max 1000/page)
2. For each member: upsert `patreon_sync`, sync groups if user is linked
3. Mark orphaned sync rows (not in API response) as `former_patron`
4. Enforce grace period demotions for expired former/declined patrons
5. Log summary to admin log

### Notification (`notification/type/patreon_linked.php`)

Sent to all users with `a_` (admin) or `m_` (moderator) permissions when a user links their Patreon account. Shows the username and their tier. The linking user is excluded from the notification.

### Unlinking

- UCP "Unlink" button deletes `phpbb_oauth_accounts` record and `phpbb_patreon_sync` record
- User is demoted from all patron groups immediately (no grace period on manual unlink)

---

## Webhook Registration

Webhooks are registered via the Patreon portal UI (recommended) or programmatically via the API:

**Portal (recommended):**
1. Go to [patreon.com/portal/registration/register-webhooks](https://www.patreon.com/portal/registration/register-webhooks)
2. Create webhook with URL from ACP (shown in a copyable field)
3. Select triggers: `members:pledge:create`, `members:pledge:update`, `members:pledge:delete`
4. Paste the secret into the ACP Webhook Secret field

**API (requires `w:campaigns.webhook` scope):**
ACP has a "Register via API" button that POSTs to `/api/oauth2/v2/webhooks`.

**Verification:** ACP has "Check Status" (queries Patreon for webhook health) and "Test Ping" (sends a self-signed payload to the local endpoint).

---

## ACP Features

- **API Credentials:** Client ID, Client Secret, Creator tokens, Campaign ID with "Fetch" button
- **Webhook:** URL display (copyable), secret field, Register/Check/Test buttons
- **Tier Mapping:** "Fetch Tiers" button loads tiers from API with names; admin selects phpBB group per tier
- **Grace Period:** configurable days before demotion
- **Linked Users:** table showing username, Patreon ID, tier, status, pledge, timestamps
- **Sync Now:** manual full reconciliation button
- **Collapsible Help:** every section has a "How does this work?" toggle with detailed explanation

---

## Key Constraints & Gotchas

| Constraint | Detail |
|---|---|
| auth_method = db | The extension handles OAuth itself; does NOT require phpBB's auth method to be set to 'oauth'. The built-in `ucp_auth_link` module is bypassed entirely. |
| No Patreon sandbox | All testing is against live Patreon with real accounts |
| Redirect URI | Must be set to `/patreon/callback` (not phpBB's `ucp.php`) in Patreon API client settings |
| Config key duality | `auth_oauth_patreon_key`/`secret` are synced copies of `patreon_client_id`/`secret` (phpBB OAuth convention). ACP writes both on save. |
| `User-Agent` required | Omitting it causes silent 403s from Patreon's edge |
| PHPoAuthLib | phpBB 3.3.x ships with `carlos-mg89/oauth` (fork of `lusitanian/oauth`). Custom service class needed for Patreon. Must override `getAuthorizationMethod()` to return `HEADER_BEARER`. |
| `pledge:delete` vs `declined` | `patron_status: declined_patron` means payment failed but not yet cancelled — treated as still-active during grace period |
| Creator token expiry | Both access + refresh tokens stored; api_client auto-refreshes on 401 |
| Webhook always 200 | Even on signature failure, to prevent Patreon retry storms |

---

## Out of Scope (Future Considerations)

- Displaying patron-only forum sections (handled by phpBB's native group-based forum permissions once groups are assigned)
- Patreon post embedding in forum (no write API; would require manual cross-posting)
- Multiple campaigns (single campaign assumed; `patreon_campaign_id` config is singular)
- Discord role sync (separate concern; Patreon handles this natively)
