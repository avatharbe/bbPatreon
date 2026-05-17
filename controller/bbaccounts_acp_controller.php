<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * ACP controller for the "bbAccounts Integration" mode — rule CRUD plus
 * the manual "Run credit now" action.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\controller;

class bbaccounts_acp_controller
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\log\log_interface */
	protected $log;
	/** @var \phpbb\request\request_interface */
	protected $request;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\user */
	protected $user;
	/** @var \avathar\bbpatreon\service\bbaccounts_recorder */
	protected $recorder;
	/** @var string */
	protected $rules_table;
	/** @var string */
	protected $table_prefix;
	/** @var string */
	protected $u_action;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\log\log_interface $log,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\avathar\bbpatreon\service\bbaccounts_recorder $recorder,
		string $rules_table,
		string $table_prefix
	)
	{
		$this->db           = $db;
		$this->language     = $language;
		$this->log          = $log;
		$this->request      = $request;
		$this->template     = $template;
		$this->user         = $user;
		$this->recorder     = $recorder;
		$this->rules_table  = $rules_table;
		$this->table_prefix = $table_prefix;
	}

	public function set_page_url(string $u_action): void
	{
		$this->u_action = $u_action;
	}

	public function handle(): void
	{
		$this->language->add_lang('info_acp_bbaccounts_integration', 'avathar/bbpatreon');

		if (!$this->recorder->is_available())
		{
			$this->template->assign_var('S_BBACCOUNTS_MISSING', true);
			return;
		}

		$action = $this->request->variable('action', '');

		switch ($action)
		{
			case 'add':
			case 'edit':
				$this->render_form($action);
				break;
			case 'save':
				$this->save_rule();
				$this->render_list();
				break;
			case 'toggle':
				$this->toggle_rule();
				$this->render_list();
				break;
			case 'delete':
				$this->delete_rule();
				$this->render_list();
				break;
			case 'run_credit':
				$this->run_credit();
				$this->render_list();
				break;
			default:
				$this->render_list();
				break;
		}
	}

	protected function render_list(): void
	{
		$sql = 'SELECT r.rule_id, r.rule_label, r.expense_account_id, r.wallet_account_id, r.amount_per_dollar, r.is_active, r.rule_order,
				ae.account_code AS expense_code, ae.account_name AS expense_name,
				aw.account_code AS wallet_code,  aw.account_name AS wallet_name,
				ae.currency_code AS expense_currency
			FROM ' . $this->rules_table . ' r
			LEFT JOIN ' . $this->table_prefix . 'bbaccounts_accounts ae ON ae.account_id = r.expense_account_id
			LEFT JOIN ' . $this->table_prefix . 'bbaccounts_accounts aw ON aw.account_id = r.wallet_account_id
			ORDER BY r.rule_order ASC, r.rule_id ASC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$this->template->assign_block_vars('rules', [
				'RULE_ID'    => (int) $row['rule_id'],
				'LABEL'      => $row['rule_label'],
				'EXPENSE'    => sprintf('%s — %s', $row['expense_code'] ?? '?', $row['expense_name'] ?? '(deleted)'),
				'WALLET'     => sprintf('%s — %s', $row['wallet_code'] ?? '?', $row['wallet_name'] ?? '(deleted)'),
				'CURRENCY'   => $row['expense_currency'] ?? '',
				'RATE'       => $row['amount_per_dollar'],
				'IS_ACTIVE'  => (bool) $row['is_active'],
				'U_EDIT'     => $this->u_action . '&action=edit&rule_id=' . (int) $row['rule_id'],
				'U_TOGGLE'   => $this->u_action . '&action=toggle&rule_id=' . (int) $row['rule_id'] . '&hash=' . generate_link_hash('toggle_rule'),
				'U_DELETE'   => $this->u_action . '&action=delete&rule_id=' . (int) $row['rule_id'] . '&hash=' . generate_link_hash('delete_rule'),
			]);
		}
		$this->db->sql_freeresult($result);

		add_form_key('bbpatreon_run_credit');
		$this->template->assign_vars([
			'U_ACTION'          => $this->u_action,
			'U_ADD'             => $this->u_action . '&action=add',
			'RUN_CREDIT_PERIOD' => gmdate('Y-m'),
			'S_RUN_CREDIT_FORM' => true,
		]);
	}

	protected function render_form(string $action): void
	{
		$rule_id = $this->request->variable('rule_id', 0);
		$rule = [
			'rule_id'            => 0,
			'rule_label'         => '',
			'expense_account_id' => 0,
			'wallet_account_id'  => 0,
			'amount_per_dollar'  => '0.00',
			'is_active'          => 1,
			'rule_order'         => 0,
		];
		if ($action === 'edit' && $rule_id)
		{
			$sql = 'SELECT * FROM ' . $this->rules_table . ' WHERE rule_id = ' . (int) $rule_id;
			$result = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			if ($row)
			{
				$rule = $row;
			}
		}

		add_form_key('bbpatreon_rule_form');

		$sql = 'SELECT account_id, account_code, account_name, account_type, currency_code, subledger_type, is_active
			FROM ' . $this->table_prefix . "bbaccounts_accounts
			WHERE is_active = 1
			ORDER BY currency_code ASC, account_code ASC";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			if ($row['account_type'] === 'expense')
			{
				$this->template->assign_block_vars('expense_options', [
					'ID'       => (int) $row['account_id'],
					'LABEL'    => sprintf('%s — %s [%s]', $row['account_code'], $row['account_name'], $row['currency_code']),
					'SELECTED' => (int) $row['account_id'] === (int) $rule['expense_account_id'],
				]);
			}
			if ($row['subledger_type'] === 'customer')
			{
				$this->template->assign_block_vars('wallet_options', [
					'ID'       => (int) $row['account_id'],
					'LABEL'    => sprintf('%s — %s [%s]', $row['account_code'], $row['account_name'], $row['currency_code']),
					'SELECTED' => (int) $row['account_id'] === (int) $rule['wallet_account_id'],
				]);
			}
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'S_FORM'            => true,
			'S_EDIT'            => $action === 'edit',
			'RULE_ID'           => (int) $rule['rule_id'],
			'RULE_LABEL'        => $rule['rule_label'],
			'AMOUNT_PER_DOLLAR' => $rule['amount_per_dollar'],
			'IS_ACTIVE'         => (bool) $rule['is_active'],
			'U_FORM_ACTION'     => $this->u_action . '&action=save',
			'U_BACK'            => $this->u_action,
		]);
	}

	protected function save_rule(): void
	{
		if (!check_form_key('bbpatreon_rule_form'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$rule_id            = $this->request->variable('rule_id', 0);
		$rule_label         = trim($this->request->variable('rule_label', '', true));
		$expense_account_id = $this->request->variable('expense_account_id', 0);
		$wallet_account_id  = $this->request->variable('wallet_account_id', 0);
		$amount_per_dollar  = $this->request->variable('amount_per_dollar', '0.00');
		$is_active          = $this->request->variable('is_active', 0) ? 1 : 0;
		$rule_order         = $this->request->variable('rule_order', 0);

		$errors = $this->validate_rule($rule_label, $expense_account_id, $wallet_account_id, $amount_per_dollar);
		if (!empty($errors))
		{
			$back = $this->u_action . '&action=' . ($rule_id ? 'edit&rule_id=' . $rule_id : 'add');
			trigger_error(implode('<br>', $errors) . adm_back_link($back), E_USER_WARNING);
		}

		$data = [
			'rule_label'         => $rule_label,
			'expense_account_id' => (int) $expense_account_id,
			'wallet_account_id'  => (int) $wallet_account_id,
			'amount_per_dollar'  => $amount_per_dollar,
			'is_active'          => $is_active,
			'rule_order'         => (int) $rule_order,
		];

		if ($rule_id)
		{
			$sql = 'UPDATE ' . $this->rules_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $data) . '
				WHERE rule_id = ' . (int) $rule_id;
			$this->db->sql_query($sql);
		}
		else
		{
			$sql = 'INSERT INTO ' . $this->rules_table . ' ' . $this->db->sql_build_array('INSERT', $data);
			$this->db->sql_query($sql);
		}
	}

	protected function validate_rule(string $label, int $expense_id, int $wallet_id, string $rate): array
	{
		$errors = [];
		if ($label === '')
		{
			$errors[] = 'Label cannot be empty.';
		}
		if ((float) $rate <= 0)
		{
			$errors[] = 'Amount per dollar must be greater than zero.';
		}

		$sql = 'SELECT account_id, account_type, subledger_type, currency_code, is_active
			FROM ' . $this->table_prefix . 'bbaccounts_accounts
			WHERE account_id IN (' . (int) $expense_id . ', ' . (int) $wallet_id . ')';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);

		$by_id = [];
		foreach ($rows as $r)
		{
			$by_id[(int) $r['account_id']] = $r;
		}

		if (!isset($by_id[$expense_id]))
		{
			$errors[] = 'Expense account not found.';
		}
		else
		{
			if ($by_id[$expense_id]['account_type'] !== 'expense')
			{
				$errors[] = 'Selected expense account is not of type expense.';
			}
			if (!$by_id[$expense_id]['is_active'])
			{
				$errors[] = 'Selected expense account is inactive.';
			}
		}
		if (!isset($by_id[$wallet_id]))
		{
			$errors[] = 'Wallet account not found.';
		}
		else
		{
			if ($by_id[$wallet_id]['subledger_type'] !== 'customer')
			{
				$errors[] = 'Selected wallet account does not use customer subledger.';
			}
			if (!$by_id[$wallet_id]['is_active'])
			{
				$errors[] = 'Selected wallet account is inactive.';
			}
		}
		if (isset($by_id[$expense_id], $by_id[$wallet_id]) && $by_id[$expense_id]['currency_code'] !== $by_id[$wallet_id]['currency_code'])
		{
			$errors[] = sprintf('Expense and wallet accounts must share the same currency pool (got %s vs %s).', $by_id[$expense_id]['currency_code'], $by_id[$wallet_id]['currency_code']);
		}
		return $errors;
	}

	protected function toggle_rule(): void
	{
		$rule_id = $this->request->variable('rule_id', 0);
		$hash    = $this->request->variable('hash', '');
		if (!$rule_id || !check_link_hash($hash, 'toggle_rule'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}
		$sql = 'UPDATE ' . $this->rules_table . ' SET is_active = 1 - is_active WHERE rule_id = ' . (int) $rule_id;
		$this->db->sql_query($sql);
	}

	protected function delete_rule(): void
	{
		$rule_id = $this->request->variable('rule_id', 0);
		$hash    = $this->request->variable('hash', '');
		if (!$rule_id || !check_link_hash($hash, 'delete_rule'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}
		$sql = 'DELETE FROM ' . $this->rules_table . ' WHERE rule_id = ' . (int) $rule_id;
		$this->db->sql_query($sql);
	}

	protected function run_credit(): void
	{
		if (!check_form_key('bbpatreon_run_credit'))
		{
			trigger_error($this->language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}
		$period = $this->request->variable('period', gmdate('Y-m'));
		if (!preg_match('/^\d{4}-\d{2}$/', $period))
		{
			trigger_error('Invalid period format.' . adm_back_link($this->u_action), E_USER_WARNING);
		}
		$result = $this->recorder->credit_active_patrons_for_period($period);
		$this->log->add('admin', (int) $this->user->data['user_id'], $this->user->ip, 'LOG_BBPATREON_CREDIT_RUN_MANUAL', false, [$period, (int) $result['credited'], (int) $result['skipped_already_credited']]);

		$this->template->assign_vars([
			'S_RUN_RESULT' => true,
			'RUN_PERIOD'   => $period,
			'RUN_CREDITED' => (int) $result['credited'],
			'RUN_SKIPPED'  => (int) $result['skipped_already_credited'],
			'RUN_ERRORS'   => count($result['errors']),
		]);
	}
}
