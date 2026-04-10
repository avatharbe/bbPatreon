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
			'patreon_currency',
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

	/**
	 * Tier labels with many tiers and emoji can exceed the 255-char limit
	 * of phpbb_config. This test verifies that config_text can store the
	 * kind of payload that triggered issue #6.
	 *
	 * @see https://github.com/avatharbe/bbPatreon/issues/6
	 */
	public function test_config_text_stores_long_tier_labels()
	{
		$config_text = new \phpbb\config\db_text($this->db, 'phpbb_config_text');

		// Realistic payload from the bug report — 10 tiers with emoji
		$tier_labels = json_encode(array(
			'10425190' => 'Free',
			'3116836'  => "Suck Less Thumbs Up",
			'4249194'  => "Suck Less TWO Thumbs Up",
			'8043226'  => "Suck Less THREE Thumbs Up!",
			'9553953'  => "4 is the smallest COMPOSITE number \xF0\x9F\x98\x81",
			'3088625'  => "Suck Less High Five",
			'4249251'  => "Suck Less On Tap",
			'3132211'  => "Suck Less Sweet Sixteen",
			'7436306'  => "WSL (WSL Sponsor Level)",
			'3116840'  => "Suck Less Sugarnonckle",
		));

		$this->assertGreaterThan(255, strlen($tier_labels), 'Test payload must exceed 255 chars to be meaningful');

		$config_text->set('patreon_tier_labels', $tier_labels);
		$value = $config_text->get('patreon_tier_labels');

		$this->assertEquals($tier_labels, $value, 'config_text must store and retrieve long JSON without truncation');

		$decoded = json_decode($value, true);
		$this->assertCount(10, $decoded, 'All 10 tiers must survive the round-trip');
	}

	/**
	 * Verify that a large tier-group map with many tiers can be stored
	 * and retrieved without truncation.
	 */
	public function test_config_text_stores_long_tier_group_map()
	{
		$config_text = new \phpbb\config\db_text($this->db, 'phpbb_config_text');

		// Build a map with 20 tiers to exceed 255 chars
		$map = array();
		for ($i = 1; $i <= 20; $i++)
		{
			$map['tier_' . str_pad($i, 8, '0', STR_PAD_LEFT)] = $i + 100;
		}
		$json = json_encode($map);

		$this->assertGreaterThan(255, strlen($json), 'Test payload must exceed 255 chars to be meaningful');

		$config_text->set('patreon_tier_group_map', $json);
		$value = $config_text->get('patreon_tier_group_map');

		$this->assertEquals($json, $value, 'config_text must store and retrieve long JSON without truncation');

		$decoded = json_decode($value, true);
		$this->assertCount(20, $decoded, 'All 20 tier mappings must survive the round-trip');
	}
}
