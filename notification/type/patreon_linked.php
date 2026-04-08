<?php
/**
 *
 * Patreon Integration for phpBB.
 * Notification sent to admins/moderators when a user links their Patreon account.
 *
 * @copyright (c) 2024 Sajaki
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\notification\type;

class patreon_linked extends \phpbb\notification\type\base
{
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

		// Notify all admins and global moderators
		$admin_ary = $this->auth->acl_get_list(false, 'a_', false);
		$mod_ary = $this->auth->acl_get_list(false, 'm_', false);

		$user_ids = [];

		if (!empty($admin_ary))
		{
			foreach ($admin_ary as $forum_users)
			{
				foreach ($forum_users as $users)
				{
					$user_ids = array_merge($user_ids, $users);
				}
			}
		}

		if (!empty($mod_ary))
		{
			foreach ($mod_ary as $forum_users)
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
		return $this->language->lang('NOTIFICATION_PATREON_LINKED_REFERENCE',
			$this->get_data('tier_label') ?: $this->language->lang('PATREON_NEVER')
		);
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
		$this->set_data('tier_label', $type_data['tier_label'] ?? '');
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
