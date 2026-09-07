<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests that the tier/group multi-mapping migrations (v1_3_0, v1_3_1)
 * declare the expected schema changes. See issue #5.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\dbal;

use PHPUnit\Framework\TestCase;

class tier_group_migration_test extends TestCase
{
	protected function get_migration(string $class)
	{
		$config = $this->createMock(\phpbb\config\config::class);
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db_tools = $this->createMock(\phpbb\db\tools\tools_interface::class);

		return new $class($config, $db, $db_tools, 'phpbb_', __DIR__, 'php');
	}

	/**
	 * v1_3_0 must declare the patreon_tier_groups join table with a
	 * composite primary key on (tier_id, group_id), so a tier can map
	 * to more than one group without duplicate rows.
	 */
	public function test_tier_group_join_table_schema()
	{
		$migration = $this->get_migration(\avathar\bbpatreon\migrations\v1_3_0_tier_group_join::class);
		$schema = $migration->update_schema();

		$this->assertArrayHasKey('add_tables', $schema);

		$table = null;
		foreach ($schema['add_tables'] as $table_name => $table_def)
		{
			if (strpos($table_name, 'patreon_tier_groups') !== false)
			{
				$table = $table_def;
				break;
			}
		}

		$this->assertNotNull($table, 'patreon_tier_groups table must be declared');
		$this->assertArrayHasKey('tier_id', $table['COLUMNS']);
		$this->assertArrayHasKey('group_id', $table['COLUMNS']);
		$this->assertEquals(['tier_id', 'group_id'], $table['PRIMARY_KEY']);
	}

	/**
	 * v1_3_0 depends on v1_2_5 (the last migration shipped before it),
	 * keeping the dependency chain unbroken.
	 */
	public function test_tier_group_join_depends_on_patron_stats_page()
	{
		$this->assertEquals(
			['\avathar\bbpatreon\migrations\v1_2_5_patron_stats_page'],
			\avathar\bbpatreon\migrations\v1_3_0_tier_group_join::depends_on()
		);
	}

	/**
	 * v1_3_1 must drop patreon_tiers.group_id — the join table added by
	 * v1_3_0 is the sole source of truth for tier-to-group mapping.
	 */
	public function test_drop_tier_group_id_schema()
	{
		$migration = $this->get_migration(\avathar\bbpatreon\migrations\v1_3_1_drop_tier_group_id::class);
		$schema = $migration->update_schema();

		$this->assertArrayHasKey('drop_columns', $schema);

		$dropped = null;
		foreach ($schema['drop_columns'] as $table_name => $columns)
		{
			if (strpos($table_name, 'patreon_tiers') !== false)
			{
				$dropped = $columns;
				break;
			}
		}

		$this->assertNotNull($dropped, 'patreon_tiers columns must be declared for dropping');
		$this->assertContains('group_id', $dropped);
	}

	/**
	 * v1_3_1 must run after v1_3_0 — dropping the column before the
	 * join table exists and is backfilled would lose data.
	 */
	public function test_drop_tier_group_id_depends_on_join_table()
	{
		$this->assertEquals(
			['\avathar\bbpatreon\migrations\v1_3_0_tier_group_join'],
			\avathar\bbpatreon\migrations\v1_3_1_drop_tier_group_id::depends_on()
		);
	}
}
