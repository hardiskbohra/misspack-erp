<?php

namespace Tests\Unit;

use App\Models\CashflowEntry;
use App\Services\EmployeeProfile;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Which way the money went — asked of the running code, not of the source.
 *
 * A salary the office filed in the ledger read as ₹0 on the person's own record,
 * because the record was written as "the credits filed against them" while the
 * ledger's own default — and the way a salary is actually paid — is a **debit**:
 * money out of the company. The direction was *assumed* instead of read, and no
 * source-reading check could see it, because every line was spelled correctly.
 *
 * So this file builds the rows in memory (no database, no migration, nothing to
 * seed) and asserts what the figures come to:
 *
 *   1. a debit filed against a person *is* money paid to them;
 *   2. a credit filed against them is money that came back, with the opposite
 *      sign — it reduces what they were paid rather than adding to it;
 *   3. the total and the month strip agree with each other — the two are the
 *      same rows read twice, and a total that disagrees with the table under it
 *      is a total nobody trusts;
 *   4. the query that fetches a person's pay pins the person and nothing else.
 *
 * Run it with:  php artisan test --filter=EmployeePayTest
 */
class EmployeePayTest extends TestCase
{
    /** A ledger row, as the form writes one: the amount lands in its own column. */
    private function entry(string $type, float $amount, string $date = '2026-10-05', array $extra = []): CashflowEntry
    {
        return new CashflowEntry(array_merge([
            'transaction_type' => $type,
            'credit_amount' => $type === 'credit' ? $amount : 0,
            'debit_amount' => $type === 'debit' ? $amount : 0,
            'entry_date' => $date,
            'accounting_status' => 'booked',
        ], $extra));
    }

    /** @return Collection<int, CashflowEntry> */
    private function rows(array $entries): Collection
    {
        return new Collection($entries);
    }

    /**
     * The bug the office reported: a ₹1,00,000 salary debit read as zero.
     */
    public function test_a_salary_debit_is_money_paid_to_the_person(): void
    {
        $salary = $this->entry('debit', 100000);

        $this->assertTrue($salary->isMoneyOut(), 'a debit is money out of the company');
        $this->assertSame(100000.0, $salary->amountMoved());
        $this->assertSame(100000.0, $salary->signedAmount(), 'paid to the person is positive');

        $totals = (new EmployeeProfile())->payFrom($this->rows([$salary]));

        $this->assertSame(100000.0, $totals['total'], 'the record must show the salary that was paid');
        $this->assertSame(100000.0, $totals['paid']);
        $this->assertSame(0.0, $totals['recovered']);
        $this->assertSame(1, $totals['entries']);
        $this->assertSame(1, $totals['payments']);
        $this->assertSame(1, $totals['months_paid']);
    }

    public function test_a_credit_is_money_that_came_back(): void
    {
        $recovery = $this->entry('credit', 5000, '2026-10-09', ['accounting_status' => 'pending']);

        $this->assertFalse($recovery->isMoneyOut());
        $this->assertSame(-5000.0, $recovery->signedAmount());

        $totals = (new EmployeeProfile())->payFrom($this->rows([$recovery]));

        $this->assertSame(0.0, $totals['paid']);
        $this->assertSame(5000.0, $totals['recovered']);
        $this->assertSame(-5000.0, $totals['total'], 'money back reduces what the person was paid');
        $this->assertSame(0, $totals['payments'], 'a recovery is not a payment');
        $this->assertSame(0, $totals['months_paid'], 'a month with only a recovery is not a month paid');
        $this->assertSame(1, $totals['pending'], 'a pending row is reported as pending');
    }

    public function test_the_total_and_the_months_are_the_same_rows_read_twice(): void
    {
        $rows = $this->rows([
            $this->entry('debit', 100000, '2026-10-05'),
            $this->entry('debit', 20000, '2026-10-25', ['accounting_status' => 'pending']),
            $this->entry('credit', 5000, '2026-10-09'),
        ]);

        $profile = new EmployeeProfile();
        $totals = $profile->payFrom($rows);
        $months = $profile->monthsFrom($rows);

        $this->assertSame(120000.0, $totals['paid']);
        $this->assertSame(5000.0, $totals['recovered']);
        $this->assertSame(115000.0, $totals['total']);
        $this->assertSame(2, $totals['payments']);
        $this->assertSame(1, $totals['months_paid']);
        $this->assertSame(1, $totals['pending']);

        $this->assertCount(1, $months, 'one month holds all three rows');
        $this->assertSame('2026-10', $months[0]['period']);
        $this->assertSame(115000.0, $months[0]['net'], 'the month adds up to the same net as the total');
        $this->assertSame(120000.0, $months[0]['paid']);
        $this->assertSame(5000.0, $months[0]['recovered']);
        $this->assertSame(2, $months[0]['payments']);

        $this->assertSame(
            $totals['total'],
            round(array_sum(array_column($months, 'net')), 2),
            'the months must add up to the total'
        );
    }

    public function test_a_month_that_only_takes_money_back_is_negative(): void
    {
        $months = (new EmployeeProfile())->monthsFrom($this->rows([
            $this->entry('credit', 7500, '2026-09-30'),
        ]));

        $this->assertSame('2026-09', $months[0]['period']);
        $this->assertSame(-7500.0, $months[0]['net']);
        $this->assertSame(0, $months[0]['payments']);
    }

    /**
     * The query fetches the person's entries, whichever way the money went — a
     * direction pinned here is the bug, written again.
     */
    public function test_the_query_pins_the_person_and_not_the_direction(): void
    {
        $user = new \App\Models\User();
        $user->id = 7;

        $sql = (new EmployeeProfile())->salaryQuery($user)->toSql();

        $this->assertStringContainsString('employee_id', $sql);
        $this->assertStringNotContainsString('transaction_type', $sql, 'pay is both ways; the direction is read, not filtered');
    }

    /**
     * Money out is a debit — and an entry with no direction typed on it (the old
     * rows) still counts as money out, which is the ledger's own default.
     */
    public function test_money_out_means_the_debits(): void
    {
        $sql = CashflowEntry::query()->moneyOut()->toSql();

        $this->assertStringContainsString('transaction_type', $sql);
        $this->assertSame(1, substr_count($sql, '?'), 'the debit; the null is written into the SQL itself');
        $this->assertStringContainsString('is null', $sql, 'an entry with no direction typed is money out');

        $legacy = new CashflowEntry(['debit_amount' => 1500]);
        $this->assertTrue($legacy->isMoneyOut(), 'an entry with no type is money out');
        $this->assertSame(1500.0, $legacy->signedAmount());
    }
}
