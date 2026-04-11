<?php
/**
 *
 * Patreon Integration for phpBB.
 * Public supporters page controller.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

class supporters_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\controller\helper */
	protected $helper;

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
		\phpbb\template\template $template,
		\phpbb\controller\helper $helper,
		string $patreon_sync_table,
		string $patreon_tiers_table,
		string $oauth_accounts_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->template				= $template;
		$this->helper				= $helper;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->patreon_tiers_table	= $patreon_tiers_table;
		$this->oauth_accounts_table	= $oauth_accounts_table;
	}

	/**
	 * Display the public supporters page.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handle()
	{
		if (empty($this->config['patreon_supporters_page_enabled']))
		{
			throw new \phpbb\exception\http_exception(404, 'NO_PAGE_FOUND');
		}

		$this->language->add_lang('common', 'avathar/bbpatreon');

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

		// Collect user IDs to batch-load rank info
		$supporters = [];
		$user_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$supporters[] = $row;
			$user_ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		// Load rank data for all supporters
		$ranks = [];
		if (!empty($user_ids))
		{
			$sql_ranks = 'SELECT rank_id, rank_title FROM ' . RANKS_TABLE;
			$rank_result = $this->db->sql_query($sql_ranks);
			while ($rank_row = $this->db->sql_fetchrow($rank_result))
			{
				$ranks[(int) $rank_row['rank_id']] = $rank_row['rank_title'];
			}
			$this->db->sql_freeresult($rank_result);
		}

		foreach ($supporters as $row)
		{
			// Resolve group name (translate built-in groups, apply colour)
			$group_name_raw = $row['group_name'] ?: '';
			if ($group_name_raw && (int) $row['group_type'] === GROUP_SPECIAL)
			{
				$group_name_raw = $this->language->is_set('G_' . $group_name_raw)
					? $this->language->lang('G_' . $group_name_raw)
					: $group_name_raw;
			}

			$group_name = $group_name_raw;
			if ($group_name && !empty($row['group_colour']))
			{
				$group_name = '<span style="font-weight: bold; color: #' . $row['group_colour'] . ';">' . $group_name . '</span>';
			}

			// Resolve rank title
			$rank_title = '';
			if (!empty($row['user_rank']) && isset($ranks[(int) $row['user_rank']]))
			{
				$rank_title = $ranks[(int) $row['user_rank']];
			}

			// Pledge amount: only show if ACP allows AND user opted in
			$pledge_display = '';
			if ($show_amounts && !empty($row['show_pledge_public']) && (int) $row['pledge_cents'] > 0)
			{
				$pledge_display = $this->format_currency((int) $row['pledge_cents']);
			}

			$this->template->assign_block_vars('supporters', [
				'AVATAR'		=> phpbb_get_user_avatar($row),
				'USERNAME'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
				'TIER_LABEL'	=> $row['tier_label'] ?: '',
				'GROUP_NAME'	=> $group_name,
				'RANK_TITLE'	=> $rank_title,
				'PLEDGE_AMOUNT'	=> $pledge_display,
			]);
		}

		$this->template->assign_vars([
			'S_HAS_SUPPORTERS'		=> !empty($supporters),
			'S_SHOW_AMOUNTS'		=> $show_amounts,
			'TOTAL_SUPPORTERS'		=> count($supporters),
		]);

		return $this->helper->render('supporters_body.html', $this->language->lang('PATREON_SUPPORTERS_TITLE'));
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
