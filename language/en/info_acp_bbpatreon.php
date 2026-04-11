<?php
/**
 *
 * Patreon Integration for phpBB.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_BBPATREON_TITLE'	=> 'Patreon Integration',
	'ACP_BBPATREON'			=> 'Settings',

	'ACP_BBPATREON_SETTING_SAVED'	=> 'Patreon settings have been saved successfully!',
	'ACP_BBPATREON_HELP'			=> 'How does this work?',

	// Overview panel
	'ACP_BBPATREON_OVERVIEW_TITLE'		=> 'What this extension does',
	'ACP_BBPATREON_OVERVIEW'			=> 'This extension connects your Patreon creator page to your forum. Forum members can link their Patreon account from their User Control Panel. Once linked, the extension automatically assigns them to phpBB usergroups based on the Patreon tier they are pledging at. When a patron upgrades, downgrades, or cancels their pledge, their group membership is updated accordingly. This lets you use phpBB\'s built-in group permissions to control access to patron-only forums, special ranks, or other features.',

	'ACP_BBPATREON_HOW_IT_WORKS_TITLE'	=> 'Setup steps',
	'ACP_BBPATREON_STEP_CREDENTIALS'	=> 'Enter your Patreon API credentials below. You get these by creating an OAuth client at <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">patreon.com/portal/registration/register-clients</a>. Set the redirect URI to: <strong>https://yourforum.com/ucp.php?mode=login&amp;login=external&amp;oauth_service=patreon</strong> (replace with your actual forum URL).',
	'ACP_BBPATREON_STEP_TIERS'			=> 'Map each Patreon tier to a phpBB usergroup in the Tier Mapping section. You can find your tier IDs via the Patreon API (see documentation). Then set your forum permissions on those groups as needed.',
	'ACP_BBPATREON_STEP_WEBHOOK'		=> 'Register a webhook so Patreon notifies your forum in real-time when pledges change. This is optional but recommended &mdash; without it, changes are only picked up by the nightly sync.',
	'ACP_BBPATREON_STEP_USERS'			=> 'Tell your members to visit their User Control Panel and click "Link your Patreon Account". Once linked, their tier and group are synced automatically.',

	'ACP_BBPATREON_SYNC_TITLE'			=> 'How syncing works',
	'ACP_BBPATREON_SYNC_EXPLAIN'		=> 'Group membership is kept up to date through three mechanisms: <strong>OAuth link</strong> (groups are assigned immediately when a user links their account), <strong>Webhooks</strong> (Patreon sends real-time notifications when a pledge is created, changed, or cancelled), and a <strong>Nightly cron task</strong> (a full reconciliation runs every 24 hours as a safety net, catching anything the webhooks may have missed). You can also trigger a manual sync from this page using the "Sync Now" button.',

	'ACP_BBPATREON_STATUSES_TITLE'		=> 'Pledge statuses',
	'ACP_BBPATREON_STATUS_ACTIVE'		=> 'Actively pledging and payment is current. User is assigned to their tier\'s group.',
	'ACP_BBPATREON_STATUS_DECLINED'		=> 'Payment failed but the pledge has not been cancelled yet. During the grace period the user keeps their group. If the payment is not resolved, they will be demoted when the grace period expires.',
	'ACP_BBPATREON_STATUS_FORMER'		=> 'Pledge was cancelled. The user will be removed from their patron group immediately (or after the grace period, if configured).',
	'ACP_BBPATREON_STATUS_PENDING'		=> 'Patreon account is linked to the forum but the user is not currently a patron of your campaign.',

	// API Credentials
	'ACP_BBPATREON_API_CREDENTIALS'			=> 'API Credentials',
	'ACP_BBPATREON_API_CREDENTIALS_EXPLAIN'	=> 'Enter the credentials from your Patreon API client. The Client ID and Secret are used for the user-facing OAuth login flow. The Creator tokens are used for server-to-server API calls (fetching member data, registering webhooks). All four values come from <a href="https://www.patreon.com/portal/registration/register-clients" target="_blank">your Patreon API client page</a>.',
	'ACP_BBPATREON_CLIENT_ID'				=> 'Client ID',
	'ACP_BBPATREON_CLIENT_ID_EXPLAIN'		=> 'The OAuth Client ID from your Patreon Developer Portal.',
	'ACP_BBPATREON_CLIENT_SECRET'			=> 'Client Secret',
	'ACP_BBPATREON_CREATOR_TOKEN'			=> 'Creator Access Token',
	'ACP_BBPATREON_CREATOR_TOKEN_EXPLAIN'	=> 'Used for server-to-server API calls (fetching members, registering webhooks). This token is automatically refreshed when it expires, as long as the refresh token is valid.',
	'ACP_BBPATREON_CREATOR_REFRESH'			=> 'Creator Refresh Token',
	'ACP_BBPATREON_CAMPAIGN_ID'				=> 'Campaign ID',
	'ACP_BBPATREON_CAMPAIGN_ID_EXPLAIN'		=> 'The numeric ID of your Patreon campaign. Save your creator tokens first, then click "Fetch" to auto-detect it.',
	'ACP_BBPATREON_FETCH_CAMPAIGN'			=> 'Fetch',
	'ACP_BBPATREON_CAMPAIGN_FETCHED'		=> 'Campaign ID fetched successfully: %s',
	'ACP_BBPATREON_CAMPAIGN_FETCH_ERROR'	=> 'Could not fetch campaign ID. Verify that the Creator Access Token is saved and valid.',

	// Webhook
	'ACP_BBPATREON_WEBHOOK'						=> 'Webhook',
	'ACP_BBPATREON_WEBHOOK_EXPLAIN'				=> 'Webhooks enable real-time sync. When a patron creates, updates, or cancels a pledge on Patreon, a signed HTTP request is sent to your forum which immediately updates their group membership. Without webhooks, changes are only picked up by the nightly cron task. Your forum must be reachable over HTTPS for webhooks to work.<br><br>There are two ways to register a webhook:<br><strong>Option A (recommended):</strong> Go to <a href="https://www.patreon.com/portal/registration/register-webhooks" target="_blank">patreon.com/portal/registration/register-webhooks</a>, create a webhook using the URL shown below, select the pledge triggers, and paste the secret shown by Patreon into the field below.<br><strong>Option B:</strong> Click the "Register via API" button. This requires the <code>w:campaigns.webhook</code> scope on your creator access token.',
	'ACP_BBPATREON_WEBHOOK_URL'					=> 'Your Webhook URL',
	'ACP_BBPATREON_WEBHOOK_URL_EXPLAIN'			=> 'Copy this URL into the Patreon webhook portal, or use it when registering via the API. Click the field to select it.',
	'ACP_BBPATREON_WEBHOOK_SECRET'				=> 'Webhook Secret',
	'ACP_BBPATREON_WEBHOOK_SECRET_EXPLAIN'		=> 'Used to verify the HMAC-MD5 signature on incoming webhooks. Paste the secret shown by Patreon after registering your webhook in the portal.',
	'ACP_BBPATREON_REGISTER_WEBHOOK'			=> 'Register via API',
	'ACP_BBPATREON_REGISTER_WEBHOOK_EXPLAIN'	=> 'Registers a webhook programmatically. Requires the <code>w:campaigns.webhook</code> scope on your creator token. If this fails, use the Patreon portal instead (see help above).',
	'ACP_BBPATREON_WEBHOOK_REGISTERED'			=> 'Webhook registered successfully with Patreon.',
	'ACP_BBPATREON_CHECK_WEBHOOK'				=> 'Check Status',
	'ACP_BBPATREON_CHECK_WEBHOOK_EXPLAIN'		=> 'Query the Patreon API for registered webhooks and their health, or send a test ping to your own endpoint to verify it is reachable.',
	'ACP_BBPATREON_TEST_WEBHOOK'				=> 'Test Ping',
	'ACP_BBPATREON_WEBHOOK_CHECK_ERROR'			=> 'Could not fetch webhook status from Patreon. Verify that the Creator Access Token is valid.',
	'ACP_BBPATREON_WEBHOOK_NONE_REGISTERED'		=> 'No webhooks are registered with Patreon for this API client.',
	'ACP_BBPATREON_WEBHOOK_STATUS_HEADER'		=> '<strong>Registered webhooks:</strong>',
	'ACP_BBPATREON_WEBHOOK_STATUS_ROW'			=> '<strong>URL:</strong> %1$s<br><strong>Paused:</strong> %2$s<br><strong>Triggers:</strong> %3$s<br><strong>Last attempted:</strong> %4$s<br><strong>Consecutive failures:</strong> %5$s<br><strong>Matches this forum:</strong> %6$s',
	'ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET'		=> 'Cannot test: no webhook secret is configured. Register a webhook or enter a secret first.',
	'ACP_BBPATREON_WEBHOOK_TEST_OK'				=> 'Webhook test successful! Your endpoint at <strong>%s</strong> responded correctly with a valid signature match.',
	'ACP_BBPATREON_WEBHOOK_TEST_FAIL'			=> 'Webhook test failed. HTTP status: %1$s. Response: %2$s',
	'ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR'		=> 'Webhook test failed: could not reach endpoint. Error: %s',

	// Tier mapping
	'ACP_BBPATREON_TIER_MAPPING'			=> 'Tier to Group Mapping',
	'ACP_BBPATREON_TIER_MAPPING_EXPLAIN'	=> 'Map each Patreon tier to a phpBB usergroup. When a patron is pledging at a given tier, they are added to the corresponding group. If they switch tiers, they are moved to the new group and removed from the old one. If they cancel, they are removed from all patron groups (subject to the grace period). Click "Fetch Tiers" to load your tiers from Patreon automatically.',
	'ACP_BBPATREON_FETCH_TIERS'				=> 'Fetch Tiers',
	'ACP_BBPATREON_FETCH_TIERS_EXPLAIN'		=> 'Load your Patreon tiers from the API. Campaign ID must be saved first.',
	'ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN'	=> 'Cannot fetch tiers: no Campaign ID configured. Save it first.',
	'ACP_BBPATREON_FETCH_TIERS_EMPTY'		=> 'No tiers found for this campaign.',
	'ACP_BBPATREON_FETCH_TIERS_DONE'		=> '%d tier(s) fetched from Patreon. Select a group for each tier and click Submit.',
	'ACP_BBPATREON_TIER_ID'					=> 'Patreon Tier ID',
	'ACP_BBPATREON_PHPBB_GROUP'				=> 'phpBB Group',
	'ACP_BBPATREON_SELECT_GROUP'			=> '-- Select Group --',
	'ACP_BBPATREON_ADD_TIER_MAP'			=> 'Add Tier Mapping',
	'ACP_BBPATREON_GRACE_PERIOD'			=> 'Grace Period',
	'ACP_BBPATREON_GRACE_PERIOD_EXPLAIN'	=> 'Number of days to wait before removing a user from their patron group after they cancel or their payment fails. During this period, the user keeps their group access. Set to 0 to remove group membership immediately. The nightly cron task enforces grace period expirations.',

	// Linked users
	'ACP_BBPATREON_LINKED_USERS'			=> 'Linked Users',
	'ACP_BBPATREON_LINKED_USERS_EXPLAIN'	=> 'Forum users who have linked their Patreon account via the User Control Panel. The status and tier columns reflect the last known data from Patreon (via webhook or sync). If a user appears here but has no tier, they have linked their Patreon account but are not currently a patron.',
	'ACP_BBPATREON_NO_LINKED_USERS'			=> 'No users have linked their Patreon accounts yet.',
	'ACP_BBPATREON_PATREON_ID'				=> 'Patreon ID',
	'ACP_BBPATREON_TIER'					=> 'Tier',
	'ACP_BBPATREON_STATUS'					=> 'Status',
	'ACP_BBPATREON_PLEDGE'					=> 'Pledge',
	'ACP_BBPATREON_PATRONS'					=> 'Patrons',
	'ACP_BBPATREON_UNPUBLISHED'				=> 'unpublished',
	'ACP_BBPATREON_LAST_WEBHOOK'			=> 'Last Webhook',
	'ACP_BBPATREON_LAST_SYNCED'				=> 'Last Synced',
	'ACP_BBPATREON_LAST_SYNC'				=> 'Last cron sync',

	// Actions
	'ACP_BBPATREON_MANUAL_SYNC'		=> 'Sync Now',
	'ACP_BBPATREON_SYNC_DONE'		=> 'Sync complete: %1$d members fetched, %2$d linked users synced.',
	'ACP_BBPATREON_SYNC_ERROR'		=> 'Sync failed: %s',

	// Admin log
	'LOG_ACP_BBPATREON_SETTINGS'	=> '<strong>Patreon integration settings updated</strong>',
]);
