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
 * make promotion/demotion decisions. The actual group_user_add/del calls
 * are not tested here because they are phpBB core globals — those are
 * covered by the functional tests.
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
	 * Helper: build a group_mapper with the given config overrides.
	 *
	 * Uses the global $phpbb_root_path set by phpBB's test bootstrap
	 * so that the constructor's include_once for functions_user.php
	 * resolves correctly in CI.
	 */
	protected function get_mapper(array $config_data = array())
	{
		global $phpbb_root_path, $phpEx;

		$defaults = array(
			'patreon_tier_group_map'	=> '{"tier-1": 5, "tier-2": 6}',
			'patreon_grace_period_days'	=> 0,
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));

		return new \avathar\bbpatreon\service\group_mapper(
			$this->config,
			$this->db,
			$this->log,
			$phpbb_root_path,
			$phpEx
		);
	}

	/**
	 * Tier-to-group map must be decoded from the JSON config string into
	 * an associative array. This is the foundation for every sync decision.
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
	 * An empty JSON object in config should yield an empty map, not an
	 * error. This is the normal state before the admin configures tiers.
	 */
	public function test_get_tier_group_map_empty()
	{
		$mapper = $this->get_mapper(array('patreon_tier_group_map' => '{}'));

		$this->assertEmpty($mapper->get_tier_group_map());
	}

	/**
	 * Corrupt or non-JSON config values must not crash the mapper.
	 * This can happen if the config row is manually edited in the DB.
	 */
	public function test_get_tier_group_map_invalid_json()
	{
		$mapper = $this->get_mapper(array('patreon_tier_group_map' => 'not-json'));

		$this->assertEmpty($mapper->get_tier_group_map());
	}

	/**
	 * get_all_patron_group_ids() extracts the unique group IDs from
	 * the tier map. Used by demote_from_all_patron_groups() to know
	 * which groups to remove a user from.
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
	 * When two tiers map to the same group (e.g. "Silver" and "Gold"
	 * both get the same forum group), the group ID list must be
	 * deduplicated so demote_from_all doesn't call group_user_del twice.
	 */
	public function test_get_all_patron_group_ids_deduplicates()
	{
		$mapper = $this->get_mapper(array(
			'patreon_tier_group_map' => '{"tier-1": 5, "tier-2": 5}',
		));

		$ids = $mapper->get_all_patron_group_ids();

		$this->assertCount(1, $ids);
	}

	/**
	 * When no tiers are mapped, sync_user_groups must be a no-op.
	 * This prevents errors on a freshly installed extension where the
	 * admin hasn't configured tier mappings yet.
	 */
	public function test_sync_skips_on_empty_map()
	{
		$mapper = $this->get_mapper(array('patreon_tier_group_map' => '{}'));

		$this->db->expects($this->never())->method('sql_query');

		$mapper->sync_user_groups(2, 'tier-1', 'active_patron');
	}

	/**
	 * When a patron cancels but a grace period is configured, the mapper
	 * must NOT demote immediately. The nightly cron task handles deferred
	 * demotion by checking timestamps. Without this guard, former patrons
	 * would lose access the moment the webhook fires, defeating the
	 * purpose of the grace period.
	 */
	public function test_sync_skips_demotion_during_grace_period()
	{
		$mapper = $this->get_mapper(array('patreon_grace_period_days' => 7));

		$this->db->expects($this->never())->method('sql_query');

		$mapper->sync_user_groups(2, 'tier-1', 'former_patron');
	}
}
