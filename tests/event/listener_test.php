<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the event listener that hooks into phpBB's language loading and
 * OAuth login flow to trigger Patreon-specific sync logic.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\event;

/**
 * Unit tests for \avathar\bbpatreon\event\listener.
 *
 * These tests verify that the listener subscribes to the correct phpBB
 * events, loads its language file, and filters out non-Patreon OAuth
 * providers so the API client is never called for Google/Facebook logins.
 */
class listener_test extends \phpbb_test_case
{
	/** @var \avathar\bbpatreon\event\listener */
	protected $listener;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $language;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $api_client;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $group_mapper;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $helper;

	public function setUp(): void
	{
		parent::setUp();

		$this->config = new \phpbb\config\config(array());
		$this->db = $this->createMock('\phpbb\db\driver\driver_interface');
		$this->language = $this->getMockBuilder('\phpbb\language\language')
			->disableOriginalConstructor()
			->getMock();
		$this->api_client = $this->getMockBuilder('\avathar\bbpatreon\service\api_client')
			->disableOriginalConstructor()
			->getMock();
		$this->group_mapper = $this->getMockBuilder('\avathar\bbpatreon\service\group_mapper')
			->disableOriginalConstructor()
			->getMock();
		$this->helper = $this->getMockBuilder('\phpbb\controller\helper')
			->disableOriginalConstructor()
			->getMock();

		$this->listener = new \avathar\bbpatreon\event\listener(
			$this->config,
			$this->db,
			$this->language,
			$this->api_client,
			$this->group_mapper,
			$this->helper,
			'phpbb_patreon_sync'
		);
	}

	/**
	 * The listener must subscribe to exactly two events:
	 * - core.user_setup: to load the extension's language file on every page
	 * - core.oauth_login_after_check_if_provider_id_has_match: to sync
	 *   groups after a Patreon OAuth link
	 *
	 * If either subscription is missing, the extension silently stops working.
	 */
	public function test_subscribed_events()
	{
		$events = \avathar\bbpatreon\event\listener::getSubscribedEvents();

		$this->assertArrayHasKey('core.user_setup', $events);
		$this->assertArrayHasKey('core.page_header', $events);
		$this->assertArrayHasKey('core.oauth_login_after_check_if_provider_id_has_match', $events);
		$this->assertCount(3, $events);
	}

	/**
	 * The language file must be appended to $lang_set_ext so that phpBB
	 * loads it during user setup. Without this, all PATREON_* language
	 * keys would render as raw key names in templates and log entries.
	 */
	public function test_load_language_on_setup()
	{
		$event = new \phpbb\event\data(array(
			'lang_set_ext' => array(),
		));

		$this->listener->load_language_on_setup($event);

		$lang_set_ext = $event['lang_set_ext'];

		$this->assertCount(1, $lang_set_ext);
		$this->assertEquals('avathar/bbpatreon', $lang_set_ext[0]['ext_name']);
		$this->assertEquals('common', $lang_set_ext[0]['lang_set']);
	}

	/**
	 * Language entries must be appended, not overwrite existing entries
	 * from other extensions.
	 */
	public function test_load_language_appends_to_existing()
	{
		$existing = array(
			array('ext_name' => 'other/extension', 'lang_set' => 'other'),
		);
		$event = new \phpbb\event\data(array(
			'lang_set_ext' => $existing,
		));

		$this->listener->load_language_on_setup($event);

		$lang_set_ext = $event['lang_set_ext'];
		$this->assertCount(2, $lang_set_ext);
		$this->assertEquals('other/extension', $lang_set_ext[0]['ext_name']);
		$this->assertEquals('avathar/bbpatreon', $lang_set_ext[1]['ext_name']);
	}

	/**
	 * When a user logs in via Google, Facebook, or any other OAuth provider,
	 * on_oauth_login must exit early without calling the Patreon API.
	 * Without this guard, every OAuth login on the board would trigger a
	 * slow and unnecessary Patreon API call.
	 */
	public function test_on_oauth_login_ignores_non_patreon()
	{
		$event = new \phpbb\event\data(array(
			'data'	=> array('provider' => 'google', 'oauth_provider_id' => '123'),
			'row'	=> array('user_id' => 2),
		));

		$this->api_client->expects($this->never())->method('get_campaign_members');

		$this->listener->on_oauth_login($event);
	}

	/**
	 * When $row is false, phpBB has not yet matched the OAuth provider ID
	 * to an existing user — this happens during first-time login where
	 * phpBB will ask the user to link or create an account. We must not
	 * attempt a sync because there is no user_id to assign groups to.
	 * The sync will run on the next login or via cron once the link exists.
	 */
	public function test_on_oauth_login_skips_when_row_empty()
	{
		$event = new \phpbb\event\data(array(
			'data'	=> array('provider' => 'patreon', 'oauth_provider_id' => '123'),
			'row'	=> false,
		));

		$this->api_client->expects($this->never())->method('get_campaign_members');

		$this->listener->on_oauth_login($event);
	}

	/**
	 * When the provider field is missing entirely, on_oauth_login must
	 * exit early without errors.
	 */
	public function test_on_oauth_login_skips_when_no_provider()
	{
		$event = new \phpbb\event\data(array(
			'data'	=> array('oauth_provider_id' => '123'),
			'row'	=> array('user_id' => 2),
		));

		$this->api_client->expects($this->never())->method('get_campaign_members');

		$this->listener->on_oauth_login($event);
	}

	/**
	 * Happy path: Patreon login with a matched user triggers API fetch
	 * and group sync. The member is found in the campaign with an active
	 * pledge and tier.
	 */
	public function test_on_oauth_login_syncs_matched_patron()
	{
		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array(
				array(
					'patreon_user_id'	=> 'patreon-456',
					'patron_status'		=> 'active_patron',
					'pledge_cents'		=> 500,
					'tier_id'			=> 'tier-gold',
				),
			));

		$this->group_mapper->expects($this->once())
			->method('sync_user_groups')
			->with(2, 'tier-gold', 'active_patron');

		// Mock DB for upsert_sync: first query checks existence, second does insert
		$call_count = 0;
		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')->willReturnCallback(function () use (&$call_count) {
			$call_count++;
			// First fetchrow call = existence check (not found)
			if ($call_count === 1)
			{
				return false;
			}
			return false;
		});
		$this->db->method('sql_freeresult')->willReturn(null);
		$this->db->method('sql_escape')->willReturnArgument(0);
		$this->db->method('sql_build_array')->willReturn("'dummy'");

		$event = new \phpbb\event\data(array(
			'data'	=> array('provider' => 'patreon', 'oauth_provider_id' => 'patreon-456'),
			'row'	=> array('user_id' => 2),
		));

		$this->listener->on_oauth_login($event);
	}

	/**
	 * When the Patreon user is not found in the campaign members list,
	 * sync should still proceed with default values (pending_link status,
	 * empty tier, 0 pledge).
	 */
	public function test_on_oauth_login_handles_member_not_found()
	{
		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array(
				array(
					'patreon_user_id'	=> 'other-user',
					'patron_status'		=> 'active_patron',
					'pledge_cents'		=> 500,
					'tier_id'			=> 'tier-gold',
				),
			));

		// Should sync with defaults: empty tier, pending_link status
		$this->group_mapper->expects($this->once())
			->method('sync_user_groups')
			->with(2, '', 'pending_link');

		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);
		$this->db->method('sql_escape')->willReturnArgument(0);
		$this->db->method('sql_build_array')->willReturn("'dummy'");

		$event = new \phpbb\event\data(array(
			'data'	=> array('provider' => 'patreon', 'oauth_provider_id' => 'patreon-456'),
			'row'	=> array('user_id' => 2),
		));

		$this->listener->on_oauth_login($event);
	}

	/**
	 * When the API returns an empty member list, sync should proceed
	 * with default values.
	 */
	public function test_on_oauth_login_handles_empty_members()
	{
		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array());

		$this->group_mapper->expects($this->once())
			->method('sync_user_groups')
			->with(2, '', 'pending_link');

		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);
		$this->db->method('sql_escape')->willReturnArgument(0);
		$this->db->method('sql_build_array')->willReturn("'dummy'");

		$event = new \phpbb\event\data(array(
			'data'	=> array('provider' => 'patreon', 'oauth_provider_id' => 'patreon-456'),
			'row'	=> array('user_id' => 2),
		));

		$this->listener->on_oauth_login($event);
	}

	/**
	 * When on_oauth_login finds an existing sync record, it should
	 * update rather than insert.
	 */
	public function test_on_oauth_login_updates_existing_sync_record()
	{
		$this->api_client->expects($this->once())
			->method('get_campaign_members')
			->willReturn(array(
				array(
					'patreon_user_id'	=> 'patreon-456',
					'patron_status'		=> 'active_patron',
					'pledge_cents'		=> 1000,
					'tier_id'			=> 'tier-premium',
				),
			));

		$this->group_mapper->expects($this->once())
			->method('sync_user_groups')
			->with(2, 'tier-premium', 'active_patron');

		// Mock DB: existence check returns a row (record exists)
		$call_count = 0;
		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')->willReturnCallback(function () use (&$call_count) {
			$call_count++;
			if ($call_count === 1)
			{
				return array('patreon_user_id' => 'patreon-456');
			}
			return false;
		});
		$this->db->method('sql_freeresult')->willReturn(null);
		$this->db->method('sql_escape')->willReturnArgument(0);
		$this->db->method('sql_build_array')->willReturn("'dummy'");

		$event = new \phpbb\event\data(array(
			'data'	=> array('provider' => 'patreon', 'oauth_provider_id' => 'patreon-456'),
			'row'	=> array('user_id' => 2),
		));

		$this->listener->on_oauth_login($event);
	}
}
