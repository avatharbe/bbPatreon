<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Language strings for the Patron Stats ACP mode.
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
	'ACP_BBPATREON_PATRON_STATS'				=> 'Patron Stats',
	'ACP_BBPATREON_PATRON_STATS_EXPLAIN'		=> 'A quick overview of patron activity, computed live from this board\'s synced Patreon data, so you don\'t need to visit the Patreon dashboard for a summary.',

	'ACP_BBPATREON_TIER'						=> 'Tier',
	'ACP_BBPATREON_STATS_OVERVIEW'				=> 'Overview',
	'ACP_BBPATREON_STATS_ACTIVE_PATRONS'		=> 'Active patrons',
	'ACP_BBPATREON_STATS_MONTHLY_PLEDGE'		=> 'Total monthly pledge amount',
	'ACP_BBPATREON_STATS_DECLINED_PATRONS'		=> 'Declined patrons',
	'ACP_BBPATREON_STATS_PER_TIER'				=> 'Active patrons per tier',
	'ACP_BBPATREON_STATS_NO_ACTIVE_PATRONS'	=> 'No active patrons yet.',
]);
