<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the PHPoAuthLib Patreon service adapter.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\oauth;

use PHPUnit\Framework\TestCase;
use OAuth\Common\Http\Exception\TokenResponseException;

class patreon_test extends TestCase
{
	protected function get_service()
	{
		$credentials = $this->createMock(\OAuth\Common\Consumer\CredentialsInterface::class);
		$credentials->method('getConsumerId')->willReturn('test_id');
		$credentials->method('getConsumerSecret')->willReturn('test_secret');
		$credentials->method('getCallbackUrl')->willReturn('https://example.com/callback');

		$httpClient = $this->createMock(\OAuth\Common\Http\Client\ClientInterface::class);
		$storage = $this->createMock(\OAuth\Common\Storage\TokenStorageInterface::class);

		return new \avathar\bbpatreon\oauth\patreon(
			$credentials,
			$httpClient,
			$storage,
			array('identity')
		);
	}

	/**
	 * Authorization endpoint must point to Patreon's OAuth URL.
	 */
	public function test_authorization_endpoint()
	{
		$service = $this->get_service();

		$endpoint = $service->getAuthorizationEndpoint();

		$this->assertStringContainsString('patreon.com/oauth2/authorize', (string) $endpoint);
	}

	/**
	 * Access token endpoint must point to Patreon's token URL.
	 */
	public function test_access_token_endpoint()
	{
		$service = $this->get_service();

		$endpoint = $service->getAccessTokenEndpoint();

		$this->assertStringContainsString('patreon.com/api/oauth2/token', (string) $endpoint);
	}

	/**
	 * Scope constants must match Patreon API v2 scope values.
	 */
	public function test_scope_constants()
	{
		$this->assertEquals('identity', \avathar\bbpatreon\oauth\patreon::SCOPE_IDENTITY);
		$this->assertEquals('identity[email]', \avathar\bbpatreon\oauth\patreon::SCOPE_IDENTITY_EMAIL);
		$this->assertEquals('campaigns', \avathar\bbpatreon\oauth\patreon::SCOPE_CAMPAIGNS);
		$this->assertEquals('campaigns.members', \avathar\bbpatreon\oauth\patreon::SCOPE_CAMPAIGNS_MEMBERS);
	}

	/**
	 * parseAccessTokenResponse with valid JSON should return a token.
	 */
	public function test_parse_valid_token_response()
	{
		$service = $this->get_service();

		$response = json_encode(array(
			'access_token'	=> 'test_access_token',
			'refresh_token'	=> 'test_refresh_token',
			'expires_in'	=> 2678400,
			'scope'			=> 'identity campaigns',
			'token_type'	=> 'Bearer',
		));

		// parseAccessTokenResponse is protected, use reflection
		$method = new \ReflectionMethod($service, 'parseAccessTokenResponse');
		$method->setAccessible(true);

		$token = $method->invoke($service, $response);

		$this->assertEquals('test_access_token', $token->getAccessToken());
		$this->assertEquals('test_refresh_token', $token->getRefreshToken());
	}

	/**
	 * parseAccessTokenResponse with invalid JSON should throw.
	 */
	public function test_parse_invalid_json_throws()
	{
		$service = $this->get_service();

		$method = new \ReflectionMethod($service, 'parseAccessTokenResponse');
		$method->setAccessible(true);

		$this->expectException(TokenResponseException::class);
		$method->invoke($service, 'not valid json');
	}

	/**
	 * parseAccessTokenResponse with error in response should throw.
	 */
	public function test_parse_error_response_throws()
	{
		$service = $this->get_service();

		$method = new \ReflectionMethod($service, 'parseAccessTokenResponse');
		$method->setAccessible(true);

		$this->expectException(TokenResponseException::class);
		$method->invoke($service, json_encode(array('error' => 'invalid_grant')));
	}

	/**
	 * Token response without refresh_token should still work.
	 */
	public function test_parse_response_without_refresh_token()
	{
		$service = $this->get_service();

		$method = new \ReflectionMethod($service, 'parseAccessTokenResponse');
		$method->setAccessible(true);

		$token = $method->invoke($service, json_encode(array(
			'access_token' => 'access_only',
		)));

		$this->assertEquals('access_only', $token->getAccessToken());
	}

	/**
	 * Token response without expires_in should still work.
	 */
	public function test_parse_response_without_expires()
	{
		$service = $this->get_service();

		$method = new \ReflectionMethod($service, 'parseAccessTokenResponse');
		$method->setAccessible(true);

		$token = $method->invoke($service, json_encode(array(
			'access_token'	=> 'access_token',
			'refresh_token'	=> 'refresh_token',
		)));

		$this->assertEquals('access_token', $token->getAccessToken());
	}
}
