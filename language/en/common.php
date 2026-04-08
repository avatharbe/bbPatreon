<?php
/**
 *
 * Patreon Integration for phpBB.
 *
 * @copyright (c) 2024 Sajaki
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
	// Required by phpBB OAuth provider system
	'AUTH_PROVIDER_OAUTH_SERVICE_PATREON'	=> 'Patreon',

	// General
	'PATREON_NEVER'	=> 'Never',

	// Notifications
	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Someone links their Patreon account',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> linked their Patreon account',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Tier: %s',

	// Log entries
	'LOG_PATREON_API_ERROR'				=> '<strong>Patreon API error:</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Patreon token refresh failed</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Patreon webhook received without signature</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Patreon webhook signature validation failed</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Patreon cron sync completed:</strong> %1$s members fetched, %2$s synced',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Patreon manual sync:</strong> %1$s members fetched, %2$s synced',
	'LOG_PATREON_LINKED'				=> '<strong>Patreon account linked:</strong> user %1$s linked Patreon ID %2$s (tier: %3$s, status: %4$s)',
	'LOG_PATREON_UNLINKED'				=> '<strong>Patreon account unlinked:</strong> user %1$s unlinked Patreon ID %2$s',
	'LOG_PATREON_WEBHOOK_EVENT'			=> '<strong>Patreon webhook received:</strong> %1$s (status: %2$s, tier: %3$s)',
	'LOG_PATREON_GROUP_ADD'				=> '<strong>Patreon group promotion:</strong> user ID %1$s added to group ID %2$s',
	'LOG_PATREON_GROUP_REMOVE'			=> '<strong>Patreon group demotion:</strong> user ID %1$s removed from group ID %2$s',
]);
