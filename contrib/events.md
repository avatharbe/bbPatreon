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

### 1.2 Routes

| Route name | Path | Method | Purpose |
|---|---|---|---|
| `avathar_bbpatreon_webhook` | `/patreon/webhook` | POST | Patreon webhook receiver (HMAC-MD5 signed) |
| `avathar_bbpatreon_callback` | `/patreon/callback` | GET | OAuth callback — Patreon redirects here after user authorises |

### 1.3 Notification Types

| Type name | Recipients | Purpose |
|---|---|---|
| `avathar.bbpatreon.notification.type.patreon_linked` | Admins and moderators | Sent when a user links their Patreon account via the UCP |

---

## 2. Events Subscribed from Other Extensions

Extensions can listen to each other's events or consume each other's services. This section documents every place where bbPatreon reaches *out* to another extension.

None. bbPatreon does not subscribe to any events dispatched by third-party extensions.

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
| `core.oauth_login_after_check_if_provider_id_has_match` | `on_oauth_login()` | After phpBB matches an OAuth account link, fetch the user's Patreon tier and sync their group membership |

### 3.2 Template Events

None. bbPatreon does not use template events. The UCP and ACP pages are rendered via dedicated modules and templates (`ucp_bbpatreon_body.html`, `acp_bbpatreon_body.html`).
