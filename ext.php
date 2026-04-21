<?php
/**
 *
 * Patreon Integration for phpBB.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon;

class ext extends \phpbb\extension\base
{
	public function is_enableable()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.0', '>=')
			&& extension_loaded('curl');
	}

	public function enable_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')
				->enable_notifications('avathar.bbpatreon.notification.type.patreon_linked');
			return 'notification';
		}

		return parent::enable_step($old_state);
	}

	public function disable_step($old_state)
	{
		if ($old_state === false)
		{
			$notification_manager = $this->container->get('notification_manager');
			$notification_manager->purge_notifications('avathar.bbpatreon.notification.type.patreon_linked');
			$notification_manager->disable_notifications('avathar.bbpatreon.notification.type.patreon_linked');
			return 'notification';
		}

		return parent::disable_step($old_state);
	}

	public function purge_step($old_state)
	{
		if ($old_state === false)
		{
			$this->container->get('notification_manager')
				->purge_notifications('avathar.bbpatreon.notification.type.patreon_linked');
			return 'notification';
		}

		if ($old_state === 'notification')
		{
			// Remove orphan OAuth links from the core table — without the
			// extension's patreon_sync/patreon_tiers tables these are useless.
			$db = $this->container->get('dbal.conn');
			$db->sql_query('DELETE FROM ' . $this->container->getParameter('tables.auth_provider_oauth_account_assoc') .
				" WHERE provider = 'patreon'");
			return 'oauth_cleanup';
		}

		return parent::purge_step($old_state);
	}
}
