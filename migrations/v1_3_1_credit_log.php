<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\migrations;

/**
 * Adds the bbpatreon_credit_log table — bbpatreon-owned idempotency
 * state for the bbAccounts integration. One row per (rule, patron,
 * period) tuple that was successfully credited, with a back-link to
 * the bbAccounts journal entry id. The UNIQUE KEY enforces "at most
 * one credit per rule-user-period," so the recorder can short-circuit
 * cleanly on the second run in a given period without touching the
 * bbAccounts schema.
 *
 * Background: bbAccounts' ledger->create_entry() takes reference_id
 * as an int, so the original plan to encode (rule_id, user_id, period)
 * into a composite string reference is infeasible. bbpatreon owns its
 * idempotency state instead.
 */
class v1_3_1_credit_log extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_3_0_bbaccounts_integration'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'bbpatreon_credit_log');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'bbpatreon_credit_log' => [
					'COLUMNS' => [
						'log_id'     => ['UINT',      null, 'auto_increment'],
						'rule_id'    => ['UINT',      0],
						'user_id'    => ['UINT',      0],
						'period'     => ['VCHAR:7',   ''],
						'journal_id' => ['UINT',      0],
						'created_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'log_id',
					'KEYS' => [
						'rule_user_period' => ['UNIQUE', ['rule_id', 'user_id', 'period']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'bbpatreon_credit_log',
			],
		];
	}
}
