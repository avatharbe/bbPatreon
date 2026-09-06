<?php
/**
 *
 * Patreon Integration for phpBB.
 * Public service exposing opted-in patron data for other extensions to
 * consume without querying bbPatreon's tables directly. This is the only
 * supported way to read public patron data cross-extension — treat its
 * public methods as a stable API contract (see contrib/events.md).
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\service;

class patron_data_provider
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var string */
	protected $patreon_sync_table;

	/** @var string */
	protected $patreon_tiers_table;

	/** @var string */
	protected $oauth_accounts_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		string $patreon_sync_table,
		string $patreon_tiers_table,
		string $oauth_accounts_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->patreon_tiers_table	= $patreon_tiers_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
	}

	/**
	 * Fetch every patron who has opted in to public display, fully
	 * formatted for rendering. Only includes active patrons who set
	 * show_public = 1 — the board owner cannot override this consent.
	 *
	 * @return array<int, array{
	 *     user_id: int,
	 *     username: string,
	 *     avatar: string,
	 *     tier_label: string,
	 *     group_name: string,
	 *     rank_title: string,
	 *     pledge_amount: string,
	 * }>
	 */
	public function get_public_supporters(): array
	{
		$show_amounts = !empty($this->config['patreon_supporters_show_amounts']);

		$sql = 'SELECT u.username, u.user_colour, u.user_id, u.user_rank,
				u.user_avatar, u.user_avatar_type, u.user_avatar_width, u.user_avatar_height,
				u.group_id AS user_default_group, g.group_name, g.group_type, g.group_colour,
				pt.tier_label, ps.pledge_cents, ps.show_pledge_public
			FROM ' . $this->patreon_sync_table . ' ps
			JOIN ' . $this->oauth_accounts_table . " oa
				ON (oa.provider = 'patreon' AND oa.oauth_provider_id = ps.patreon_user_id)
			JOIN " . USERS_TABLE . ' u ON (u.user_id = oa.user_id)
			LEFT JOIN ' . GROUPS_TABLE . ' g ON (g.group_id = u.group_id)
			LEFT JOIN ' . $this->patreon_tiers_table . " pt ON (pt.tier_id = ps.tier_id)
			WHERE ps.show_public = 1
				AND ps.pledge_status = 'active_patron'
			ORDER BY pt.amount_cents DESC, u.username ASC";
		$result = $this->db->sql_query($sql);

		$rows = [];
		$user_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
			$user_ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		$ranks = $this->get_rank_titles($user_ids);

		$supporters = [];
		foreach ($rows as $row)
		{
			$supporters[] = [
				'user_id'		=> (int) $row['user_id'],
				'username'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'avatar'		=> phpbb_get_user_avatar($row),
				'tier_label'	=> $row['tier_label'] ?: '',
				'group_name'	=> $this->format_group_name($row),
				'rank_title'	=> !empty($row['user_rank']) ? ($ranks[(int) $row['user_rank']] ?? '') : '',
				'pledge_amount'	=> ($show_amounts && !empty($row['show_pledge_public']) && (int) $row['pledge_cents'] > 0)
					? $this->format_currency((int) $row['pledge_cents'])
					: '',
			];
		}

		return $supporters;
	}

	/**
	 * Count patrons currently eligible for get_public_supporters(), without
	 * the cost of formatting every row. Used for lightweight display (e.g.
	 * a nav-link badge) where the full list isn't needed.
	 */
	public function get_public_supporters_count(): int
	{
		$sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->patreon_sync_table . "
			WHERE show_public = 1 AND pledge_status = 'active_patron'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ? (int) $row['cnt'] : 0;
	}

	/**
	 * Batch-load rank titles for a set of user IDs to avoid N+1 queries.
	 *
	 * @param int[] $user_ids
	 * @return array<int, string> rank_id => rank_title
	 */
	protected function get_rank_titles(array $user_ids): array
	{
		if (empty($user_ids))
		{
			return [];
		}

		$ranks = [];
		$sql = 'SELECT rank_id, rank_title FROM ' . RANKS_TABLE;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ranks[(int) $row['rank_id']] = $row['rank_title'];
		}
		$this->db->sql_freeresult($result);

		return $ranks;
	}

	/**
	 * Resolve a display-ready group name: translate built-in ("special")
	 * group names and wrap in the group's colour, matching how phpBB
	 * renders group names elsewhere.
	 */
	protected function format_group_name(array $row): string
	{
		$group_name_raw = $row['group_name'] ?: '';
		if ($group_name_raw && (int) $row['group_type'] === GROUP_SPECIAL)
		{
			$group_name_raw = $this->language->is_set('G_' . $group_name_raw)
				? $this->language->lang('G_' . $group_name_raw)
				: $group_name_raw;
		}

		if ($group_name_raw && !empty($row['group_colour']))
		{
			return '<span style="font-weight: bold; color: #' . $row['group_colour'] . ';">' . $group_name_raw . '</span>';
		}

		return $group_name_raw;
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
}
