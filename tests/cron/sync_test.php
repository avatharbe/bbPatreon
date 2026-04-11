<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the cron sync task.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\cron;

use PHPUnit\Framework\TestCase;

class sync_test extends TestCase
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $log;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $api_client;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $group_mapper;

	protected function get_task(array $config_data = array())
	{
		$defaults = array(
			'patreon_creator_access_token'	=> '',
			'patreon_campaign_id'			=> '',
			'patreon_last_cron_sync'		=> 0,
			'patreon_grace_period_days'		=> 0,
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$this->log = $this->createMock(\phpbb\log\log_interface::class);
		$this->api_client = $this->getMockBuilder(\avathar\bbpatreon\service\api_client::class)
			->disableOriginalConstructor()
			->getMock();
		$this->group_mapper = $this->getMockBuilder(\avathar\bbpatreon\service\group_mapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);
		$this->db->method('sql_escape')->willReturnArgument(0);
		$this->db->method('sql_build_array')->willReturn("'dummy'");
		$this->db->method('sql_in_set')->willReturn('1=1');

		return new \avathar\bbpatreon\cron\task\sync(
			$this->config,
			$this->db,
			$this->log,
			$this->api_client,
			$this->group_mapper,
			'phpbb_patreon_sync',
			'phpbb_oauth_accounts'
		);
	}

	/**
	 * is_runnable() returns false when no access token is configured.
	 */
	public function test_is_runnable_false_without_token()
	{
		$task = $this->get_task();

		$this->assertFalse($task->is_runnable());
	}

	/**
	 * is_runnable() returns false when no campaign ID is configured.
	 */
	public function test_is_runnable_false_without_campaign()
	{
		$task = $this->get_task(array(
			'patreon_creator_access_token'	=> 'token123',
		));

		$this->assertFalse($task->is_runnable());
	}

	/**
	 * is_runnable() returns true when both token and campaign are set.
	 */
	public function test_is_runnable_true_with_credentials()
	{
		$task = $this->get_task(array(
			'patreon_creator_access_token'	=> 'token123',
			'patreon_campaign_id'			=> 'campaign456',
		));

		$this->assertTrue($task->is_runnable());
	}

	/**
	 * should_run() returns true when never run before.
	 */
	public function test_should_run_true_when_never_run()
	{
		$task = $this->get_task(array(
			'patreon_last_cron_sync' => 0,
		));

		$this->assertTrue($task->should_run());
	}

	/**
	 * should_run() returns true when last sync was more than 24h ago.
	 */
	public function test_should_run_true_after_24h()
	{
		$task = $this->get_task(array(
			'patreon_last_cron_sync' => time() - 86401,
		));

		$this->assertTrue($task->should_run());
	}

	/**
	 * should_run() returns false when last sync was less than 24h ago.
	 */
	public function test_should_run_false_within_24h()
	{
		$task = $this->get_task(array(
			'patreon_last_cron_sync' => time() - 3600,
		));

		$this->assertFalse($task->should_run());
	}

	/**
	 * run() with empty API result should update last_cron_sync and return early.
	 */
	public function test_run_empty_members()
	{
		$task = $this->get_task(array(
			'patreon_creator_access_token'	=> 'token123',
			'patreon_campaign_id'			=> 'campaign456',
		));

		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array());

		$this->group_mapper->expects($this->never())
			->method('sync_user_groups');

		$task->run();

		$this->assertGreaterThan(0, (int) $this->config['patreon_last_cron_sync']);
	}

	/**
	 * run() with members but no linked users should upsert but not sync groups.
	 */
	public function test_run_members_no_linked_users()
	{
		$task = $this->get_task(array(
			'patreon_creator_access_token'	=> 'token123',
			'patreon_campaign_id'			=> 'campaign456',
		));

		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array(
				array('patreon_user_id' => 'p1', 'tier_id' => 't1', 'patron_status' => 'active_patron', 'pledge_cents' => 500),
			));

		$this->group_mapper->expects($this->never())
			->method('sync_user_groups');

		$this->log->expects($this->once())
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_CRON_SYNC', $this->anything(), $this->callback(function ($args) {
				return $args[0] === '1' && $args[1] === '0';
			}));

		$task->run();
	}

	/**
	 * run() should skip members with empty patreon_user_id.
	 */
	public function test_run_skips_empty_user_id()
	{
		$task = $this->get_task(array(
			'patreon_creator_access_token'	=> 'token123',
			'patreon_campaign_id'			=> 'campaign456',
		));

		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array(
				array('patreon_user_id' => '', 'tier_id' => 't1', 'patron_status' => 'active_patron', 'pledge_cents' => 500),
			));

		$task->run();

		// No sync, but log should show 1 member total, 0 synced
		$this->assertGreaterThan(0, (int) $this->config['patreon_last_cron_sync']);
	}
}
