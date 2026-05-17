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
 * calendar month. Idempotent via the composite reference triple
 * (source/type/id) — a second call for the same period is a no-op.
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

	public function __construct(
		$ledger,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\log\log_interface $log,
		string $table_prefix
	)
	{
		$this->ledger       = $ledger;
		$this->db           = $db;
		$this->log          = $log;
		$this->table_prefix = $table_prefix;
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
				$reference_id = sprintf('%d-%d-%s', (int) $rule['rule_id'], (int) $patron['user_id'], $period_ymd);

				if ($this->journal_entry_exists($reference_id))
				{
					$result['skipped_already_credited']++;
					continue;
				}

				$amount = bcmul(
					bcdiv((string) $patron['pledge_cents'], '100', 2),
					(string) $rule['amount_per_dollar'],
					2
				);

				$entry = [
					'entry_date'       => time(),
					'description'      => sprintf('Patreon monthly credit (%s) — %s', $rule['rule_label'], $period_ymd),
					'reference_source' => 'avathar.bbpatreon',
					'reference_type'   => 'pledge_period',
					'reference_id'     => $reference_id,
					'lines' => [
						['account_id' => (int) $rule['expense_account_id'], 'debit'  => $amount, 'credit' => '0.00'],
						['account_id' => (int) $rule['wallet_account_id'],  'debit'  => '0.00',  'credit' => $amount, 'subledger_user_id' => (int) $patron['user_id']],
					],
				];

				try
				{
					$this->ledger->create_entry($entry);
					$result['credited']++;
				}
				catch (\Throwable $e)
				{
					$result['errors'][] = [
						'user_id' => (int) $patron['user_id'],
						'rule_id' => (int) $rule['rule_id'],
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
		$sql = "SELECT user_id, pledge_cents
			FROM " . $this->table_prefix . "patreon_sync
			WHERE user_id > 0
				AND pledge_cents > 0
				AND pledge_status IN ('active_patron', 'declined_patron')";
		$result = $this->db->sql_query($sql);
		$rows = $this->db->sql_fetchrowset($result);
		$this->db->sql_freeresult($result);
		return $rows ?: [];
	}

	protected function journal_entry_exists(string $reference_id): bool
	{
		$sql = "SELECT journal_id
			FROM " . $this->table_prefix . "bbaccounts_journal
			WHERE reference_source = 'avathar.bbpatreon'
				AND reference_type = 'pledge_period'
				AND reference_id = '" . $this->db->sql_escape($reference_id) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$found = $this->db->sql_fetchfield('journal_id', false, $result);
		$this->db->sql_freeresult($result);
		return $found !== false;
	}
}
