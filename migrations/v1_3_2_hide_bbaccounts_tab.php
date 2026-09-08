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
 * The "bbAccounts Integration" ACP tab was always visible, even with
 * bbAccounts not installed, where it just showed an errorbox explaining
 * that. Adds an `ext_avathar/bbaccounts` check to the stored module_auth
 * so the tab itself disappears from the ACP menu until bbAccounts is
 * installed and enabled.
 */
class v1_3_2_hide_bbaccounts_tab extends \phpbb\db\migration\migration
{
	const OLD_AUTH = 'ext_avathar/bbpatreon && acl_a_board';
	const NEW_AUTH = 'ext_avathar/bbpatreon && ext_avathar/bbaccounts && acl_a_board';

	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_3_1_drop_tier_group_id'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . MODULES_TABLE . "
			WHERE module_basename = '" . $this->db->sql_escape('\avathar\bbpatreon\acp\main_module') . "'
				AND module_mode = 'bbaccounts_integration'
				AND module_auth = '" . $this->db->sql_escape(self::NEW_AUTH) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row !== false;
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'set_new_auth']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'set_old_auth']]],
		];
	}

	public function set_new_auth()
	{
		$this->update_module_auth(self::OLD_AUTH, self::NEW_AUTH);
	}

	public function set_old_auth()
	{
		$this->update_module_auth(self::NEW_AUTH, self::OLD_AUTH);
	}

	protected function update_module_auth($from_auth, $to_auth)
	{
		$sql = 'UPDATE ' . MODULES_TABLE . "
			SET module_auth = '" . $this->db->sql_escape($to_auth) . "'
			WHERE module_basename = '" . $this->db->sql_escape('\avathar\bbpatreon\acp\main_module') . "'
				AND module_mode = 'bbaccounts_integration'
				AND module_auth = '" . $this->db->sql_escape($from_auth) . "'";
		$this->db->sql_query($sql);
	}
}
