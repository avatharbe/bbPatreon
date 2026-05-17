<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Integration test: two recorder runs in the same period are idempotent
 * (mirrors what happens when the nightly cron fires twice in the same
 * calendar month).
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
		$ledger_store = [];
		$ledger = new class($ledger_store) {
			public array $store;
			public function __construct(array &$store) { $this->store = &$store; }
			public function create_entry(array $entry): int
			{
				$this->store[$entry['reference_id']] = $entry;
				return count($this->store);
			}
		};

		$db = $this->createMock('\phpbb\db\driver\driver_interface');
		$log = $this->createMock('\phpbb\log\log_interface');

		$rule = [
			'rule_id'            => 1,
			'rule_label'         => 'Forum POINTS',
			'expense_account_id' => 6,
			'wallet_account_id'  => 2,
			'amount_per_dollar'  => '100.00',
		];
		$patrons = [
			['user_id' => 42, 'pledge_cents' => 500],
			['user_id' => 43, 'pledge_cents' => 1000],
		];

		$db->method('sql_query')->willReturnCallback(function ($sql) {
			if (strpos($sql, 'bbpatreon_credit_rules') !== false) return 'rules';
			if (strpos($sql, 'patreon_sync') !== false)           return 'patrons';
			return 'other';
		});
		$db->method('sql_query_limit')->willReturn('journal-limit');
		$db->method('sql_fetchrowset')->willReturnCallback(function ($handle) use ($rule, $patrons) {
			if ($handle === 'rules')   return [$rule];
			if ($handle === 'patrons') return $patrons;
			return [];
		});
		$db->method('sql_escape')->willReturnArgument(0);

		$existing_refs = &$ledger->store;
		$db->method('sql_fetchfield')->willReturnCallback(function () use (&$existing_refs) {
			static $expected_refs = ['1-42-2026-05', '1-43-2026-05'];
			static $call_index = 0;
			$ref = $expected_refs[$call_index % 2];
			$call_index++;
			return isset($existing_refs[$ref]) ? 'existing' : false;
		});

		$recorder = new \avathar\bbpatreon\service\bbaccounts_recorder($ledger, $db, $log, 'phpbb_', 'phpbb_oauth_accounts');

		$result1 = $recorder->credit_active_patrons_for_period('2026-05');
		$this->assertSame(2, $result1['credited'], 'first run should credit both patrons');
		$this->assertCount(2, $ledger->store);
		$this->assertArrayHasKey('1-42-2026-05', $ledger->store);
		$this->assertArrayHasKey('1-43-2026-05', $ledger->store);

		$result2 = $recorder->credit_active_patrons_for_period('2026-05');
		$this->assertSame(0, $result2['credited'], 'second run in same period should credit nobody');
		$this->assertSame(2, $result2['skipped_already_credited'], 'second run should skip both as already credited');
		$this->assertCount(2, $ledger->store, 'no new entries should appear');
	}
}
