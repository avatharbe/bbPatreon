<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026, Sajaki, https://www.avathar.be/forum
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\ucp;

/**
 * bbPatreon UCP module info.
 */
class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\avathar\bbpatreon\ucp\main_module',
			'title'		=> 'UCP_BBPATREON_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'UCP_BBPATREON',
					'auth'	=> 'ext_avathar/bbpatreon',
					'cat'	=> ['UCP_BBPATREON_TITLE'],
				],
			],
		];
	}
}
