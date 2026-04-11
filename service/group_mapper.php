<?php
/**
 *
 * Patreon Integration for phpBB.
 * Maps Patreon tiers to phpBB usergroups.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\service;

class group_mapper
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var string */
	protected $patreon_tiers_table;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config				$config
	 * @param \phpbb\db\driver\driver_interface	$db
	 * @param \phpbb\log\log_interface			$log
	 * @param string							$root_path
	 * @param string							$php_ext
	 * @param string							$patreon_tiers_table
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		string $root_path,
		string $php_ext,
		string $patreon_tiers_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->log					= $log;
		$this->patreon_tiers_table	= $patreon_tiers_table;

		if (!function_exists('group_user_add'))
		{
			include_once($root_path . 'includes/functions_user.' . $php_ext);
		}
	}

	/**
	 * Get the tier-to-group mapping from the patreon_tiers table.
	 *
	 * @return array tier_id => group_id
	 */
	public function get_tier_group_map(): array
	{
		$sql = 'SELECT tier_id, group_id FROM ' . $this->patreon_tiers_table;
		$result = $this->db->sql_query($sql);

		$map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[$row['tier_id']] = (int) $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		return $map;
	}

	/**
	 * Get all phpBB group IDs that are used in the tier mapping.
	 *
	 * @return array
	 */
	public function get_all_patron_group_ids(): array
	{
		return array_unique(array_values($this->get_tier_group_map()));
	}

	/**
	 * Sync a user's group memberships based on their current tier and pledge status.
	 *
	 * @param int			$user_id		phpBB user ID
	 * @param string|null	$new_tier_id	Current Patreon tier ID (empty = no tier)
	 * @param string		$pledge_status	Patreon pledge status
	 */
	public function sync_user_groups(int $user_id, ?string $new_tier_id, string $pledge_status): void
	{
		$map = $this->get_tier_group_map();
		if (empty($map))
		{
			return;
		}

		$patron_group_ids = $this->get_all_patron_group_ids();
		$target_group_id = (!empty($new_tier_id) && isset($map[$new_tier_id])) ? (int) $map[$new_tier_id] : 0;

		// For declined/former patrons with grace period, don't demote yet
		if (in_array($pledge_status, ['declined_patron', 'former_patron'], true))
		{
			$grace_days = (int) $this->config['patreon_grace_period_days'];
			if ($grace_days > 0)
			{
				return;
			}
			// No grace period: fall through to demotion
			$target_group_id = 0;
		}

		// Active patron: remove from wrong groups, add to correct one
		if ($pledge_status === 'active_patron' && $target_group_id > 0)
		{
			// Remove from all patron groups except the target
			foreach ($patron_group_ids as $group_id)
			{
				if ((int) $group_id !== $target_group_id && $this->user_in_group($user_id, (int) $group_id))
				{
					group_user_del((int) $group_id, [$user_id]);
				}
			}

			// Add to target group
			if (!$this->user_in_group($user_id, $target_group_id))
			{
				group_user_add($target_group_id, [$user_id]);
				$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_GROUP_ADD', false, [
					(string) $user_id,
					(string) $target_group_id,
				]);
			}
		}
		else
		{
			// Not active or no target group: remove from all patron groups
			$this->demote_from_all_patron_groups($user_id);
		}
	}

	/**
	 * Remove a user from all patron-mapped groups.
	 *
	 * @param int $user_id phpBB user ID
	 */
	public function demote_from_all_patron_groups(int $user_id): void
	{
		$patron_group_ids = $this->get_all_patron_group_ids();

		foreach ($patron_group_ids as $group_id)
		{
			if ($this->user_in_group($user_id, (int) $group_id))
			{
				group_user_del((int) $group_id, [$user_id]);
				$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_GROUP_REMOVE', false, [
					(string) $user_id,
					(string) $group_id,
				]);
			}
		}
	}

	/**
	 * Check if a user belongs to a specific group.
	 *
	 * @param int $user_id
	 * @param int $group_id
	 * @return bool
	 */
	protected function user_in_group(int $user_id, int $group_id): bool
	{
		$sql = 'SELECT user_id FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . (int) $user_id . '
				AND group_id = ' . (int) $group_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row !== false;
	}
}
