<?php
/**
 *
 * Patreon Integration for phpBB.
 * Migration: drop patreon_tiers.group_id now that patreon_tier_groups is
 * the single source of truth for tier-to-group mapping. See issue #5.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\migrations;

class v1_3_1_drop_tier_group_id extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_3_0_tier_group_join'];
	}

	public function effectively_installed()
	{
		return !$this->db_tools->sql_column_exists($this->table_prefix . 'patreon_tiers', 'group_id');
	}

	public function update_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'patreon_tiers' => [
					'group_id',
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'patreon_tiers' => [
					'group_id' => ['UINT', 0],
				],
			],
		];
	}
}
