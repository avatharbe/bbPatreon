<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the ACP module info and module wiring.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\functional;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ACP module metadata and wiring.
 *
 * These tests verify the ACP module info returns correct data
 * and the module class delegates to the right controller service.
 */
class acp_test extends TestCase
{
	/**
	 * The ACP module info must declare the 'settings' mode with
	 * the correct auth requirement. If this is wrong, the module
	 * either doesn't appear in the ACP menu or is accessible to
	 * non-admin users.
	 */
	public function test_acp_module_info()
	{
		$info = new \avathar\bbpatreon\acp\main_info();
		$module = $info->module();

		$this->assertArrayHasKey('modes', $module);
		$this->assertArrayHasKey('settings', $module['modes']);
		$this->assertEquals('ACP_BBPATREON', $module['modes']['settings']['title']);
		$this->assertStringContainsString('acl_a_board', $module['modes']['settings']['auth']);
		$this->assertStringContainsString('ext_avathar/bbpatreon', $module['modes']['settings']['auth']);
	}

	/**
	 * The ACP module must set the correct template and page title,
	 * then delegate to the controller. If the template name is wrong,
	 * phpBB will throw a template-not-found error.
	 */
	public function test_acp_module_sets_template_and_title()
	{
		$controller = $this->createMock(\avathar\bbpatreon\controller\acp_controller::class);
		$controller->expects($this->once())->method('set_page_url');
		$controller->expects($this->once())->method('display_options');

		$container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
		$container->method('get')
			->with('avathar.bbpatreon.controller.acp')
			->willReturn($controller);

		$module = new \avathar\bbpatreon\acp\main_module();

		// phpBB modules get the container via $GLOBALS
		$GLOBALS['phpbb_container'] = $container;

		$module->main(0, 'settings');

		$this->assertEquals('acp_bbpatreon_body', $module->tpl_name);
		$this->assertEquals('ACP_BBPATREON_TITLE', $module->page_title);

		unset($GLOBALS['phpbb_container']);
	}

	/**
	 * All API credential input fields must be present in the template.
	 * This verifies the template file exists and contains the expected fields.
	 */
	public function test_acp_template_has_credential_fields()
	{
		$template_path = dirname(__DIR__, 2) . '/adm/style/acp_bbpatreon_body.html';

		$this->assertFileExists($template_path, 'ACP template file must exist');

		$content = file_get_contents($template_path);
		$this->assertStringContainsString('patreon_client_id', $content);
		$this->assertStringContainsString('patreon_client_secret', $content);
		$this->assertStringContainsString('patreon_creator_access_token', $content);
		$this->assertStringContainsString('patreon_campaign_id', $content);
	}
}
