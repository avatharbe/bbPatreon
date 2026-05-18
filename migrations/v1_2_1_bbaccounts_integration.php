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
 * Adds the bbpatreon_credit_rules table (admin-configured journal-entry
 * rules that drive bbAccounts integration) and registers the new
 * "bbAccounts Integration" ACP mode. Idempotency keyed on the table.
 */
class v1_2_1_bbaccounts_integration extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_2_0_show_pledge'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'bbpatreon_credit_rules');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'bbpatreon_credit_rules' => [
					'COLUMNS' => [
						'rule_id'            => ['UINT',    null, 'auto_increment'],
						'rule_label'         => ['VCHAR:100', ''],
						'expense_account_id' => ['UINT',    0],
						'wallet_account_id'  => ['UINT',    0],
						'amount_per_dollar'  => ['DECIMAL:10,2', '0.00'],
						'is_active'          => ['BOOL',    1],
						'rule_order'         => ['UINT',    0],
					],
					'PRIMARY_KEY' => 'rule_id',
					'KEYS' => [
						'is_active_order' => ['INDEX', ['is_active', 'rule_order']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'bbpatreon_credit_rules',
			],
		];
	}

	public function update_data()
	{
		return [
			['module.add', [
				'acp',
				'ACP_BBPATREON_TITLE',
				[
					'module_basename' => '\avathar\bbpatreon\acp\main_module',
					'module_langname' => 'ACP_BBPATREON_BBACCOUNTS_INTEGRATION',
					'module_mode'     => 'bbaccounts_integration',
					'module_auth'     => 'ext_avathar/bbpatreon && acl_a_board',
				],
			]],
		];
	}
}
