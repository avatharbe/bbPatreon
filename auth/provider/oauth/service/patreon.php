<?php
/**
 *
 * Patreon Integration for phpBB.
 * phpBB OAuth service for Patreon.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\auth\provider\oauth\service;

use phpbb\auth\provider\oauth\service\exception;

class patreon extends \phpbb\auth\provider\oauth\service\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config				$config		Config object
	 * @param \phpbb\request\request_interface	$request	Request object
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\request\request_interface $request)
	{
		$this->config	= $config;
		$this->request	= $request;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_auth_scope()
	{
		return [
			'identity',
			'identity[email]',
			'campaigns',
			'campaigns.members',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_service_credentials()
	{
		return [
			'key'		=> $this->config['auth_oauth_patreon_key'],
			'secret'	=> $this->config['auth_oauth_patreon_secret'],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_external_service_class()
	{
		return '\\avathar\\bbpatreon\\oauth\\patreon';
	}

	/**
	 * {@inheritdoc}
	 */
	public function perform_auth_login()
	{
		if (!($this->service_provider instanceof \avathar\bbpatreon\oauth\patreon))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_INVALID_SERVICE_TYPE');
		}

		try
		{
			$this->service_provider->requestAccessToken($this->request->variable('code', ''));
		}
		catch (\OAuth\Common\Http\Exception\TokenResponseException $e)
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		try
		{
			$result = json_decode($this->service_provider->request(
				'https://www.patreon.com/api/oauth2/v2/identity?fields[user]=email,full_name'
			), true);
		}
		catch (\OAuth\Common\Exception\Exception $e)
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		if (!isset($result['data']['id']))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		return $result['data']['id'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function perform_token_auth()
	{
		if (!($this->service_provider instanceof \avathar\bbpatreon\oauth\patreon))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_INVALID_SERVICE_TYPE');
		}

		try
		{
			$result = json_decode($this->service_provider->request(
				'https://www.patreon.com/api/oauth2/v2/identity?fields[user]=email,full_name'
			), true);
		}
		catch (\OAuth\Common\Exception\Exception $e)
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		if (!isset($result['data']['id']))
		{
			throw new exception('AUTH_PROVIDER_OAUTH_ERROR_REQUEST');
		}

		return $result['data']['id'];
	}
}
