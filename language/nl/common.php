<?php
/**
 *
 * Patreon-integratie voor phpBB.
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
	'PATREON_NEVER'	=> 'Nooit',

	// Notifications
	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Iemand koppelt zijn Patreon-account',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> heeft zijn Patreon-account gekoppeld',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Tier: %s',

	// Log entries
	'LOG_PATREON_API_ERROR'				=> '<strong>Patreon API-fout:</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Patreon token vernieuwing mislukt</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Patreon webhook ontvangen zonder handtekening</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Patreon webhook handtekeningvalidatie mislukt</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Patreon cron-synchronisatie voltooid:</strong> %1$s leden opgehaald, %2$s gesynchroniseerd',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Patreon handmatige synchronisatie:</strong> %1$s leden opgehaald, %2$s gesynchroniseerd',
]);
