<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the public patron_data_provider service.
 *
 * Note: get_public_supporters() calls phpBB's global get_username_string()
 * and phpbb_get_user_avatar() per row, which need more of phpBB's runtime
 * bootstrapped than a plain unit test provides. These tests exercise the
 * query/aggregation/formatting logic directly (the parts this refactor
 * actually changes) and the empty-result path of get_public_supporters(),
 * which never reaches those calls — matching the same caution the existing
 * supporters_controller_test.php already takes around this code path.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\service;

use PHPUnit\Framework\TestCase;

class patron_data_provider_test extends TestCase
{
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $language;

	protected function get_provider(array $config_data = array())
	{
		$defaults = array(
			'patreon_currency'					=> 'USD',
			'patreon_supporters_show_amounts'	=> 1,
		);

		$config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$this->language = $this->getMockBuilder(\phpbb\language\language::class)
			->disableOriginalConstructor()
			->getMock();

		return new \avathar\bbpatreon\service\patron_data_provider(
			$config,
			$this->db,
			$this->language,
			'phpbb_patreon_sync',
			'phpbb_patreon_tiers',
			'phpbb_oauth_accounts'
		);
	}

	protected function call($provider, string $method, array $args = array())
	{
		$ref = new \ReflectionMethod($provider, $method);
		$ref->setAccessible(true);
		return $ref->invokeArgs($provider, $args);
	}

	/**
	 * get_public_supporters() returns an empty array (and never touches
	 * rank lookup or per-row formatting) when there are no opted-in
	 * active patrons.
	 */
	public function test_get_public_supporters_empty()
	{
		$provider = $this->get_provider();

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);

		$this->assertSame([], $provider->get_public_supporters());
	}

	/**
	 * get_public_supporters_count() casts the aggregate row to int.
	 */
	public function test_get_public_supporters_count()
	{
		$provider = $this->get_provider();

		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturn(array('cnt' => '3'));
		$this->db->method('sql_freeresult')->willReturn(null);

		$this->assertSame(3, $provider->get_public_supporters_count());
	}

	/**
	 * get_public_supporters_count() defaults to 0 when the query returns
	 * no row (e.g. sql_return_on_error suppressed a failure upstream).
	 */
	public function test_get_public_supporters_count_defaults_to_zero()
	{
		$provider = $this->get_provider();

		$this->db->method('sql_query')->willReturn(false);
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);

		$this->assertSame(0, $provider->get_public_supporters_count());
	}

	/**
	 * get_rank_titles() short-circuits without querying when given no
	 * user IDs — avoids an unnecessary RANKS_TABLE scan.
	 */
	public function test_get_rank_titles_empty_input_skips_query()
	{
		$provider = $this->get_provider();

		$this->db->expects($this->never())->method('sql_query');

		$this->assertSame([], $this->call($provider, 'get_rank_titles', [[]]));
	}

	/**
	 * get_rank_titles() batch-loads all ranks in one query, keyed by
	 * rank_id as int.
	 */
	public function test_get_rank_titles_returns_map()
	{
		$provider = $this->get_provider();

		$rows = array(
			array('rank_id' => '1', 'rank_title' => 'Bronze'),
			array('rank_id' => '2', 'rank_title' => 'Gold'),
		);
		$this->db->method('sql_query')->willReturn('result');
		$this->db->method('sql_fetchrow')->willReturnCallback(function () use (&$rows) {
			return array_shift($rows) ?: false;
		});
		$this->db->method('sql_freeresult')->willReturn(null);

		$ranks = $this->call($provider, 'get_rank_titles', [[1, 2]]);

		$this->assertSame(['Bronze', 'Gold'], [$ranks[1], $ranks[2]]);
	}

	/**
	 * format_group_name() returns an empty string when the user has no
	 * group (e.g. LEFT JOIN found nothing).
	 */
	public function test_format_group_name_empty_when_no_group()
	{
		$provider = $this->get_provider();

		$name = $this->call($provider, 'format_group_name', [[
			'group_name' => '', 'group_type' => 0, 'group_colour' => '',
		]]);

		$this->assertSame('', $name);
	}

	/**
	 * format_group_name() wraps the name in a coloured span when the
	 * group has a colour set.
	 */
	public function test_format_group_name_applies_colour()
	{
		$provider = $this->get_provider();

		$name = $this->call($provider, 'format_group_name', [[
			'group_name' => 'VIP', 'group_type' => GROUP_OPEN, 'group_colour' => 'FF0000',
		]]);

		$this->assertSame('<span style="font-weight: bold; color: #FF0000;">VIP</span>', $name);
	}

	/**
	 * format_group_name() translates built-in ("special") group names
	 * via the G_<NAME> language key when one is set.
	 */
	public function test_format_group_name_translates_special_groups()
	{
		$provider = $this->get_provider();

		$this->language->method('is_set')->with('G_ADMINISTRATORS')->willReturn(true);
		$this->language->method('lang')->with('G_ADMINISTRATORS')->willReturn('Administrators');

		$name = $this->call($provider, 'format_group_name', [[
			'group_name' => 'ADMINISTRATORS', 'group_type' => GROUP_SPECIAL, 'group_colour' => '',
		]]);

		$this->assertSame('Administrators', $name);
	}

	/**
	 * format_currency formats cents using the configured campaign currency.
	 */
	public function test_format_currency_usd()
	{
		$provider = $this->get_provider(array('patreon_currency' => 'USD'));

		$this->assertSame('$5.00', $this->call($provider, 'format_currency', [500]));
	}
}
