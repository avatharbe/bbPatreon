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
 * bbPatreon UCP module.
 */
class main_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	/**
	 * Main UCP module
	 *
	 * @param int    $id   The module ID
	 * @param string $mode The module mode (for example: manage or settings)
	 * @throws \Exception
	 */
	public function main($id, $mode)
	{
		global $phpbb_container;

		/** @var \avathar\bbpatreon\controller\ucp_controller $ucp_controller */
		$ucp_controller = $phpbb_container->get('avathar.bbpatreon.controller.ucp');

		// Load a template for our UCP page
		$this->tpl_name = 'ucp_bbpatreon_body';

		// Set the page title for our UCP page
		$this->page_title = 'UCP_BBPATREON_TITLE';

		// Make the $u_action url available in our UCP controller
		$ucp_controller->set_page_url($this->u_action);

		// Load the display options handle in our UCP controller
		$ucp_controller->display_options();
	}
}
