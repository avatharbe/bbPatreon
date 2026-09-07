<?php
/**
 *
 * Patreon Integration for phpBB.
 * Public service exposing the published Patreon tier catalogue for other
 * extensions to render (e.g. a "Membership Tiers" page via phpbb/pages).
 * Treat its public methods as a stable API contract (see contrib/events.md).
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\service;

class tier_data_provider
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $patreon_tiers_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		string $patreon_tiers_table
	)
	{
		$this->config				= $config;
		$this->db					= $db;
		$this->patreon_tiers_table	= $patreon_tiers_table;
	}

	/**
	 * Fetch every tier currently published on Patreon, ordered cheapest
	 * first. Unpublished (retired) tiers are excluded — they're kept in
	 * the table only to preserve existing patron/group mappings.
	 *
	 * @return array<int, array{
	 *     tier_id: string,
	 *     tier_label: string,
	 *     description: string,
	 *     amount: string,
	 *     amount_cents: int,
	 *     subscribe_url: string,
	 * }>
	 */
	public function get_published_tiers(): array
	{
		$campaign_id = $this->config['patreon_campaign_id'] ?? '';

		$sql = 'SELECT tier_id, tier_label, description, amount_cents, currency
			FROM ' . $this->patreon_tiers_table . '
			WHERE published = 1
			ORDER BY amount_cents ASC';
		$result = $this->db->sql_query($sql);

		$tiers = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$tiers[] = [
				'tier_id'		=> $row['tier_id'],
				'tier_label'	=> $row['tier_label'],
				'description'	=> $row['description'],
				'amount'		=> $this->format_currency((int) $row['amount_cents'], $row['currency']),
				'amount_cents'	=> (int) $row['amount_cents'],
				'subscribe_url'	=> $this->build_subscribe_url($campaign_id, $row['tier_id']),
			];
		}
		$this->db->sql_freeresult($result);

		return $tiers;
	}

	/**
	 * Build the Patreon "join at this tier" checkout URL. Empty when no
	 * campaign is configured yet, rather than a broken link.
	 */
	protected function build_subscribe_url(string $campaign_id, string $tier_id): string
	{
		if (empty($campaign_id))
		{
			return '';
		}

		return 'https://www.patreon.com/join/' . rawurlencode($campaign_id) . '/checkout?rid=' . rawurlencode($tier_id);
	}

	/**
	 * Format a pledge amount in cents with a currency symbol. Falls back
	 * to the campaign's configured currency if the tier row predates the
	 * per-tier currency column being populated.
	 */
	protected function format_currency(int $cents, ?string $currency): string
	{
		$symbols = [
			'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$',
			'AUD' => 'A$', 'NZD' => 'NZ$', 'JPY' => '¥', 'CHF' => 'CHF ',
			'SEK' => 'kr ', 'NOK' => 'kr ', 'DKK' => 'kr ', 'PLN' => 'zł',
			'BRL' => 'R$', 'MXN' => 'MX$',
		];

		if (empty($currency))
		{
			$currency = !empty($this->config['patreon_currency']) ? $this->config['patreon_currency'] : 'USD';
		}

		$symbol = $symbols[$currency] ?? $currency . ' ';

		return $symbol . number_format($cents / 100, 2);
	}
}
