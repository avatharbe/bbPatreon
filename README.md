bbPatreon for phpBB 3.3
==========

Patreon integration for phpBB — link patron accounts via OAuth and automatically manage forum group membership based on pledge tiers.
Developed and maintained by [Avathar.be](https://www.avathar.be).

#### Version
1.0.0-RC4

[![Tests](https://github.com/avatharbe/bbpatreon/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/avatharbe/bbpatreon/actions/workflows/tests.yml)

#### Support
- [Support forum](https://www.avathar.be/forum)

#### Requirements
- phpBB 3.3.0 or higher
- PHP 8.1 or higher
- PHP curl extension

#### Features

**Account linking**
- Users link their Patreon account from the UCP via OAuth 2.0
- Unlink from UCP with immediate group demotion

**Tier-based group sync**
- Automatic phpBB group assignment based on Patreon pledge tier
- Tier-to-group mapping configured in the ACP with "Fetch Tiers" button (no manual ID lookup)
- Grace period option: delay group removal after a patron cancels or payment fails

**Real-time and scheduled sync**
- Real-time sync via Patreon webhooks (pledge create, update, delete)
- Nightly cron task for full reconciliation against the Patreon members API
- Manual "Sync Now" button in the ACP for on-demand reconciliation
- "Refresh my status" button in UCP so users can trigger an immediate re-sync (rate-limited to once per 5 minutes)

**UCP patron dashboard**
- Tier name, pledge status (color-coded), pledge amount, assigned forum group, and last sync time
- Human-readable status labels (Active Patron, Payment Declined, Former Patron, Pending)

**Public supporters page**
- Public page at `/patreon/supporters` listing opted-in active patrons
- Shows avatar, username (coloured), rank, group (coloured), tier name, and optionally pledge amount
- ACP master switch to enable/disable the page and the pledge amount column
- UCP opt-in checkboxes: "Show me as a supporter" and "Show my pledge amount" — both default off
- Link in the navbar sandwich menu when enabled
- Patreon tier badge on the "The Team" page for active patrons

**Administration**
- "Fetch Campaign ID" button in the ACP (auto-detects from the API)
- Webhook management: register via API or manually via the Patreon portal, with "Check Status" and "Test Ping" buttons
- Notification to admins and moderators when a user links their Patreon account
- Linked users overview in the ACP showing coloured usernames, tier, status, pledge amount, and sync timestamps
- Creator access token auto-refresh on expiry
- Collapsible help text throughout the ACP explaining how each section works

#### Languages supported
- Dutch, English, French, German, Portuguese, Spanish

### Changelog
- 1.0.0-RC4
  - Public supporters page at `/patreon/supporters` with avatar, rank, coloured group, tier (#2)
  - Optional pledge amount column on supporters page, gated by ACP toggle + UCP opt-in (#2)
  - Patreon tier badge on "The Team" page for active patrons (#2)
  - Supporters page link in navbar sandwich menu (#2)
  - ACP: coloured usernames in linked users table
  - UCP: "Refresh my status" re-sync button with 5-minute rate limit (#3)
  - UCP: show assigned forum group, human-readable status labels, last sync time (#3)
  - UCP: color-coded pledge status (active/declined/former/pending) (#3)
  - UCP: "Show me as a supporter" and "Show my pledge amount" opt-in checkboxes (#2)
  - Migrations: `show_public`, `show_pledge_public` columns, supporters page config keys
  - CI: PHPUnit 9.x on PHP 8.1-8.4 with MySQL, PostgreSQL, and Windows
  
- 1.0.0-dev
  - Initial release
  - OAuth 2.0 account linking via custom PHPoAuthLib service (works with `auth_method = db`)
  - Patreon API v2 client with creator token auto-refresh
  - Tier-to-group mapper with grace period support
  - Dedicated `patreon_tiers` table for tier metadata and group mapping
  - Webhook receiver with HMAC-MD5 signature validation
  - Nightly cron reconciliation task
  - ACP: API credentials, webhook management, tier mapping, linked users table
  - UCP: link/unlink Patreon account, view tier and pledge status
  - Notification type for admin/moderator alerts on account linking
  - GitHub Actions CI (PHP 8.1-8.4) and EPV validation

### Installation
1. [Download the latest release](https://github.com/avatharbe/bbpatreon/releases) and unzip it.
2. Copy the entire contents from the unzipped folder to `/ext/avathar/bbpatreon/`.
3. Navigate in the ACP to `Customise -> Manage extensions`.
4. Find `bbPatreon` under "Disabled Extensions" and click `Enable`.

### Configuration
1. Navigate to `ACP -> Extensions -> Patreon Integration -> Settings`.
2. Enter your Patreon API credentials (Client ID, Client Secret, Creator tokens).
3. Click `Fetch` next to Campaign ID to auto-detect it.
4. Click `Fetch Tiers` and assign a phpBB group to each Patreon tier, then click `Submit`.
5. Register a webhook via the Patreon portal or the API button, and paste the secret.
6. Tell your members to visit `UCP -> Patreon` and click "Link your Patreon Account".

### Testing
See [contrib/TESTING.md](contrib/TESTING.md) for details on running the test suite.

### Uninstallation
1. Navigate in the ACP to `Customise -> Manage extensions`.
2. Click the `Disable` link for `bbPatreon`.
3. To permanently uninstall, click `Delete Data`, then delete the `bbpatreon` folder from `/ext/avathar/`.

### License
[GNU General Public License v2](http://opensource.org/licenses/GPL-2.0)

© 2026 - Avathar.be (Andy Vandenberghe)
