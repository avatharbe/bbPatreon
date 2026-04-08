<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Sajaki, https://www.avathar.be/forum
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\acp;

/**
 * bbPatreon ACP module info.
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\avathar\bbpatreon\acp\main_module',
			'title'		=> 'ACP_BBPATREON_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_BBPATREON',
					'auth'	=> 'ext_avathar/bbpatreon && acl_a_board',
					'cat'	=> ['ACP_BBPATREON_TITLE'],
				],
			],
		];
	}
}
