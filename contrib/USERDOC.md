# bbPatreon — User Documentation

Patreon integration for phpBB 3.3. Links Patreon accounts to forum users via OAuth and automatically manages phpBB group membership based on Patreon pledge tiers.

---

## Requirements

- phpBB 3.3.0 or later
- PHP 8.1+ with the `curl` extension enabled
- A Patreon account with a Creator page
- HTTPS on your forum (required by Patreon OAuth)

---

## Installation

1. Download or clone the extension to `ext/avathar/bbpatreon/`
2. In the ACP, go to **Customise > Extensions** and enable **bbPatreon**
3. The migration will create the `patreon_sync` database table and add the necessary configuration keys

---

## Patreon Setup

Before configuring the extension, you need to create an OAuth client on Patreon.

### Create a Patreon API Client

1. Go to [https://www.patreon.com/portal/registration/register-clients](https://www.patreon.com/portal/registration/register-clients)
2. Click **Create Client**
3. Fill in:
   - **App Name:** Your forum name (e.g. "Avathar Forum")
   - **Description:** Brief description
   - **App Category:** Community
   - **Redirect URIs:** `https://yourforumurlpath/patreon/callback`
     
4. After creating the client, note down:
   - **Client ID**
   - **Client Secret**
   - **Creator's Access Token**
   - **Creator's Refresh Token**

### Find Your Campaign ID

Your Campaign ID can be found by making an API call or by checking the URL when you visit your campaign page in the Patreon creator dashboard. It is the numeric ID in URLs like `https://www.patreon.com/api/oauth2/v2/campaigns/XXXXXXX`.

Alternatively, use the Creator Access Token to call:
```
curl -H "Authorization: Bearer YOUR_TOKEN" https://www.patreon.com/api/oauth2/v2/campaigns
```
The `id` field in the response is your Campaign ID.

### Find Your Tier IDs

To map tiers to forum groups, you need the Patreon Tier IDs. These are visible in the API response:
```
curl -H "Authorization: Bearer YOUR_TOKEN" \
  "https://www.patreon.com/api/oauth2/v2/campaigns/YOUR_CAMPAIGN_ID?include=tiers&fields[tier]=title,amount_cents"
```
Each tier in the `included` array has an `id` and a `title`.

---

## ACP Configuration

Go to **ACP > Extensions > Patreon Integration > Settings**.

### API Credentials

| Field | Description |
|---|---|
| **Client ID** | The OAuth Client ID from the Patreon Developer Portal |
| **Client Secret** | The OAuth Client Secret |
| **Creator Access Token** | Your creator access token (used for server-to-server API calls like fetching member data) |
| **Creator Refresh Token** | Used to automatically refresh the access token when it expires |
| **Campaign ID** | Your Patreon campaign's numeric ID |

Click **Submit** to save.

### Webhook

Webhooks allow Patreon to notify your forum in real-time when a patron creates, updates, or cancels a pledge.

- **Webhook Secret:** find it in you Patreon page (under api section)
- **Register Webhook:** Click this button to register a webhook endpoint with Patreon. Your forum must be accessible at `https://yourforum.com/patreon/webhook`. The API credentials and Campaign ID must be saved first.

### Tier to Group Mapping

This is the core feature — mapping Patreon tiers to phpBB usergroups.

1. Click **Add Tier Mapping**
2. Enter the **Patreon Tier ID** (the numeric ID from the API)
3. Select the **phpBB Group** to assign patrons of that tier to
4. Repeat for each tier you want to map
5. Click **Submit** to save

When a patron links their account or when a pledge event fires, the extension will:
- Add the user to the group matching their current tier
- Remove the user from any other patron-mapped groups they no longer belong to

**Grace Period:** The number of days to wait before removing a user from their patron group after they stop pledging. Set to `0` for immediate removal. During the grace period, the user keeps their group membership even though they are no longer an active patron.

### Linked Users

The ACP shows a table of all forum users who have linked their Patreon accounts, including:
- Username
- Patreon User ID
- Current tier
- Pledge status (`active_patron`, `declined_patron`, `former_patron`, `pending_link`)
- Pledge amount
- Last webhook and last sync timestamps

### Manual Sync

Click **Sync Now** to trigger an immediate full reconciliation against the Patreon API. This fetches all campaign members, updates the sync table, and re-evaluates group assignments. Useful after changing tier mappings or troubleshooting.

---

## How It Works — User Perspective

### Linking a Patreon Account

1. The user logs into the forum with their normal username and password
2. They go to **User Control Panel > Patreon**
3. They click **Link your Patreon Account**
4. They are redirected to Patreon to authorise the connection
5. After authorising, they are returned to the forum
6. The extension fetches their patron status and tier from the Patreon API
7. They are automatically added to the corresponding phpBB group
8. The UCP page now shows their linked status, tier, and pledge amount

### Unlinking

1. The user goes to **User Control Panel > Patreon**
2. They click **Unlink Patreon Account** and confirm
3. Their patron sync data is removed
4. They are immediately removed from all patron-mapped groups (no grace period on manual unlink)

---

## Sync Mechanisms

The extension uses three mechanisms to keep group membership in sync:

### 1. OAuth Link (on demand)

When a user links their Patreon account, the extension immediately queries the Patreon API for their membership status and assigns the appropriate group.

### 2. Webhooks (near real-time)

Patreon sends webhook events when:
- **`members:pledge:create`** — A patron starts pledging. The user is added to their tier's group.
- **`members:pledge:update`** — A patron changes their tier. The user is moved to the new tier's group.
- **`members:pledge:delete`** — A patron cancels. If the grace period is 0, they are demoted immediately. Otherwise, demotion is deferred to the cron task.

Webhooks are validated using HMAC-MD5 signatures. Invalid signatures are logged and ignored.

### 3. Nightly Cron (safety net)

A cron task runs every 24 hours to perform a full reconciliation:
- Fetches all campaign members from the Patreon API
- Updates the sync table for every member
- Re-evaluates and corrects group assignments for all linked users
- Marks members who are no longer in the API response as `former_patron`
- Enforces grace period demotions for patrons whose grace period has expired

The cron task only runs when the Creator Access Token and Campaign ID are configured.

---

## Troubleshooting

### Users are not being assigned to groups

- Verify the **Tier to Group Mapping** in the ACP has the correct Tier IDs
- Check that the **Creator Access Token** is valid (try the manual sync)
- Look at the **Admin Log** (ACP > Maintenance > Admin Log) for Patreon-related entries

### OAuth login fails

- Verify the **Redirect URI** in your Patreon API client matches exactly: `https://yourforum.com/ucp.php?mode=login&login=external&oauth_service=patreon`
- Ensure **Client ID** and **Client Secret** are correct in both the ACP and phpBB's OAuth settings
- Check that your forum uses HTTPS

### Webhooks are not working

- Verify your forum is accessible at `https://yourforum.com/patreon/webhook` from the internet
- Check the **Admin Log** for signature validation failures
- Re-register the webhook from the ACP if the secret has changed
- Test with a manual curl request (see Testing below)

### Creator access token expired

The extension automatically refreshes the token on 401 responses. If the refresh token has also expired, you will need to generate new tokens from the Patreon Developer Portal and update them in the ACP.

### Grace period not working as expected

- Grace period only applies to webhook `pledge:delete` events and cron reconciliation
- Manual unlink from the UCP always demotes immediately
- The cron task must run for deferred demotions to take effect — verify your forum's cron is running (check `patreon_last_cron_sync` via the ACP linked users panel)

---

## Testing Webhooks

You can test the webhook endpoint without real Patreon events by generating a signed payload:

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

Replace `YOUR_TIER_ID` with a real tier ID from your mapping, and `PATREON_USER_ID` with a Patreon user ID that is linked to a forum account in `phpbb_oauth_accounts`.

---

## Permissions

The extension uses standard phpBB permissions:

- **ACP access:** Requires `acl_a_board` (standard board administration permission)
- **UCP access:** All registered users can access the Patreon UCP panel (no special permission required)

Patron-only forum areas are managed through phpBB's native group-based forum permissions. Once a user is added to a patron group, they automatically gain whatever permissions that group has.

---

## Uninstalling

1. In the ACP, go to **Customise > Extensions**
2. Disable **bbPatreon**
3. Optionally purge the extension data (this drops the `patreon_sync` table and removes all config keys)

Note: Disabling the extension does not remove users from patron groups. If you want to clean up group memberships before uninstalling, run a manual sync with all tier mappings removed first.
