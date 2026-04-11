<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the UCP module info and module wiring.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\functional;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the UCP module metadata and wiring.
 *
 * These tests verify the UCP module info returns correct data
 * and the module class delegates to the right controller service.
 */
class ucp_test extends TestCase
{
	/**
	 * The UCP module info must declare the 'settings' mode.
	 * Auth should only require the extension to be enabled (no ACL),
	 * so any registered user can access it.
	 */
	public function test_ucp_module_info()
	{
		$info = new \avathar\bbpatreon\ucp\main_info();
		$module = $info->module();

		$this->assertArrayHasKey('modes', $module);
		$this->assertArrayHasKey('settings', $module['modes']);
		$this->assertEquals('UCP_BBPATREON', $module['modes']['settings']['title']);
		$this->assertStringContainsString('ext_avathar/bbpatreon', $module['modes']['settings']['auth']);
	}

	/**
	 * The UCP module must set the correct template and page title,
	 * then delegate to the controller.
	 */
	public function test_ucp_module_sets_template_and_title()
	{
		$controller = $this->createMock(\avathar\bbpatreon\controller\ucp_controller::class);
		$controller->expects($this->once())->method('set_page_url');
		$controller->expects($this->once())->method('display_options');

		$container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
		$container->method('get')
			->with('avathar.bbpatreon.controller.ucp')
			->willReturn($controller);

		$module = new \avathar\bbpatreon\ucp\main_module();

		$GLOBALS['phpbb_container'] = $container;

		$module->main(0, 'settings');

		$this->assertEquals('ucp_bbpatreon_body', $module->tpl_name);
		$this->assertEquals('UCP_BBPATREON_TITLE', $module->page_title);

		unset($GLOBALS['phpbb_container']);
	}
}
