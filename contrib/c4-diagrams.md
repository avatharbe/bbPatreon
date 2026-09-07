# bbPatreon — C4 Architecture Diagrams (Mermaid)

## C4 Context Diagram

```mermaid
C4Context
    title System Context — bbPatreon

    Person(patron, "Forum User / Patron", "Existing phpBB user who is also a Patreon supporter")
    Person(admin, "Forum Admin", "Configures Patreon integration via ACP")

    System(forum, "avathar.be/forum", "phpBB 3.3 forum with bbPatreon extension installed")

    System_Ext(patreon, "Patreon Platform", "OAuth2 provider, Campaign API v2, Webhooks")
    System(bbaccounts, "bbAccounts (optional sibling ext)", "Double-entry ledger — receives monthly journal entries crediting active patrons' wallets")

    Rel(patron, forum, "Browses forum, links Patreon account via UCP")
    Rel(admin, forum, "Configures tiers, credentials, webhooks via ACP")
    Rel(forum, patreon, "OAuth redirect, API calls (members, campaigns, tiers)")
    Rel(patreon, forum, "Webhooks (pledge create/update/delete), OAuth callback")
    Rel(forum, bbaccounts, "1.2.4+: posts monthly journal entries via ledger service (soft-coupled)")
```

## C4 Container Diagram

```mermaid
C4Container
    title Container Diagram — bbPatreon Extension

    Person(patron, "Patron", "Forum user linking Patreon")
    Person(admin, "Admin", "Manages integration")

    System_Boundary(forum, "phpBB Forum") {
        Container(phpbb, "phpBB Core", "PHP", "Forum engine, user/group management, OAuth framework, cron scheduler")
        Container(ext, "bbPatreon Extension", "PHP", "Patreon integration: OAuth, webhooks, sync, group mapping, optional bbAccounts recorder")
        Container(bbacc, "bbAccounts Extension (optional)", "PHP", "Double-entry ledger; receives credit journal entries from bbPatreon when both extensions are installed")
        ContainerDb(db, "MySQL Database", "MySQL", "phpBB tables + bbPatreon tables (patreon_sync, patreon_tiers, patreon_tier_groups, bbpatreon_credit_rules, bbpatreon_credit_log) + bbAccounts tables")
    }

    System_Ext(patreon, "Patreon API v2", "OAuth2, Campaigns, Members, Webhooks")

    Rel(patron, phpbb, "UCP: link/unlink Patreon", "HTTPS")
    Rel(admin, phpbb, "ACP: configure tiers, credentials, credit rules", "HTTPS")
    Rel(phpbb, ext, "Delegates to extension controllers & services")
    Rel(ext, db, "Reads/writes sync state, config, group membership, credit_rules, credit_log")
    Rel(ext, patreon, "OAuth flow, GET members/campaigns/tiers, POST webhook registration", "HTTPS")
    Rel(patreon, ext, "POST /patreon/webhook (pledge events)", "HTTPS")
    Rel(ext, bbacc, "Posts journal entries via @?avathar.bbaccounts.service.ledger (1.2.4+, nullable DI)")
```

## C4 Component Diagram

```mermaid
C4Component
    title Component Diagram — bbPatreon Extension

    Container_Boundary(ext, "bbPatreon Extension") {
        Component(ucp, "UCP Controller", "PHP", "Link/unlink Patreon account, OAuth redirect & callback processing, supporters opt-in")
        Component(acp, "ACP Controller", "PHP", "Settings, tier→groups checkbox mapping (1.3.0+), webhook management, manual sync, paginated linked users (1.3.0+)")
        Component(acp_stats, "Patron Stats ACP Controller", "PHP", "1.3.0+: read-only patron/pledge/per-tier overview")
        Component(acp_bbacc, "bbAccounts ACP Controller", "PHP", "1.2.4+: rule CRUD + 'Run credit now' button for the bbAccounts integration mode")
        Component(supporters, "Supporters Controller", "PHP", "GET /patreon/supporters — public page, delegates to patron_data_provider")
        Component(webhook, "Webhook Controller", "PHP", "POST /patreon/webhook — validates HMAC-MD5 signature, dispatches pledge events")
        Component(callback, "Callback Controller", "PHP", "GET /patreon/callback — forwards OAuth code to UCP")
        Component(oauth_svc, "OAuth Service", "PHP", "PHPoAuthLib service for Patreon OAuth2 endpoints")
        Component(api_client, "API Client", "PHP", "Curl-based Patreon API v2 wrapper, auto-refreshes on 401")
        Component(group_mapper, "Group Mapper", "PHP", "1.3.0+: resolves tier_id to [phpBB group_id, ...] (many-to-many), promotes/demotes users across all mapped groups, optional default-group toggle (1.2.4+, uses alphabetically-first group)")
        Component(patron_provider, "Patron Data Provider", "PHP", "1.3.0+: public service — opted-in supporter data, consent enforced internally")
        Component(tier_provider, "Tier Data Provider", "PHP", "1.3.0+: public service — published tier catalogue + Patreon subscribe URLs")
        Component(recorder, "bbAccounts Recorder", "PHP", "1.2.4+: posts monthly journal entries via @?avathar.bbaccounts.service.ledger; outbox idempotency via bbpatreon_credit_log")
        Component(cron, "Cron Sync Task", "PHP", "Nightly reconciliation — paginated member fetch, group fix-up, grace enforcement, end-of-run bbAccounts credit invocation")
        Component(notification, "Notification", "PHP", "Alerts users with u_patreon_notify (1.2.4+; default admins only) when a user links Patreon")
        Component(listener, "Event Listener", "PHP", "Hooks into phpBB events: language load, OAuth login sync, navbar/team-page injection, core.permissions registration")
    }

    ContainerDb(db, "Database", "MySQL", "phpbb_patreon_sync, phpbb_patreon_tiers, phpbb_patreon_tier_groups, phpbb_bbpatreon_credit_rules, phpbb_bbpatreon_credit_log, phpbb_oauth_accounts, phpbb_config")
    System_Ext(patreon, "Patreon API v2", "OAuth2 + REST API + Webhooks")
    System(bbacc_ledger, "bbAccounts Ledger Service (optional)", "@avathar.bbaccounts.service.ledger — accepts balanced double-entry journal entries from sibling extensions")

    Rel(ucp, oauth_svc, "Initiates OAuth redirect")
    Rel(callback, ucp, "Forwards OAuth code")
    Rel(ucp, api_client, "Fetches Patreon identity & member data")
    Rel(ucp, group_mapper, "Assigns group on link")
    Rel(ucp, notification, "Fires patreon_linked notification")
    Rel(listener, api_client, "Fetches tier on OAuth login")
    Rel(listener, group_mapper, "Syncs group on OAuth login")
    Rel(webhook, group_mapper, "Adjusts group on pledge event")
    Rel(webhook, db, "Upserts sync row")
    Rel(acp, api_client, "Fetches campaigns, tiers, webhook status")
    Rel(acp, group_mapper, "Manual sync triggers group updates")
    Rel(acp_bbacc, recorder, "Run credit now → credit_active_patrons_for_period")
    Rel(acp_bbacc, db, "CRUD on bbpatreon_credit_rules")
    Rel(acp_stats, db, "Live aggregate queries on patreon_sync")
    Rel(supporters, patron_provider, "get_public_supporters()")
    Rel(listener, patron_provider, "get_public_supporters_count() for the nav-link badge")
    Rel(cron, api_client, "GET /campaigns/{id}/members (paginated)")
    Rel(cron, group_mapper, "Reconciles all group memberships")
    Rel(cron, db, "Upserts sync rows, marks orphans")
    Rel(cron, recorder, "End-of-run: credit_active_patrons_for_period(gmdate('Y-m'))")
    Rel(group_mapper, db, "group_user_add / safe_group_user_del / group_user_attributes")
    Rel(recorder, db, "Reads credit_rules + patreon_sync, writes credit_log")
    Rel(recorder, bbacc_ledger, "create_entry(...) per (rule × patron × period)")
    Rel(api_client, patreon, "HTTPS REST calls")
    Rel(oauth_svc, patreon, "OAuth2 authorize/token")
    Rel(patreon, webhook, "POST pledge events")
```

## Flow: OAuth Linking

```mermaid
sequenceDiagram
    actor User
    participant UCP as UCP Controller
    participant OAuth as OAuth Service
    participant Patreon as Patreon Platform
    participant CB as Callback Controller
    participant API as API Client
    participant GM as Group Mapper
    participant DB as Database
    participant Notif as Notification

    User->>UCP: Click "Link Patreon Account"
    UCP->>OAuth: Create PHPoAuthLib service
    OAuth->>Patreon: Redirect to /oauth2/authorize
    Patreon->>User: Show consent screen
    User->>Patreon: Authorize
    Patreon->>CB: GET /patreon/callback?code=...
    CB->>UCP: Forward code
    UCP->>OAuth: Exchange code for token
    OAuth->>Patreon: POST /api/oauth2/token
    Patreon-->>OAuth: Access token
    UCP->>Patreon: GET /api/oauth2/v2/identity
    Patreon-->>UCP: Patreon user ID
    UCP->>DB: INSERT phpbb_oauth_accounts
    UCP->>API: get_campaign_members()
    API->>Patreon: GET /campaigns/{id}/members
    Patreon-->>API: Member data (tier, status, pledge)
    API-->>UCP: Member record
    UCP->>DB: UPSERT phpbb_patreon_sync
    UCP->>GM: assign group for tier
    GM->>DB: group_user_add()
    UCP->>Notif: Fire patreon_linked
    Notif->>DB: Notify admins/mods
    UCP-->>User: Show linked status & tier
```

## Flow: Webhook Pledge Event

```mermaid
sequenceDiagram
    participant Patreon as Patreon Platform
    participant WH as Webhook Controller
    participant GM as Group Mapper
    participant DB as Database

    Patreon->>WH: POST /patreon/webhook
    Note over WH: X-Patreon-Signature header
    WH->>WH: Validate HMAC-MD5 signature

    alt Signature invalid
        WH->>WH: Log warning
        WH-->>Patreon: 200 OK (prevent retry storm)
    end

    alt members:pledge:create
        WH->>DB: UPSERT phpbb_patreon_sync (active_patron)
        WH->>GM: Promote user to tier group
        GM->>DB: group_user_add()
    else members:pledge:update
        WH->>DB: UPDATE phpbb_patreon_sync (new tier/status)
        WH->>GM: Re-evaluate tier, swap groups if changed
        GM->>DB: group_user_del() old + group_user_add() new
    else members:pledge:delete
        WH->>DB: UPDATE phpbb_patreon_sync (former_patron)
        alt Grace period = 0
            WH->>GM: Demote immediately
            GM->>DB: group_user_del()
        else Grace period > 0
            Note over WH: Skip demotion, cron enforces later
        end
    end

    WH-->>Patreon: 200 OK
```

## Flow: Nightly Cron Reconciliation

```mermaid
sequenceDiagram
    participant Cron as Cron Sync Task
    participant API as API Client
    participant Patreon as Patreon API v2
    participant GM as Group Mapper
    participant DB as Database

    Note over Cron: Runs every 24 hours
    Cron->>API: get_campaign_members()
    loop Paginated (max 1000/page)
        API->>Patreon: GET /campaigns/{id}/members
        Patreon-->>API: Page of members
    end
    API-->>Cron: All members

    loop Each member
        Cron->>DB: UPSERT phpbb_patreon_sync
        Cron->>GM: Sync group if user is linked
        GM->>DB: Adjust group membership
    end

    Cron->>DB: Mark orphaned rows as former_patron
    Cron->>DB: Find expired grace period members
    loop Each expired member
        Cron->>GM: Demote from patron groups
        GM->>DB: group_user_del()
    end

    Cron->>DB: Log summary to admin log
    Cron->>DB: Update patreon_last_cron_sync
```

## Flow: Monthly bbAccounts credit (1.2.4+)

```mermaid
sequenceDiagram
    participant Trigger as Cron / "Run credit now" button
    participant Rec as bbAccounts Recorder
    participant DB as Database
    participant Ledger as bbAccounts ledger

    Note over Trigger,Ledger: Triggered nightly by cron with period = gmdate('Y-m'),<br/>or on-demand by ACP "Run credit now" with admin-picked period

    Trigger->>Rec: credit_active_patrons_for_period(period)

    alt bbAccounts not installed (ledger == null)
        Rec-->>Trigger: {credited: 0, skipped_no_rules: 1}
    end

    Rec->>DB: SELECT active rules from bbpatreon_credit_rules
    alt No active rules configured
        Rec-->>Trigger: {credited: 0, skipped_no_rules: 1}
    end

    Rec->>DB: SELECT active-pledge patrons (JOIN patreon_sync × oauth_accounts on patreon_user_id)

    loop For each (rule × patron)
        Rec->>DB: SELECT bbpatreon_credit_log WHERE rule_id + user_id + period
        alt Row already exists
            Note over Rec: Skipped (already credited this period)
        else No row
            Note over Rec: Begin DB transaction
            Rec->>Ledger: create_entry(time(), description, lines,<br/>'auto', rule_id, 'avathar.bbpatreon')
            Note right of Ledger: DR expense_account amount<br/>CR wallet_account amount<br/>(subledger_user_id = patron user_id)
            Ledger-->>Rec: journal_id
            Rec->>DB: INSERT bbpatreon_credit_log<br/>(rule_id, user_id, period, journal_id)
            Note over Rec: Commit (or rollback both on any throw)
        end
    end

    Rec-->>Trigger: {credited, skipped_already_credited, errors[]}
    Trigger->>DB: Log to admin log (LOG_BBPATREON_CREDIT_RUN[_MANUAL])
```
