# bbPatreon — C4 Architecture Diagrams (Mermaid)

## C4 Context Diagram

```mermaid
C4Context
    title System Context — bbPatreon

    Person(patron, "Forum User / Patron", "Existing phpBB user who is also a Patreon supporter")
    Person(admin, "Forum Admin", "Configures Patreon integration via ACP")

    System(forum, "avathar.be/forum", "phpBB 3.3 forum with bbPatreon extension installed")

    System_Ext(patreon, "Patreon Platform", "OAuth2 provider, Campaign API v2, Webhooks")

    Rel(patron, forum, "Browses forum, links Patreon account via UCP")
    Rel(admin, forum, "Configures tiers, credentials, webhooks via ACP")
    Rel(forum, patreon, "OAuth redirect, API calls (members, campaigns, tiers)")
    Rel(patreon, forum, "Webhooks (pledge create/update/delete), OAuth callback")
```

## C4 Container Diagram

```mermaid
C4Container
    title Container Diagram — bbPatreon Extension

    Person(patron, "Patron", "Forum user linking Patreon")
    Person(admin, "Admin", "Manages integration")

    System_Boundary(forum, "phpBB Forum") {
        Container(phpbb, "phpBB Core", "PHP", "Forum engine, user/group management, OAuth framework, cron scheduler")
        Container(ext, "bbPatreon Extension", "PHP", "Patreon integration: OAuth, webhooks, sync, group mapping")
        ContainerDb(db, "MySQL Database", "MySQL", "phpBB tables + phpbb_patreon_sync")
    }

    System_Ext(patreon, "Patreon API v2", "OAuth2, Campaigns, Members, Webhooks")

    Rel(patron, phpbb, "UCP: link/unlink Patreon", "HTTPS")
    Rel(admin, phpbb, "ACP: configure tiers & credentials", "HTTPS")
    Rel(phpbb, ext, "Delegates to extension controllers & services")
    Rel(ext, db, "Reads/writes sync state, config, group membership")
    Rel(ext, patreon, "OAuth flow, GET members/campaigns/tiers, POST webhook registration", "HTTPS")
    Rel(patreon, ext, "POST /patreon/webhook (pledge events)", "HTTPS")
```

## C4 Component Diagram

```mermaid
C4Component
    title Component Diagram — bbPatreon Extension

    Container_Boundary(ext, "bbPatreon Extension") {
        Component(ucp, "UCP Controller", "PHP", "Link/unlink Patreon account, OAuth redirect & callback processing")
        Component(acp, "ACP Controller", "PHP", "Settings, tier mapping, webhook management, manual sync, linked users")
        Component(webhook, "Webhook Controller", "PHP", "POST /patreon/webhook — validates HMAC-MD5 signature, dispatches pledge events")
        Component(callback, "Callback Controller", "PHP", "GET /patreon/callback — forwards OAuth code to UCP")
        Component(oauth_svc, "OAuth Service", "PHP", "PHPoAuthLib service for Patreon OAuth2 endpoints")
        Component(api_client, "API Client", "PHP", "Curl-based Patreon API v2 wrapper, auto-refreshes on 401")
        Component(group_mapper, "Group Mapper", "PHP", "Resolves tier_id to phpBB group_id, promotes/demotes users")
        Component(cron, "Cron Sync Task", "PHP", "Nightly reconciliation — paginated member fetch, group fix-up, grace enforcement")
        Component(notification, "Notification", "PHP", "Alerts admins/mods when a user links Patreon")
        Component(listener, "Event Listener", "PHP", "Hooks into phpBB OAuth login event to fetch tier and sync groups")
    }

    ContainerDb(db, "Database", "MySQL", "phpbb_patreon_sync, phpbb_oauth_accounts, phpbb_config")
    System_Ext(patreon, "Patreon API v2", "OAuth2 + REST API + Webhooks")

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
    Rel(cron, api_client, "GET /campaigns/{id}/members (paginated)")
    Rel(cron, group_mapper, "Reconciles all group memberships")
    Rel(cron, db, "Upserts sync rows, marks orphans")
    Rel(group_mapper, db, "group_user_add / group_user_del")
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
