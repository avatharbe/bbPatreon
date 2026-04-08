<?php
/**
 *
 * Patreon-Integration für phpBB.
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
	'AUTH_PROVIDER_OAUTH_SERVICE_PATREON'	=> 'Patreon',

	'PATREON_NEVER'	=> 'Nie',

	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Jemand verknüpft sein Patreon-Konto',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> hat sein Patreon-Konto verknüpft',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Stufe: %s',

	'LOG_PATREON_API_ERROR'				=> '<strong>Patreon API-Fehler:</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Patreon Token-Erneuerung fehlgeschlagen</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Patreon Webhook ohne Signatur empfangen</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Patreon Webhook-Signaturvalidierung fehlgeschlagen</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Patreon Cron-Synchronisation abgeschlossen:</strong> %1$s Mitglieder abgerufen, %2$s synchronisiert',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Patreon manuelle Synchronisation:</strong> %1$s Mitglieder abgerufen, %2$s synchronisiert',
]);
