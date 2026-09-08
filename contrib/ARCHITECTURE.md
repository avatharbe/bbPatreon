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
- Notify users with the `u_patreon_notify` permission when a user links their Patreon account (defaults to admins only; admins can grant the perm to moderator roles or specific groups)
- Optionally credit active patrons' wallets in the [bbAccounts](https://github.com/avatharbe/bbAccounts) ledger extension every month (soft-coupled; bbPatreon works fine when bbAccounts is not installed)

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
│  │  UCP Controller   Group Mapper  ◄─────────┤      │   │
│  │  (link/unlink)    (tier → group)          │      │   │
│  │       │                 │                 │      │   │
│  │       ▼                 │                 ▼      │   │
│  │  Notification           │       bbAccounts        │   │
│  │  (u_patreon_notify)     │       Recorder          │   │
│  │                         │       (1.2.4+)          │   │
│  └─────────────────────────┼─────────────┬───────────┘   │
│                            │             │ @? nullable   │
│                            │             ▼ (soft-coupled)│
│                            │   ┌────────────────────┐    │
│                            │   │ avathar/bbAccounts │    │
│                            │   │  ledger service    │    │
│                            │   │  (optional sibling)│    │
│                            │   └─────────┬──────────┘    │
│                            │             │               │
│  phpBB Core Tables          │   Ext Tables (bbpatreon)   │
│  ├── phpbb_users            └─→ ├── phpbb_patreon_sync   │
│  ├── phpbb_groups               ├── phpbb_patreon_tiers  │
│  ├── phpbb_user_group           ├── phpbb_bbpatreon_     │
│  ├── phpbb_oauth_accounts       │   credit_rules (1.2.4+)│
│  └── phpbb_oauth_tokens         └── phpbb_bbpatreon_     │
│                                     credit_log  (1.2.4+) │
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

### Tier catalogue (`phpbb_patreon_tiers`)

Static tier metadata cached locally so admins can manage tier→group mappings without making an API call on every ACP load:

```sql
CREATE TABLE phpbb_patreon_tiers (
    tier_id        VARCHAR(64)  NOT NULL,     -- Patreon tier ID (PK)
    tier_label     VARCHAR(255),               -- Human-readable name
    amount_cents   INT UNSIGNED DEFAULT 0,     -- Tier's price point in cents
    currency       VARCHAR(8),
    patron_count   INT UNSIGNED DEFAULT 0,
    published      TINYINT(1)   DEFAULT 1,
    PRIMARY KEY (tier_id)
);
```

`published` doubles as a "still on Patreon" flag (1.3.0+): the ACP "Fetch Tiers" action (`acp_controller::ExtractTiers()`) sets it to `0` for any row whose `tier_id` is absent from the fresh API response — same handling whether the tier was unpublished on Patreon directly or deleted (and possibly recreated under a new `tier_id`). Rows are never deleted, only unpublished, so existing `phpbb_patreon_tier_groups` mappings survive and admins can still see the retired tier's history in the ACP mapping list (greyed out). `tier_data_provider::get_published_tiers()` excludes `published=0` rows from the public catalogue.

### Tier-to-group mapping (`phpbb_patreon_tier_groups`, 1.3.0+)

A tier can map to more than one phpBB group (e.g. a shared "all patrons" perk group plus a tier-specific exclusive group). `patreon_tiers.group_id` was dropped in favour of this many-to-many join table (v1_3_0 added the table and backfilled existing single mappings; v1_3_1 dropped the old column):

```sql
CREATE TABLE phpbb_patreon_tier_groups (
    tier_id  VARCHAR(64)  NOT NULL,
    group_id INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (tier_id, group_id)
);
```

`group_mapper::get_tier_group_map()` returns `tier_id => [group_id, ...]`, ordered alphabetically by group name. When the `bbpatreon_set_default_group` toggle is on and a tier maps to multiple groups, the first group in that alphabetical order is used as the patron's default group — not admin-configurable order, just alphabetical.

### bbAccounts integration tables (1.2.4+)

These two tables are only used when the [bbAccounts](https://github.com/avatharbe/bbAccounts) extension is installed. Without bbAccounts they are inert (rows can exist but no journal entries are posted).

```sql
-- Admin-configured credit rules. One row per (expense_account_id,
-- wallet_account_id, amount_per_dollar) mapping. An admin with multiple
-- rules can credit several pools at once (e.g. forum POINTS + a separate
-- USD-denominated patron wallet).
CREATE TABLE phpbb_bbpatreon_credit_rules (
    rule_id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_label         VARCHAR(100),
    expense_account_id INT UNSIGNED DEFAULT 0,
    wallet_account_id  INT UNSIGNED DEFAULT 0,
    amount_per_dollar  DECIMAL(10,2) DEFAULT 0.00,
    is_active          TINYINT(1)    DEFAULT 1,
    rule_order         INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (rule_id),
    KEY is_active_order (is_active, rule_order)
);

-- Outbox idempotency log. One row per (rule, patron, calendar month)
-- tuple that was successfully credited. UNIQUE KEY makes the recorder
-- short-circuit cleanly on the second run in a given period without
-- touching the bbAccounts journal.
CREATE TABLE phpbb_bbpatreon_credit_log (
    log_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id    INT UNSIGNED DEFAULT 0,
    user_id    INT UNSIGNED DEFAULT 0,
    period     VARCHAR(7),               -- YYYY-MM
    journal_id INT UNSIGNED DEFAULT 0,   -- back-link to phpbb_bbaccounts_journal.journal_id
    created_at INT UNSIGNED DEFAULT 0,
    PRIMARY KEY (log_id),
    UNIQUE KEY rule_user_period (rule_id, user_id, period)
);
```

### ACP Configuration (stored in phpbb_config)

```
patreon_client_id
patreon_client_secret
patreon_creator_access_token
patreon_creator_refresh_token
patreon_campaign_id
patreon_currency               -- campaign's currency code, set by "Fetch Campaign" (default: USD)
patreon_webhook_secret
patreon_grace_period_days     -- days before demotion after pledge:delete (default: 0)
patreon_last_cron_sync        -- unix timestamp of last cron run
patreon_supporters_page_enabled
patreon_supporters_show_amounts
bbpatreon_set_default_group   -- bool, 1.2.4+ — promote/demote also sets/resets default group
auth_oauth_patreon_key        -- synced copy of patreon_client_id (phpBB OAuth convention)
auth_oauth_patreon_secret     -- synced copy of patreon_client_secret (phpBB OAuth convention)
```

There is no `config_text` usage in this extension — the tier→group mapping lives in the relational `phpbb_patreon_tier_groups` table (see Data Model above), not a serialized config blob.

### Permissions (1.2.4+)

| Key | Default-granted to | Purpose |
|---|---|---|
| `u_patreon_notify` | `ROLE_ADMIN_FULL` | Receive "X linked their Patreon account" notification. Admins can grant to moderator roles or specific groups via ACP → Permissions. |

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
│   ├── parameters.yml                   # Table names: %avathar.bbpatreon.tables.*%
│   ├── routing.yml                      # Routes: /patreon/webhook (POST), /patreon/callback (GET),
│   │                                    #         /patreon/supporters (GET, 1.1.0+)
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
│   │                                    # fires avathar.bbpatreon.pledge_changed (see events.md)
│   │
│   ├── callback.php                     # OAuth callback (GET /patreon/callback)
│   │                                    # Patreon redirects here after user authorises
│   │                                    # forwards ?code= to UCP module for processing
│   │
│   ├── acp_controller.php              # ACP "Settings" mode logic
│   │                                    # save settings, fetch campaign ID, fetch tiers
│   │                                    #   (fires avathar.bbpatreon.tiers_updated, 1.3.0+),
│   │                                    # register/check/test webhook, manual sync,
│   │                                    # paginated linked users table (1.3.0+, issue #22),
│   │                                    # tier→group checkbox mapping (1.3.0+, issue #5)
│   │
│   ├── patron_stats_acp_controller.php # ACP "Patron Stats" mode logic (1.3.0+)
│   │                                    # read-only: active/declined patron counts,
│   │                                    # total monthly pledge amount, per-tier breakdown —
│   │                                    # all computed live from patreon_sync
│   │
│   ├── bbaccounts_acp_controller.php   # ACP "bbAccounts Integration" mode logic (1.2.4+)
│   │                                    # credit rule CRUD, "Run credit now" button
│   │
│   ├── supporters_controller.php       # Public supporters page (GET /patreon/supporters)
│   │                                    # 404s when patreon_supporters_page_enabled is off
│   │                                    # delegates all data/formatting to patron_data_provider
│   │
│   └── ucp_controller.php              # UCP Patreon page logic
│                                        # handles OAuth redirect + callback processing
│                                        # link/unlink account, display status
│                                        # supporters opt-in checkboxes (1.1.0+/1.2.0+)
│                                        # fires patreon_linked notification on link
│
├── event/
│   └── listener.php                     # Listens on phpBB events
│                                        # core.user_setup → load language
│                                        # core.page_header → inject Supporters navbar link;
│                                        #   count delegates to patron_data_provider (1.3.0+)
│                                        # core.memberlist_team_modify_template_vars
│                                        #   → inject Patreon tier badge on Team page
│                                        # core.oauth_login_after_check_if_provider_id_has_match
│                                        #   → fetch tier, upsert sync, call group_mapper
│                                        # core.permissions → register u_patreon_notify
│                                        #   in phpBB's MASK UI (1.2.4+)
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
│   ├── group_mapper.php                 # Resolves tier_id → [phpBB group_id, ...] (1.3.0+: many
│   │                                    #   groups per tier, via phpbb_patreon_tier_groups)
│   │                                    # promotes via group_user_add() for every mapped group
│   │                                    # demotes via group_user_del() (via safe_group_user_del
│   │                                    #   helper that resets default-group to Registered first
│   │                                    #   if the bbpatreon_set_default_group toggle is on)
│   │                                    # handles grace period (skips demotion, cron enforces)
│   │                                    # handles tier changes (remove old groups, add new ones)
│   │                                    # 1.2.4+: optional default-group toggle calls
│   │                                    #   group_user_attributes('default', target_group_id, …)
│   │                                    #   so the patron's username adopts the group colour/rank;
│   │                                    #   1.3.0+: uses the alphabetically-first mapped group
│   │
│   ├── patron_data_provider.php         # Public service (1.3.0+, issue #2/#10)
│   │                                    # get_public_supporters() / get_public_supporters_count()
│   │                                    # sole source of truth for opted-in supporter data —
│   │                                    # consumed by supporters_controller AND event/listener.php
│   │                                    #   (nav badge count), see events.md §1.6
│   │
│   ├── tier_data_provider.php           # Public service (1.3.0+, issue #10)
│   │                                    # get_published_tiers(): label, description, formatted
│   │                                    #   amount, Patreon subscribe URL — for a third-party
│   │                                    #   "Membership Tiers" page. See events.md §1.6
│   │
│   └── bbaccounts_recorder.php          # bbAccounts integration (1.2.4+)
│                                        # Nullable DI on @?avathar.bbaccounts.service.ledger
│                                        # credit_active_patrons_for_period($period_ymd):
│                                        #   for each (active rule × active-pledge patron):
│                                        #     SELECT bbpatreon_credit_log → if present, skip
│                                        #     transaction: ledger->create_entry + INSERT log row
│                                        # Idempotent via UNIQUE KEY (rule, user, period)
│                                        # Called from cron/task/sync::run and ACP "Run credit now"
│
├── notification/
│   └── type/
│       └── patreon_linked.php           # Notification sent to users with u_patreon_notify
│                                        #   (1.2.4+; default-granted to admins only)
│                                        # when a user links their Patreon account
│                                        # get_reference() looks up the human-readable tier label
│                                        #   from patreon_tiers (1.2.4+; previously showed raw ID)
│
├── migrations/
│   ├── v1_0_0_initial.php              # Creates phpbb_patreon_sync, phpbb_patreon_tiers
│   │                                    # Adds the patreon_* config keys
│   │                                    # Registers ACP module (under ACP_CAT_DOT_MODS)
│   │                                    # Registers UCP module
│   ├── v1_1_0_supporters_page.php      # Adds show_public column + supporters page config
│   ├── v1_2_0_show_pledge.php          # Adds show_pledge_public column + config toggle
│   ├── v1_2_1_bbaccounts_integration.php  # 1.2.4 series — adds bbpatreon_credit_rules
│   │                                        # table + bbaccounts_integration ACP mode
│   ├── v1_2_2_credit_log.php           # Adds bbpatreon_credit_log (outbox idempotency)
│   ├── v1_2_3_notification_perm.php    # Adds u_patreon_notify permission + grant
│   ├── v1_2_4_default_group.php        # Adds bbpatreon_set_default_group config flag
│   ├── v1_2_5_patron_stats_page.php    # Registers the "Patron Stats" ACP mode (1.3.0)
│   ├── v1_3_0_tier_group_join.php      # Adds phpbb_patreon_tier_groups; backfills existing
│   │                                    #   single group_id mappings into it (issue #5)
│   ├── v1_3_1_drop_tier_group_id.php   # Drops patreon_tiers.group_id — the join table is
│   │                                    #   now the sole source of truth (issue #5)
│   └── v1_3_2_hide_bbaccounts_tab.php  # Updates the stored module_auth of the
│                                        #   bbaccounts_integration ACP mode for existing
│                                        #   installs to add the ext_avathar/bbaccounts check
│
├── acp/
│   ├── main_info.php                    # ACP module metadata
│   │                                    # Modes: settings, bbaccounts_integration (1.2.4+;
│   │                                    #        1.3.0+: hidden unless bbAccounts is enabled,
│   │                                    #        via ext_avathar/bbaccounts auth token),
│   │                                    #        patron_stats (1.3.0+)
│   └── main_module.php                  # ACP module class
│                                        # Dispatches on $mode:
│                                        #   settings → acp_controller::display_options
│                                        #   bbaccounts_integration → bbaccounts_acp_controller::handle
│                                        #   patron_stats → patron_stats_acp_controller::handle
│
├── ucp/
│   ├── main_info.php                    # UCP module metadata (mode: settings)
│   └── main_module.php                  # UCP module class → delegates to ucp_controller
│
├── adm/style/
│   ├── acp_bbpatreon_body.html          # ACP "Settings" template (Twig)
│   │                                    # - Overview panel (collapsible)
│   │                                    # - API credentials fieldset
│   │                                    # - Webhook fieldset with URL, secret, register/check/test
│   │                                    # - Tier mapping table: checkbox list per tier (1.3.0+)
│   │                                    # - Paginated linked users table (1.3.0+, issue #22)
│   │                                    # - Submit + Sync Now buttons
│   ├── acp_bbpatreon_patron_stats.html  # ACP "Patron Stats" template (1.3.0+, old-style syntax)
│   └── acp_bbpatreon_bbaccounts_integration.html  # ACP "bbAccounts Integration" template (1.2.4+)
│
├── styles/prosilver/template/
│   ├── ucp_bbpatreon_body.html          # UCP template (Twig)
│   │                                    # - Linked: shows tier, status, pledge, unlink button
│   │                                    # - Not linked: shows link button (form POST)
│   │                                    # - Supporters opt-in checkboxes (1.1.0+/1.2.0+)
│   ├── supporters_body.html             # Public supporters page template (1.1.0+)
│   │                                    # - avathar_bbpatreon_supporters_body_before/_after
│   │                                    #   template events (1.3.0+, see events.md §1.7)
│   └── event/
│       ├── navbar_header_quick_links_after.html   # Supporters nav link (quick links menu)
│       └── overall_header_navigation_append.html  # Supporters nav link (sandwich menu)
│
├── language/{en,nl,de,fr,es,pt}/
│   ├── common.php                       # OAuth provider title, notifications, log entries
│   ├── info_acp_bbpatreon.php           # ACP "Settings" labels, help text, status messages
│   ├── info_ucp_bbpatreon.php           # UCP labels, status messages
│   ├── info_acp_bbaccounts_integration.php  # ACP "bbAccounts Integration" mode labels (1.2.4+)
│   └── info_acp_bbpatreon_patron_stats.php  # ACP "Patron Stats" mode labels (1.3.0+; non-English
│                                             #   files are English placeholders pending translation)
│
├── tests/                               # PHPUnit test suite (see contrib/TESTING.md)
└── contrib/                             # Architecture, user docs, testing guide, events/API contract
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

Reads tier→groups mappings from `phpbb_patreon_tier_groups` (1.3.0+) to resolve `tier_id → [phpbb_group_id, ...]` — a tier can map to more than one group (issue #5). `get_tier_group_map()` orders each tier's groups alphabetically by group name, so the first element is well-defined as that tier's "primary" group.

- **Promotion:** `group_user_add()` — adds the user to every group mapped to their tier
- **Demotion:** `safe_group_user_del()` helper — wraps phpBB's `group_user_del()`. When the `bbpatreon_set_default_group` config flag is on, checks whether the group being removed is the user's current default; if so, resets default to the Registered users group first (otherwise the user would be left with an invalid default group_id pointing at a group they're no longer in).
- **Tier change:** remove from any group not mapped to the new tier via `safe_group_user_del`, add every group mapped to the new tier via `group_user_add`
- **Unmapped tier (1.3.0+):** if an `active_patron` is on a `tier_id` with no row in `phpbb_patreon_tier_groups` yet (e.g. right after a tier is deleted and recreated on Patreon under a new ID, before the admin re-maps it in ACP), `sync_user_groups()` leaves their current groups untouched and logs `LOG_PATREON_TIER_UNMAPPED` instead of falling through to demotion — a paying patron is never silently stripped of their group just because the admin hasn't caught up yet.
- **Grace period:** when status is `former_patron`/`declined_patron` and grace_period > 0, demotion is skipped; the nightly cron enforces it by checking `last_synced_at + grace_days < now()`
- **Default-group toggle (1.2.4+):** when `bbpatreon_set_default_group=1`, promotion also calls `group_user_attributes('default', target_group_id, …)` so the patron's username takes on that group's colour and rank. 1.3.0+: when a tier maps to multiple groups, `target_group_id` is the alphabetically-first one. Demotion resets the default to Registered users (custom pre-promotion default groups are not preserved across cycles).

### Public Data Provider Services (`service/patron_data_provider.php`, `service/tier_data_provider.php`, 1.3.0+)

Two public DI services documented as a stable API contract in `contrib/events.md` §1.6, so other extensions can read bbPatreon data without querying its tables directly:

- **`patron_data_provider`** — `get_public_supporters()` / `get_public_supporters_count()`. Enforces the opt-in consent check (`show_public=1`, `active_patron` only) itself, so a caller can't accidentally leak non-consented data. Consumed by both `supporters_controller` (the public page) and `event/listener.php` (the nav-link supporter-count badge) — the same extraction that made this a public service also removed the last two direct-query call sites for this data.
- **`tier_data_provider`** — `get_published_tiers()`. Returns the catalogue of `published=1` tiers with a ready-to-use Patreon subscribe URL, for a third-party "Membership Tiers" page (e.g. via `phpbb/pages`). Paired with the `avathar.bbpatreon.tiers_updated` PHP event (fired when the ACP "Fetch Tiers" action refreshes the catalogue) so a consumer knows when to invalidate anything it cached.

### bbAccounts Recorder (`service/bbaccounts_recorder.php`, 1.2.4+)

Single-purpose service that posts journal entries to the bbAccounts ledger on behalf of active-pledge patrons. Soft-coupled via nullable DI on `@?avathar.bbaccounts.service.ledger` — when bbAccounts is not installed, `is_available()` returns false and `credit_active_patrons_for_period()` short-circuits with `skipped_no_rules=1`.

**ACP visibility (1.3.0+):** the "bbAccounts Integration" ACP tab itself is gated by an `ext_avathar/bbaccounts` auth token in `acp/main_info.php`, using phpBB's built-in `ext_` module-auth check (`$phpbb_extension_manager->all_enabled()`) — the tab is hidden from the ACP menu entirely, rather than showing and rendering an "extension not installed" errorbox. `bbaccounts_acp_controller::handle()`'s `is_available()` check (via `S_BBACCOUNTS_MISSING`) remains as a fallback for anyone hitting the URL directly.

**Outbox pattern (idempotency):**

bbAccounts' `ledger->create_entry()` takes `reference_id` as an int, so the composite `<rule>-<user>-<period>` cannot fit in the ledger's reference fields. bbPatreon owns its own idempotency state in `phpbb_bbpatreon_credit_log` with a UNIQUE KEY on `(rule_id, user_id, period)`. Before posting, the recorder queries the log; on successful create_entry it INSERTs the log row with the returned `journal_id`; both wrapped in a DB transaction so a credit_log insert failure rolls back the journal entry.

**Per-patron filter:**

The recorder joins `patreon_sync` with `phpbb_oauth_accounts` (`provider='patreon'`) — only patrons who have completed the UCP OAuth link flow are credited (a `user_id` is required for the bbAccounts subledger). Creator-side known patrons (synced from the campaign API but never linked) are intentionally excluded.

**Trigger surfaces:**

- `cron/task/sync::run()` invokes the recorder at the end of every cron run with `gmdate('Y-m')` as the period (UTC).
- `controller/bbaccounts_acp_controller::run_credit()` invokes the recorder when the admin clicks "Run credit now" in the ACP, with a period picked from a `<input type="month">` field (defaults to current UTC month). Used for back-filling missed months or testing freshly-configured rules.

### Cron Task (`cron/task/sync.php`)

Runs every 24 hours. Full reconciliation:

1. `GET /api/oauth2/v2/campaigns/{id}/members` (paginated, max 1000/page)
2. For each member: upsert `patreon_sync`, sync groups if user is linked
3. Mark orphaned sync rows (not in API response) as `former_patron`
4. Enforce grace period demotions for expired former/declined patrons
5. Log summary to admin log

### Notification (`notification/type/patreon_linked.php`)

Sent to users with the `u_patreon_notify` permission (default-granted to `ROLE_ADMIN_FULL` only) when a user links their Patreon account. Admins can extend the perm to moderator roles or specific groups via ACP → Permissions. Shows the username and tier label (the human-readable name from `phpbb_patreon_tiers.tier_label`, looked up at render time; falls back to the raw tier_id if the tier row is missing). The linking user is excluded from the notification.

**Pre-1.2.4 behaviour:** the notification went to all users with any `a_*` (admin) or `m_*` (moderator) permission. The dedicated `u_patreon_notify` perm was introduced in 1.2.4 (issue #19) so admins can prevent moderators from receiving these notifications.

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

### "Settings" mode
- **API Credentials:** Client ID, Client Secret, Creator tokens, Campaign ID with "Fetch" button
- **Webhook:** URL display (copyable), secret field, Register/Check/Test buttons
- **Tier Mapping:** "Fetch Tiers" button loads tiers from API with names; admin ticks one or more phpBB groups per tier (1.3.0+, issue #5)
- **Grace Period:** configurable days before demotion
- **Default Group toggle:** optionally makes a tier's (alphabetically-first, if multiple) mapped group the patron's default group
- **Supporters Page toggle:** enables the public `/patreon/supporters` page and its pledge-amount sub-toggle
- **Linked Users:** paginated table (25/page, 1.3.0+, issue #22) showing username, Patreon ID, tier, status, pledge, timestamps
- **Sync Now:** manual full reconciliation button
- **Collapsible Help:** every section has a "How does this work?" toggle with detailed explanation

### "Patron Stats" mode (1.3.0+, issue #4)
Read-only overview computed live from `patreon_sync`: active/declined patron counts, total monthly pledge amount, active patrons per tier.

### "bbAccounts Integration" mode (1.2.4+)
Credit rule CRUD (expense account, wallet account, amount-per-dollar) and a "Run credit now" button. See STEP 7 in `USERDOC.md`.

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
