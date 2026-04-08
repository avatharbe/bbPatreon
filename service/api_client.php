<?php
/**
 *
 * Patreon Integration for phpBB.
 * Patreon API v2 client using creator access token.
 *
 * @copyright (c) 2024 Sajaki
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\service;

class api_client
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var string */
	protected $base_url = 'https://www.patreon.com/api/oauth2/v2/';

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config		$config
	 * @param \phpbb\log\log_interface	$log
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\log\log_interface $log)
	{
		$this->config	= $config;
		$this->log		= $log;
	}

	/**
	 * Make an API request using the creator access token.
	 *
	 * @param string	$url		Full URL or path relative to base_url
	 * @param string	$method		HTTP method
	 * @param array		$post_data	POST body data
	 * @param bool		$is_retry	Whether this is a retry after token refresh
	 * @return array	Decoded JSON response
	 */
	public function request(string $url, string $method = 'GET', array $post_data = [], bool $is_retry = false): array
	{
		if (strpos($url, 'https://') !== 0)
		{
			$url = $this->base_url . ltrim($url, '/');
		}

		$token = $this->config['patreon_creator_access_token'];
		if (empty($token))
		{
			return ['error' => 'No creator access token configured'];
		}

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL				=> $url,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> 30,
			CURLOPT_HTTPHEADER		=> [
				'Authorization: Bearer ' . $token,
				'User-Agent: Avathar Forum - Patreon Sync',
				'Content-Type: application/json',
			],
		]);

		if ($method === 'POST')
		{
			curl_setopt($ch, CURLOPT_POST, true);
			if (!empty($post_data))
			{
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
			}
		}

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($error)
		{
			$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_API_ERROR', false, [$error]);
			return ['error' => $error];
		}

		// Auto-refresh on 401 and retry once
		if ($http_code === 401 && !$is_retry)
		{
			if ($this->refresh_token())
			{
				return $this->request($url, $method, $post_data, true);
			}
		}

		$data = json_decode($response, true);
		if ($data === null)
		{
			return ['error' => 'Invalid JSON response', 'http_code' => $http_code];
		}

		return $data;
	}

	/**
	 * Refresh the creator access token.
	 *
	 * @return bool True on success
	 */
	public function refresh_token(): bool
	{
		$refresh_token = $this->config['patreon_creator_refresh_token'];
		if (empty($refresh_token))
		{
			return false;
		}

		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_URL				=> 'https://www.patreon.com/api/oauth2/token',
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_TIMEOUT			=> 30,
			CURLOPT_POST			=> true,
			CURLOPT_POSTFIELDS		=> http_build_query([
				'grant_type'	=> 'refresh_token',
				'refresh_token'	=> $refresh_token,
				'client_id'		=> $this->config['patreon_client_id'],
				'client_secret'	=> $this->config['patreon_client_secret'],
			]),
			CURLOPT_HTTPHEADER		=> [
				'User-Agent: Avathar Forum - Patreon Sync',
			],
		]);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http_code !== 200)
		{
			$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_TOKEN_REFRESH_FAILED', false, [(string) $http_code]);
			return false;
		}

		$data = json_decode($response, true);
		if (!isset($data['access_token']))
		{
			return false;
		}

		$this->config->set('patreon_creator_access_token', $data['access_token']);
		if (isset($data['refresh_token']))
		{
			$this->config->set('patreon_creator_refresh_token', $data['refresh_token']);
		}

		return true;
	}

	/**
	 * Get all campaign members with their tiers.
	 *
	 * @return array Array of member data
	 */
	public function get_campaign_members(): array
	{
		$campaign_id = $this->config['patreon_campaign_id'];
		if (empty($campaign_id))
		{
			return [];
		}

		$members = [];
		$url = 'campaigns/' . $campaign_id . '/members'
			. '?include=currently_entitled_tiers,user'
			. '&fields[member]=patron_status,currently_entitled_amount_cents'
			. '&fields[tier]=title'
			. '&fields[user]=email,full_name'
			. '&page[count]=1000';

		while ($url)
		{
			$result = $this->request($url);

			if (isset($result['error']) || !isset($result['data']))
			{
				break;
			}

			// Build included resources index
			$included = [];
			if (isset($result['included']))
			{
				foreach ($result['included'] as $resource)
				{
					$included[$resource['type']][$resource['id']] = $resource;
				}
			}

			foreach ($result['data'] as $member)
			{
				$patreon_user_id = '';
				if (isset($member['relationships']['user']['data']['id']))
				{
					$patreon_user_id = $member['relationships']['user']['data']['id'];
				}

				$tier_id = '';
				$tier_label = '';
				if (!empty($member['relationships']['currently_entitled_tiers']['data']))
				{
					$tier_data = $member['relationships']['currently_entitled_tiers']['data'][0];
					$tier_id = $tier_data['id'];
					if (isset($included['tier'][$tier_id]['attributes']['title']))
					{
						$tier_label = $included['tier'][$tier_id]['attributes']['title'];
					}
				}

				$members[] = [
					'patreon_user_id'	=> $patreon_user_id,
					'patron_status'		=> $member['attributes']['patron_status'] ?? '',
					'pledge_cents'		=> $member['attributes']['currently_entitled_amount_cents'] ?? 0,
					'tier_id'			=> $tier_id,
					'tier_label'		=> $tier_label,
				];
			}

			// Pagination
			$url = $result['links']['next'] ?? null;
		}

		return $members;
	}

	/**
	 * Register a webhook with Patreon.
	 *
	 * @param string	$callback_url	The webhook URL
	 * @return array	API response
	 */
	public function register_webhook(string $callback_url): array
	{
		$campaign_id = $this->config['patreon_campaign_id'];

		return $this->request('webhooks', 'POST', [
			'data' => [
				'type'			=> 'webhook',
				'attributes'	=> [
					'triggers'	=> [
						'members:pledge:create',
						'members:pledge:update',
						'members:pledge:delete',
					],
					'uri'		=> $callback_url,
					'paused'	=> false,
				],
				'relationships'	=> [
					'campaign'	=> [
						'data' => [
							'type'	=> 'campaign',
							'id'	=> $campaign_id,
						],
					],
				],
			],
		]);
	}
}
