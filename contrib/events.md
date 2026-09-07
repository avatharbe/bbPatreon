# bbPatreon Extension — Events & Integration Points

## 1. Own Events & API (emitted by this extension)

This section is the public API contract. These are the events and services that bbPatreon deliberately exposes so that *other* extensions can integrate with it. If you are building an extension and want to react to pledge changes, display patron status, or query linked Patreon accounts, this is where to look. Changing anything listed here is a breaking change and requires a major version bump.

### 1.1 PHP Events

#### `avathar.bbpatreon.pledge_changed`

Fired after a Patreon webhook pledge event (create, update, or delete) has been fully processed — the sync table is updated, group membership has been adjusted, and the webhook has been logged. Use this to trigger downstream actions in response to revenue changes (e.g. award badges, send PMs, update stats dashboards, notify Discord).

- **Placement:** `controller\webhook::handle()`
- **Since:** 1.0.0
- **Arguments:**
  - `event_type` (string) — Webhook trigger: `members:pledge:create`, `members:pledge:update`, or `members:pledge:delete`
  - `user_id` (int|null) — phpBB user ID if the Patreon account is linked to a forum user, `null` otherwise
  - `patreon_user_id` (string) — Patreon user ID
  - `patron_status` (string) — `active_patron`, `declined_patron`, `former_patron`, or `pending_link`
  - `tier_id` (string) — Patreon tier ID (empty string if no active tier)
  - `tier_label` (string) — Human-readable tier name (empty string if no active tier)
  - `pledge_cents` (int) — Pledge amount in cents (0 if cancelled)
- **Known listeners:** none

**Example listener:**
```php
public static function getSubscribedEvents()
{
    return array(
        'avathar.bbpatreon.pledge_changed' => 'on_pledge_changed',
    );
}

public function on_pledge_changed($event)
{
    $event_type = $event['event_type'];
    $user_id    = $event['user_id'];
    $tier_label = $event['tier_label'];
    $cents      = $event['pledge_cents'];

    if ($user_id && $event_type === 'members:pledge:create')
    {
        // New patron — send a welcome PM, award a badge, etc.
    }
}
```

#### `avathar.bbpatreon.tiers_updated`

Fired after the ACP "Fetch Tiers" action has refreshed the tier catalogue from Patreon (`patreon_tiers` upserted). There's no equivalent event for individual tier reads — this fires only when the catalogue itself changes, so a consumer knows when to invalidate a cache built from `tier_data_provider` (see 1.6) rather than re-fetching it on every page load.

- **Placement:** `controller\acp_controller::ExtractTiers()`
- **Since:** unreleased
- **Arguments:**
  - `tier_ids` (string[]) — Patreon tier IDs that were added or updated in this run
- **Known listeners:** none

### 1.2 Routes

| Route name | Path | Method | Purpose |
|---|---|---|---|
| `avathar_bbpatreon_webhook` | `/patreon/webhook` | POST | Patreon webhook receiver (HMAC-MD5 signed) |
| `avathar_bbpatreon_callback` | `/patreon/callback` | GET | OAuth callback — Patreon redirects here after user authorises |

### 1.3 Notification Types

| Type name | Recipients | Purpose |
|---|---|---|
| `avathar.bbpatreon.notification.type.patreon_linked` | Users with `u_patreon_notify` (default: ROLE_ADMIN_FULL only) | Sent when a user links their Patreon account via the UCP |

Since 1.2.4, recipients are gated by the new `u_patreon_notify` permission instead of the broad "any admin or moderator" rule used in 1.0.0 / 1.1.0. Admins can grant `u_patreon_notify` to moderator roles or specific groups via ACP → Permissions ("Miscellaneous" category).

### 1.4 Permissions

| Permission | Default-granted to | Purpose |
|---|---|---|
| `u_patreon_notify` | `ROLE_ADMIN_FULL` | Receive the patreon_linked notification (see 1.3) |

### 1.5 Database tables

| Table | Owner | Purpose |
|---|---|---|
| `phpbb_patreon_sync` | bbPatreon | Per-patron state — `patreon_user_id`, `tier_id`, `pledge_status`, `pledge_cents`, sync timestamps, public-display opt-in flags. Primary key is `patreon_user_id`; the link to phpBB's `user_id` lives in core's `phpbb_oauth_accounts` (`provider='patreon'`, `oauth_provider_id = patreon_user_id`). |
| `phpbb_patreon_tiers` | bbPatreon | Static tier catalogue — `tier_id`, `tier_label`, `amount_cents`, mapped phpBB `group_id`, published flag. |
| `phpbb_bbpatreon_credit_rules` | bbPatreon (1.2.4+) | Admin-configured bbAccounts integration rules. One row per `(expense_account_id, wallet_account_id, amount_per_dollar)` mapping. Drives the recurring bbAccounts journal entries posted to active patrons. |
| `phpbb_bbpatreon_credit_log` | bbPatreon (1.2.4+) | Outbox idempotency log for the bbAccounts integration. UNIQUE KEY on `(rule_id, user_id, period)` — guarantees at most one journal entry per (rule, patron, calendar month). Each row carries a back-link to the `phpbb_bbaccounts_journal.journal_id` it represents. |

### 1.6 Public Services

#### `avathar.bbpatreon.service.patron_data_provider`

The supported way for another extension to read opted-in public patron data — use this instead of querying `phpbb_patreon_sync` directly. Consent is enforced inside the service (only `show_public = 1` + `active_patron` rows are ever returned); the board owner cannot override individual user consent, and neither can a caller of this service.

- **Class:** `\avathar\bbpatreon\service\patron_data_provider`
- **Since:** unreleased
- **Methods:**
  - `get_public_supporters(): array` — one entry per opted-in active patron, pre-formatted for display: `user_id` (int), `username` (string, HTML — pre-rendered via phpBB's `get_username_string()`), `avatar` (string, HTML), `tier_label` (string), `group_name` (string, HTML — colour + built-in group translation already applied), `rank_title` (string), `pledge_amount` (string — formatted with the campaign currency, or `''` if amounts are disabled or the user didn't opt in to showing theirs). Ordered by tier amount descending, then username.
  - `get_public_supporters_count(): int` — count only, for lightweight display (e.g. a nav-link badge) without formatting every row.

**Example usage:**
```php
/** @var \avathar\bbpatreon\service\patron_data_provider $provider */
$provider = $phpbb_container->get('avathar.bbpatreon.service.patron_data_provider');

foreach ($provider->get_public_supporters() as $supporter)
{
    // render $supporter['username'], $supporter['tier_label'], etc.
    // in your own template — no query, no consent logic to duplicate.
}
```

#### `avathar.bbpatreon.service.tier_data_provider`

The supported way for another extension to read the published Patreon tier catalogue — e.g. to render a "Membership Tiers" page (via `phpbb/pages` or a custom controller) with "Subscribe on Patreon" links, without calling the Patreon API directly. Pair with the `avathar.bbpatreon.tiers_updated` event (see 1.1) to know when to invalidate anything you cache from it.

- **Class:** `\avathar\bbpatreon\service\tier_data_provider`
- **Since:** unreleased
- **Methods:**
  - `get_published_tiers(): array` — one entry per tier with `published = 1` (retired tiers are excluded), ordered cheapest first: `tier_id` (string), `tier_label` (string), `description` (string), `amount` (string — formatted with the tier's currency), `amount_cents` (int), `subscribe_url` (string — Patreon's "join at this tier" checkout link, or `''` if no campaign is configured yet).

**Example usage:**
```php
/** @var \avathar\bbpatreon\service\tier_data_provider $provider */
$provider = $phpbb_container->get('avathar.bbpatreon.service.tier_data_provider');

foreach ($provider->get_published_tiers() as $tier)
{
    // render $tier['tier_label'], $tier['amount'], link to $tier['subscribe_url']
}
```

### 1.7 Template Events

Hooks inside bbPatreon's own templates that other extensions can use to inject markup, without modifying bbPatreon's templates.

| Event | Placement | Since |
|---|---|---|
| `avathar_bbpatreon_supporters_body_before` | `supporters_body.html`, right after the page title | unreleased |
| `avathar_bbpatreon_supporters_body_after` | `supporters_body.html`, right after the supporters list panel | unreleased |

To hook into either, create `styles/<style>/template/event/<event_name>.html` in your own extension — no changes to bbPatreon required.

---

## 2. Services / Extensions Consumed (1.2.4+)

bbPatreon optionally consumes services exposed by other extensions when they are installed. All such consumptions are soft-coupled (nullable DI) — bbPatreon works fine when the other extension is absent.

| Service ID | Source extension | Used by | Purpose |
|---|---|---|---|
| `avathar.bbaccounts.service.ledger` | [bbAccounts](https://github.com/avatharbe/bbAccounts) | `service\bbaccounts_recorder` | Posts a balanced double-entry journal entry per (active rule, active-pledge patron, calendar month) tuple. Recorder is invoked by the nightly cron (`cron/task/sync::run`) and by the ACP "Run credit now" button. Skipped silently when the ledger service is not available in the container. |

bbPatreon does not subscribe to any events dispatched by third-party extensions.

---

## 3. phpBB Core Events & Template Events

phpBB itself fires hundreds of named events at key moments — when a post is rendered, when a page loads, when a user is deleted, and so on. Extensions hook into these events without modifying any phpBB core files.

**PHP events** are fired from within phpBB's PHP code. Your extension subscribes to them by registering a listener class (implementing `EventSubscriberInterface`). When the event fires, phpBB passes a data object containing variables you can read and write.

**Template events** are fired from within phpBB's HTML templates. Your extension hooks into them simply by creating an HTML file whose name matches the event, placed at `styles/.../template/event/<event_name>.html`.

This section lists every phpBB hook that bbPatreon uses internally to deliver its functionality.

### 3.1 PHP Events — Event listener (`event/listener.php`)

| phpBB Core Event | Handler | Purpose |
|---|---|---|
| `core.user_setup` | `load_language_on_setup()` | Load the bbpatreon language file on every page |
| `core.page_header` | `add_page_header_links()` | Inject the "Supporters" navbar link when the public supporters page is enabled |
| `core.memberlist_team_modify_template_vars` | `add_patreon_to_team()` | Inject Patreon tier badge into the "The Team" page row for active patrons |
| `core.oauth_login_after_check_if_provider_id_has_match` | `on_oauth_login()` | After phpBB matches an OAuth account link, fetch the user's Patreon tier and sync their group membership |
| `core.permissions` | `on_permissions()` | Register `u_patreon_notify` in phpBB's permission MASK UI so admins can grant it via the role/group/user tabs (1.2.4+) |

### 3.2 Template Events

None. bbPatreon does not use template events. The UCP and ACP pages are rendered via dedicated modules and templates (`ucp_bbpatreon_body.html`, `acp_bbpatreon_body.html`).
