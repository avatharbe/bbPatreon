<?php
/**
 *
 * Patreon Integration for phpBB.
 * Tests the api_client service's error-handling paths that don't require
 * a live Patreon connection.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\service;

/**
 * Unit tests for \avathar\bbpatreon\service\api_client.
 *
 * The api_client wraps curl calls to the Patreon API v2. Since we cannot
 * make real HTTP requests in CI, these tests cover the early-return guard
 * clauses that fire before any curl call is made. They ensure the client
 * fails gracefully when required config values are missing, rather than
 * making a doomed API call that would log confusing errors.
 */
class api_client_test extends \phpbb_test_case
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $log;

	public function setUp(): void
	{
		parent::setUp();

		$this->log = $this->createMock('\phpbb\log\log_interface');
	}

	/**
	 * Helper: build an api_client with the given config overrides.
	 * Defaults have all tokens empty to simulate a fresh install.
	 */
	protected function get_client(array $config_data = array())
	{
		$defaults = array(
			'patreon_creator_access_token'	=> '',
			'patreon_creator_refresh_token'	=> '',
			'patreon_client_id'				=> 'test_client_id',
			'patreon_client_secret'			=> 'test_client_secret',
			'patreon_campaign_id'			=> '12345',
		);

		$this->config = new \phpbb\config\config(array_merge($defaults, $config_data));

		return new \avathar\bbpatreon\service\api_client($this->config, $this->log);
	}

	/**
	 * request() must return an error array (not throw) when no creator
	 * access token is configured. This prevents a curl call with an empty
	 * Authorization header, which Patreon would reject with 401.
	 */
	public function test_request_returns_error_when_no_token()
	{
		$client = $this->get_client();

		$result = $client->request('campaigns');

		$this->assertArrayHasKey('error', $result);
		$this->assertStringContainsString('No creator access token', $result['error']);
	}

	/**
	 * A relative URL path should be prepended with the base API URL.
	 * Verify that the guard clause fires before curl, regardless of URL format.
	 */
	public function test_request_relative_url_returns_error_when_no_token()
	{
		$client = $this->get_client();

		$result = $client->request('campaigns/12345/members');

		$this->assertArrayHasKey('error', $result);
	}

	/**
	 * An absolute URL (starting with https://) must be used as-is.
	 * The guard clause should still fire when the token is empty.
	 */
	public function test_request_absolute_url_returns_error_when_no_token()
	{
		$client = $this->get_client();

		$result = $client->request('https://www.patreon.com/api/oauth2/v2/campaigns');

		$this->assertArrayHasKey('error', $result);
	}

	/**
	 * get_campaign_members() must return an empty array when no campaign
	 * ID is set. Without a campaign ID, the API URL would be malformed
	 * (/campaigns//members) and Patreon would return 404.
	 */
	public function test_get_campaign_members_returns_empty_when_no_campaign()
	{
		$client = $this->get_client(array('patreon_campaign_id' => ''));

		$result = $client->get_campaign_members();

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * refresh_token() must return false when no refresh token is stored.
	 * This happens on a fresh install before the admin enters creator
	 * tokens. The caller (request()) checks this return value and stops
	 * retrying, avoiding an infinite loop of failed refresh attempts.
	 */
	public function test_refresh_token_returns_false_when_no_refresh_token()
	{
		$client = $this->get_client();

		$this->assertFalse($client->refresh_token());
	}

	/**
	 * register_webhook() delegates to request() with POST method.
	 * When no token is configured, it must return the token error.
	 */
	public function test_register_webhook_returns_error_when_no_token()
	{
		$client = $this->get_client();

		$result = $client->register_webhook('https://example.com/webhook');

		$this->assertArrayHasKey('error', $result);
		$this->assertStringContainsString('No creator access token', $result['error']);
	}

	/**
	 * The request() return type is always array, even on error.
	 * Callers check for the 'error' key to detect failures.
	 */
	public function test_request_always_returns_array()
	{
		$client = $this->get_client();

		$result = $client->request('any-endpoint');

		$this->assertIsArray($result);
	}

	/**
	 * get_campaign_members() must return array type even when
	 * campaign_id is set but token is missing (request will fail).
	 */
	public function test_get_campaign_members_returns_array_on_request_error()
	{
		$client = $this->get_client(array(
			'patreon_campaign_id'			=> '12345',
			'patreon_creator_access_token'	=> '',
		));

		$result = $client->get_campaign_members();

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}
}
