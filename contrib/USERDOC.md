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
     
4. After creating the client, note down:
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

## STEP 4 : Setting up Webhooks (optional) 

What are Webhooks ? 
A webhook is a type of event-driven API. Rather than sending information in response to another app's request, a webhook sends information or performs a specific function in response to a trigger — like the time of day, clicking a button, or receiving a form submission. Since the application sending the data initiates the transfer, webhooks are often referred to as "reverse APIs." 

We are using Webhooks to allow Patreon to notify your forum in real-time when a patron creates, updates, or cancels a pledge. 

- **Webhook Secret:** find it in you Patreon page (under api section)
- **Register Webhook:** Click this button to register a webhook endpoint with Patreon. Your forum must be accessible at `https://yourforum.com/patreon/webhook`. The API credentials and Campaign ID must be saved first.

