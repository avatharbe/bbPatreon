<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests that the v1_1_0 migration declares the show_public column and config key.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\dbal;

use PHPUnit\Framework\TestCase;

class supporters_migration_test extends TestCase
{
	/** @var \avathar\bbpatreon\migrations\v1_1_0_supporters_page */
	protected $migration;

	public function setUp(): void
	{
		parent::setUp();

		$config = $this->createMock(\phpbb\config\config::class);
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db_tools = $this->createMock(\phpbb\db\tools\tools_interface::class);

		$this->migration = new \avathar\bbpatreon\migrations\v1_1_0_supporters_page(
			$config, $db, $db_tools, 'phpbb_', __DIR__, 'php'
		);
	}

	/**
	 * Migration must depend on v1_0_0_initial.
	 */
	public function test_depends_on_initial()
	{
		$deps = \avathar\bbpatreon\migrations\v1_1_0_supporters_page::depends_on();
		$this->assertContains('\avathar\bbpatreon\migrations\v1_0_0_initial', $deps);
	}

	/**
	 * Must add show_public column to patreon_sync table.
	 */
	public function test_adds_show_public_column()
	{
		$schema = $this->migration->update_schema();

		$this->assertArrayHasKey('add_columns', $schema);

		$found = false;
		foreach ($schema['add_columns'] as $table => $columns)
		{
			if (strpos($table, 'patreon_sync') !== false && isset($columns['show_public']))
			{
				$found = true;
				$this->assertEquals('BOOL', $columns['show_public'][0]);
				$this->assertEquals(0, $columns['show_public'][1]);
			}
		}
		$this->assertTrue($found, 'show_public column must be added to patreon_sync');
	}

	/**
	 * Must add patreon_supporters_page_enabled config key.
	 */
	public function test_adds_supporters_config()
	{
		$update_data = $this->migration->update_data();

		$config_adds = array();
		foreach ($update_data as $entry)
		{
			if ($entry[0] === 'config.add')
			{
				$config_adds[$entry[1][0]] = $entry[1][1];
			}
		}

		$this->assertArrayHasKey('patreon_supporters_page_enabled', $config_adds);
		$this->assertEquals(0, $config_adds['patreon_supporters_page_enabled']);
	}
}
