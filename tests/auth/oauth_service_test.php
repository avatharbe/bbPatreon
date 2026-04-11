<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the phpBB OAuth service provider for Patreon.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\auth;

use PHPUnit\Framework\TestCase;

class oauth_service_test extends TestCase
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $request;

	protected function get_service(array $config_data = array())
	{
		$defaults = array(
			'auth_oauth_patreon_key'	=> 'test_client_id',
			'auth_oauth_patreon_secret'	=> 'test_client_secret',
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));
		$this->request = $this->createMock(\phpbb\request\request_interface::class);

		return new \avathar\bbpatreon\auth\provider\oauth\service\patreon(
			$this->config,
			$this->request
		);
	}

	/**
	 * Auth scope must include identity and campaigns.
	 */
	public function test_get_auth_scope()
	{
		$service = $this->get_service();

		$scopes = $service->get_auth_scope();

		$this->assertContains('identity', $scopes);
		$this->assertContains('identity[email]', $scopes);
		$this->assertContains('campaigns', $scopes);
		$this->assertContains('campaigns.members', $scopes);
	}

	/**
	 * Service credentials must come from config.
	 */
	public function test_get_service_credentials()
	{
		$service = $this->get_service();

		$credentials = $service->get_service_credentials();

		$this->assertEquals('test_client_id', $credentials['key']);
		$this->assertEquals('test_client_secret', $credentials['secret']);
	}

	/**
	 * Credentials should be empty strings when not configured.
	 */
	public function test_get_service_credentials_empty()
	{
		$service = $this->get_service(array(
			'auth_oauth_patreon_key'	=> '',
			'auth_oauth_patreon_secret'	=> '',
		));

		$credentials = $service->get_service_credentials();

		$this->assertEquals('', $credentials['key']);
		$this->assertEquals('', $credentials['secret']);
	}

	/**
	 * External service class must point to the OAuth library adapter.
	 */
	public function test_get_external_service_class()
	{
		$service = $this->get_service();

		$this->assertEquals(
			'\\avathar\\bbpatreon\\oauth\\patreon',
			$service->get_external_service_class()
		);
	}
}
