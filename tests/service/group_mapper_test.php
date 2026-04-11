<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the group_mapper service which resolves Patreon tiers to phpBB
 * groups and decides whether to promote, demote, or skip a user.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\service;

/**
 * Unit tests for \avathar\bbpatreon\service\group_mapper.
 *
 * These tests cover the pure-logic methods that parse config values and
 * make promotion/demotion decisions. group_user_add/del are stubbed in
 * bootstrap.php; calls are tracked in $GLOBALS['phpbb_test_group_calls'].
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

		// Reset group call tracking
		$GLOBALS['phpbb_test_group_calls'] = array();
	}

	/**
	 * Helper: build a group_mapper with the given config overrides.
	 *
	 * @param array $config_data  Config overrides
	 * @param array $tier_rows    Tier table rows (null = default 2 tiers)
	 * @param array $user_group_ids  Group IDs the user belongs to
	 */
	protected function get_mapper(array $config_data = array(), array $tier_rows = null, array $user_group_ids = array())
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

		// Track which query we're serving results for.
		// The mapper calls sql_query for:
		//   1. get_tier_group_map (SELECT tier_id, group_id)
		//   2. user_in_group checks (SELECT user_id ... WHERE group_id = X)
		// get_tier_group_map may be called multiple times (from sync + get_all_patron_group_ids).
		$query_results = array();
		$query_index = 0;

		$this->db->method('sql_query')
			->willReturnCallback(function ($sql) use (&$query_results, &$query_index, $tier_rows, $user_group_ids) {
				if (strpos($sql, 'tier_id') !== false && strpos($sql, 'group_id') !== false)
				{
					// Tier map query: return tier rows
					$query_results[$query_index] = $tier_rows;
				}
				else if (strpos($sql, 'group_id') !== false)
				{
					// user_in_group check: extract group_id from query
					preg_match('/group_id\s*=\s*(\d+)/', $sql, $m);
					$gid = isset($m[1]) ? (int) $m[1] : 0;
					if (in_array($gid, $user_group_ids, true))
					{
						$query_results[$query_index] = array(array('user_id' => 2));
					}
					else
					{
						$query_results[$query_index] = array();
					}
				}
				else
				{
					$query_results[$query_index] = array();
				}
				$query_index++;
				return $query_index; // unique result ID
			});

		$fetch_positions = array();
		$this->db->method('sql_fetchrow')
			->willReturnCallback(function ($result_id) use (&$query_results, &$fetch_positions) {
				$idx = $result_id - 1;
				if (!isset($fetch_positions[$idx]))
				{
					$fetch_positions[$idx] = 0;
				}
				$rows = isset($query_results[$idx]) ? $query_results[$idx] : array();
				if ($fetch_positions[$idx] < count($rows))
				{
					return $rows[$fetch_positions[$idx]++];
				}
				return false;
			});

		$this->db->method('sql_freeresult')
			->willReturn(null);

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

		$ids = $mapper->get_all_patron_group_ids();

		$this->assertCount(1, $ids);
	}

	/**
	 * When no tiers are mapped, sync_user_groups must be a no-op.
	 */
	public function test_sync_skips_on_empty_map()
	{
		$mapper = $this->get_mapper(array(), array());

		$mapper->sync_user_groups(2, 'tier-1', 'active_patron');

		$this->assertEmpty($GLOBALS['phpbb_test_group_calls']);
	}

	/**
	 * When a patron cancels but a grace period is configured, the mapper
	 * must NOT demote immediately.
	 */
	public function test_sync_skips_demotion_during_grace_period()
	{
		$mapper = $this->get_mapper(array('patreon_grace_period_days' => 7));

		$mapper->sync_user_groups(2, 'tier-1', 'former_patron');

		$this->assertEmpty($GLOBALS['phpbb_test_group_calls']);
	}

	/**
	 * Grace period must also apply to declined_patron status.
	 */
	public function test_sync_skips_demotion_during_grace_period_declined()
	{
		$mapper = $this->get_mapper(array('patreon_grace_period_days' => 3));

		$mapper->sync_user_groups(2, 'tier-1', 'declined_patron');

		$this->assertEmpty($GLOBALS['phpbb_test_group_calls']);
	}

	/**
	 * Active patron with a valid tier and not already in the group
	 * should trigger group_user_add.
	 */
	public function test_sync_active_patron_adds_to_group()
	{
		$mapper = $this->get_mapper(array(), null, array());

		$this->log->expects($this->once())
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_GROUP_ADD', $this->anything(), $this->anything());

		$mapper->sync_user_groups(2, 'tier-1', 'active_patron');

		$adds = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'add'; });
		$this->assertCount(1, $adds);
		$add = reset($adds);
		$this->assertEquals(5, $add['group_id']);
		$this->assertEquals(array(2), $add['user_ids']);
	}

	/**
	 * Active patron already in the target group should NOT trigger
	 * another group_user_add.
	 */
	public function test_sync_active_patron_already_in_group_skips()
	{
		// User already in group 5 (the target for tier-1)
		$mapper = $this->get_mapper(array(), null, array(5));

		$this->log->expects($this->never())->method('add');

		$mapper->sync_user_groups(2, 'tier-1', 'active_patron');

		$this->assertEmpty($GLOBALS['phpbb_test_group_calls']);
	}

	/**
	 * Active patron switching tiers should be removed from old group
	 * and added to new group.
	 */
	public function test_sync_active_patron_switches_tier()
	{
		// User is in group 5 (tier-1), switching to tier-2 (group 6)
		$mapper = $this->get_mapper(array(), null, array(5));

		$mapper->sync_user_groups(2, 'tier-2', 'active_patron');

		$dels = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'del'; });
		$adds = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'add'; });

		$this->assertCount(1, $dels, 'Should remove from old group');
		$this->assertCount(1, $adds, 'Should add to new group');

		$del = reset($dels);
		$this->assertEquals(5, $del['group_id']);

		$add = reset($adds);
		$this->assertEquals(6, $add['group_id']);
	}

	/**
	 * Former patron with no grace period should trigger demotion
	 * from all patron groups the user belongs to.
	 */
	public function test_sync_former_patron_no_grace_demotes()
	{
		// User is in both mapped groups
		$mapper = $this->get_mapper(array('patreon_grace_period_days' => 0), null, array(5, 6));

		$this->log->expects($this->exactly(2))
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_GROUP_REMOVE', $this->anything(), $this->anything());

		$mapper->sync_user_groups(2, '', 'former_patron');

		$dels = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'del'; });
		$this->assertCount(2, $dels);
	}

	/**
	 * Active patron with unknown tier should trigger demotion path.
	 */
	public function test_sync_active_patron_unknown_tier_demotes()
	{
		// User in group 5
		$mapper = $this->get_mapper(array(), null, array(5));

		$mapper->sync_user_groups(2, 'tier-unknown', 'active_patron');

		$dels = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'del'; });
		$this->assertCount(1, $dels);
	}

	/**
	 * demote_from_all_patron_groups must remove user from every
	 * group in the tier map.
	 */
	public function test_demote_from_all_removes_from_all_groups()
	{
		$mapper = $this->get_mapper(array(), null, array(5, 6));

		$this->log->expects($this->exactly(2))
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_GROUP_REMOVE', $this->anything(), $this->anything());

		$mapper->demote_from_all_patron_groups(2);

		$dels = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'del'; });
		$this->assertCount(2, $dels);
	}

	/**
	 * demote_from_all_patron_groups must not attempt removal when
	 * user is not in any patron groups.
	 */
	public function test_demote_from_all_skips_when_not_in_groups()
	{
		$mapper = $this->get_mapper(array(), null, array());

		$this->log->expects($this->never())->method('add');

		$mapper->demote_from_all_patron_groups(2);

		$this->assertEmpty($GLOBALS['phpbb_test_group_calls']);
	}

	/**
	 * Tier with group_id 0 means "no group mapping".
	 * Active patron on such a tier should go to the demotion path.
	 */
	public function test_sync_tier_mapped_to_zero_demotes()
	{
		$mapper = $this->get_mapper(array(), array(
			array('tier_id' => 'tier-free', 'group_id' => '0'),
			array('tier_id' => 'tier-paid', 'group_id' => '5'),
		), array());

		$mapper->sync_user_groups(2, 'tier-free', 'active_patron');

		$adds = array_filter($GLOBALS['phpbb_test_group_calls'], function ($c) { return $c['action'] === 'add'; });
		$this->assertEmpty($adds, 'Should not add to group 0');
	}
}
