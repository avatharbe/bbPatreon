<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the supporters page controller.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\controller;

use PHPUnit\Framework\TestCase;

class supporters_controller_test extends TestCase
{
	protected function get_controller(array $config_data = array())
	{
		$defaults = array(
			'patreon_supporters_page_enabled' => 0,
		);

		$config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$language = $this->getMockBuilder(\phpbb\language\language::class)
			->disableOriginalConstructor()
			->getMock();
		$template = $this->createMock(\phpbb\template\template::class);
		$helper = $this->getMockBuilder(\phpbb\controller\helper::class)
			->disableOriginalConstructor()
			->getMock();

		return new \avathar\bbpatreon\controller\supporters_controller(
			$config,
			$db,
			$language,
			$template,
			$helper,
			'phpbb_patreon_sync',
			'phpbb_patreon_tiers',
			'phpbb_oauth_accounts'
		);
	}

	/**
	 * When the supporters page is disabled, handle() must throw a 404.
	 */
	public function test_handle_returns_404_when_disabled()
	{
		$controller = $this->get_controller();

		$this->expectException(\phpbb\exception\http_exception::class);

		$controller->handle();
	}
}
