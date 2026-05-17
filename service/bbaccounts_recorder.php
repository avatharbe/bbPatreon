<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Posts journal entries to bbAccounts for active-pledge patrons.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\service;

/**
 * Posts one journal entry per active-pledge patron per active rule per
 * calendar month. Idempotency follows the outbox pattern: bbpatreon
 * owns a local credit_log table with UNIQUE KEY (rule_id, user_id,
 * period) and a journal_id back-link to the bbAccounts entry. Before
 * posting a journal, the recorder checks credit_log; on successful
 * create_entry it writes the credit_log row. A second run for the
 * same (rule, patron, period) finds the row and short-circuits.
 * Soft-coupled: when the bbAccounts ledger service is absent, every
 * method short-circuits.
 */
class bbaccounts_recorder
{
	/** @var object|null Nullable; duck-typed against ledger->create_entry */
	protected $ledger;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var string */
	protected $table_prefix;

	/** @var string */
	protected $oauth_accounts_table;

	/** @var string */
	protected $credit_log_table;

	public function __construct(
		$ledger,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		string $table_prefix,
		string $oauth_accounts_table,
		string $credit_log_table
	)
	{
		$this->ledger               = $ledger;
		$this->db                   = $db;
		$this->log                  = $log;
		$this->table_prefix         = $table_prefix;
		$this->oauth_accounts_table = $oauth_accounts_table;
		$this->credit_log_table     = $credit_log_table;
	}

	public function is_available(): bool
	{
		return $this->ledger !== null;
	}

	/**
	 * Credit every active-pledge patron for the given period (YYYY-MM).
	 * Returns counts: credited / skipped_already_credited / skipped_no_rules / errors[].
	 */
	public function credit_active_patrons_for_period(string $period_ymd): array
	{
		$result = ['credited' => 0, 'skipped_already_credited' => 0, 'skipped_no_rules' => 0, 'errors' => []];

		if (!$this->is_available())
		{
			$result['skipped_no_rules'] = 1;
			return $result;
		}

		$rules = $this->load_active_rules();
		if (empty($rules))
		{
			$result['skipped_no_rules'] = 1;
			return $result;
		}

		$patrons = $this->load_active_pledge_patrons();
		if (empty($patrons))
		{
			return $result;
		}

		foreach ($rules as $rule)
		{
			foreach ($patrons as $patron)
			{
				$rule_id = (int) $rule['rule_id'];
				$user_id = (int) $patron['user_id'];

				if ($this->credit_log_entry_exists($rule_id, $user_id, $period_ymd))
				{
					$result['skipped_already_credited']++;
					continue;
				}

				$amount = bcmul(
					bcdiv((string) $patron['pledge_cents'], '100', 2),
					(string) $rule['amount_per_dollar'],
					2
				);

				$lines = [
					['account_id' => (int) $rule['expense_account_id'], 'debit'  => $amount, 'credit' => '0.00'],
					['account_id' => (int) $rule['wallet_account_id'],  'debit'  => '0.00',  'credit' => $amount, 'subledger_user_id' => $user_id],
				];

				// Wrap create_entry + write_credit_log in a transaction so a
				// credit_log INSERT failure rolls back the journal entry —
				// prevents orphan journal rows that would let a second run
				// duplicate the credit.
				$this->db->sql_transaction('begin');
				try
				{
					// bbAccounts ledger->create_entry uses positional args.
					// reference_id (int) carries the rule_id back to bbAccounts
					// for at-a-glance attribution; precise idempotency lives
					// in bbpatreon's own credit_log table (outbox pattern).
					$journal_id = (int) $this->ledger->create_entry(
						time(),
						sprintf('Patreon monthly credit (%s) — %s', $rule['rule_label'], $period_ymd),
						$lines,
						'pledge_period',
						$rule_id,
						'avathar.bbpatreon'
					);
					$this->write_credit_log($rule_id, $user_id, $period_ymd, $journal_id);
					$this->db->sql_transaction('commit');
					$result['credited']++;
				}
				catch (\Throwable $e)
				{
					$this->db->sql_transaction('rollback');
					$result['errors'][] = [
						'user_id' => $user_id,
						'rule_id' => $rule_id,
						'message' => $e->getMessage(),
					];
				}
			}
		}

		return $result;
	}

	protected function load_active_rules(): array
	{
		$sql = 'SELECT rule_id, rule_label, expense_account_id, wallet_account_id, amount_per_dollar
			FROM ' . $this->table_prefix . 'bbpatreon_credit_rules
			WHERE is_active = 1
			ORDER BY rule_order ASC, rule_id ASC';
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $rows ?: [];
	}

	protected function load_active_pledge_patrons(): array
	{
		// patreon_sync.patreon_user_id is the Patreon external ID; the
		// link to the phpBB user_id lives in core's oauth_accounts table
		// (provider='patreon', oauth_provider_id = patreon_user_id).
		// Unlinked patrons (creator-side known via API sync but never
		// OAuth'd into the forum) are intentionally excluded — without a
		// phpBB user_id we have no subledger to credit.
		$sql = "SELECT oa.user_id AS user_id, s.pledge_cents AS pledge_cents
			FROM " . $this->table_prefix . "patreon_sync s
			INNER JOIN " . $this->oauth_accounts_table . " oa
				ON oa.provider = 'patreon'
				AND oa.oauth_provider_id = s.patreon_user_id
			WHERE oa.user_id > 0
				AND s.pledge_cents > 0
				AND s.pledge_status IN ('active_patron', 'declined_patron')";
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $rows ?: [];
	}

	protected function credit_log_entry_exists(int $rule_id, int $user_id, string $period_ymd): bool
	{
		$sql = 'SELECT log_id
			FROM ' . $this->credit_log_table . "
			WHERE rule_id = " . $rule_id . "
				AND user_id = " . $user_id . "
				AND period = '" . $this->db->sql_escape($period_ymd) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$found = $this->db->sql_fetchfield('log_id', false, $result);
		$this->db->sql_freeresult($result);
		return $found !== false;
	}

	protected function write_credit_log(int $rule_id, int $user_id, string $period_ymd, int $journal_id): void
	{
		$sql = 'INSERT INTO ' . $this->credit_log_table . ' ' . $this->db->sql_build_array('INSERT', [
			'rule_id'    => $rule_id,
			'user_id'    => $user_id,
			'period'     => $period_ymd,
			'journal_id' => $journal_id,
			'created_at' => time(),
		]);
		$this->db->sql_query($sql);
	}
}
