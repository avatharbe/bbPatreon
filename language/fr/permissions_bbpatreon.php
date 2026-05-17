<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Permission MASK UI strings.
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
	'ACL_U_PATREON_NOTIFY' => ['lang' => 'bbPatreon: Receive Patreon-account-linked notifications'],
]);
