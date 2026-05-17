<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Language strings for the bbAccounts Integration ACP mode.
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
	'ACP_BBPATREON_BBACCOUNTS_INTEGRATION'         => 'bbAccounts Integration',
	'ACP_BBPATREON_BBACCOUNTS_INTEGRATION_EXPLAIN' => 'Configure how active patrons receive monthly journal-entry credits through the bbAccounts ledger. Each rule maps a (Patron Reward Expense account, User Wallets account, amount-per-dollar rate). Credits are posted by the nightly cron and can be triggered manually below.',
	'ACP_BBPATREON_BBACCOUNTS_MISSING'             => 'The bbAccounts extension is not installed or not enabled. Install bbAccounts to enable patron wallet crediting.',

	'ACP_BBPATREON_RULE_ADD'           => 'Add credit rule',
	'ACP_BBPATREON_RULE_EDIT'          => 'Edit credit rule',
	'ACP_BBPATREON_RULE_LABEL'         => 'Rule label',
	'ACP_BBPATREON_RULE_ACTIVE'        => 'Active',
	'ACP_BBPATREON_RULE_TOGGLE'        => 'Toggle active',
	'ACP_BBPATREON_NO_RULES'           => 'No credit rules configured. Add a rule to enable bbAccounts integration.',
	'ACP_BBPATREON_EXPENSE_ACCOUNT'    => 'Expense account (DR)',
	'ACP_BBPATREON_WALLET_ACCOUNT'     => 'User Wallets account (CR)',
	'ACP_BBPATREON_CURRENCY'           => 'Currency pool',
	'ACP_BBPATREON_AMOUNT_PER_DOLLAR'  => 'Units credited per $1 pledged',

	'ACP_BBPATREON_RUN_CREDIT'         => 'Run credit now',
	'ACP_BBPATREON_RUN_CREDIT_EXPLAIN' => 'Manually trigger a credit run for the selected period. Already-credited (rule, patron, period) triples are skipped; safe to re-run.',
	'ACP_BBPATREON_RUN_PERIOD'         => 'Period (YYYY-MM)',
	'ACP_BBPATREON_RUN_CREDIT_BUTTON'  => 'Run credit now',
	'ACP_BBPATREON_RUN_CREDIT_CONFIRM' => 'Post journal entries for active patrons in the selected period? Already-credited patrons will be skipped.',
	'ACP_BBPATREON_RUN_RESULT'         => 'Credit run completed for',
	'ACP_BBPATREON_RUN_CREDITED'       => 'credited',
	'ACP_BBPATREON_RUN_SKIPPED'        => 'skipped (already credited)',
	'ACP_BBPATREON_RUN_ERRORS'         => 'errors',
]);
