<?php
/**
 *
 * Patreon Integration for phpBB.
 * Event listener.
 *
 * @copyright (c) 2024 Sajaki
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \avathar\bbpatreon\service\api_client */
	protected $api_client;

	/** @var \avathar\bbpatreon\service\group_mapper */
	protected $group_mapper;

	/** @var string */
	protected $patreon_sync_table;

	/**
	 * Constructor.
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\avathar\bbpatreon\service\api_client $api_client,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		string $patreon_sync_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->api_client			= $api_client;
		$this->group_mapper			= $group_mapper;
		$this->patreon_sync_table	= $patreon_sync_table;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'	=> 'load_language_on_setup',
			'core.oauth_login_after_check_if_provider_id_has_match'	=> 'on_oauth_login',
		];
	}

	/**
	 * Load extension language file during user setup.
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'avathar/bbpatreon',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * After OAuth login/link, fetch Patreon tier and sync groups.
	 */
	public function on_oauth_login($event)
	{
		$data = $event['data'];

		// Only handle Patreon provider
		if (!isset($data['provider']) || $data['provider'] !== 'patreon')
		{
			return;
		}

		$row = $event['row'];

		// Only sync when we have a matched user (row is truthy)
		if (empty($row))
		{
			return;
		}

		$patreon_user_id = $data['oauth_provider_id'];
		$user_id = (int) $row['user_id'];

		// Fetch this user's membership data from Patreon using creator token
		$members = $this->api_client->get_campaign_members();

		$patron_status = 'pending_link';
		$tier_id = '';
		$tier_label = '';
		$pledge_cents = 0;

		foreach ($members as $member)
		{
			if ($member['patreon_user_id'] === $patreon_user_id)
			{
				$patron_status = $member['patron_status'] ?: 'pending_link';
				$tier_id = $member['tier_id'];
				$tier_label = $member['tier_label'];
				$pledge_cents = (int) $member['pledge_cents'];
				break;
			}
		}

		// Upsert patreon_sync table
		$this->upsert_sync($patreon_user_id, $tier_id, $tier_label, $patron_status, $pledge_cents);

		// Sync phpBB group membership
		$this->group_mapper->sync_user_groups($user_id, $tier_id, $patron_status);
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
