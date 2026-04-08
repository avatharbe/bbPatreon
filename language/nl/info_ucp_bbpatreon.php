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
]);
