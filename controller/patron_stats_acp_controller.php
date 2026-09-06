<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * ACP controller for the "Patron Stats" mode — a read-only overview of
 * patron activity computed live from the patreon_sync table, so the
 * board owner doesn't need to visit Patreon's own dashboard. See #4.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

class patron_stats_acp_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var string */
	protected $patreon_sync_table;

	/** @var string */
	protected $patreon_tiers_table;

	/** @var string */
	protected $u_action;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		string $patreon_sync_table,
		string $patreon_tiers_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->language				= $language;
		$this->template				= $template;
		$this->patreon_sync_table	= $patreon_sync_table;
		$this->patreon_tiers_table	= $patreon_tiers_table;
	}

	public function set_page_url(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	public function handle(): void
	{
		$this->language->add_lang('info_acp_bbpatreon_patron_stats', 'avathar/bbpatreon');

		$totals = $this->get_patron_totals();
		$tiers = $this->get_patron_tier_breakdown();

		foreach ($tiers as $tier)
		{
			$this->template->assign_block_vars('tier_stats', [
				'TIER_LABEL'	=> $tier['tier_label'] !== '' ? $tier['tier_label'] : $tier['tier_id'],
				'PATRON_COUNT'	=> $tier['patron_count'],
			]);
		}

		$this->template->assign_vars([
			'U_ACTION'					=> $this->u_action,
			'TOTAL_ACTIVE_PATRONS'		=> $totals['total_active'],
			'TOTAL_DECLINED_PATRONS'	=> $totals['total_declined'],
			'TOTAL_PLEDGE_AMOUNT'		=> $this->format_currency($totals['total_pledge_cents']),
			'S_HAS_TIER_STATS'			=> !empty($tiers),
		]);
	}

	/**
	 * Aggregate active/declined patron counts and total active pledge
	 * amount in a single query.
	 *
	 * @return array{total_active: int, total_pledge_cents: int, total_declined: int}
	 */
	protected function get_patron_totals(): array
	{
		$sql = 'SELECT
				COUNT(CASE WHEN pledge_status = \'active_patron\' THEN 1 END) AS total_active,
				SUM(CASE WHEN pledge_status = \'active_patron\' THEN pledge_cents ELSE 0 END) AS total_pledge_cents,
				COUNT(CASE WHEN pledge_status = \'declined_patron\' THEN 1 END) AS total_declined
			FROM ' . $this->patreon_sync_table;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return [
			'total_active'			=> (int) ($row['total_active'] ?? 0),
			'total_pledge_cents'	=> (int) ($row['total_pledge_cents'] ?? 0),
			'total_declined'		=> (int) ($row['total_declined'] ?? 0),
		];
	}

	/**
	 * Count active patrons per tier, ordered by tier amount ascending.
	 *
	 * @return array<int, array{tier_id: string, tier_label: string, patron_count: int}>
	 */
	protected function get_patron_tier_breakdown(): array
	{
		$sql = 'SELECT ps.tier_id, pt.tier_label, pt.amount_cents, COUNT(*) AS patron_count
			FROM ' . $this->patreon_sync_table . ' ps
			LEFT JOIN ' . $this->patreon_tiers_table . " pt ON (pt.tier_id = ps.tier_id)
			WHERE ps.pledge_status = 'active_patron'
			GROUP BY ps.tier_id, pt.tier_label, pt.amount_cents
			ORDER BY pt.amount_cents ASC";
		$result = $this->db->sql_query($sql);

		$tiers = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$tiers[] = [
				'tier_id'		=> $row['tier_id'],
				'tier_label'	=> $row['tier_label'] ?? '',
				'patron_count'	=> (int) $row['patron_count'],
			];
		}
		$this->db->sql_freeresult($result);

		return $tiers;
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
