<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests that the v1_0_0 migration declares all required config keys,
 * tables, and modules.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\dbal;

use PHPUnit\Framework\TestCase;

class migration_test extends TestCase
{
	/** @var \avathar\bbpatreon\migrations\v1_0_0_initial */
	protected $migration;

	public function setUp(): void
	{
		parent::setUp();

		$config = $this->createMock(\phpbb\config\config::class);
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$db_tools = $this->createMock(\phpbb\db\tools\tools_interface::class);

		$this->migration = new \avathar\bbpatreon\migrations\v1_0_0_initial(
			$config, $db, $db_tools, 'phpbb_', __DIR__, 'php'
		);
	}

	/**
	 * Every config key that the extension reads at runtime must be
	 * declared in the migration's update_data. If a key is missing,
	 * the extension will read null from phpbb\config\config and fail.
	 */
	public function test_patreon_config_keys_exist()
	{
		$expected_keys = array(
			'patreon_client_id',
			'patreon_client_secret',
			'patreon_creator_access_token',
			'patreon_creator_refresh_token',
			'patreon_campaign_id',
			'patreon_webhook_secret',
			'patreon_currency',
			'patreon_grace_period_days',
			'patreon_last_cron_sync',
		);

		$update_data = $this->migration->update_data();
		$config_adds = array();
		foreach ($update_data as $entry)
		{
			if ($entry[0] === 'config.add')
			{
				$config_adds[] = $entry[1][0];
			}
		}

		foreach ($expected_keys as $key)
		{
			$this->assertContains($key, $config_adds, "Config key '$key' must be declared in migration update_data");
		}
	}

	/**
	 * The patreon_tiers table must declare all required columns including
	 * description (TEXT), patron_count, and published.
	 */
	public function test_patreon_tiers_table_schema()
	{
		$schema = $this->migration->update_schema();

		$this->assertArrayHasKey('add_tables', $schema);

		// Find the tiers table (key contains 'patreon_tiers')
		$tiers_table = null;
		foreach ($schema['add_tables'] as $table_name => $table_def)
		{
			if (strpos($table_name, 'patreon_tiers') !== false)
			{
				$tiers_table = $table_def;
				break;
			}
		}

		$this->assertNotNull($tiers_table, 'patreon_tiers table must be declared');
		$this->assertArrayHasKey('COLUMNS', $tiers_table);

		$columns = $tiers_table['COLUMNS'];
		$this->assertArrayHasKey('tier_id', $columns);
		$this->assertArrayHasKey('tier_label', $columns);
		$this->assertArrayHasKey('description', $columns);
		$this->assertArrayHasKey('group_id', $columns);
		$this->assertArrayHasKey('amount_cents', $columns);
		$this->assertArrayHasKey('currency', $columns);
		$this->assertArrayHasKey('patron_count', $columns);
		$this->assertArrayHasKey('published', $columns);
	}

	/**
	 * The patreon_sync table must declare all columns needed for
	 * tracking pledge status per Patreon user.
	 */
	public function test_patreon_sync_table_schema()
	{
		$schema = $this->migration->update_schema();

		$sync_table = null;
		foreach ($schema['add_tables'] as $table_name => $table_def)
		{
			if (strpos($table_name, 'patreon_sync') !== false)
			{
				$sync_table = $table_def;
				break;
			}
		}

		$this->assertNotNull($sync_table, 'patreon_sync table must be declared');

		$columns = $sync_table['COLUMNS'];
		$this->assertArrayHasKey('patreon_user_id', $columns);
		$this->assertArrayHasKey('tier_id', $columns);
		$this->assertArrayHasKey('pledge_status', $columns);
		$this->assertArrayHasKey('pledge_cents', $columns);
		$this->assertArrayHasKey('last_webhook_at', $columns);
		$this->assertArrayHasKey('last_synced_at', $columns);
	}
}
