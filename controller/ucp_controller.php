<?php
/**
 *
 * Patreon Integration for phpBB.
 * UCP controller.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

use OAuth\Common\Consumer\Credentials;
use OAuth\ServiceFactory;

class ucp_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \avathar\bbpatreon\service\group_mapper */
	protected $group_mapper;

	/** @var \avathar\bbpatreon\service\api_client */
	protected $api_client;

	/** @var string */
	protected $patreon_sync_table;

	/** @var string */
	protected $patreon_tiers_table;

	/** @var string */
	protected $oauth_accounts_table;

	/** @var string */
	protected $oauth_token_table;

	/** @var string */
	protected $oauth_state_table;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var string */
	protected $u_action;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\log\log_interface $log,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		\avathar\bbpatreon\service\api_client $api_client,
		\phpbb\notification\manager $notification_manager,
		string $patreon_sync_table,
		string $patreon_tiers_table,
		string $oauth_accounts_table,
		string $oauth_token_table,
		string $oauth_state_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->request				= $request;
		$this->template				= $template;
		$this->user					= $user;
		$this->group_mapper			= $group_mapper;
		$this->api_client			= $api_client;
		$this->log					= $log;
		$this->notification_manager	= $notification_manager;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->patreon_tiers_table	= $patreon_tiers_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
		$this->oauth_token_table	= $oauth_token_table;
		$this->oauth_state_table	= $oauth_state_table;
	}

	public function display_options()
	{
		$this->language->add_lang('common', 'avathar/bbpatreon');

		add_form_key('avathar_bbpatreon_ucp');

		$errors = [];
		$user_id = (int) $this->user->data['user_id'];

		// Handle OAuth callback (Patreon redirected back with ?code=...)
		$code = $this->request->variable('code', '');
		if ($code)
		{
			$result = $this->handle_oauth_callback($code, $user_id);
			if ($result === true)
			{
				meta_refresh(3, $this->u_action);
				$message = $this->language->lang('UCP_BBPATREON_LINKED') . '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
				trigger_error($message);
			}
			else
			{
				$errors[] = $result;
			}
		}

		// Handle link button — redirect to Patreon OAuth
		if ($this->request->is_set_post('link'))
		{
			$this->redirect_to_patreon();
			return;
		}

		// Handle re-sync button
		if ($this->request->is_set_post('resync'))
		{
			$errors = $this->handle_resync($user_id, $errors);
		}

		// Check if account is linked
		$sql = 'SELECT oauth_provider_id FROM ' . $this->oauth_accounts_table . '
			WHERE user_id = ' . (int) $user_id . "
				AND provider = 'patreon'";
		$result = $this->db->sql_query($sql);
		$oauth_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$is_linked = ($oauth_row !== false);
		$patreon_user_id = $is_linked ? $oauth_row['oauth_provider_id'] : '';

		// Handle unlink
		if ($this->request->is_set_post('unlink') && $is_linked)
		{
			list($errors, $sql, $is_linked, $patreon_user_id) = $this->HandleUnlinking($errors, $patreon_user_id, $user_id, $is_linked);
		}

		// Handle supporter opt-in preference
		if ($this->request->is_set_post('save_preferences') && $is_linked)
		{
			if (!check_form_key('avathar_bbpatreon_ucp'))
			{
				$errors[] = $this->language->lang('FORM_INVALID');
			}
			else
			{
				$show_public = ($this->config['patreon_supporters_page_enabled'] && $this->request->variable('show_public', 0)) ? 1 : 0;
				$show_pledge = ($this->config['patreon_supporters_show_amounts'] && $this->request->variable('show_pledge_public', 0)) ? 1 : 0;
				$sql = 'UPDATE ' . $this->patreon_sync_table . '
					SET show_public = ' . $show_public . ',
						show_pledge_public = ' . $show_pledge . "
					WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
				$this->db->sql_query($sql);

				meta_refresh(3, $this->u_action);
				$message = $this->language->lang('UCP_BBPATREON_PREFERENCES_SAVED') . '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
				trigger_error($message);
			}
		}

		// Get sync data if linked (join on patreon_tiers for label)
		$sync_data = [];
		if ($is_linked)
		{
			$sql = 'SELECT ps.*, pt.tier_label
				FROM ' . $this->patreon_sync_table . ' ps
				LEFT JOIN ' . $this->patreon_tiers_table . " pt ON (pt.tier_id = ps.tier_id)
				WHERE ps.patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
			$result = $this->db->sql_query($sql);
			$sync_data = $this->db->sql_fetchrow($result) ?: [];
			$this->db->sql_freeresult($result);
		}

		// Look up assigned group name if linked and has a tier
		$group_name = '';
		if ($is_linked && !empty($sync_data['tier_id']))
		{
			$group_name = $this->get_assigned_group_name($sync_data['tier_id']);
		}

		$s_errors = !empty($errors);

		$this->template->assign_vars([
			'S_ERROR'		=> $s_errors,
			'ERROR_MSG'		=> $s_errors ? implode('<br>', $errors) : '',
			'U_UCP_ACTION'	=> $this->u_action,

			'S_PATREON_LINKED'		=> $is_linked,
			'PATREON_USER_ID'		=> $patreon_user_id,
			'PATREON_TIER_LABEL'	=> $sync_data['tier_label'] ?? '',
			'PATREON_PLEDGE_STATUS'	=> $this->get_status_label($sync_data['pledge_status'] ?? ''),
			'PATREON_PLEDGE_STATUS_CLASS'	=> $this->get_status_class($sync_data['pledge_status'] ?? ''),
			'PATREON_PLEDGE_AMOUNT'	=> isset($sync_data['pledge_cents']) && (int) $sync_data['pledge_cents'] > 0 ? $this->format_currency((int) $sync_data['pledge_cents']) : '',
			'PATREON_GROUP_NAME'	=> $group_name,
			'PATREON_LAST_SYNCED'	=> !empty($sync_data['last_synced_at']) ? $this->user->format_date((int) $sync_data['last_synced_at']) : '',
			'S_SUPPORTERS_PAGE_ENABLED'		=> (bool) $this->config['patreon_supporters_page_enabled'],
			'S_SHOW_PUBLIC'					=> !empty($sync_data['show_public']),
			'S_SUPPORTERS_SHOW_AMOUNTS'		=> (bool) $this->config['patreon_supporters_show_amounts'],
			'S_SHOW_PLEDGE_PUBLIC'			=> !empty($sync_data['show_pledge_public']),
		]);
	}

	/**
	 * Redirect the user to Patreon's authorization page.
	 */
	protected function redirect_to_patreon(): void
	{
		$storage = new \phpbb\auth\provider\oauth\token_storage(
			$this->db, $this->user, $this->oauth_token_table, $this->oauth_state_table
		);

		$service = $this->create_oauth_service($storage);

		redirect($service->getAuthorizationUri(), false, true);
	}

	/**
	 * Handle the OAuth callback after Patreon redirects back.
	 *
	 * @return true|string True on success, error message on failure
	 */
	protected function handle_oauth_callback(string $code, int $user_id)
	{
		$storage = new \phpbb\auth\provider\oauth\token_storage(
			$this->db, $this->user, $this->oauth_token_table, $this->oauth_state_table
		);

		$service = $this->create_oauth_service($storage);

		try
		{
			$service->requestAccessToken($code);
		}
		catch (\OAuth\Common\Http\Exception\TokenResponseException $e)
		{
			return $this->language->lang('UCP_BBPATREON_OAUTH_ERROR') . ' ' . $e->getMessage();
		}

		// Get Patreon identity
		try
		{
			$result = json_decode($service->request(
				'https://www.patreon.com/api/oauth2/v2/identity?fields[user]=email,full_name'
			), true);
		}
		catch (\OAuth\Common\Exception\Exception $e)
		{
			return $this->language->lang('UCP_BBPATREON_OAUTH_ERROR') . ' ' . $e->getMessage();
		}

		if (!isset($result['data']['id']))
		{
			return $this->language->lang('UCP_BBPATREON_OAUTH_ERROR');
		}

		$patreon_user_id = $result['data']['id'];

		// Check if this Patreon account is already linked to another user
		$sql = 'SELECT user_id FROM ' . $this->oauth_accounts_table . "
			WHERE provider = 'patreon'
				AND oauth_provider_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
		$db_result = $this->db->sql_query($sql);
		$existing = $this->db->sql_fetchrow($db_result);
		$this->db->sql_freeresult($db_result);

		if ($existing && (int) $existing['user_id'] !== $user_id)
		{
			return $this->language->lang('UCP_BBPATREON_ALREADY_LINKED');
		}

		// Insert link if not already present
		if (!$existing)
		{
			$sql = 'INSERT INTO ' . $this->oauth_accounts_table . ' ' . $this->db->sql_build_array('INSERT', [
				'user_id'			=> $user_id,
				'provider'			=> 'patreon',
				'oauth_provider_id'	=> $patreon_user_id,
			]);
			$this->db->sql_query($sql);
		}

		// Fetch tier data and sync groups
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

		// Upsert sync table
		$this->upsert_sync($patreon_user_id, $tier_id, $patron_status, $pledge_cents);

		// Sync groups
		$this->group_mapper->sync_user_groups($user_id, $tier_id, $patron_status);

		// Look up tier label for logging
		$tier_label = $this->get_tier_label($tier_id);

		// Log the link event
		$this->log->add('admin', $user_id, $this->user->ip, 'LOG_PATREON_LINKED', false, [
			$this->user->data['username'],
			$patreon_user_id,
			$tier_label ?: '-',
			$patron_status,
		]);

		// Notify admins/moderators
		$this->notification_manager->add_notifications('avathar.bbpatreon.notification.type.patreon_linked', [
			'user_id'			=> $user_id,
			'patreon_user_id'	=> $patreon_user_id,
			'tier_id'			=> $tier_id,
			'pledge_status'		=> $patron_status,
		]);

		return true;
	}

	/**
	 * Create the PHPoAuthLib Patreon service instance.
	 */
	protected function create_oauth_service(\phpbb\auth\provider\oauth\token_storage $storage): \OAuth\OAuth2\Service\AbstractService
	{
		$callback = generate_board_url() . '/patreon/callback';

		$credentials = new Credentials(
			$this->config['auth_oauth_patreon_key'],
			$this->config['auth_oauth_patreon_secret'],
			$callback
		);

		$service_factory = new ServiceFactory();
		$service_factory->registerService('patreon', '\\avathar\\bbpatreon\\oauth\\patreon');

		return $service_factory->createService('patreon', $credentials, $storage, [
			'identity',
			'identity[email]',
			'campaigns',
			'campaigns.members',
		]);
	}

	/**
	 * Look up a tier label from the patreon_tiers table.
	 */
	protected function get_tier_label(string $tier_id): string
	{
		if (empty($tier_id))
		{
			return '';
		}

		$sql = 'SELECT tier_label FROM ' . $this->patreon_tiers_table . "
			WHERE tier_id = '" . $this->db->sql_escape($tier_id) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ? $row['tier_label'] : '';
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

	/**
	 * Format a pledge amount in cents with the campaign currency symbol.
	 *
	 * @param int $cents
	 * @return string
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
	 * Handle the re-sync button: fetch fresh tier data from Patreon and update groups.
	 * Rate-limited to once per 5 minutes per user.
	 */
	protected function handle_resync(int $user_id, array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_ucp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
			return $errors;
		}

		// Check if linked
		$sql = 'SELECT oauth_provider_id FROM ' . $this->oauth_accounts_table . '
			WHERE user_id = ' . (int) $user_id . "
				AND provider = 'patreon'";
		$result = $this->db->sql_query($sql);
		$oauth_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$oauth_row)
		{
			$errors[] = $this->language->lang('UCP_BBPATREON_NOT_LINKED');
			return $errors;
		}

		$patreon_user_id = $oauth_row['oauth_provider_id'];

		// Rate limit: check last_synced_at
		$sql = 'SELECT last_synced_at FROM ' . $this->patreon_sync_table . "
			WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
		$result = $this->db->sql_query($sql);
		$sync_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($sync_row && (time() - (int) $sync_row['last_synced_at']) < 300)
		{
			$errors[] = $this->language->lang('UCP_BBPATREON_RESYNC_TOO_SOON');
			return $errors;
		}

		// Fetch fresh data from Patreon API
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

		$this->upsert_sync($patreon_user_id, $tier_id, $patron_status, $pledge_cents);
		$this->group_mapper->sync_user_groups($user_id, $tier_id, $patron_status);

		meta_refresh(3, $this->u_action);
		$message = $this->language->lang('UCP_BBPATREON_RESYNCED') . '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
		trigger_error($message);

		return $errors;
	}

	/**
	 * Look up the phpBB group name assigned to a tier.
	 */
	protected function get_assigned_group_name(string $tier_id): string
	{
		$sql = 'SELECT g.group_name
			FROM ' . $this->patreon_tiers_table . ' pt
			JOIN ' . GROUPS_TABLE . ' g ON (g.group_id = pt.group_id)
			WHERE pt.tier_id = \'' . $this->db->sql_escape($tier_id) . '\'
				AND pt.group_id > 0';
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return '';
		}

		// phpBB stores group names as language keys for built-in groups
		return $this->language->is_set('G_' . $row['group_name'])
			? $this->language->lang('G_' . $row['group_name'])
			: $row['group_name'];
	}

	/**
	 * Get a human-readable label for a pledge status.
	 */
	protected function get_status_label(string $status): string
	{
		$key = 'UCP_BBPATREON_STATUS_' . strtoupper($status);
		return $this->language->is_set($key) ? $this->language->lang($key) : $status;
	}

	/**
	 * Get a CSS class for a pledge status.
	 */
	protected function get_status_class(string $status): string
	{
		switch ($status)
		{
			case 'active_patron':	return 'patreon-active';
			case 'declined_patron':	return 'patreon-declined';
			case 'former_patron':	return 'patreon-former';
			default:				return 'patreon-pending';
		}
	}

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	/**
	 * @param array $errors
	 * @param mixed $patreon_user_id
	 * @param int $user_id
	 * @param bool $is_linked
	 * @return array
	 */
	public function HandleUnlinking(array $errors, mixed $patreon_user_id, int $user_id, bool $is_linked): array
	{
		if (!check_form_key('avathar_bbpatreon_ucp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$sql = 'DELETE FROM ' . $this->patreon_sync_table . "
					WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
			$this->db->sql_query($sql);

			$sql = 'DELETE FROM ' . $this->oauth_accounts_table . "
					WHERE user_id = " . (int) $user_id . "
						AND provider = 'patreon'";
			$this->db->sql_query($sql);

			$this->group_mapper->demote_from_all_patron_groups($user_id);

			$this->log->add('admin', $user_id, $this->user->ip, 'LOG_PATREON_UNLINKED', false, [
				$this->user->data['username'],
				$patreon_user_id,
			]);

			$is_linked = false;
			$patreon_user_id = '';

			meta_refresh(3, $this->u_action);
			$message = $this->language->lang('UCP_BBPATREON_UNLINKED') . '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
			trigger_error($message);
		}
		return array($errors, $sql, $is_linked, $patreon_user_id);
	}
}
