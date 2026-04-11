<?php
/**
 *
 * Patreon Integration for phpBB.
 * Migration: add show_public column and supporters page config.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\migrations;

class v1_1_0_supporters_page extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_0_0_initial'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'patreon_sync', 'show_public');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'patreon_sync' => [
					'show_public' => ['BOOL', 0],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'patreon_sync' => [
					'show_public',
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['patreon_supporters_page_enabled', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['patreon_supporters_page_enabled']],
		];
	}
}
