<?php
/**
 *
 * Patreon Integration for phpBB.
 * Functional tests for the UCP Patreon page.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\functional;

/**
 * Functional tests for the UCP module.
 *
 * These tests verify that a logged-in user can access the Patreon UCP page
 * and sees the correct UI state (link button when not linked). They catch
 * module registration errors, DI wiring problems, and template rendering
 * failures that only surface in a real phpBB request.
 *
 * @group functional
 */
class ucp_test extends \phpbb_functional_test_case
{
	protected static function setup_extensions()
	{
		return array('avathar/bbpatreon');
	}

	/**
	 * The UCP module must load for a logged-in user.
	 *
	 * Unlike the ACP, the UCP module has auth = 'ext_avathar/bbpatreon'
	 * (no ACL check), so any registered user should be able to access it.
	 * If this fails, it typically means the UCP module was not registered
	 * by the migration, or the DI container cannot build the controller.
	 */
	public function test_ucp_module_accessible()
	{
		$this->login();

		$this->add_lang_ext('avathar/bbpatreon', 'info_ucp_bbpatreon');

		$crawler = self::request('GET', 'ucp.php?i=-avathar-bbpatreon-ucp-main_module&mode=settings&sid=' . $this->sid);

		$this->assertStringContainsString($this->lang('UCP_BBPATREON_TITLE'), $crawler->text());
	}

	/**
	 * When a user has not linked their Patreon account, the UCP page must
	 * show the "Link your Patreon Account" button and the explanatory text.
	 *
	 * This is the entry point for the entire OAuth flow. If the button is
	 * missing, users have no way to link their accounts. If the text is
	 * missing, the language file is not being loaded.
	 */
	public function test_ucp_shows_link_button_when_not_linked()
	{
		$this->login();

		$this->add_lang_ext('avathar/bbpatreon', 'info_ucp_bbpatreon');

		$crawler = self::request('GET', 'ucp.php?i=-avathar-bbpatreon-ucp-main_module&mode=settings&sid=' . $this->sid);

		$this->assertEquals(1, $crawler->filter('input[name="link"]')->count());
		$this->assertStringContainsString($this->lang('UCP_BBPATREON_NOT_LINKED'), $crawler->text());
	}
}
