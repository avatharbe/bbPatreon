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
	'UCP_BBPATREON'			=> 'Patreon',
	'UCP_BBPATREON_TITLE'	=> 'Patreon',

	'UCP_BBPATREON_LINKED_STATUS'	=> 'Your Patreon Account',
	'UCP_BBPATREON_PATREON_ID'		=> 'Patreon ID',
	'UCP_BBPATREON_TIER'			=> 'Current Tier',
	'UCP_BBPATREON_STATUS'			=> 'Pledge Status',
	'UCP_BBPATREON_PLEDGE'			=> 'Pledge Amount',

	'UCP_BBPATREON_NOT_LINKED'		=> 'Your Patreon account is not linked. Link it to receive your patron benefits on this forum.',
	'UCP_BBPATREON_LINK_ACCOUNT'	=> 'Link your Patreon Account',
	'UCP_BBPATREON_UNLINK'			=> 'Unlink Patreon Account',
	'UCP_BBPATREON_UNLINK_CONFIRM'	=> 'Are you sure you want to unlink your Patreon account? You will lose your patron group membership.',
	'UCP_BBPATREON_UNLINKED'		=> 'Your Patreon account has been unlinked successfully.',
	'UCP_BBPATREON_LINKED'			=> 'Your Patreon account has been linked successfully!',
	'UCP_BBPATREON_OAUTH_ERROR'		=> 'Could not connect to Patreon.',
	'UCP_BBPATREON_ALREADY_LINKED'	=> 'This Patreon account is already linked to a different forum user.',
]);
