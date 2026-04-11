<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the webhook controller's handle() method.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

class webhook_controller_test extends TestCase
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $log;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $request;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $group_mapper;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $dispatcher;

	protected function get_controller(array $config_data = array())
	{
		$defaults = array(
			'patreon_webhook_secret'		=> 'test_secret',
			'patreon_grace_period_days'		=> 0,
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$this->db = $this->createMock(\phpbb\db\driver\driver_interface::class);
		$this->log = $this->createMock(\phpbb\log\log_interface::class);
		$this->request = $this->createMock(\phpbb\request\request::class);
		$this->group_mapper = $this->getMockBuilder(\avathar\bbpatreon\service\group_mapper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->dispatcher = $this->createMock(\phpbb\event\dispatcher_interface::class);
		$this->dispatcher->method('trigger_event')->willReturnArgument(1);

		$this->db->method('sql_query')->willReturn(true);
		$this->db->method('sql_fetchrow')->willReturn(false);
		$this->db->method('sql_freeresult')->willReturn(null);
		$this->db->method('sql_escape')->willReturnArgument(0);
		$this->db->method('sql_build_array')->willReturn("'dummy'");

		return new \avathar\bbpatreon\controller\webhook(
			$this->config,
			$this->db,
			$this->log,
			$this->request,
			$this->group_mapper,
			$this->dispatcher,
			'phpbb_patreon_sync',
			'phpbb_patreon_tiers',
			'phpbb_oauth_accounts'
		);
	}

	/**
	 * When no webhook secret is configured, should log and return ok.
	 */
	public function test_handle_no_secret_configured()
	{
		$controller = $this->get_controller(array('patreon_webhook_secret' => ''));

		$this->request->method('server')->willReturn('');

		$this->log->expects($this->once())
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_WEBHOOK_NO_SIGNATURE');

		$response = $controller->handle();

		$this->assertInstanceOf(JsonResponse::class, $response);
	}

	/**
	 * When no signature header is sent, should log and return ok.
	 */
	public function test_handle_no_signature_header()
	{
		$controller = $this->get_controller();

		$this->request->method('server')->willReturnMap(array(
			array('HTTP_X_PATREON_SIGNATURE', '', ''),
			array('HTTP_X_PATREON_EVENT', '', ''),
		));

		$this->log->expects($this->once())
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_WEBHOOK_NO_SIGNATURE');

		$response = $controller->handle();

		$this->assertInstanceOf(JsonResponse::class, $response);
	}

	/**
	 * When the signature doesn't match, should log bad signature.
	 */
	public function test_handle_bad_signature()
	{
		$controller = $this->get_controller();

		$this->request->method('server')->willReturnMap(array(
			array('HTTP_X_PATREON_SIGNATURE', '', 'bad_signature'),
			array('HTTP_X_PATREON_EVENT', '', 'members:pledge:create'),
		));

		$this->log->expects($this->once())
			->method('add')
			->with('admin', $this->anything(), '', 'LOG_PATREON_WEBHOOK_BAD_SIGNATURE');

		$response = $controller->handle();

		$this->assertInstanceOf(JsonResponse::class, $response);
	}

	/**
	 * Response must always have 200 status and JSON content type.
	 */
	public function test_handle_returns_json_response()
	{
		$controller = $this->get_controller(array('patreon_webhook_secret' => ''));
		$this->request->method('server')->willReturn('');

		$response = $controller->handle();

		$this->assertEquals(200, $response->getStatusCode());
		$this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
	}
}
