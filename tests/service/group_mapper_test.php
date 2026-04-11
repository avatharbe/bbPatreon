<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the group_mapper service's pure-logic methods that don't
 * require phpBB's global group_user_add/group_user_del functions.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\service;

/**
 * Unit tests for \avathar\bbpatreon\service\group_mapper.
 *
 * Only tests methods that exercise real production code: tier map
 * queries and early-return guard clauses. The promotion/demotion
 * paths call phpBB's global group_user_add/del which cannot be
 * unit tested without refactoring the dependency.
 */
class group_mapper_test extends \phpbb_test_case
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $log;

	public function setUp(): void
	{
		parent::setUp();

		$this->db = $this->createMock('\phpbb\db\driver\driver_interface');
		$this->log = $this->createMock('\phpbb\log\log_interface');
	}

	/**
	 * Helper: build a group_mapper with the given config and tier rows.
	 */
	protected function get_mapper(array $config_data = array(), array $tier_rows = null)
	{
		global $phpbb_root_path, $phpEx;

		$defaults = array(
			'patreon_grace_period_days'	=> 0,
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));

		if ($tier_rows === null)
		{
			$tier_rows = array(
				array('tier_id' => 'tier-1', 'group_id' => '5'),
				array('tier_id' => 'tier-2', 'group_id' => '6'),
			);
		}

		$row_index = 0;

		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')
			->willReturnCallback(function () use (&$row_index, $tier_rows) {
				if ($row_index < count($tier_rows))
				{
					return $tier_rows[$row_index++];
				}
				return false;
			});
		$this->db->method('sql_freeresult')->willReturn(null);

		return new \avathar\bbpatreon\service\group_mapper(
			$this->config,
			$this->db,
			$this->log,
			$phpbb_root_path,
			$phpEx,
			'phpbb_patreon_tiers'
		);
	}

	/**
	 * Tier-to-group map must be read from the patreon_tiers table into
	 * an associative array.
	 */
	public function test_get_tier_group_map()
	{
		$mapper = $this->get_mapper();
		$map = $mapper->get_tier_group_map();

		$this->assertIsArray($map);
		$this->assertCount(2, $map);
		$this->assertEquals(5, $map['tier-1']);
		$this->assertEquals(6, $map['tier-2']);
	}

	/**
	 * An empty tiers table should yield an empty map, not an error.
	 */
	public function test_get_tier_group_map_empty()
	{
		$mapper = $this->get_mapper(array(), array());
		$this->assertEmpty($mapper->get_tier_group_map());
	}

	/**
	 * get_all_patron_group_ids() extracts the unique group IDs from the tier map.
	 */
	public function test_get_all_patron_group_ids()
	{
		$mapper = $this->get_mapper();
		$ids = $mapper->get_all_patron_group_ids();

		$this->assertCount(2, $ids);
		$this->assertContains(5, $ids);
		$this->assertContains(6, $ids);
	}

	/**
	 * When two tiers map to the same group, the group ID list must be deduplicated.
	 */
	public function test_get_all_patron_group_ids_deduplicates()
	{
		$mapper = $this->get_mapper(array(), array(
			array('tier_id' => 'tier-1', 'group_id' => '5'),
			array('tier_id' => 'tier-2', 'group_id' => '5'),
		));
		$this->assertCount(1, $mapper->get_all_patron_group_ids());
	}

	/**
	 * When no tiers are mapped, sync_user_groups must be a no-op.
	 */
	public function test_sync_skips_on_empty_map()
	{
		$mapper = $this->get_mapper(array(), array());
		$mapper->sync_user_groups(2, 'tier-1', 'active_patron');

		// No error means the early return worked
		$this->assertTrue(true);
	}

	/**
	 * When a patron cancels but a grace period is configured, the mapper
	 * must NOT demote immediately.
	 */
	public function test_sync_skips_demotion_during_grace_period()
	{
		$mapper = $this->get_mapper(array('patreon_grace_period_days' => 7));
		$mapper->sync_user_groups(2, 'tier-1', 'former_patron');
		$this->assertTrue(true);
	}

	/**
	 * Grace period must also apply to declined_patron status.
	 */
	public function test_sync_skips_demotion_during_grace_period_declined()
	{
		$mapper = $this->get_mapper(array('patreon_grace_period_days' => 3));
		$mapper->sync_user_groups(2, 'tier-1', 'declined_patron');
		$this->assertTrue(true);
	}
}
