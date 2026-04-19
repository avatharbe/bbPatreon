<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the patreon_linked notification type.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\notification;

use PHPUnit\Framework\TestCase;

class patreon_linked_test extends TestCase
{
	/** @var \avathar\bbpatreon\notification\type\patreon_linked */
	protected $notification;

	/** @var \PHPUnit\Framework\MockObject\MockObject|\phpbb\user_loader */
	protected $user_loader;

	protected function setUp(): void
	{
		parent::setUp();

		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$language = $this->createMock(\phpbb\language\language::class);
		$user = $this->createMock(\phpbb\user::class);
		$auth = $this->createMock(\phpbb\auth\auth::class);

		$this->notification = new \avathar\bbpatreon\notification\type\patreon_linked(
			$db,
			$language,
			$user,
			$auth,
			'./',
			'php',
			'phpbb_user_notifications'
		);

		$this->notification->set_initial_data([
			'notification_id'	=> 1,
			'item_id'			=> 42,
			'item_parent_id'	=> 0,
			'user_id'			=> 2,
			'notification_read'	=> 0,
			'notification_time'	=> time(),
			'notification_data'	=> serialize(['tier_id' => 'gold', 'pledge_status' => 'active']),
		]);

		$this->user_loader = $this->getMockBuilder(\phpbb\user_loader::class)
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * Test that set_user_loader() exists and get_avatar() works after calling it.
	 *
	 * This verifies that the notification type properly accepts user_loader
	 * injection via set_user_loader(), which is required by phpBB's DI
	 * service configuration (calls: [set_user_loader, ['@user_loader']]).
	 */
	public function test_get_avatar_with_user_loader()
	{
		$this->user_loader->expects($this->once())
			->method('get_avatar')
			->with(42, false, true)
			->willReturn('<img src="avatar.png" />');

		$this->notification->set_user_loader($this->user_loader);

		$result = $this->notification->get_avatar();
		$this->assertEquals('<img src="avatar.png" />', $result);
	}

	/**
	 * Test that get_title() works after set_user_loader() is called.
	 */
	public function test_get_title_with_user_loader()
	{
		$this->user_loader->expects($this->once())
			->method('get_username')
			->with(42, 'no_profile')
			->willReturn('TestUser');

		$language = $this->createMock(\phpbb\language\language::class);
		$language->method('lang')
			->with('NOTIFICATION_PATREON_LINKED', 'TestUser')
			->willReturn('TestUser linked their Patreon account');

		// Rebuild with the configured language mock
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$user = $this->createMock(\phpbb\user::class);
		$auth = $this->createMock(\phpbb\auth\auth::class);

		$notification = new \avathar\bbpatreon\notification\type\patreon_linked(
			$db, $language, $user, $auth, './', 'php', 'phpbb_user_notifications'
		);

		$notification->set_initial_data([
			'notification_id'	=> 1,
			'item_id'			=> 42,
			'item_parent_id'	=> 0,
			'user_id'			=> 2,
			'notification_read'	=> 0,
			'notification_time'	=> time(),
			'notification_data'	=> serialize([]),
		]);

		$notification->set_user_loader($this->user_loader);

		$result = $notification->get_title();
		$this->assertEquals('TestUser linked their Patreon account', $result);
	}
}
