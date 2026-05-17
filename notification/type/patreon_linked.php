<?php
/**
 *
 * Patreon Integration for phpBB.
 * Notification sent to admins/moderators when a user links their Patreon account.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\notification\type;

class patreon_linked extends \phpbb\notification\type\base
{
	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var string */
	protected $patreon_tiers_table;

	/** @var array Per-instance cache of tier_id => tier_label lookups. */
	protected $tier_label_cache = [];

	/**
	 * Set user loader
	 *
	 * @param \phpbb\user_loader $user_loader
	 */
	public function set_user_loader(\phpbb\user_loader $user_loader)
	{
		$this->user_loader = $user_loader;
	}

	/**
	 * Set the patreon_tiers table name (for tier-label lookups in get_reference).
	 *
	 * @param string $patreon_tiers_table
	 */
	public function set_patreon_tiers_table(string $patreon_tiers_table)
	{
		$this->patreon_tiers_table = $patreon_tiers_table;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_type()
	{
		return 'avathar.bbpatreon.notification.type.patreon_linked';
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_item_id($type_data)
	{
		return (int) $type_data['user_id'];
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_item_parent_id($type_data)
	{
		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function find_users_for_notification($type_data, $options = [])
	{
		$options = array_merge([
			'ignore_users'	=> [],
		], $options);

		// Gated by the dedicated u_patreon_notify perm (default-granted to
		// ROLE_ADMIN_FULL only — admins can grant to mod roles or specific
		// groups via the ACP). See issue #19.
		$notify_ary = $this->auth->acl_get_list(false, 'u_patreon_notify', false);

		$user_ids = [];
		if (!empty($notify_ary))
		{
			foreach ($notify_ary as $forum_users)
			{
				foreach ($forum_users as $users)
				{
					$user_ids = array_merge($user_ids, $users);
				}
			}
		}

		$user_ids = array_unique($user_ids);

		// Don't notify the user who linked their own account
		$user_ids = array_diff($user_ids, [(int) $type_data['user_id']]);

		if (empty($user_ids))
		{
			return [];
		}

		return $this->check_user_notification_options($user_ids, $options);
	}

	/**
	 * {@inheritdoc}
	 */
	public function users_to_query()
	{
		return [$this->item_id];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title()
	{
		$username = $this->user_loader->get_username($this->item_id, 'no_profile');

		return $this->language->lang('NOTIFICATION_PATREON_LINKED', $username);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_reference()
	{
		$tier_id = (string) $this->get_data('tier_id');
		if ($tier_id === '')
		{
			$label = $this->language->lang('PATREON_NEVER');
		}
		else
		{
			$label = $this->resolve_tier_label($tier_id);
		}
		return $this->language->lang('NOTIFICATION_PATREON_LINKED_REFERENCE', $label);
	}

	/**
	 * Look up the human-readable tier label by Patreon tier_id from the
	 * patreon_tiers table. Falls back to the raw tier_id when the row is
	 * missing (e.g. tier deleted from the campaign since the patron linked).
	 * Cached per-instance so multiple get_reference() calls in one render
	 * hit the DB at most once per tier_id.
	 *
	 * @param string $tier_id
	 * @return string
	 */
	protected function resolve_tier_label(string $tier_id): string
	{
		if (isset($this->tier_label_cache[$tier_id]))
		{
			return $this->tier_label_cache[$tier_id];
		}

		if (!$this->patreon_tiers_table)
		{
			return $tier_id;
		}

		$sql = 'SELECT tier_label FROM ' . $this->patreon_tiers_table . "
			WHERE tier_id = '" . $this->db->sql_escape($tier_id) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$label = ($row && $row['tier_label'] !== '') ? (string) $row['tier_label'] : $tier_id;
		$this->tier_label_cache[$tier_id] = $label;
		return $label;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_url()
	{
		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_email_template()
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_email_template_variables()
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_avatar()
	{
		return $this->user_loader->get_avatar($this->item_id, false, true);
	}

	/**
	 * {@inheritdoc}
	 */
	public function create_insert_array($type_data, $pre_create_data = [])
	{
		$this->set_data('tier_id', $type_data['tier_id'] ?? '');
		$this->set_data('pledge_status', $type_data['pledge_status'] ?? '');
		$this->set_data('patreon_user_id', $type_data['patreon_user_id'] ?? '');

		parent::create_insert_array($type_data, $pre_create_data);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available()
	{
		return true;
	}
}
