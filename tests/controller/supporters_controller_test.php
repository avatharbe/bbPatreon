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
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $template;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $patron_data_provider;

	protected function get_controller(array $config_data = array())
	{
		$defaults = array(
			'patreon_supporters_page_enabled' => 0,
		);

		$config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$language = $this->getMockBuilder(\phpbb\language\language::class)
			->disableOriginalConstructor()
			->getMock();
		$this->template = $this->createMock(\phpbb\template\template::class);
		$helper = $this->getMockBuilder(\phpbb\controller\helper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->patron_data_provider = $this->getMockBuilder(\avathar\bbpatreon\service\patron_data_provider::class)
			->disableOriginalConstructor()
			->getMock();

		return new \avathar\bbpatreon\controller\supporters_controller(
			$config,
			$language,
			$this->template,
			$helper,
			$this->patron_data_provider
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

	/**
	 * handle() must never call the data provider when disabled — no
	 * point querying data for a page that 404s anyway.
	 */
	public function test_handle_skips_provider_when_disabled()
	{
		$controller = $this->get_controller();

		$this->patron_data_provider->expects($this->never())
			->method('get_public_supporters');

		try
		{
			$controller->handle();
		}
		catch (\phpbb\exception\http_exception $e)
		{
			// expected
		}
	}

	/**
	 * handle() assigns one supporters block row per entry returned by
	 * the provider, mapping its array keys onto the expected template
	 * var names, and reports the correct total count.
	 */
	public function test_handle_assigns_supporters_from_provider()
	{
		$controller = $this->get_controller(array('patreon_supporters_page_enabled' => 1));

		$this->patron_data_provider->method('get_public_supporters')->willReturn([
			[
				'user_id'		=> 5,
				'username'		=> '<a>Alice</a>',
				'avatar'		=> '<img>',
				'tier_label'	=> 'Gold',
				'group_name'	=> 'VIP',
				'rank_title'	=> 'Legend',
				'pledge_amount'	=> '$5.00',
			],
		]);

		$this->template->expects($this->once())
			->method('assign_block_vars')
			->with('supporters', [
				'AVATAR'		=> '<img>',
				'USERNAME'		=> '<a>Alice</a>',
				'TIER_LABEL'	=> 'Gold',
				'GROUP_NAME'	=> 'VIP',
				'RANK_TITLE'	=> 'Legend',
				'PLEDGE_AMOUNT'	=> '$5.00',
			]);
		$this->template->expects($this->once())
			->method('assign_vars')
			->with($this->callback(function ($vars) {
				return $vars['S_HAS_SUPPORTERS'] === true && $vars['TOTAL_SUPPORTERS'] === 1;
			}));

		$controller->handle();
	}
}
