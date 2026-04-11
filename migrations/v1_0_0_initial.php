<?php
/**
 *
 * Patreon Integration for phpBB.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\migrations;

class v1_0_0_initial extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v320\v320'];
	}

	public function effectively_installed()
	{
		return isset($this->config['patreon_client_id']);
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'patreon_sync' => [
					'COLUMNS' => [
						'patreon_user_id'	=> ['VCHAR:64', ''],
						'tier_id'			=> ['VCHAR:64', ''],
						'pledge_status'		=> ['VCHAR:20', 'pending_link'],
						'pledge_cents'		=> ['UINT', 0],
						'last_webhook_at'	=> ['TIMESTAMP', 0],
						'last_synced_at'	=> ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'patreon_user_id',
				],
				$this->table_prefix . 'patreon_tiers' => [
					'COLUMNS' => [
						'tier_id'		=> ['VCHAR:64', ''],
						'tier_label'	=> ['TEXT', ''],
						'group_id'		=> ['UINT', 0],
						'amount_cents'	=> ['UINT', 0],
						'currency'		=> ['VCHAR:3', ''],
					],
					'PRIMARY_KEY' => 'tier_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'patreon_sync',
				$this->table_prefix . 'patreon_tiers',
			],
		];
	}

	public function update_data()
	{
		return [
			// Config keys
			['config.add', ['patreon_client_id', '']],
			['config.add', ['patreon_client_secret', '']],
			['config.add', ['patreon_creator_access_token', '']],
			['config.add', ['patreon_creator_refresh_token', '']],
			['config.add', ['patreon_campaign_id', '']],
			['config.add', ['patreon_webhook_secret', '']],
			['config.add', ['patreon_currency', 'USD']],
			['config.add', ['patreon_grace_period_days', 0]],
			['config.add', ['patreon_last_cron_sync', 0]],

			// phpBB OAuth convention keys (synced with patreon_client_id/secret by ACP)
			['config.add', ['auth_oauth_patreon_key', '']],
			['config.add', ['auth_oauth_patreon_secret', '']],

			// ACP module
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_BBPATREON_TITLE',
			]],
			['module.add', [
				'acp',
				'ACP_BBPATREON_TITLE',
				[
					'module_basename'	=> '\avathar\bbpatreon\acp\main_module',
					'modes'				=> ['settings'],
				],
			]],

			// UCP module
			['module.add', [
				'ucp',
				0,
				'UCP_BBPATREON_TITLE',
			]],
			['module.add', [
				'ucp',
				'UCP_BBPATREON_TITLE',
				[
					'module_basename'	=> '\avathar\bbpatreon\ucp\main_module',
					'modes'				=> ['settings'],
				],
			]],
		];
	}
}
