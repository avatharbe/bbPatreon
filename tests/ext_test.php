<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the extension entry point.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests;

use PHPUnit\Framework\TestCase;

class ext_test extends TestCase
{
	protected $container;
	protected $extension_finder;
	protected $migrator;

	protected function setUp(): void
	{
		parent::setUp();

		$this->container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
		$this->extension_finder = $this->getMockBuilder(\phpbb\finder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->migrator = $this->getMockBuilder(\phpbb\db\migrator::class)
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * Extension is enableable when phpBB >= 3.3.0 and curl is loaded.
	 */
	public function test_is_enableable()
	{
		$ext = new \avathar\bbpatreon\ext(
			$this->container,
			$this->extension_finder,
			$this->migrator,
			'avathar/bbpatreon',
			''
		);

		// curl is loaded in the CLI environment
		$this->assertTrue($ext->is_enableable());
	}

	/**
	 * enable_step with false state should enable notifications.
	 */
	public function test_enable_step_enables_notifications()
	{
		$notification_manager = $this->createMock(\phpbb\notification\manager::class);
		$notification_manager->expects($this->once())
			->method('enable_notifications')
			->with('avathar.bbpatreon.notification.type.patreon_linked');

		$this->container->method('get')
			->with('notification_manager')
			->willReturn($notification_manager);

		$ext = new \avathar\bbpatreon\ext(
			$this->container,
			$this->extension_finder,
			$this->migrator,
			'avathar/bbpatreon',
			''
		);

		$result = $ext->enable_step(false);
		$this->assertEquals('notification', $result);
	}

	/**
	 * disable_step with false state should disable notifications.
	 */
	public function test_disable_step_disables_notifications()
	{
		$notification_manager = $this->createMock(\phpbb\notification\manager::class);
		$notification_manager->expects($this->once())
			->method('disable_notifications')
			->with('avathar.bbpatreon.notification.type.patreon_linked');

		$this->container->method('get')
			->with('notification_manager')
			->willReturn($notification_manager);

		$ext = new \avathar\bbpatreon\ext(
			$this->container,
			$this->extension_finder,
			$this->migrator,
			'avathar/bbpatreon',
			''
		);

		$result = $ext->disable_step(false);
		$this->assertEquals('notification', $result);
	}

	/**
	 * purge_step with false state should purge notifications.
	 */
	public function test_purge_step_purges_notifications()
	{
		$notification_manager = $this->createMock(\phpbb\notification\manager::class);
		$notification_manager->expects($this->once())
			->method('purge_notifications')
			->with('avathar.bbpatreon.notification.type.patreon_linked');

		$this->container->method('get')
			->with('notification_manager')
			->willReturn($notification_manager);

		$ext = new \avathar\bbpatreon\ext(
			$this->container,
			$this->extension_finder,
			$this->migrator,
			'avathar/bbpatreon',
			''
		);

		$result = $ext->purge_step(false);
		$this->assertEquals('notification', $result);
	}
}
