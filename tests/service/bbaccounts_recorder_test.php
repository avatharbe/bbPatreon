<?php
/**
 *
 * bbPatreon. An extension for the phpBB Forum Software package.
 * Unit tests for bbaccounts_recorder service.
 *
 * @copyright (c) 2026 A. Vandenberghe
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace avathar\bbpatreon\tests\service;

class bbaccounts_recorder_test extends \phpbb_test_case
{
	/** @var \PHPUnit\Framework\MockObject\MockObject|null */
	protected $ledger;
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $db;
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $log;

	public function setUp(): void
	{
		parent::setUp();
		$this->ledger = $this->getMockBuilder('stdClass')
			->addMethods(['create_entry'])
			->getMock();
		$this->db  = $this->createMock('\phpbb\db\driver\driver_interface');
		$this->log = $this->createMock('\phpbb\log\log_interface');
	}

	protected function get_recorder($ledger_or_null = 'default')
	{
		if ($ledger_or_null === 'default')
		{
			$ledger_or_null = $this->ledger;
		}
		return new \avathar\bbpatreon\service\bbaccounts_recorder(
			$ledger_or_null,
			$this->db,
			$this->log,
			'phpbb_'
		);
	}

	public function test_no_ledger_returns_no_op(): void
	{
		$rec = $this->get_recorder(null);
		$result = $rec->credit_active_patrons_for_period('2026-05');
		$this->assertSame(
			['credited' => 0, 'skipped_already_credited' => 0, 'skipped_no_rules' => 1, 'errors' => []],
			$result
		);
	}

	public function test_is_available_reflects_ledger_presence(): void
	{
		$this->assertTrue($this->get_recorder()->is_available());
		$this->assertFalse($this->get_recorder(null)->is_available());
	}

	public function test_zero_active_rules_short_circuits(): void
	{
		$this->db->expects($this->once())
			->method('sql_query')
			->willReturn('rules_result');
		$this->db->expects($this->once())
			->method('sql_fetchrowset')
			->with('rules_result')
			->willReturn([]);
		$this->ledger->expects($this->never())->method('create_entry');

		$rec = $this->get_recorder();
		$result = $rec->credit_active_patrons_for_period('2026-05');
		$this->assertSame(1, $result['skipped_no_rules']);
		$this->assertSame(0, $result['credited']);
	}

	public function test_one_rule_one_patron_creates_one_entry(): void
	{
		$this->db->method('sql_query')->willReturnOnConsecutiveCalls('rules_result', 'patrons_result');
		$this->db->method('sql_query_limit')->willReturn('idem_result');
		$this->db->method('sql_fetchrowset')->willReturnCallback(function ($handle) {
			if ($handle === 'rules_result')
			{
				return [['rule_id' => 1, 'rule_label' => 'Forum POINTS', 'expense_account_id' => 6, 'wallet_account_id' => 2, 'amount_per_dollar' => '100.00']];
			}
			if ($handle === 'patrons_result')
			{
				return [['user_id' => 42, 'pledge_cents' => 500]];
			}
			return [];
		});
		$this->db->method('sql_fetchfield')->willReturn(false);
		$this->db->method('sql_escape')->willReturnArgument(0);

		$this->ledger->expects($this->once())
			->method('create_entry')
			->with($this->callback(function ($entry) {
				return $entry['reference_source'] === 'avathar.bbpatreon'
					&& $entry['reference_type']   === 'pledge_period'
					&& $entry['reference_id']     === '1-42-2026-05'
					&& count($entry['lines']) === 2
					&& $entry['lines'][0]['account_id'] === 6
					&& $entry['lines'][1]['account_id'] === 2
					&& $entry['lines'][1]['subledger_user_id'] === 42;
			}));

		$rec = $this->get_recorder();
		$result = $rec->credit_active_patrons_for_period('2026-05');
		$this->assertSame(1, $result['credited']);
		$this->assertSame(0, $result['skipped_already_credited']);
	}

	public function test_existing_journal_entry_skips(): void
	{
		$this->db->method('sql_query')->willReturnOnConsecutiveCalls('rules_result', 'patrons_result');
		$this->db->method('sql_query_limit')->willReturn('idem_result');
		$this->db->method('sql_fetchrowset')->willReturnCallback(function ($handle) {
			if ($handle === 'rules_result')
			{
				return [['rule_id' => 1, 'rule_label' => 'Forum POINTS', 'expense_account_id' => 6, 'wallet_account_id' => 2, 'amount_per_dollar' => '100.00']];
			}
			if ($handle === 'patrons_result')
			{
				return [['user_id' => 42, 'pledge_cents' => 500]];
			}
			return [];
		});
		$this->db->method('sql_fetchfield')->willReturn('999');
		$this->db->method('sql_escape')->willReturnArgument(0);

		$this->ledger->expects($this->never())->method('create_entry');

		$rec = $this->get_recorder();
		$result = $rec->credit_active_patrons_for_period('2026-05');
		$this->assertSame(1, $result['skipped_already_credited']);
		$this->assertSame(0, $result['credited']);
	}

	public function test_ledger_exception_continues_batch(): void
	{
		$this->db->method('sql_query')->willReturnOnConsecutiveCalls('rules_result', 'patrons_result');
		$this->db->method('sql_query_limit')->willReturn('idem_result');
		$this->db->method('sql_fetchrowset')->willReturnCallback(function ($handle) {
			if ($handle === 'rules_result')
			{
				return [['rule_id' => 1, 'rule_label' => 'Forum POINTS', 'expense_account_id' => 6, 'wallet_account_id' => 2, 'amount_per_dollar' => '100.00']];
			}
			if ($handle === 'patrons_result')
			{
				return [['user_id' => 42, 'pledge_cents' => 500], ['user_id' => 43, 'pledge_cents' => 1000]];
			}
			return [];
		});
		$this->db->method('sql_fetchfield')->willReturn(false);
		$this->db->method('sql_escape')->willReturnArgument(0);

		$call_count = 0;
		$this->ledger->method('create_entry')->willReturnCallback(function () use (&$call_count) {
			$call_count++;
			if ($call_count === 1)
			{
				throw new \RuntimeException('synthetic');
			}
			return 42;
		});

		$rec = $this->get_recorder();
		$result = $rec->credit_active_patrons_for_period('2026-05');
		$this->assertSame(1, $result['credited']);
		$this->assertCount(1, $result['errors']);
		$this->assertSame(42, $result['errors'][0]['user_id']);
	}
}
