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
	'UCP_BBPATREON'			=> 'Patreon',
	'UCP_BBPATREON_TITLE'	=> 'Patreon',

	'UCP_BBPATREON_LINKED_STATUS'	=> 'Dein Patreon-Konto',
	'UCP_BBPATREON_PATREON_ID'		=> 'Patreon-ID',
	'UCP_BBPATREON_TIER'			=> 'Aktuelle Stufe',
	'UCP_BBPATREON_STATUS'			=> 'Pledge-Status',
	'UCP_BBPATREON_PLEDGE'			=> 'Pledge-Betrag',

	'UCP_BBPATREON_NOT_LINKED'		=> 'Dein Patreon-Konto ist nicht verknüpft. Verknüpfe es, um deine Patron-Vorteile in diesem Forum zu erhalten.',
	'UCP_BBPATREON_LINK_ACCOUNT'	=> 'Patreon-Konto verknüpfen',
	'UCP_BBPATREON_UNLINK'			=> 'Patreon-Konto trennen',
	'UCP_BBPATREON_UNLINK_CONFIRM'	=> 'Bist du sicher, dass du dein Patreon-Konto trennen möchtest? Du verlierst deine Patron-Gruppenmitgliedschaft.',
	'UCP_BBPATREON_UNLINKED'		=> 'Dein Patreon-Konto wurde erfolgreich getrennt.',
	'UCP_BBPATREON_LINKED'			=> 'Dein Patreon-Konto wurde erfolgreich verknüpft!',
	'UCP_BBPATREON_OAUTH_ERROR'		=> 'Verbindung zu Patreon fehlgeschlagen.',
	'UCP_BBPATREON_ALREADY_LINKED'	=> 'Dieses Patreon-Konto ist bereits mit einem anderen Forenbenutzer verknüpft.',

	'UCP_BBPATREON_GROUP'			=> 'Forengruppe',
	'UCP_BBPATREON_LAST_SYNCED'		=> 'Letzte Aktualisierung',
	'UCP_BBPATREON_RESYNC'			=> 'Status aktualisieren',
	'UCP_BBPATREON_RESYNCED'		=> 'Dein Patreon-Status wurde erfolgreich aktualisiert.',
	'UCP_BBPATREON_RESYNC_TOO_SOON'	=> 'Du kannst deinen Status nur alle 5 Minuten aktualisieren. Bitte versuche es später erneut.',

	'UCP_BBPATREON_PREFERENCES'				=> 'Einstellungen',
	'UCP_BBPATREON_SHOW_PUBLIC'				=> 'Als Unterstützer anzeigen',
	'UCP_BBPATREON_SHOW_PUBLIC_EXPLAIN'		=> 'Wenn aktiviert, werden dein Benutzername und deine Stufe auf der öffentlichen Unterstützerseite angezeigt. Pledge-Beträge werden nie angezeigt. Nur für zahlende Patrons verfügbar.',
	'UCP_BBPATREON_SHOW_PLEDGE'				=> 'Meinen Pledge-Betrag anzeigen',
	'UCP_BBPATREON_SHOW_PLEDGE_EXPLAIN'		=> 'Wenn aktiviert, wird dein Pledge-Betrag auf der Unterstützerseite sichtbar. Erfordert, dass „Als Unterstützer anzeigen" aktiviert ist.',
	'UCP_BBPATREON_PREFERENCES_SAVED'		=> 'Deine Einstellungen wurden gespeichert.',

	'UCP_BBPATREON_STATUS_ACTIVE_PATRON'	=> 'Aktiver Patron',
	'UCP_BBPATREON_STATUS_DECLINED_PATRON'	=> 'Zahlung abgelehnt',
	'UCP_BBPATREON_STATUS_FORMER_PATRON'	=> 'Ehemaliger Patron',
	'UCP_BBPATREON_STATUS_PENDING_LINK'		=> 'Ausstehend',
]);
