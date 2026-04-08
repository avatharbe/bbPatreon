<?php
/**
 *
 * Patreon Integration for phpBB.
 * Nightly cron task for reconciling Patreon membership data.
 *
 * @copyright (c) 2024 Sajaki
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\cron\task;

class sync extends \phpbb\cron\task\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \avathar\bbpatreon\service\api_client */
	protected $api_client;

	/** @var \avathar\bbpatreon\service\group_mapper */
	protected $group_mapper;

	/** @var string */
	protected $patreon_sync_table;

	/** @var string */
	protected $oauth_accounts_table;

	/**
	 * Constructor.
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		\avathar\bbpatreon\service\api_client $api_client,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		string $patreon_sync_table,
		string $oauth_accounts_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->log					= $log;
		$this->api_client			= $api_client;
		$this->group_mapper			= $group_mapper;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_runnable()
	{
		return !empty($this->config['patreon_creator_access_token'])
			&& !empty($this->config['patreon_campaign_id']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function should_run()
	{
		return (time() - (int) $this->config['patreon_last_cron_sync']) > 86400;
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		$members = $this->api_client->get_campaign_members();

		if (empty($members))
		{
			$this->config->set('patreon_last_cron_sync', time());
			return;
		}

		// Build lookup of linked phpBB users by patreon_user_id
		$linked_users = $this->get_all_linked_users();

		// Track which patreon_user_ids we see from the API
		$seen_ids = [];
		$synced = 0;

		foreach ($members as $member)
		{
			$patreon_user_id = $member['patreon_user_id'];
			if (empty($patreon_user_id))
			{
				continue;
			}

			$seen_ids[] = $patreon_user_id;

			// Upsert sync table
			$this->upsert_sync(
				$patreon_user_id,
				$member['tier_id'],
				$member['tier_label'],
				$member['patron_status'] ?: 'pending_link',
				(int) $member['pledge_cents']
			);

			// Sync groups if linked
			if (isset($linked_users[$patreon_user_id]))
			{
				$user_id = (int) $linked_users[$patreon_user_id];
				$this->group_mapper->sync_user_groups(
					$user_id,
					$member['tier_id'],
					$member['patron_status'] ?: 'pending_link'
				);
				$synced++;
			}
		}

		// Handle orphaned sync rows (users no longer in API response)
		$this->handle_orphaned_members($seen_ids, $linked_users);

		// Enforce grace period demotions
		$this->enforce_grace_period($linked_users);

		$this->config->set('patreon_last_cron_sync', time());

		$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_CRON_SYNC', false, [
			(string) count($members),
			(string) $synced,
		]);
	}

	/**
	 * Get all Patreon-linked phpBB users.
	 *
	 * @return array patreon_user_id => phpbb_user_id
	 */
	protected function get_all_linked_users(): array
	{
		$sql = 'SELECT user_id, oauth_provider_id FROM ' . $this->oauth_accounts_table . "
			WHERE provider = 'patreon'";
		$result = $this->db->sql_query($sql);

		$users = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[$row['oauth_provider_id']] = $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $users;
	}

	/**
	 * Mark sync rows as former_patron if not found in API response.
	 */
	protected function handle_orphaned_members(array $seen_ids, array $linked_users): void
	{
		if (empty($seen_ids))
		{
			return;
		}

		$sql = 'SELECT patreon_user_id FROM ' . $this->patreon_sync_table . "
			WHERE pledge_status NOT IN ('former_patron', 'pending_link')
				AND " . $this->db->sql_in_set('patreon_user_id', $seen_ids, true);
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$orphan_id = $row['patreon_user_id'];

			$sql_update = 'UPDATE ' . $this->patreon_sync_table . "
				SET pledge_status = 'former_patron',
					tier_id = '',
					pledge_cents = 0,
					last_synced_at = " . time() . "
				WHERE patreon_user_id = '" . $this->db->sql_escape($orphan_id) . "'";
			$this->db->sql_query($sql_update);

			if (isset($linked_users[$orphan_id]))
			{
				$grace_days = (int) $this->config['patreon_grace_period_days'];
				if ($grace_days === 0)
				{
					$this->group_mapper->demote_from_all_patron_groups((int) $linked_users[$orphan_id]);
				}
			}
		}
		$this->db->sql_freeresult($result);
	}

	/**
	 * Enforce grace period demotions for former/declined patrons.
	 */
	protected function enforce_grace_period(array $linked_users): void
	{
		$grace_days = (int) $this->config['patreon_grace_period_days'];
		if ($grace_days <= 0)
		{
			return;
		}

		$cutoff = time() - ($grace_days * 86400);

		$sql = 'SELECT patreon_user_id FROM ' . $this->patreon_sync_table . "
			WHERE pledge_status IN ('former_patron', 'declined_patron')
				AND last_webhook_at > 0
				AND last_webhook_at < " . (int) $cutoff;
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$patreon_id = $row['patreon_user_id'];
			if (isset($linked_users[$patreon_id]))
			{
				$this->group_mapper->demote_from_all_patron_groups((int) $linked_users[$patreon_id]);
			}
		}
		$this->db->sql_freeresult($result);
	}

	/**
	 * Insert or update the patreon_sync record.
	 */
	protected function upsert_sync(string $patreon_user_id, string $tier_id, string $tier_label, string $pledge_status, int $pledge_cents): void
	{
		$sql = 'SELECT patreon_user_id FROM ' . $this->patreon_sync_table . "
			WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
		$result = $this->db->sql_query($sql);
		$exists = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$data = [
			'tier_id'			=> $tier_id,
			'tier_label'		=> $tier_label,
			'pledge_status'		=> $pledge_status,
			'pledge_cents'		=> $pledge_cents,
			'last_synced_at'	=> time(),
		];

		if ($exists)
		{
			$sql = 'UPDATE ' . $this->patreon_sync_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $data) . "
				WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
		}
		else
		{
			$data['patreon_user_id'] = $patreon_user_id;
			$sql = 'INSERT INTO ' . $this->patreon_sync_table . ' ' . $this->db->sql_build_array('INSERT', $data);
		}

		$this->db->sql_query($sql);
	}
}
