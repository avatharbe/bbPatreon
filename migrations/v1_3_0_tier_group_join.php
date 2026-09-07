<?php
/**
 *
 * Patreon Integration for phpBB.
 * Migration: add the patreon_tier_groups join table and backfill it from
 * the existing single-group patreon_tiers.group_id column, so a tier can
 * map to multiple phpBB groups. See issue #5.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\migrations;

class v1_3_0_tier_group_join extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_2_5_patron_stats_page'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'patreon_tier_groups');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'patreon_tier_groups' => [
					'COLUMNS' => [
						'tier_id'	=> ['VCHAR:64', ''],
						'group_id'	=> ['UINT', 0],
					],
					'PRIMARY_KEY' => ['tier_id', 'group_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'patreon_tier_groups',
			],
		];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'backfill_from_patreon_tiers']]],
		];
	}

	/**
	 * Copy each tier's existing single group_id into the new join table.
	 * patreon_tiers.group_id is left in place here; a later migration
	 * drops it once every reader has moved to the join table.
	 */
	public function backfill_from_patreon_tiers()
	{
		$sql = 'SELECT tier_id, group_id FROM ' . $this->table_prefix . 'patreon_tiers
			WHERE group_id > 0';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$sql_insert = 'INSERT INTO ' . $this->table_prefix . 'patreon_tier_groups ' . $this->db->sql_build_array('INSERT', [
				'tier_id'	=> $row['tier_id'],
				'group_id'	=> (int) $row['group_id'],
			]);
			$this->db->sql_query($sql_insert);
		}
		$this->db->sql_freeresult($result);
	}
}
