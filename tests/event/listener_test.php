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

		$this->listener = new \avathar\bbpatreon\event\listener(
			$this->config,
			$this->db,
			$this->language,
			$this->api_client,
			$this->group_mapper,
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
		$this->assertArrayHasKey('core.oauth_login_after_check_if_provider_id_has_match', $events);
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
}
