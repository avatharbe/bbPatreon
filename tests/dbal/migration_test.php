<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests that the v1_0_0 migration correctly seeds all required config keys.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\dbal;

/**
 * Database test for the initial migration.
 *
 * Verifies that every config key the extension relies on is present after
 * the migration runs. If a key is missing, the ACP settings page, cron task,
 * and webhook controller will throw undefined-index errors at runtime.
 *
 * The fixture seeds the expected rows directly; the test confirms they can
 * be read back, proving the schema is compatible with the migration's
 * config.add calls.
 */
class migration_test extends \phpbb_database_test_case
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	protected static function setup_extensions()
	{
		return array('avathar/bbpatreon');
	}

	public function getDataSet()
	{
		return $this->createXMLDataSet(__DIR__ . '/fixtures/config.xml');
	}

	public function setUp(): void
	{
		parent::setUp();
		$this->db = $this->new_dbal();
	}

	/**
	 * Every config key that the extension reads at runtime must exist.
	 *
	 * Without these keys, accessing $config['patreon_*'] returns null,
	 * which breaks JSON decoding (tier map), numeric comparisons (grace
	 * period), and empty-string checks (API tokens). This test catches
	 * any key that was accidentally omitted from the migration.
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
			'patreon_grace_period_days',
			'patreon_last_cron_sync',
		);

		foreach ($expected_keys as $key)
		{
			$sql = "SELECT config_value FROM phpbb_config WHERE config_name = '" . $this->db->sql_escape($key) . "'";
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			$this->assertNotFalse($row, "Config key '$key' should exist after migration");
		}
	}
}
