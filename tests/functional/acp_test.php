<?php
/**
 *
 * Patreon Integration for phpBB.
 * Functional tests for the ACP settings page.
 *
 * @copyright (c) 2024 Sajaki
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\functional;

/**
 * Functional tests for the ACP module.
 *
 * These tests verify that the ACP page loads correctly after the extension
 * is enabled and the migration has run. They catch DI wiring errors (wrong
 * service arguments in services.yml), missing language keys, and template
 * syntax errors that unit tests cannot detect.
 *
 * @group functional
 */
class acp_test extends \phpbb_functional_test_case
{
	protected static function setup_extensions()
	{
		return array('avathar/bbpatreon');
	}

	/**
	 * The ACP module must load without errors for an administrator.
	 *
	 * This is the most important functional test: it exercises the full
	 * DI container build, template rendering, and language loading in
	 * one request. If the services.yml has a typo or a missing argument,
	 * this test will fail with a container build error.
	 */
	public function test_acp_module_accessible()
	{
		$this->login();
		$this->admin_login();

		$this->add_lang_ext('avathar/bbpatreon', 'info_acp_bbpatreon');

		$crawler = self::request('GET', 'adm/index.php?i=-avathar-bbpatreon-acp-main_module&mode=settings&sid=' . $this->sid);

		$this->assertStringContainsString($this->lang('ACP_BBPATREON_TITLE'), $crawler->text());
	}

	/**
	 * All API credential input fields must be present in the rendered form.
	 *
	 * If a field is missing, the admin cannot configure the extension.
	 * This catches cases where the template was edited but a field was
	 * accidentally removed, or where a template variable is undefined
	 * causing the field to not render.
	 */
	public function test_acp_has_credentials_fields()
	{
		$this->login();
		$this->admin_login();

		$crawler = self::request('GET', 'adm/index.php?i=-avathar-bbpatreon-acp-main_module&mode=settings&sid=' . $this->sid);

		$this->assertEquals(1, $crawler->filter('input[name="patreon_client_id"]')->count());
		$this->assertEquals(1, $crawler->filter('input[name="patreon_client_secret"]')->count());
		$this->assertEquals(1, $crawler->filter('input[name="patreon_creator_access_token"]')->count());
		$this->assertEquals(1, $crawler->filter('input[name="patreon_campaign_id"]')->count());
	}

	/**
	 * The Submit, Sync Now, and Fetch Tiers action buttons must all be
	 * present. These are the primary admin actions — if any is missing,
	 * the admin loses a key capability without any visible error.
	 */
	public function test_acp_has_action_buttons()
	{
		$this->login();
		$this->admin_login();

		$crawler = self::request('GET', 'adm/index.php?i=-avathar-bbpatreon-acp-main_module&mode=settings&sid=' . $this->sid);

		$this->assertEquals(1, $crawler->filter('input[name="submit"]')->count());
		$this->assertEquals(1, $crawler->filter('input[name="sync"]')->count());
		$this->assertEquals(1, $crawler->filter('input[name="fetch_tiers"]')->count());
	}
}
