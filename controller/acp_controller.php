<?php
/**
 *
 * Patreon Integration for phpBB.
 * ACP controller.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

class acp_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \avathar\bbpatreon\service\api_client */
	protected $api_client;

	/** @var \avathar\bbpatreon\service\group_mapper */
	protected $group_mapper;

	/** @var string */
	protected $patreon_sync_table;

	/** @var string */
	protected $patreon_tiers_table;

	/** @var string */
	protected $oauth_accounts_table;

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
		\avathar\bbpatreon\service\api_client $api_client,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		string $patreon_sync_table,
		string $patreon_tiers_table,
		string $oauth_accounts_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->log					= $log;
		$this->request				= $request;
		$this->template				= $template;
		$this->user					= $user;
		$this->api_client			= $api_client;
		$this->group_mapper			= $group_mapper;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->patreon_tiers_table	= $patreon_tiers_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
	}

	public function display_options()
	{
		$this->language->add_lang('common', 'avathar/bbpatreon');
		$this->language->add_lang('info_acp_bbpatreon', 'avathar/bbpatreon');

		add_form_key('avathar_bbpatreon_acp');

		$errors = [];

		// Handle form submissions
		if ($this->request->is_set_post('submit'))
		{
			$errors = $this->SetSettings($errors);
		}
		else if ($this->request->is_set_post('sync'))
		{
			$errors = $this->SyncMembers($errors);
		}
		else if ($this->request->is_set_post('fetch_campaign'))
		{
			$errors = $this->FetchCampaign($errors);
		}
		else if ($this->request->is_set_post('fetch_tiers'))
		{
			$errors = $this->ExtractTiers($errors);
		}
		else if ($this->request->is_set_post('register_webhook'))
		{
			$errors = $this->RegisterWebhooks($errors);
		}
		else if ($this->request->is_set_post('check_webhook'))
		{
			$errors = $this->CheckWebhookStatus($errors);
		}
		else if ($this->request->is_set_post('test_webhook'))
		{
			$errors = $this->TestWebhook($errors);
		}

		$s_errors = !empty($errors);

		// Get linked users for the table
		$linked_users = $this->get_linked_users();

		// Get phpBB groups for the tier mapping dropdowns
		$groups = $this->get_phpbb_groups();

		// Load tiers from database
		$sql = 'SELECT tier_id, tier_label, group_id FROM ' . $this->patreon_tiers_table . ' ORDER BY tier_label ASC';
		$result = $this->db->sql_query($sql);
		$tiers = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$tiers[] = $row;
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'S_ERROR'		=> $s_errors,
			'ERROR_MSG'		=> $s_errors ? implode('<br>', $errors) : '',
			'U_ACTION'		=> $this->u_action,

			'PATREON_WEBHOOK_URL'			=> generate_board_url() . '/patreon/webhook',
			'PATREON_CLIENT_ID'				=> $this->config['patreon_client_id'],
			'PATREON_CLIENT_SECRET'			=> $this->config['patreon_client_secret'],
			'PATREON_CREATOR_ACCESS_TOKEN'	=> $this->config['patreon_creator_access_token'],
			'PATREON_CREATOR_REFRESH_TOKEN'	=> $this->config['patreon_creator_refresh_token'],
			'PATREON_CAMPAIGN_ID'			=> $this->config['patreon_campaign_id'],
			'PATREON_WEBHOOK_SECRET'		=> $this->config['patreon_webhook_secret'],
			'PATREON_GRACE_PERIOD_DAYS'		=> (int) $this->config['patreon_grace_period_days'],
			'PATREON_LAST_SYNC'				=> $this->config['patreon_last_cron_sync'] ? $this->user->format_date((int) $this->config['patreon_last_cron_sync']) : $this->language->lang('PATREON_NEVER'),
		]);

		// Assign tier mapping rows
		foreach ($tiers as $tier)
		{
			$this->template->assign_block_vars('tier_map', [
				'TIER_ID'		=> $tier['tier_id'],
				'TIER_LABEL'	=> $tier['tier_label'],
				'GROUP_ID'		=> (int) $tier['group_id'],
			]);
		}

		// Assign groups for dropdown
		foreach ($groups as $group)
		{
			$this->template->assign_block_vars('groups', [
				'GROUP_ID'		=> $group['group_id'],
				'GROUP_NAME'	=> $group['group_name'],
			]);
		}

		// Assign linked users
		foreach ($linked_users as $lu)
		{
			$this->template->assign_block_vars('linked_users', [
				'USERNAME'			=> $lu['username'],
				'PATREON_USER_ID'	=> $lu['patreon_user_id'],
				'TIER_LABEL'		=> $lu['tier_label'],
				'PLEDGE_STATUS'		=> $lu['pledge_status'],
				'PLEDGE_AMOUNT'		=> $this->format_currency($lu['pledge_cents']),
				'LAST_WEBHOOK'		=> $lu['last_webhook_at'] ? $this->user->format_date((int) $lu['last_webhook_at']) : '-',
				'LAST_SYNCED'		=> $lu['last_synced_at'] ? $this->user->format_date((int) $lu['last_synced_at']) : '-',
			]);
		}
	}

	/**
	 * core function for saving acp settings
	 * @return void
	 */
	protected function save_settings(): void
	{
		$client_id = $this->request->variable('patreon_client_id', '');
		$client_secret = $this->request->variable('patreon_client_secret', '');

		$this->config->set('patreon_client_id', $client_id);
		$this->config->set('patreon_client_secret', $client_secret);
		$this->config->set('patreon_creator_access_token', $this->request->variable('patreon_creator_access_token', ''));
		$this->config->set('patreon_creator_refresh_token', $this->request->variable('patreon_creator_refresh_token', ''));
		$this->config->set('patreon_campaign_id', $this->request->variable('patreon_campaign_id', ''));
		$this->config->set('patreon_webhook_secret', $this->request->variable('patreon_webhook_secret', ''));
		$this->config->set('patreon_grace_period_days', $this->request->variable('patreon_grace_period_days', 0));

		// Sync phpBB OAuth convention keys
		$this->config->set('auth_oauth_patreon_key', $client_id);
		$this->config->set('auth_oauth_patreon_secret', $client_secret);

		// Update tier-group mappings from form arrays
		$tier_ids = $this->request->variable('tier_ids', ['']);
		$group_ids = $this->request->variable('group_ids', [0]);

		foreach ($tier_ids as $i => $tid)
		{
			$tid = trim($tid);
			if (!empty($tid))
			{
				$sql = 'UPDATE ' . $this->patreon_tiers_table . '
					SET group_id = ' . (int) ($group_ids[$i] ?? 0) . "
					WHERE tier_id = '" . $this->db->sql_escape($tid) . "'";
				$this->db->sql_query($sql);
			}
		}
	}

	/**
	 * Perform the manual sync
	 *
	 * @return string
	 */
	protected function run_manual_sync(): string
	{
		$members = $this->api_client->get_campaign_members();

		if (isset($members['error']))
		{
			return $this->language->lang('ACP_BBPATREON_SYNC_ERROR', $members['error']);
		}

		// Get linked users
		$sql = 'SELECT user_id, oauth_provider_id FROM ' . $this->oauth_accounts_table . "
			WHERE provider = 'patreon'";
		$result = $this->db->sql_query($sql);
		$linked = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$linked[$row['oauth_provider_id']] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		$synced = 0;
		foreach ($members as $member)
		{
			if (empty($member['patreon_user_id']))
			{
				continue;
			}

			// Upsert sync
			$this->upsert_sync(
				$member['patreon_user_id'],
				$member['tier_id'],
				$member['patron_status'] ?: 'pending_link',
				(int) $member['pledge_cents']
			);

			if (isset($linked[$member['patreon_user_id']]))
			{
				$this->group_mapper->sync_user_groups(
					$linked[$member['patreon_user_id']],
					$member['tier_id'],
					$member['patron_status'] ?: 'pending_link'
				);
				$synced++;
			}
		}

		$this->config->set('patreon_last_cron_sync', time());
		$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_PATREON_MANUAL_SYNC', false, [
			(string) count($members),
			(string) $synced,
		]);

		return $this->language->lang('ACP_BBPATREON_SYNC_DONE', count($members), $synced);
	}

	protected function register_webhook(): array
	{
		$callback_url = generate_board_url() . '/patreon/webhook';
		$result = $this->api_client->register_webhook($callback_url);

		if (isset($result['data']['attributes']['secret']))
		{
			$this->config->set('patreon_webhook_secret', $result['data']['attributes']['secret']);
		}

		return $result;
	}

	/**
	 * Fetch registered webhooks from Patreon API and report status.
	 */
	protected function check_webhook_status(): ?string
	{
		$result = $this->api_client->request('webhooks');

		if (!isset($result['data']))
		{
			return null;
		}

		if (empty($result['data']))
		{
			return $this->language->lang('ACP_BBPATREON_WEBHOOK_NONE_REGISTERED');
		}

		$webhook_url = generate_board_url() . '/patreon/webhook';
		$lines = [];

		foreach ($result['data'] as $wh)
		{
			$attrs = $wh['attributes'] ?? [];
			$uri = $attrs['uri'] ?? '';
			$paused = !empty($attrs['paused']) ? $this->language->lang('YES') : $this->language->lang('NO');
			$triggers = implode(', ', $attrs['triggers'] ?? []);
			$last_attempted = $attrs['last_attempted_at'] ?? '-';
			$num_failed = $attrs['num_consecutive_times_failed'] ?? 0;
			$secret_match = ($uri === $webhook_url) ? $this->language->lang('YES') : $this->language->lang('NO');

			$lines[] = $this->language->lang('ACP_BBPATREON_WEBHOOK_STATUS_ROW',
				$uri,
				$paused,
				$triggers,
				$last_attempted,
				(string) $num_failed,
				$secret_match
			);
		}

		return $this->language->lang('ACP_BBPATREON_WEBHOOK_STATUS_HEADER') . '<br>' . implode('<br><br>', $lines);
	}

	/**
	 * Send a self-signed test ping to our own webhook endpoint.
	 */
	protected function test_webhook(): string
	{
		$secret = $this->config['patreon_webhook_secret'];
		if (empty($secret))
		{
			return $this->language->lang('ACP_BBPATREON_WEBHOOK_TEST_NO_SECRET');
		}

		$webhook_url = generate_board_url() . '/patreon/webhook';

		$body = json_encode([
			'data' => [
				'type'			=> 'member',
				'id'			=> 'test-ping-' . time(),
				'attributes'	=> [
					'patron_status'						=> 'active_patron',
					'currently_entitled_amount_cents'	=> 0,
				],
				'relationships'	=> [
					'currently_entitled_tiers'	=> ['data' => []],
					'user'						=> ['data' => ['id' => 'bbpatreon-test-ping', 'type' => 'user']],
				],
			],
		]);

		$signature = hash_hmac('md5', $body, $secret);

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL				=> $webhook_url,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> 10,
			CURLOPT_POST			=> true,
			CURLOPT_POSTFIELDS		=> $body,
			CURLOPT_HTTPHEADER		=> [
				'Content-Type: application/json',
				'X-Patreon-Event: members:pledge:create',
				'X-Patreon-Signature: ' . $signature,
			],
		]);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error)
		{
			return $this->language->lang('ACP_BBPATREON_WEBHOOK_TEST_CURL_ERROR', $error);
		}

		$decoded = json_decode($response, true);
		$status = $decoded['status'] ?? 'unknown';

		if ($http_code === 200 && $status === 'ok')
		{
			return $this->language->lang('ACP_BBPATREON_WEBHOOK_TEST_OK', $webhook_url);
		}

		return $this->language->lang('ACP_BBPATREON_WEBHOOK_TEST_FAIL', (string) $http_code, $response);
	}

	protected function get_linked_users(): array
	{
		$sql = 'SELECT u.username, oa.oauth_provider_id as patreon_user_id,
				pt.tier_label, ps.pledge_status, ps.pledge_cents,
				ps.last_webhook_at, ps.last_synced_at
			FROM ' . $this->oauth_accounts_table . ' oa
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = oa.user_id)
			LEFT JOIN ' . $this->patreon_sync_table . ' ps ON (ps.patreon_user_id = oa.oauth_provider_id)
			LEFT JOIN ' . $this->patreon_tiers_table . " pt ON (pt.tier_id = ps.tier_id)
			WHERE oa.provider = 'patreon'
			ORDER BY u.username ASC";
		$result = $this->db->sql_query($sql);

		$users = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$users[] = [
				'username'			=> $row['username'],
				'patreon_user_id'	=> $row['patreon_user_id'],
				'tier_label'		=> $row['tier_label'] ?? '',
				'pledge_status'		=> $row['pledge_status'] ?? '',
				'pledge_cents'		=> (int) ($row['pledge_cents'] ?? 0),
				'last_webhook_at'	=> $row['last_webhook_at'] ?? 0,
				'last_synced_at'	=> $row['last_synced_at'] ?? 0,
			];
		}
		$this->db->sql_freeresult($result);

		return $users;
	}

	protected function get_phpbb_groups(): array
	{
		$sql = 'SELECT group_id, group_name FROM ' . GROUPS_TABLE . '
			WHERE group_type <> ' . GROUP_HIDDEN . '
			ORDER BY group_name ASC';
		$result = $this->db->sql_query($sql);

		$groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $groups;
	}

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

	public function set_page_url($u_action)
	{
		$this->u_action = $u_action;
	}

	/**
	 *
	 * Save Patreon Settings
	 * @param array $errors
	 * @return array
	 */
	public function SetSettings(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$this->save_settings();
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_BBPATREON_SETTINGS');
			trigger_error($this->language->lang('ACP_BBPATREON_SETTING_SAVED') . adm_back_link($this->u_action));
		}
		return $errors;
	}

	/**
	 * Sync the members from Patreon
	 * @param array $errors
	 * @return array
	 */
	public function SyncMembers(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$result = $this->run_manual_sync();
			trigger_error($result . adm_back_link($this->u_action));
		}
		return $errors;
	}

	/**
	 * Get the Patreon Campaign
	 *
	 * @param array $errors
	 * @return array
	 */
	public function FetchCampaign(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$result = $this->api_client->request('campaigns?fields[campaign]=currency');
			if (isset($result['data'][0]['id']))
			{
				$campaign_id = $result['data'][0]['id'];
				$this->config->set('patreon_campaign_id', $campaign_id);

				$currency = $result['data'][0]['attributes']['currency'] ?? 'USD';
				$this->config->set('patreon_currency', $currency);

				trigger_error($this->language->lang('ACP_BBPATREON_CAMPAIGN_FETCHED', $campaign_id) . adm_back_link($this->u_action));
			}
			else
			{
				$errors[] = $this->language->lang('ACP_BBPATREON_CAMPAIGN_FETCH_ERROR');
			}
		}
		return $errors;
	}

	/**
	 * Get the Tiers by API Call
	 * @param array $errors
	 * @return array
	 */
	public function ExtractTiers(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$campaign_id = $this->config['patreon_campaign_id'];
			if (empty($campaign_id))
			{
				$errors[] = $this->language->lang('ACP_BBPATREON_FETCH_TIERS_NO_CAMPAIGN');
			}
			else
			{
				$result = $this->api_client->request('campaigns/' . $campaign_id . '?include=tiers&fields[tier]=title,amount_cents');
				$tiers = [];
				if (isset($result['included']))
				{
					foreach ($result['included'] as $resource)
					{
						if ($resource['type'] === 'tier')
						{
							$tiers[$resource['id']] = [
								'title'			=> $resource['attributes']['title'] ?? '',
								'amount_cents'	=> (int) ($resource['attributes']['amount_cents'] ?? 0),
							];
						}
					}
				}

				if (empty($tiers))
				{
					$errors[] = $this->language->lang('ACP_BBPATREON_FETCH_TIERS_EMPTY');
				}
				else
				{
					$currency = !empty($this->config['patreon_currency']) ? $this->config['patreon_currency'] : 'USD';

					foreach ($tiers as $tid => $tier_data)
					{
						// Check if tier already exists
						$sql = 'SELECT tier_id FROM ' . $this->patreon_tiers_table . "
							WHERE tier_id = '" . $this->db->sql_escape($tid) . "'";
						$check = $this->db->sql_query($sql);
						$exists = $this->db->sql_fetchrow($check);
						$this->db->sql_freeresult($check);

						if ($exists)
						{
							// Update label and amount, preserve group_id
							$sql = 'UPDATE ' . $this->patreon_tiers_table . '
								SET ' . $this->db->sql_build_array('UPDATE', [
									'tier_label'	=> $tier_data['title'],
									'amount_cents'	=> $tier_data['amount_cents'],
									'currency'		=> $currency,
								]) . "
								WHERE tier_id = '" . $this->db->sql_escape($tid) . "'";
						}
						else
						{
							$sql = 'INSERT INTO ' . $this->patreon_tiers_table . ' ' . $this->db->sql_build_array('INSERT', [
								'tier_id'		=> $tid,
								'tier_label'	=> $tier_data['title'],
								'group_id'		=> 0,
								'amount_cents'	=> $tier_data['amount_cents'],
								'currency'		=> $currency,
							]);
						}
						$this->db->sql_query($sql);
					}

					trigger_error($this->language->lang('ACP_BBPATREON_FETCH_TIERS_DONE', count($tiers)) . adm_back_link($this->u_action));
				}
			}
		}
		return $errors;
	}

	/**
	 * register the webhooks
	 *
	 * @param array $errors
	 * @return array
	 */
	public function RegisterWebhooks(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$result = $this->register_webhook();
			if (isset($result['error']))
			{
				$errors[] = $result['error'];
			}
			else
			{
				trigger_error($this->language->lang('ACP_BBPATREON_WEBHOOK_REGISTERED') . adm_back_link($this->u_action));
			}
		}
		return $errors;
	}

	/**
	 * Check Webhook Status
	 * @param array $errors
	 * @return array
	 */
	public function CheckWebhookStatus(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$result = $this->check_webhook_status();
			if ($result === null)
			{
				$errors[] = $this->language->lang('ACP_BBPATREON_WEBHOOK_CHECK_ERROR');
			}
			else
			{
				trigger_error($result . adm_back_link($this->u_action));
			}
		}
		return $errors;
	}

	/**
	 * @param array $errors
	 * @return array
	 */
	public function TestWebhook(array $errors): array
	{
		if (!check_form_key('avathar_bbpatreon_acp'))
		{
			$errors[] = $this->language->lang('FORM_INVALID');
		}

		if (empty($errors))
		{
			$result = $this->test_webhook();
			trigger_error($result . adm_back_link($this->u_action));
		}
		return $errors;
	}
}
