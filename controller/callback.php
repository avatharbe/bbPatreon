<?php
/**
 *
 * Patreon Integration for phpBB.
 * OAuth callback controller — Patreon redirects here after authorization.
 * Forwards the code to the UCP module for processing.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

class callback
{
	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	public function __construct(
		\phpbb\request\request $request,
		\phpbb\user $user
	)
	{
		$this->request	= $request;
		$this->user		= $user;
	}

	/**
	 * Handle the OAuth callback from Patreon.
	 * Redirects to the UCP module with the authorization code.
	 */
	public function handle(): RedirectResponse
	{
		$code = $this->request->variable('code', '');
		$state = $this->request->variable('state', '');

		// Build UCP URL with the code parameter so the UCP controller can process it
		$ucp_url = generate_board_url() . '/ucp.php?' . http_build_query([
			'i'		=> '-avathar-bbpatreon-ucp-main_module',
			'mode'	=> 'settings',
			'code'	=> $code,
			'state'	=> $state,
		]);

		return new RedirectResponse($ucp_url);
	}
}
