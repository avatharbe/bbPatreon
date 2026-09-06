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
 * Registers the new "Patron Stats" ACP mode — a read-only overview of
 * patron activity computed live from the patreon_sync table. No schema
 * or config changes; see issue #4.
 */
class v1_2_5_patron_stats_page extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_2_4_default_group'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . "
			WHERE module_basename = '" . $this->db->sql_escape('\avathar\bbpatreon\acp\main_module') . "'
				AND module_mode = 'patron_stats'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row !== false;
	}

	public function update_data()
	{
		return [
			['module.add', [
				'acp',
				'ACP_BBPATREON_TITLE',
				[
					'module_basename' => '\avathar\bbpatreon\acp\main_module',
					'module_langname' => 'ACP_BBPATREON_PATRON_STATS',
					'module_mode'     => 'patron_stats',
					'module_auth'     => 'ext_avathar/bbpatreon && acl_a_board',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			// Remove by langname (not the basename+modes array form), which
			// would otherwise remove every mode of this module — see
			// \phpbb\db\migration\tool\module::remove()'s "automatic" branch.
			['module.remove', [
				'acp',
				'ACP_BBPATREON_TITLE',
				'ACP_BBPATREON_PATRON_STATS',
			]],
		];
	}
}
