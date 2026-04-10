<?php
/**
 *
 * Patreon Integration for phpBB.
 * Moves tier_group_map and tier_labels from config to config_text
 * to avoid the 255-character limit on phpbb_config.config_value.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\migrations;

class v1_0_1_config_text extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\avathar\bbpatreon\migrations\v1_0_0_initial'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'move_to_config_text']]],
		];
	}

	public function move_to_config_text()
	{
		$keys = ['patreon_tier_group_map', 'patreon_tier_labels'];

		foreach ($keys as $key)
		{
			// Read current value from config
			$value = isset($this->config[$key]) ? $this->config[$key] : '{}';

			// Write to config_text
			$this->config_text->set($key, $value);

			// Remove from config
			$this->config->delete($key);
		}
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'move_to_config']]],
		];
	}

	public function move_to_config()
	{
		$keys = ['patreon_tier_group_map', 'patreon_tier_labels'];

		foreach ($keys as $key)
		{
			$value = $this->config_text->get($key);
			if ($value === null)
			{
				$value = '{}';
			}

			$this->config->set($key, $value);
			$this->config_text->delete($key);
		}
	}
}
