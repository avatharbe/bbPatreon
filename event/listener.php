<?php
/**
 *
 * Patreon Integration for phpBB.
 * Event listener.
 *
 * @copyright (c) 2026 A. Vandenberghe
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

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var string */
	protected $patreon_sync_table;

	/** @var string */
	protected $patreon_tiers_table;

	/** @var string */
	protected $oauth_accounts_table;

	/** @var array|null Cached patron data keyed by user_id */
	protected $team_patron_data;

	/**
	 * Constructor.
	 */
	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\avathar\bbpatreon\service\api_client $api_client,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		\phpbb\controller\helper $helper,
		string $patreon_sync_table,
		string $patreon_tiers_table,
		string $oauth_accounts_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->api_client			= $api_client;
		$this->group_mapper			= $group_mapper;
		$this->helper				= $helper;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->patreon_tiers_table	= $patreon_tiers_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'	=> 'load_language_on_setup',
			'core.page_header'	=> 'add_page_header_links',
			'core.memberlist_team_modify_template_vars'	=> 'add_patreon_to_team',
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
	 * Add supporters page link to the page header when enabled.
	 */
	public function add_page_header_links()
	{
		if (!empty($this->config['patreon_supporters_page_enabled']))
		{
			$this->language->add_lang('common', 'avathar/bbpatreon');

			$this->db->sql_return_on_error(true);
			$sql = 'SELECT COUNT(*) as cnt FROM ' . $this->patreon_sync_table . "
				WHERE show_public = 1 AND pledge_status = 'active_patron'";
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			$this->db->sql_return_on_error(false);

			$count = $row ? (int) $row['cnt'] : 0;

			global $template;
			$template->assign_vars([
				'U_PATREON_SUPPORTERS'		=> $this->helper->route('avathar_bbpatreon_supporters'),
				'S_PATREON_SUPPORTERS'		=> true,
				'PATREON_SUPPORTERS_COUNT'	=> $count,
			]);
		}
	}

	/**
	 * Add Patreon tier badge to usernames on the team page.
	 */
	public function add_patreon_to_team($event)
	{
		if (!$this->config['patreon_supporters_page_enabled'])
		{
			return;
		}

		// Lazy-load all patron data on first call
		if ($this->team_patron_data === null)
		{
			$this->team_patron_data = [];

			$sql = 'SELECT oa.user_id, pt.tier_label, ps.pledge_cents, ps.pledge_status,
						ps.show_public, ps.show_pledge_public
				FROM ' . $this->patreon_sync_table . ' ps
				JOIN ' . $this->oauth_accounts_table . " oa
					ON (oa.provider = 'patreon' AND oa.oauth_provider_id = ps.patreon_user_id)
				LEFT JOIN " . $this->patreon_tiers_table . " pt ON (pt.tier_id = ps.tier_id)
				WHERE ps.pledge_status = 'active_patron'";
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$this->team_patron_data[(int) $row['user_id']] = $row;
			}
			$this->db->sql_freeresult($result);
		}

		$row = $event['row'];
		$user_id = (int) $row['user_id'];

		if (!isset($this->team_patron_data[$user_id]))
		{
			return;
		}

		$patron = $this->team_patron_data[$user_id];
		$template_vars = $event['template_vars'];

		// Build the badge text
		$badge_parts = [];
		if (!empty($patron['tier_label']))
		{
			$badge_parts[] = $patron['tier_label'];
		}

		if (!empty($this->config['patreon_supporters_show_amounts'])
			&& !empty($patron['show_pledge_public'])
			&& !empty($patron['show_public'])
			&& (int) $patron['pledge_cents'] > 0)
		{
			$badge_parts[] = $this->format_currency((int) $patron['pledge_cents']);
		}

		if (!empty($badge_parts))
		{
			$badge = implode(' — ', $badge_parts);
			$template_vars['USERNAME_FULL'] .= ' <span class="patreon-badge" style="font-size: 0.85em; color: #e87d2a; font-style: italic;">&#9829; ' . $badge . '</span>';
		}

		$event['template_vars'] = $template_vars;
	}

	/**
	 * Format a pledge amount in cents with the campaign currency symbol.
	 */
	protected function format_currency(int $cents): string
	{
		$symbols = [
			'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$',
			'AUD' => 'A$', 'NZD' => 'NZ$', 'JPY' => '¥', 'CHF' => 'CHF ',
			'SEK' => 'kr ', 'NOK' => 'kr ', 'DKK' => 'kr ', 'PLN' => 'zł',
			'BRL' => 'R$', 'MXN' => 'MX$',
		];

		$currency = !empty($this->config['patreon_currency']) ? $this->config['patreon_currency'] : 'USD';
		$symbol = $symbols[$currency] ?? $currency . ' ';

		return $symbol . number_format($cents / 100, 2);
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
		$pledge_cents = 0;

		foreach ($members as $member)
		{
			if ($member['patreon_user_id'] === $patreon_user_id)
			{
				$patron_status = $member['patron_status'] ?: 'pending_link';
				$tier_id = $member['tier_id'];
				$pledge_cents = (int) $member['pledge_cents'];
				break;
			}
		}

		// Upsert patreon_sync table
		$this->upsert_sync($patreon_user_id, $tier_id, $patron_status, $pledge_cents);

		// Sync phpBB group membership
		$this->group_mapper->sync_user_groups($user_id, $tier_id, $patron_status);
	}

	/**
	 * Insert or update the patreon_sync record.
	 */
	protected function upsert_sync(string $patreon_user_id, string $tier_id, string $pledge_status, int $pledge_cents): void
	{
		$sql = 'SELECT patreon_user_id FROM ' . $this->patreon_sync_table . "
			WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
		$result = $this->db->sql_query($sql);
		$exists = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$data = [
			'tier_id'			=> $tier_id,
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
