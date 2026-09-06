<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Tests for the "Patron Stats" ACP controller.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\controller;

use PHPUnit\Framework\TestCase;

class patron_stats_acp_controller_test extends TestCase
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	protected function get_controller(array $config_data = array())
	{
		$defaults = array(
			'patreon_currency' => 'USD',
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$template = $this->createMock(\phpbb\template\template::class);

		return new \avathar\bbpatreon\controller\patron_stats_acp_controller(
			$this->config,
			$this->db,
			$language,
			$template,
			'phpbb_patreon_sync',
			'phpbb_patreon_tiers'
		);
	}

	/**
	 * Helper to call the protected get_patron_totals method.
	 */
	protected function get_patron_totals($controller)
	{
		$method = new \ReflectionMethod($controller, 'get_patron_totals');
		$method->setAccessible(true);
		return $method->invoke($controller);
	}

	/**
	 * Helper to call the protected get_patron_tier_breakdown method.
	 */
	protected function get_patron_tier_breakdown($controller)
	{
		$method = new \ReflectionMethod($controller, 'get_patron_tier_breakdown');
		$method->setAccessible(true);
		return $method->invoke($controller);
	}

	/**
	 * Helper to call the protected format_currency method.
	 */
	protected function format_currency($controller, int $cents)
	{
		$method = new \ReflectionMethod($controller, 'format_currency');
		$method->setAccessible(true);
		return $method->invoke($controller, $cents);
	}

	/**
	 * get_patron_totals() casts the single aggregate row to ints.
	 */
	public function test_get_patron_totals()
	{
		$controller = $this->get_controller();

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturn(array(
			'total_active'			=> '7',
			'total_pledge_cents'	=> '3500',
			'total_declined'		=> '2',
		));
		$this->db->method('sql_freeresult')->willReturn(null);

		$totals = $this->get_patron_totals($controller);

		$this->assertSame(7, $totals['total_active']);
		$this->assertSame(3500, $totals['total_pledge_cents']);
		$this->assertSame(2, $totals['total_declined']);
	}

	/**
	 * get_patron_totals() defaults to all zeros when the table is empty
	 * (conditional aggregation over zero rows can return a row of NULLs
	 * for SUM, rather than no row at all).
	 */
	public function test_get_patron_totals_defaults_to_zero()
	{
		$controller = $this->get_controller();

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturn(array(
			'total_active'			=> null,
			'total_pledge_cents'	=> null,
			'total_declined'		=> null,
		));
		$this->db->method('sql_freeresult')->willReturn(null);

		$totals = $this->get_patron_totals($controller);

		$this->assertSame(0, $totals['total_active']);
		$this->assertSame(0, $totals['total_pledge_cents']);
		$this->assertSame(0, $totals['total_declined']);
	}

	/**
	 * get_patron_tier_breakdown() returns one row per tier with the
	 * patron count cast to int.
	 */
	public function test_get_patron_tier_breakdown()
	{
		$controller = $this->get_controller();

		$rows = array(
			array('tier_id' => 't1', 'tier_label' => 'Bronze', 'patron_count' => '5'),
			array('tier_id' => 't2', 'tier_label' => 'Gold', 'patron_count' => '2'),
		);

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturnCallback(function () use (&$rows) {
			return array_shift($rows) ?: false;
		});
		$this->db->method('sql_freeresult')->willReturn(null);

		$tiers = $this->get_patron_tier_breakdown($controller);

		$this->assertCount(2, $tiers);
		$this->assertSame('t1', $tiers[0]['tier_id']);
		$this->assertSame('Bronze', $tiers[0]['tier_label']);
		$this->assertSame(5, $tiers[0]['patron_count']);
		$this->assertSame(2, $tiers[1]['patron_count']);
	}

	/**
	 * get_patron_tier_breakdown() falls back to an empty label when a
	 * tier_id has no matching row in patreon_tiers (e.g. never fetched).
	 */
	public function test_get_patron_tier_breakdown_handles_missing_label()
	{
		$controller = $this->get_controller();

		$rows = array(
			array('tier_id' => 't9', 'tier_label' => null, 'patron_count' => '1'),
		);

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturnCallback(function () use (&$rows) {
			return array_shift($rows) ?: false;
		});
		$this->db->method('sql_freeresult')->willReturn(null);

		$tiers = $this->get_patron_tier_breakdown($controller);

		$this->assertSame('', $tiers[0]['tier_label']);
	}

	/**
	 * format_currency formats cents using the configured campaign currency.
	 */
	public function test_format_currency_usd()
	{
		$controller = $this->get_controller(array('patreon_currency' => 'USD'));

		$this->assertEquals('$35.00', $this->format_currency($controller, 3500));
	}

	/**
	 * format_currency defaults to USD when no currency is configured.
	 */
	public function test_format_currency_defaults_to_usd()
	{
		$controller = $this->get_controller(array('patreon_currency' => ''));

		$this->assertEquals('$0.00', $this->format_currency($controller, 0));
	}

	/**
	 * set_page_url stores the URL.
	 */
	public function test_set_page_url()
	{
		$controller = $this->get_controller();
		$controller->set_page_url('https://example.com/acp');

		$ref = new \ReflectionProperty($controller, 'u_action');
		$ref->setAccessible(true);
		$this->assertEquals('https://example.com/acp', $ref->getValue($controller));
	}
}
