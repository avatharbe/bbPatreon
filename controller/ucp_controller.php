<?php
/**
 *
 * Patreon Integration for phpBB.
 * UCP controller.
 *
 * @copyright (c) 2024 Sajaki
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
	protected $oauth_accounts_table;

	/** @var string */
	protected $oauth_token_table;

	/** @var string */
	protected $oauth_state_table;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var string */
	protected $u_action;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		\avathar\bbpatreon\service\api_client $api_client,
		\phpbb\notification\manager $notification_manager,
		string $patreon_sync_table,
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
		$this->notification_manager	= $notification_manager;
		$this->patreon_sync_table	= $patreon_sync_table;
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

		// Check if account is linked
		$sql = 'SELECT oauth_provider_id FROM ' . $this->oauth_accounts_table . "
			WHERE user_id = " . $user_id . "
				AND provider = 'patreon'";
		$result = $this->db->sql_query($sql);
		$oauth_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$is_linked = ($oauth_row !== false);
		$patreon_user_id = $is_linked ? $oauth_row['oauth_provider_id'] : '';

		// Handle unlink
		if ($this->request->is_set_post('unlink') && $is_linked)
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
					WHERE user_id = " . $user_id . "
						AND provider = 'patreon'";
				$this->db->sql_query($sql);

				$this->group_mapper->demote_from_all_patron_groups($user_id);

				$is_linked = false;
				$patreon_user_id = '';

				meta_refresh(3, $this->u_action);
				$message = $this->language->lang('UCP_BBPATREON_UNLINKED') . '<br><br>' . $this->language->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');
				trigger_error($message);
			}
		}

		// Get sync data if linked
		$sync_data = [];
		if ($is_linked)
		{
			$sql = 'SELECT * FROM ' . $this->patreon_sync_table . "
				WHERE patreon_user_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
			$result = $this->db->sql_query($sql);
			$sync_data = $this->db->sql_fetchrow($result) ?: [];
			$this->db->sql_freeresult($result);
		}

		$s_errors = !empty($errors);

		$this->template->assign_vars([
			'S_ERROR'		=> $s_errors,
			'ERROR_MSG'		=> $s_errors ? implode('<br>', $errors) : '',
			'U_UCP_ACTION'	=> $this->u_action,

			'S_PATREON_LINKED'		=> $is_linked,
			'PATREON_USER_ID'		=> $patreon_user_id,
			'PATREON_TIER_LABEL'	=> $sync_data['tier_label'] ?? '',
			'PATREON_PLEDGE_STATUS'	=> $sync_data['pledge_status'] ?? '',
			'PATREON_PLEDGE_AMOUNT'	=> isset($sync_data['pledge_cents']) ? number_format($sync_data['pledge_cents'] / 100, 2) : '0.00',
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

		// Upsert sync table
		$this->upsert_sync($patreon_user_id, $tier_id, $tier_label, $patron_status, $pledge_cents);

		// Sync groups
		$this->group_mapper->sync_user_groups($user_id, $tier_id, $patron_status);

		// Notify admins/moderators
		$this->notification_manager->add_notifications('avathar.bbpatreon.notification.type.patreon_linked', [
			'user_id'			=> $user_id,
			'patreon_user_id'	=> $patreon_user_id,
			'tier_label'		=> $tier_label,
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

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}
}
