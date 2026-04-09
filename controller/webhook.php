<?php
/**
 *
 * Patreon Integration for phpBB.
 * Webhook controller for Patreon pledge events.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class webhook
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \avathar\bbpatreon\service\group_mapper */
	protected $group_mapper;

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

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
		\phpbb\request\request $request,
		\avathar\bbpatreon\service\group_mapper $group_mapper,
		\phpbb\event\dispatcher_interface $dispatcher,
		string $patreon_sync_table,
		string $oauth_accounts_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->log					= $log;
		$this->request				= $request;
		$this->group_mapper			= $group_mapper;
		$this->dispatcher			= $dispatcher;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
	}

	/**
	 * Handle incoming Patreon webhook.
	 *
	 * @return JsonResponse
	 */
	public function handle(): JsonResponse
	{
		$body = file_get_contents('php://input');

		// Validate HMAC-MD5 signature
		$signature = $this->request->server('HTTP_X_PATREON_SIGNATURE', '');
		$secret = $this->config['patreon_webhook_secret'];

		if (empty($secret) || empty($signature))
		{
			$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_WEBHOOK_NO_SIGNATURE');
			return new JsonResponse(['status' => 'ok']);
		}

		$expected = hash_hmac('md5', $body, $secret);
		if (!hash_equals($expected, $signature))
		{
			$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_WEBHOOK_BAD_SIGNATURE');
			return new JsonResponse(['status' => 'ok']);
		}

		// Parse event type and body
		$event_type = $this->request->server('HTTP_X_PATREON_EVENT', '');
		$payload = json_decode($body, true);

		if (!$payload || !isset($payload['data']))
		{
			return new JsonResponse(['status' => 'ok']);
		}

		$data = $payload['data'];
		$attributes = $data['attributes'] ?? [];
		$relationships = $data['relationships'] ?? [];

		// Extract Patreon user ID
		$patreon_user_id = $relationships['user']['data']['id'] ?? '';
		if (empty($patreon_user_id))
		{
			return new JsonResponse(['status' => 'ok']);
		}

		// Extract tier info
		$tier_id = '';
		$tier_label = '';
		if (!empty($relationships['currently_entitled_tiers']['data']))
		{
			$tier_id = $relationships['currently_entitled_tiers']['data'][0]['id'] ?? '';
		}

		// Check included resources for tier label
		if ($tier_id && isset($payload['included']))
		{
			foreach ($payload['included'] as $resource)
			{
				if ($resource['type'] === 'tier' && $resource['id'] === $tier_id)
				{
					$tier_label = $resource['attributes']['title'] ?? '';
					break;
				}
			}
		}

		$patron_status = $attributes['patron_status'] ?? '';
		$pledge_cents = (int) ($attributes['currently_entitled_amount_cents'] ?? 0);

		// Determine pledge_status based on event
		if ($event_type === 'members:pledge:delete')
		{
			$patron_status = 'former_patron';
			$tier_id = '';
			$pledge_cents = 0;
		}

		// Upsert sync table
		$this->upsert_sync($patreon_user_id, $tier_id, $tier_label, $patron_status, $pledge_cents);

		// Log the webhook event
		$this->log->add('admin', ANONYMOUS, '', 'LOG_PATREON_WEBHOOK_EVENT', false, [
			$event_type,
			$patron_status,
			$tier_label ?: '-',
		]);

		// Find linked phpBB user
		$user_id = $this->get_linked_user($patreon_user_id);

		if ($user_id)
		{
			switch ($event_type)
			{
				case 'members:pledge:create':
				case 'members:pledge:update':
					$this->group_mapper->sync_user_groups($user_id, $tier_id, $patron_status);
				break;

				case 'members:pledge:delete':
					$grace_days = (int) $this->config['patreon_grace_period_days'];
					if ($grace_days === 0)
					{
						$this->group_mapper->demote_from_all_patron_groups($user_id);
					}
					// If grace > 0, cron handles demotion
				break;
			}
		}

		/**
		 * Event fired after a Patreon webhook pledge event has been processed.
		 *
		 * Allows other extensions to react to pledge changes (e.g. award badges,
		 * send PMs, update stats, trigger Discord notifications).
		 *
		 * @event avathar.bbpatreon.pledge_changed
		 * @var string	event_type			Webhook trigger: members:pledge:create, update, or delete
		 * @var int|null	user_id			phpBB user ID (null if Patreon account is not linked to a forum user)
		 * @var string	patreon_user_id		Patreon user ID
		 * @var string	patron_status		active_patron, declined_patron, former_patron, or pending_link
		 * @var string	tier_id				Patreon tier ID (empty string if no active tier)
		 * @var string	tier_label			Human-readable tier name (empty string if no active tier)
		 * @var int		pledge_cents		Pledge amount in cents (0 if cancelled)
		 * @since 1.0.0
		 */
		$vars = array(
			'event_type',
			'user_id',
			'patreon_user_id',
			'patron_status',
			'tier_id',
			'tier_label',
			'pledge_cents',
		);
		extract($this->dispatcher->trigger_event('avathar.bbpatreon.pledge_changed', compact($vars)));

		return new JsonResponse(['status' => 'ok']);
	}

	/**
	 * Look up the phpBB user ID linked to a Patreon user ID.
	 *
	 * @param string $patreon_user_id
	 * @return int|null
	 */
	protected function get_linked_user(string $patreon_user_id): ?int
	{
		$sql = 'SELECT user_id FROM ' . $this->oauth_accounts_table . "
			WHERE provider = 'patreon'
				AND oauth_provider_id = '" . $this->db->sql_escape($patreon_user_id) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ? (int) $row['user_id'] : null;
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
			'last_webhook_at'	=> time(),
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
