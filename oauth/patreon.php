<?php
/**
 *
 * Patreon Integration for phpBB.
 * PHPoAuthLib service class for Patreon OAuth2.
 *
 * @copyright (c) 2024 Sajaki
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\oauth;

use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth2\Service\AbstractService;
use OAuth\OAuth2\Token\StdOAuth2Token;

class patreon extends AbstractService
{
	const SCOPE_IDENTITY = 'identity';
	const SCOPE_IDENTITY_EMAIL = 'identity[email]';
	const SCOPE_CAMPAIGNS = 'campaigns';
	const SCOPE_CAMPAIGNS_MEMBERS = 'campaigns.members';

	public function __construct(
		CredentialsInterface $credentials,
		ClientInterface $httpClient,
		TokenStorageInterface $storage,
		$scopes = [],
		?UriInterface $baseApiUri = null
	)
	{
		parent::__construct($credentials, $httpClient, $storage, $scopes, $baseApiUri, true);

		if (null === $baseApiUri)
		{
			$this->baseApiUri = new Uri('https://www.patreon.com/api/oauth2/v2/');
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAuthorizationEndpoint()
	{
		return new Uri('https://www.patreon.com/oauth2/authorize');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAccessTokenEndpoint()
	{
		return new Uri('https://www.patreon.com/api/oauth2/token');
	}

	/**
	 * {@inheritdoc}
	 */
	protected function parseAccessTokenResponse($responseBody)
	{
		$data = json_decode($responseBody, true);

		if (null === $data || !is_array($data))
		{
			throw new TokenResponseException('Unable to parse response.');
		}

		if (isset($data['error']))
		{
			throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
		}

		$token = new StdOAuth2Token();
		$token->setAccessToken($data['access_token']);

		if (isset($data['expires_in']))
		{
			$token->setLifetime($data['expires_in']);
		}

		if (isset($data['refresh_token']))
		{
			$token->setRefreshToken($data['refresh_token']);
			unset($data['refresh_token']);
		}

		unset($data['access_token'], $data['expires_in']);

		$token->setExtraParams($data);

		return $token;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getAuthorizationMethod()
	{
		return static::AUTHORIZATION_METHOD_HEADER_BEARER;
	}
}
