<?php
/**
 *
 * Patreon-Integration für phpBB.
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
	'AUTH_PROVIDER_OAUTH_SERVICE_PATREON'	=> 'Patreon',

	'PATREON_NEVER'	=> 'Nie',

	'PATREON_SUPPORTERS_TITLE'		=> 'Unsere Unterstützer',
	'PATREON_SUPPORTERS_EXPLAIN'	=> 'Diese Community-Mitglieder unterstützen uns über Patreon.',
	'PATREON_SUPPORTERS_TIER'		=> 'Stufe',
	'PATREON_SUPPORTERS_PLEDGE'		=> 'Pledge',
	'PATREON_SUPPORTERS_NONE'		=> 'Derzeit keine Unterstützer anzuzeigen.',

	'NOTIFICATION_TYPE_PATREON_LINKED'			=> 'Jemand verknüpft sein Patreon-Konto',
	'NOTIFICATION_PATREON_LINKED'				=> '<strong>%s</strong> hat sein Patreon-Konto verknüpft',
	'NOTIFICATION_PATREON_LINKED_REFERENCE'		=> 'Stufe: %s',

	'LOG_PATREON_API_ERROR'				=> '<strong>Patreon API-Fehler:</strong> %s',
	'LOG_PATREON_TOKEN_REFRESH_FAILED'	=> '<strong>Patreon Token-Erneuerung fehlgeschlagen</strong> (HTTP %s)',
	'LOG_PATREON_WEBHOOK_NO_SIGNATURE'	=> '<strong>Patreon Webhook ohne Signatur empfangen</strong>',
	'LOG_PATREON_WEBHOOK_BAD_SIGNATURE'	=> '<strong>Patreon Webhook-Signaturvalidierung fehlgeschlagen</strong>',
	'LOG_PATREON_CRON_SYNC'				=> '<strong>Patreon Cron-Synchronisation abgeschlossen:</strong> %1$s Mitglieder abgerufen, %2$s synchronisiert',
	'LOG_PATREON_MANUAL_SYNC'			=> '<strong>Patreon manuelle Synchronisation:</strong> %1$s Mitglieder abgerufen, %2$s synchronisiert',
	'LOG_PATREON_LINKED'				=> '<strong>Patreon-Konto verknüpft:</strong> Benutzer %1$s verknüpfte Patreon-ID %2$s (Stufe: %3$s, Status: %4$s)',
	'LOG_PATREON_UNLINKED'				=> '<strong>Patreon-Konto getrennt:</strong> Benutzer %1$s trennte Patreon-ID %2$s',
	'LOG_PATREON_WEBHOOK_EVENT'			=> '<strong>Patreon Webhook empfangen:</strong> %1$s (Status: %2$s, Stufe: %3$s)',
	'LOG_PATREON_GROUP_ADD'				=> '<strong>Patreon Gruppenbeförderung:</strong> Benutzer-ID %1$s zu Gruppe-ID %2$s hinzugefügt',
	'LOG_PATREON_GROUP_REMOVE'			=> '<strong>Patreon Gruppendegradierung:</strong> Benutzer-ID %1$s aus Gruppe-ID %2$s entfernt',
]);
