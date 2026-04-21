<?php
/**
 *
 * Patreon-integratie voor phpBB.
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

	'UCP_BBPATREON_LINKED_STATUS'	=> 'Je Patreon-account',
	'UCP_BBPATREON_PATREON_ID'		=> 'Patreon-ID',
	'UCP_BBPATREON_TIER'			=> 'Huidige tier',
	'UCP_BBPATREON_STATUS'			=> 'Pledge-status',
	'UCP_BBPATREON_PLEDGE'			=> 'Pledgebedrag',

	'UCP_BBPATREON_NOT_LINKED'		=> 'Je Patreon-account is niet gekoppeld. Koppel het om je patronvoordelen op dit forum te ontvangen.',
	'UCP_BBPATREON_LINK_ACCOUNT'	=> 'Koppel je Patreon-account',
	'UCP_BBPATREON_UNLINK'			=> 'Patreon-account ontkoppelen',
	'UCP_BBPATREON_UNLINK_CONFIRM'	=> 'Weet je zeker dat je je Patreon-account wilt ontkoppelen? Je verliest je patrongroepslidmaatschap.',
	'UCP_BBPATREON_UNLINKED'		=> 'Je Patreon-account is succesvol ontkoppeld.',
	'UCP_BBPATREON_LINKED'			=> 'Je Patreon-account is succesvol gekoppeld!',
	'UCP_BBPATREON_OAUTH_ERROR'		=> 'Kan geen verbinding maken met Patreon.',
	'UCP_BBPATREON_ALREADY_LINKED'	=> 'Dit Patreon-account is al gekoppeld aan een andere forumgebruiker.',

	'UCP_BBPATREON_GROUP'			=> 'Forumgroep',
	'UCP_BBPATREON_LAST_SYNCED'		=> 'Laatst bijgewerkt',
	'UCP_BBPATREON_RESYNC'			=> 'Mijn status vernieuwen',
	'UCP_BBPATREON_RESYNCED'		=> 'Je Patreon-status is succesvol bijgewerkt.',
	'UCP_BBPATREON_RESYNC_TOO_SOON'	=> 'Je kunt je status slechts elke 5 minuten vernieuwen. Probeer het later opnieuw.',

	'UCP_BBPATREON_PREFERENCES'				=> 'Voorkeuren',
	'UCP_BBPATREON_SHOW_PUBLIC'				=> 'Toon mij als supporter',
	'UCP_BBPATREON_SHOW_PUBLIC_EXPLAIN'		=> 'Indien ingeschakeld worden je gebruikersnaam en tier getoond op de openbare supporterspagina. Pledgebedragen worden nooit getoond. Alleen beschikbaar voor betalende patrons.',
	'UCP_BBPATREON_SHOW_PLEDGE'				=> 'Mijn pledgebedrag tonen',
	'UCP_BBPATREON_SHOW_PLEDGE_EXPLAIN'		=> 'Indien ingeschakeld wordt je pledgebedrag zichtbaar op de supporterspagina. Vereist dat „Toon mij als supporter" is ingeschakeld.',
	'UCP_BBPATREON_PREFERENCES_SAVED'		=> 'Je voorkeuren zijn opgeslagen.',

	'UCP_BBPATREON_STATUS_ACTIVE_PATRON'	=> 'Actieve patron',
	'UCP_BBPATREON_STATUS_DECLINED_PATRON'	=> 'Betaling geweigerd',
	'UCP_BBPATREON_STATUS_FORMER_PATRON'	=> 'Voormalige patron',
	'UCP_BBPATREON_STATUS_PENDING_LINK'		=> 'In afwachting',
]);
