<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Integration test: two recorder runs in the same period are idempotent
 * (mirrors what happens when the nightly cron fires twice in the same
 * calendar month). Idempotency follows the outbox pattern — bbpatreon
 * owns the credit_log table, the second run finds matching rows there
 * and short-circuits without touching the ledger again.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\cron;

class credit_idempotency_test extends \phpbb_test_case
{
	public function test_recorder_run_twice_in_same_period_idempotent(): void
	{
		// Fake ledger with positional-args create_entry; records every call.
		$ledger_calls = [];
		$ledger = new class($ledger_calls) {
			public array $calls;
			public int $next_id = 1000;
			public function __construct(array &$calls) { $this->calls = &$calls; }
			public function create_entry(int $entry_date, string $description, array $lines, string $reference_type = 'manual', int $reference_id = 0, string $reference_source = '', int $created_by = 0): int
			{
				$this->calls[] = ['reference_id' => $reference_id, 'reference_source' => $reference_source];
				return $this->next_id++;
			}
		};

		$db = $this->createMock('\phpbb\db\driver\driver_interface');
		$log = $this->createMock('\phpbb\log\log_interface');

		$rule = ['rule_id' => 1, 'rule_label' => 'Forum POINTS', 'expense_account_id' => 6, 'wallet_account_id' => 2, 'amount_per_dollar' => '100.00'];
		$patrons = [['user_id' => 42, 'pledge_cents' => 500], ['user_id' => 43, 'pledge_cents' => 1000]];

		$db->method('sql_query')->willReturnCallback(function ($sql) {
			if (strpos($sql, 'bbpatreon_credit_rules') !== false) return 'rules';
			if (strpos($sql, 'patreon_sync') !== false)           return 'patrons';
			return 'other';
		});
		$db->method('sql_query_limit')->willReturn('idem-limit');
		$db->method('sql_fetchrowset')->willReturnCallback(function ($handle) use ($rule, $patrons) {
			if ($handle === 'rules')   return [$rule];
			if ($handle === 'patrons') return $patrons;
			return [];
		});
		$db->method('sql_escape')->willReturnArgument(0);
		$db->method('sql_build_array')->willReturn(' (mock_cols) VALUES (mock_vals)');

		// First 2 fetchfield calls (first run): no existing log entry.
		// Next 2 fetchfield calls (second run): log entry exists.
		$check_count = 0;
		$db->method('sql_fetchfield')->willReturnCallback(function () use (&$check_count) {
			$check_count++;
			return $check_count <= 2 ? false : 'existing';
		});

		$recorder = new \avathar\bbpatreon\service\bbaccounts_recorder($ledger, $db, $log, 'phpbb_', 'phpbb_oauth_accounts', 'phpbb_bbpatreon_credit_log');

		$result1 = $recorder->credit_active_patrons_for_period('2026-05');
		$this->assertSame(2, $result1['credited'], 'first run should credit both patrons');
		$this->assertCount(2, $ledger_calls);
		$this->assertSame(1, $ledger_calls[0]['reference_id'], 'reference_id = rule_id (rule 1)');
		$this->assertSame('avathar.bbpatreon', $ledger_calls[0]['reference_source']);

		$result2 = $recorder->credit_active_patrons_for_period('2026-05');
		$this->assertSame(0, $result2['credited'], 'second run in same period should credit nobody');
		$this->assertSame(2, $result2['skipped_already_credited'], 'second run should skip both as already credited');
		$this->assertCount(2, $ledger_calls, 'no new ledger calls should be made on the second run');
	}
}
