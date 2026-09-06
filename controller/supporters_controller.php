<?php
/**
 *
 * Patreon Integration for phpBB.
 * Public supporters page controller.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

class supporters_controller
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \avathar\bbpatreon\service\patron_data_provider */
	protected $patron_data_provider;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\controller\helper $helper,
		\avathar\bbpatreon\service\patron_data_provider $patron_data_provider
	)
	{
		$this->config				= $config;
		$this->language				= $language;
		$this->template				= $template;
		$this->helper				= $helper;
		$this->patron_data_provider	= $patron_data_provider;
	}

	/**
	 * Display the public supporters page.
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function handle()
	{
		if (empty($this->config['patreon_supporters_page_enabled']))
		{
			throw new \phpbb\exception\http_exception(404, 'NO_PAGE_FOUND');
		}

		$this->language->add_lang('common', 'avathar/bbpatreon');

		$supporters = $this->patron_data_provider->get_public_supporters();

		foreach ($supporters as $supporter)
		{
			$this->template->assign_block_vars('supporters', [
				'AVATAR'		=> $supporter['avatar'],
				'USERNAME'		=> $supporter['username'],
				'TIER_LABEL'	=> $supporter['tier_label'],
				'GROUP_NAME'	=> $supporter['group_name'],
				'RANK_TITLE'	=> $supporter['rank_title'],
				'PLEDGE_AMOUNT'	=> $supporter['pledge_amount'],
			]);
		}

		$this->template->assign_vars([
			'S_HAS_SUPPORTERS'		=> !empty($supporters),
			'S_SHOW_AMOUNTS'		=> !empty($this->config['patreon_supporters_show_amounts']),
			'TOTAL_SUPPORTERS'		=> count($supporters),
		]);

		return $this->helper->render('supporters_body.html', $this->language->lang('PATREON_SUPPORTERS_TITLE'));
	}
}
