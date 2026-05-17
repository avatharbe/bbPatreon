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
 * Adds the `u_patreon_notify` permission. Without this, the patreon_linked
 * notification used a broad "all admins + all mods" filter that surprised
 * forum admins who didn't want their moderators receiving patron-linking
 * notifications (issue #19). The perm defaults to ROLE_ADMIN_FULL only;
 * admins can grant it to mod roles or specific groups via the standard
 * permission UI.
 */
class v1_1_4_notification_perm extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_1_3_credit_log'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT auth_option_id
			FROM ' . $this->table_prefix . "acl_options
			WHERE auth_option = 'u_patreon_notify'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row !== false;
	}

	public function update_data()
	{
		return [
			['permission.add',          ['u_patreon_notify', true]],
			['permission.permission_set', ['ROLE_ADMIN_FULL', 'u_patreon_notify', 'role']],
		];
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['u_patreon_notify']],
		];
	}
}
