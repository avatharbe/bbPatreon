<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests for the ACP controller's testable methods.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\controller;

use PHPUnit\Framework\TestCase;

class acp_controller_test extends TestCase
{
	protected function get_controller(array $config_data = array())
	{
		$defaults = array(
			'patreon_currency'				=> 'USD',
			'patreon_webhook_secret'		=> '',
			'patreon_creator_access_token'	=> '',
		);

		$config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$language = $this->getMockBuilder(\phpbb\language\language::class)->disableOriginalConstructor()->getMock();
		$log = $this->createMock(\phpbb\log\log_interface::class);
		$request = $this->createMock(\phpbb\request\request::class);
		$template = $this->createMock(\phpbb\template\template::class);
		$user = $this->getMockBuilder(\phpbb\user::class)->disableOriginalConstructor()->getMock();
		$api_client = $this->getMockBuilder(\avathar\bbpatreon\service\api_client::class)->disableOriginalConstructor()->getMock();
		$group_mapper = $this->getMockBuilder(\avathar\bbpatreon\service\group_mapper::class)->disableOriginalConstructor()->getMock();

		return new \avathar\bbpatreon\controller\acp_controller(
			$config,
			$db,
			$language,
			$log,
			$request,
			$template,
			$user,
			$api_client,
			$group_mapper,
			'phpbb_patreon_sync',
			'phpbb_patreon_tiers',
			'phpbb_oauth_accounts'
		);
	}

	/**
	 * Helper to call protected format_currency.
	 */
	protected function format_currency($controller, int $cents)
	{
		$method = new \ReflectionMethod($controller, 'format_currency');
		$method->setAccessible(true);
		return $method->invoke($controller, $cents);
	}

	/**
	 * format_currency with USD.
	 */
	public function test_format_currency_usd()
	{
		$controller = $this->get_controller(array('patreon_currency' => 'USD'));

		$this->assertEquals('$5.00', $this->format_currency($controller, 500));
		$this->assertEquals('$0.00', $this->format_currency($controller, 0));
		$this->assertEquals('$10.50', $this->format_currency($controller, 1050));
		$this->assertEquals('$0.01', $this->format_currency($controller, 1));
	}

	/**
	 * format_currency with EUR.
	 */
	public function test_format_currency_eur()
	{
		$controller = $this->get_controller(array('patreon_currency' => 'EUR'));

		$this->assertEquals('€5.00', $this->format_currency($controller, 500));
		$this->assertEquals('€20.00', $this->format_currency($controller, 2000));
	}

	/**
	 * format_currency with GBP.
	 */
	public function test_format_currency_gbp()
	{
		$controller = $this->get_controller(array('patreon_currency' => 'GBP'));

		$this->assertEquals('£5.00', $this->format_currency($controller, 500));
	}

	/**
	 * format_currency with unknown currency falls back to code prefix.
	 */
	public function test_format_currency_unknown()
	{
		$controller = $this->get_controller(array('patreon_currency' => 'ZAR'));

		$this->assertEquals('ZAR 5.00', $this->format_currency($controller, 500));
	}

	/**
	 * format_currency defaults to USD when currency is empty.
	 */
	public function test_format_currency_empty_defaults_to_usd()
	{
		$controller = $this->get_controller(array('patreon_currency' => ''));

		$this->assertEquals('$5.00', $this->format_currency($controller, 500));
	}

	/**
	 * set_page_url stores the URL.
	 */
	public function test_set_page_url()
	{
		$controller = $this->get_controller();
		$controller->set_page_url('https://example.com/acp');

		$ref = new \ReflectionProperty($controller, 'u_action');
		$ref->setAccessible(true);
		$this->assertEquals('https://example.com/acp', $ref->getValue($controller));
	}
}
