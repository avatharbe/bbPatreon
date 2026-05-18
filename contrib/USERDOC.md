# bbPatreon — User Documentation

Patreon integration for phpBB 3.3. Links Patreon accounts to forum users via OAuth and automatically manages phpBB group membership based on Patreon pledge tiers.

## Requirements and Installation
- phpBB 3.3.0 or later
- PHP 8.1+ with the `curl` extension enabled
- A Patreon account with a Creator page
- HTTPS on your forum (required by Patreon OAuth)

For Installation : 
1. Download or clone the extension to `ext/avathar/bbpatreon/`
2. In the ACP, go to **Customise > Extensions** and enable **bbPatreon**
3. The migration will create the `patreon_sync` database table and add the necessary configuration keys

---

## STEP 1: Create a Patreon API Client
Before configuring the extension, you need to create an OAuth client on Patreon.
1. Go to [https://www.patreon.com/portal/registration/register-clients](https://www.patreon.com/portal/registration/register-clients)

<img width="2010" height="592" alt="create client1" src="https://github.com/user-attachments/assets/6174c8c3-8b86-4636-88eb-902b4e4efcad" />

2. Click **Create Client**
3. Fill in:
   - **App Name:** Your forum name (e.g. "Avathar Forum")
   - **Description:** Brief description
   - **App Category:** Community
   - **Redirect URIs:** `https://yourforumurlpath/patreon/callback`
  
   <img width="609" height="1031" alt="filling in client" src="https://github.com/user-attachments/assets/98ffcd47-1587-4bc5-8bf5-80fabec26470" />

     
5. After creating the client, note down:
   - **Client ID**
   - **Client Secret**
   - **Creator's Access Token**
   - **Creator's Refresh Token**

## STEP 2 : Set up phpBB

1. Go to your board ACP => Extensions -> Patreon Integration
2. Configure
   - Client ID
   - Client Secret
   - Creator's Access Token
    - Creator's Refresh Token
3. Press Submit
4. Find Your Campaign ID
   - Go to your board ACP => Extensions -> Patreon Integration
   - Your Campaign ID can be found by making an API call or by checking the URL when you visit your campaign page in the Patreon creator dashboard. It is the numeric ID in URLs like `https://www.patreon.com/api/oauth2/v2/campaigns/XXXXXXX`.
   - Alternatively, use the Creator Access Token to call: `curl -H "Authorization: Bearer YOUR_CLIENT_ACCESS_TOKEN" https://www.patreon.com/api/oauth2/v2/campaigns` : The `id` field in the json response is your Campaign ID.
5. Press Submit

<img width="1838" height="672" alt="API Credentials" src="https://github.com/user-attachments/assets/fbbf980f-d0a7-41f2-b6cd-0a51ce0614a4" />


## STEP 3 : Mapping Patreon Tiers to phpBB Usergroups

To map tiers to forum groups, you can simply click the Fetch Tiers button.

Alternatively these are visible in the API response:


To map tiers to forum groups, you need the Patreon Tier IDs. These are visible in the API response:
```
curl -g -H "Authorization: Bearer YOUR_CLIENT_TOKEN" "https://www.patreon.com/api/oauth2/v2/campaigns/CAMPAIGNID?include=tiers&fields[tier]=title,amount_cents"
```
Each tier in the `included` array has an `id` and a `title`.
```
{
  "data": {
    "id": "CAMPAIGNID",
    "type": "campaign",
    "attributes": {},
    "relationships": {
      "tiers": {
        "data": [
          {
            "id": "TIERID1",
            "type": "tier"
          },
          {
            "id": "TIERID2",
            "type": "tier"
          },
          {
            "id": "TIERID3",
            "type": "tier"
          }
        ]
      }
    }
  },
  "included": [
    {
      "id": "TIERID1",
      "type": "tier",
      "attributes": {
        "amount_cents": 0,
        "title": "Free"
      }
    },
    {
      "id": "TIERID2",
      "type": "tier",
      "attributes": {
        "amount_cents": 300,
        "title": "Tier 1 — Adventurer"
      }
    },
    {
      "id": "TIERID3",
      "type": "tier",
      "attributes": {
        "amount_cents": 600,
        "title": "Tier 2 — Champion"
      }
    }
  ],
  "links": {
    "self": "https://www.patreon.com/api/oauth2/v2/campaigns/CAMPAIGNID"
  }
}
```

To map the Patreon tiers to phpBB usergroups just select the **phpBB user Group** that maps to your tier. 

When a patron links their account or when a pledge event fires, the extension will:
- Add the user to the group matching their current tier
- Remove the user from any other patron-mapped groups they no longer belong to

**Grace Period:** The number of days to wait before removing a user from their patron group after they stop pledging. Set to `0` for immediate removal. During the grace period, the user keeps their group membership even though they are no longer an active patron.


<img width="1884" height="1140" alt="Tier Mapping" src="https://github.com/user-attachments/assets/fd398f30-da4d-464f-ae5e-6ead8d6758b6" />

## STEP 4 : Setting up Webhooks (optional as there is a daily cron job) 

What are Webhooks ? 
A webhook is a type of event-driven API. Rather than sending information in response to another app's request, a webhook sends information or performs a specific function in response to a trigger — like the time of day, clicking a button, or receiving a form submission. Since the application sending the data initiates the transfer, webhooks are often referred to as "reverse APIs." 

We are using Webhooks to allow Patreon to notify your forum in real-time when a patron creates, updates, or cancels a pledge. 

- **Webhook Secret:** find it in you Patreon page (under api section)
- **Register Webhook:** Click this button to register a webhook endpoint with Patreon. Your forum must be accessible at `https://yourforum.com/patreon/webhook`. The API credentials and Campaign ID must be saved first.

## STEP 5 : Permissions (1.2.4+)

By default, only forum **administrators** receive the *"X linked their Patreon account"* notification. Moderators no longer get it automatically (this was the behaviour in 1.0.0 / 1.1.0, removed in 1.2.4 in response to admin requests).

To extend the notification to specific moderators, groups, or users, grant them the new permission:

- **ACP → Permissions → Groups → \[pick a group]**, or **ACP → Permissions → Users → \[pick a user]**
- Scroll to the **"Miscellaneous"** category in the permission table
- Set **"bbPatreon: Receive Patreon-account-linked notifications"** to **Yes** → click **Apply**

The new permission is `u_patreon_notify`. It is added by the v1.2.4 migration and granted to `ROLE_ADMIN_FULL` by default.

## STEP 6 (Optional) : Make the tier group the patron's default group

By default, bbPatreon adds a patron to their tier-mapped group as a **secondary** group membership — the patron's *default* group (which controls username colour and rank in posts) is left unchanged.

If you want the tier-mapped group to ALSO become the patron's primary/default group (so their username takes on the group's colour and rank):

- **ACP → Extensions → bbPatreon → Settings**
- Tick **"Set tier group as default"** → Submit

On demotion (cancellation, grace-period expiry, or tier change), the patron's default group reverts to **Registered users**. Note: any custom default group the user set manually before being promoted is NOT preserved across promote/demote cycles.

## STEP 7 (Optional) : bbAccounts Integration

If you have the [bbAccounts](https://github.com/avatharbe/bbAccounts) extension installed, bbPatreon can credit active patrons' wallets with points (or any unit you configure in bbAccounts) every month. This works fine if bbAccounts is not installed — the integration is soft-coupled and silently inert.

### Setup

1. **In bbAccounts ACP → Chart of Accounts**, make sure you have:
   - An **expense** account (account type: `expense`) — e.g. `5009 Patron Reward Expense` in the POINTS currency pool. Create one if needed.
   - A **wallet** account (account type: `liability`, subledger type: `customer`) — usually the seeded `2100 User Wallets` account in the POINTS pool.
   - Both accounts must be in the **same currency pool**.

2. **In bbPatreon ACP → bbAccounts Integration**, click **"Add credit rule"**:
   - **Label:** descriptive name (e.g. `Forum POINTS reward`)
   - **Expense account:** pick the one from step 1 (only `expense` accounts appear in this dropdown)
   - **Wallet account:** pick the User Wallets account (only customer-subledger accounts appear)
   - **Amount per dollar:** how many units to credit per $1 pledged. For example, `100.00` means a $5/month pledge credits 500 POINTS per month; `1.00` means $5/month credits 5 POINTS.
   - **Active:** yes
   - Click **Submit**

3. **Trigger the first credit run:**
   - Either wait for the nightly cron, or
   - Use the **"Run credit now"** form on the same ACP page. Default period is the current UTC month. Result panel shows how many patrons were credited / skipped.

### How it works

- Per active-pledge patron (status `active_patron` or `declined_patron`) with `pledge_cents > 0` who has linked their Patreon account via the UCP OAuth flow.
- One bbAccounts journal entry per (rule × patron × month) tuple. The journal entry is balanced: DR your expense account, CR User Wallets (with the patron as the subledger).
- Idempotent: repeat runs in the same period never double-credit. Tracked via the `bbpatreon_credit_log` table with a UNIQUE KEY on `(rule_id, user_id, period)`.
- Cancellation: when a patron's pledge ends, the cron simply stops crediting them in future periods. Past credits stand — no retroactive reversal.
- Mid-month tier changes use the patron's CURRENT `pledge_cents` × the rule's rate on the next cron run.
- Multiple rules are allowed — useful if you want to credit two different pools (e.g. forum POINTS + a separate USD-denominated patron-only wallet). Each rule fires once per patron per period.

### Where to see the results

- **bbAccounts → Reports → User Statement → \[pick a patron]** shows every credit posted to that patron's User Wallets account, with the rule label in the description.
- **ACP → Admin Logs** has a per-run summary entry (`LOG_BBPATREON_CREDIT_RUN_MANUAL` for button-driven, `LOG_BBPATREON_CREDIT_RUN` for cron-driven) with the period and credited / skipped counts.

