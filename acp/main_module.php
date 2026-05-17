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
 * bbPatreon ACP module.
 */
class main_module
{
	public $page_title;
	public $tpl_name;
	public $u_action;

	/**
	 * Main ACP module
	 *
	 * @param int    $id   The module ID
	 * @param string $mode The module mode (for example: settings, bbaccounts_integration)
	 * @throws \Exception
	 */
	public function main($id, $mode)
	{
		global $phpbb_container;

		switch ($mode)
		{
			case 'bbaccounts_integration':
				/** @var \avathar\bbpatreon\controller\bbaccounts_acp_controller $controller */
				$controller = $phpbb_container->get('avathar.bbpatreon.controller.bbaccounts_acp');
				$this->tpl_name   = 'acp_bbpatreon_bbaccounts_integration';
				$this->page_title = 'ACP_BBPATREON_BBACCOUNTS_INTEGRATION';
				$controller->set_page_url($this->u_action);
				$controller->handle();
				break;

			case 'settings':
			default:
				/** @var \avathar\bbpatreon\controller\acp_controller $acp_controller */
				$acp_controller = $phpbb_container->get('avathar.bbpatreon.controller.acp');
				$this->tpl_name   = 'acp_bbpatreon_body';
				$this->page_title = 'ACP_BBPATREON_TITLE';
				$acp_controller->set_page_url($this->u_action);
				$acp_controller->display_options();
				break;
		}
	}
}
