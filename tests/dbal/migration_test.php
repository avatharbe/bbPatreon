<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests that the v1_0_0 migration correctly seeds all required config keys
 * and creates the expected database tables.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\dbal;

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
	 * The patreon_tiers table must support inserting and reading back
	 * tier rows with TEXT labels (emoji, long names).
	 */
	public function test_patreon_tiers_table_stores_tiers()
	{
		$tiers = array(
			array('tier_id' => '10425190', 'tier_label' => 'Free', 'group_id' => 0, 'amount_cents' => 0, 'currency' => 'USD'),
			array('tier_id' => '3116836', 'tier_label' => "Suck Less Thumbs Up \xF0\x9F\x91\x8D", 'group_id' => 5, 'amount_cents' => 500, 'currency' => 'USD'),
			array('tier_id' => '9553953', 'tier_label' => "4 is the smallest COMPOSITE number \xF0\x9F\x98\x81", 'group_id' => 6, 'amount_cents' => 2000, 'currency' => 'EUR'),
		);

		foreach ($tiers as $tier)
		{
			$sql = 'INSERT INTO phpbb_patreon_tiers ' . $this->db->sql_build_array('INSERT', $tier);
			$this->db->sql_query($sql);
		}

		$sql = 'SELECT tier_id, tier_label, group_id, amount_cents, currency FROM phpbb_patreon_tiers ORDER BY tier_id';
		$result = $this->db->sql_query($sql);
		$rows = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		$this->assertCount(3, $rows, 'All 3 tiers must be stored');
		$this->assertEquals('Free', $rows[0]['tier_label']);
		$this->assertStringContainsString("\xF0\x9F\x91\x8D", $rows[1]['tier_label'], 'Emoji must survive round-trip');
		$this->assertEquals(5, (int) $rows[1]['group_id']);
		$this->assertEquals(2000, (int) $rows[2]['amount_cents']);
		$this->assertEquals('EUR', $rows[2]['currency']);
	}

	/**
	 * Verify that tier_label as TEXT can store values exceeding 255 characters.
	 */
	public function test_patreon_tiers_table_stores_long_labels()
	{
		$long_label = str_repeat("Very Long Tier Name \xF0\x9F\x98\x81 ", 20);
		$this->assertGreaterThan(255, strlen($long_label));

		$sql = 'INSERT INTO phpbb_patreon_tiers ' . $this->db->sql_build_array('INSERT', array(
			'tier_id'		=> 'long-label-test',
			'tier_label'	=> $long_label,
			'group_id'		=> 0,
			'amount_cents'	=> 0,
			'currency'		=> 'USD',
		));
		$this->db->sql_query($sql);

		$sql = "SELECT tier_label FROM phpbb_patreon_tiers WHERE tier_id = 'long-label-test'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$this->assertEquals($long_label, $row['tier_label'], 'TEXT column must store labels exceeding 255 chars without truncation');
	}
}
