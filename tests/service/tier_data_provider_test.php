<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the public tier_data_provider service.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\service;

use PHPUnit\Framework\TestCase;

class tier_data_provider_test extends TestCase
{
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	protected function get_provider(array $config_data = array())
	{
		$defaults = array(
			'patreon_campaign_id'	=> '12345',
			'patreon_currency'		=> 'USD',
		);

		$config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);

		return new \avathar\bbpatreon\service\tier_data_provider(
			$config,
			$this->db,
			'phpbb_patreon_tiers'
		);
	}

	protected function call($provider, string $method, array $args = array())
	{
		$ref = new \ReflectionMethod($provider, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs($provider, $args);
	}

	/**
	 * get_published_tiers() maps each row to the documented shape,
	 * including a working subscribe URL when a campaign is configured.
	 */
	public function test_get_published_tiers_maps_rows()
	{
		$provider = $this->get_provider();

		$rows = array(
			array('tier_id' => 't1', 'tier_label' => 'Bronze', 'description' => 'Basic perks', 'amount_cents' => '500', 'currency' => 'USD'),
		);
		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturnCallback(function () use (&$rows) {
			return array_shift($rows) ?: false;
		});
		$this->db->method('sql_freeresult')->willReturn(null);

		$tiers = $provider->get_published_tiers();

		$this->assertCount(1, $tiers);
		$this->assertSame('t1', $tiers[0]['tier_id']);
		$this->assertSame('Bronze', $tiers[0]['tier_label']);
		$this->assertSame('Basic perks', $tiers[0]['description']);
		$this->assertSame('$5.00', $tiers[0]['amount']);
		$this->assertSame(500, $tiers[0]['amount_cents']);
		$this->assertSame('https://www.patreon.com/join/12345/checkout?rid=t1', $tiers[0]['subscribe_url']);
	}

	/**
	 * get_published_tiers() returns an empty array when there are no
	 * published tiers (e.g. before "Fetch Tiers" has ever been run).
	 */
	public function test_get_published_tiers_empty()
	{
		$provider = $this->get_provider();

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);

		$this->assertSame([], $provider->get_published_tiers());
	}

	/**
	 * build_subscribe_url() returns an empty string (not a broken link)
	 * when no campaign is configured yet.
	 */
	public function test_build_subscribe_url_empty_without_campaign()
	{
		$provider = $this->get_provider(array('patreon_campaign_id' => ''));

		$this->assertSame('', $this->call($provider, 'build_subscribe_url', ['', 't1']));
	}

	/**
	 * build_subscribe_url() URL-encodes the campaign and tier IDs.
	 */
	public function test_build_subscribe_url_encodes_ids()
	{
		$provider = $this->get_provider();

		$url = $this->call($provider, 'build_subscribe_url', ['12345', 'tier one']);

		$this->assertSame('https://www.patreon.com/join/12345/checkout?rid=tier%20one', $url);
	}

	/**
	 * format_currency() uses the tier's own stored currency when present.
	 */
	public function test_format_currency_uses_tier_currency()
	{
		$provider = $this->get_provider(array('patreon_currency' => 'USD'));

		$this->assertSame('€5.00', $this->call($provider, 'format_currency', [500, 'EUR']));
	}

	/**
	 * format_currency() falls back to the campaign currency config when
	 * the tier row has no currency stored (pre-existing rows).
	 */
	public function test_format_currency_falls_back_to_campaign_currency()
	{
		$provider = $this->get_provider(array('patreon_currency' => 'GBP'));

		$this->assertSame('£5.00', $this->call($provider, 'format_currency', [500, '']));
	}
}
