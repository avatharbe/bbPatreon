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
 * Adds the `bbpatreon_set_default_group` config flag. When enabled, the
 * group_mapper sets the tier-mapped group as the patron's default phpBB
 * group on promotion (so the patron's username takes on the group's
 * colour / rank), and resets default to the Registered users group on
 * demotion. Default OFF — preserves the pre-1.3.3 behaviour of treating
 * tier-mapped membership as a secondary group only. See issue #20.
 */
class v1_2_4_default_group extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_2_3_notification_perm'];
	}

	public function effectively_installed()
	{
		return isset($this->config['bbpatreon_set_default_group']);
	}

	public function update_data()
	{
		return [
			['config.add', ['bbpatreon_set_default_group', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['bbpatreon_set_default_group']],
		];
	}
}
