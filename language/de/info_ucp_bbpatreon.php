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
]);
