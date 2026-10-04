<?php

namespace Tests\Unit;

use App\Helpers\CommonHelper;
use App\Models\CashflowEntry;
use App\Models\EmployeePayslip;
use App\Models\User;
use App\Services\EmployeeProfile;
use App\Services\PayslipDocument;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The payslip itself — asked of the running code, not of the source.
 *
 * Task 61 asked for a detailed slip that generates itself, prints, and can be
 * handed to the employee. Three things can go wrong in a document, and none of
 * them is visible in the source that writes it:
 *
 *   1. the totals stop adding up to the lines printed above them, because the
 *      gross was typed once and the lines were edited later;
 *   2. the money in words is not the money in figures ("Rupees One Lakh And
 *      Fifty Thousand" for 1,50,000) — a worded amount a clerk cannot trust;
 *   3. the same document built twice gives two different answers, because the
 *      screen and the paper each assemble their own.
 *
 * So this file builds the slip in memory — no database, no migration, nothing to
 * seed — and asserts what the document comes to.
 *
 * Run it with:  php artisan test --filter=EmployeePayslipTest
 */
class EmployeePayslipTest extends TestCase
{
    /** A person, as the record holds them. */
    private function person(array $extra = []): User
    {
        return new User(array_merge([
            'name' => 'Ramesh Patel',
            'employee_code' => 'EMP-0007',
            'designation' => 'Packing Supervisor',
            'department' => 'Dispatch',
            'date_of_joining' => '2024-04-01',
            'pan_number' => 'ABCDE1234F',
            'bank_name' => 'HDFC BANK LTD',
            'bank_account_number' => '50100123456789',
            'bank_ifsc' => 'HDFC0000123',
            'mobile' => '9825012345',
            'email' => 'ramesh@example.com',
        ], $extra));
    }

    /** A slip with a breakdown, as the office's form saves one. */
    private function slip(array $extra = [], ?User $user = null): EmployeePayslip
    {
        $slip = new EmployeePayslip(array_merge([
            'user_id' => 7,
            'period' => '2026-08',
            'status' => EmployeePayslip::STATUS_ISSUED,
            'currency' => 'INR',
            'gross_amount' => 95000,
            'deductions' => 5000,
            'net_amount' => 90000,
            'paid_on' => '2026-09-01',
            'working_days' => 26,
            'paid_days' => 25,
            'components' => [
                'earnings' => [
                    ['label' => 'Basic', 'amount' => 60000],
                    ['label' => 'House rent allowance', 'amount' => 25000],
                    ['label' => 'Conveyance', 'amount' => 10000],
                ],
                'deductions' => [
                    ['label' => 'Provident fund', 'amount' => 3000],
                    ['label' => 'Professional tax', 'amount' => 2000],
                ],
            ],
        ], $extra));

        /* The relations are set, not queried: this test has no database. */
        $slip->setRelation('user', $user ?? $this->person());
        $slip->setRelation('entry', null);

        return $slip;
    }

    /**
     * The rule the whole document rests on: what is printed above the total adds
     * up to the total.
     */
    public function test_the_totals_are_the_sum_of_the_slips_own_lines(): void
    {
        $doc = (new PayslipDocument())->build($this->slip());

        $this->assertSame(95000.0, $doc['totals']['earned'], 'the earnings are the lines, added up');
        $this->assertSame(5000.0, $doc['totals']['deducted']);
        $this->assertSame(
            round($doc['totals']['earned'] - $doc['totals']['deducted'], 2),
            $doc['totals']['net'],
            'the net is what the lines leave behind'
        );
    }

    /** A slip with no breakdown still prints its totals, and still adds up. */
    public function test_a_slip_without_a_breakdown_still_says_what_it_paid(): void
    {
        $slip = $this->slip(['components' => null]);
        $doc = (new PayslipDocument())->build($slip);

        $this->assertFalse($slip->hasBreakdown());
        $this->assertCount(1, $doc['earnings'], 'one line stands in for the gross');
        $this->assertSame('Gross salary', $doc['earnings'][0]['label']);
        $this->assertSame(95000.0, $doc['earnings'][0]['amount']);
        $this->assertSame(95000.0, $doc['totals']['earned']);
        $this->assertSame(90000.0, $doc['totals']['net']);
    }

    /**
     * The money in words, which is the part a payslip is read aloud from.
     */
    public function test_the_net_is_spelled_out_the_way_the_office_says_it(): void
    {
        $this->assertSame('Rupees Zero Only', CommonHelper::inWords(0));
        $this->assertSame('Rupees One Hundred And Five Only', CommonHelper::inWords(105));
        $this->assertSame('Rupees One Lakh Fifty Thousand Only', CommonHelper::inWords(150000));
        $this->assertSame('Rupees Sixty One Lakh Sixty Three Thousand One Hundred And Forty Only', CommonHelper::inWords(6163140));
        $this->assertSame('Rupees Ninety Thousand Only', CommonHelper::inWords(90000));

        $doc = (new PayslipDocument())->build($this->slip());
        $this->assertSame('Rupees Ninety Thousand Only', $doc['totals']['net_in_words']);
    }

    /** Paise and negatives: the two cases a payroll month actually produces. */
    public function test_paise_and_recoveries_are_worded_too(): void
    {
        $this->assertSame('Rupees One Hundred And Fifty And Fifty Paise Only', CommonHelper::inWords(150.5));
        $this->assertSame('Minus Rupees Five Thousand Only', CommonHelper::inWords(-5000));
    }

    /**
     * The slip's number, its days and its share drafts — the details that make
     * the paper useful in an email thread and in a payroll file.
     */
    public function test_the_slip_carries_its_own_number_the_days_and_how_to_send_it(): void
    {
        $slip = $this->slip();

        $this->assertSame('EMP-0007/2026-08', $slip->slipNumber());
        $this->assertSame('Aug 2026', $slip->periodLabel());
        $this->assertSame(26, $slip->working_days);
        $this->assertSame(25, $slip->paid_days);
        $this->assertSame(1, $slip->lopDays(), 'the difference between the two is the loss of pay');

        $message = $slip->shareMessage();
        $this->assertStringContainsString('Aug 2026', $message);
        $this->assertStringContainsString('90,000', $message, 'the person is told what landed');
        $this->assertStringNotContainsString('Provident fund', $message, 'the slip itself stays behind the login');

        $this->assertSame(
            'https://wa.me/919825012345?text='.rawurlencode($message),
            $slip->whatsappUrl('9825012345'),
            'a ten-digit mobile is addressed as an Indian number'
        );
        $this->assertNull($slip->whatsappUrl('  '), 'no number, no draft');

        $mail = $slip->mailUrl('ramesh@example.com');
        $this->assertStringStartsWith('mailto:ramesh@example.com?subject=Payslip', $mail);
        $this->assertStringContainsString('body='.rawurlencode($message), $mail);
        $this->assertNull($slip->mailUrl('not-an-address'));
    }

    /**
     * The document the screen shows and the document the paper shows are the
     * same document: one build, two contexts, and the only difference is the
     * office's internal vocabulary.
     */
    public function test_the_page_and_the_paper_are_one_document(): void
    {
        $slip = $this->slip();
        $document = new PayslipDocument();

        $app = $document->build($slip, 'app');
        $pdf = $document->build($slip, 'pdf');

        $this->assertTrue($app['internal'], 'the office sees the state and the ledger link');
        $this->assertFalse($pdf['internal'], 'the paper does not carry the office\'s working notes');

        /* The moment each was built is the one thing that cannot be equal. */
        unset($app['slip']['generated_at'], $pdf['slip']['generated_at']);

        foreach (['slip', 'issuer', 'employee', 'attendance', 'earnings', 'deductions', 'totals', 'payment'] as $key) {
            $this->assertSame($app[$key], $pdf[$key], 'the paper and the page must agree about '.$key);
        }

        $this->assertSame('AATCM8816E', $app['issuer']['pan'], 'the office on the slip is the office on the invoice');
        $this->assertSame('EMP-0007/2026-08', $app['slip']['number']);
        $this->assertSame('01 Aug 2026', $app['slip']['from']);
        $this->assertSame('31 Aug 2026', $app['slip']['to']);
        $this->assertSame('ABCDE1234F', $app['employee']['pan']);
    }

    /**
     * The one table: a row per salary entry, and the month's payslip on the
     * month's own row.
     *
     * The failure this catches is the merge that looks right and reads wrong —
     * two rows for one month sharing a slip, or a slip disappearing because its
     * month was listed twice.
     */
    public function test_the_month_and_its_payslip_are_one_row(): void
    {
        $entry = fn (string $type, float $amount, string $date) => new CashflowEntry([
            'transaction_type' => $type,
            'credit_amount' => $type === 'credit' ? $amount : 0,
            'debit_amount' => $type === 'debit' ? $amount : 0,
            'entry_date' => $date,
        ]);

        $rows = (new EmployeeProfile())->payRows(2026, new Collection([
            $entry('debit', 100000, '2026-10-05'),
            $entry('debit', 100000, '2026-09-20'),
            $entry('debit', 40000, '2026-09-05'),
            $entry('credit', 5000, '2026-08-11'),
        ]), new Collection([
            $this->slip(['period' => '2026-09']),
            $this->slip(['period' => '2026-11']),
        ]));

        $this->assertCount(5, $rows, 'four entries and one slip with no entry behind it');

        $this->assertSame(['2026-11', '2026-10', '2026-09', '2026-09', '2026-08'], $rows->pluck('period')->all());

        /* the slip with no ledger entry is still a row: a document does not
           disappear because its month has no payment filed against it */
        $this->assertFalse($rows[0]['has_entry']);
        $this->assertNotNull($rows[0]['slip']);
        $this->assertFalse($rows[0]['generatable'], 'a slip that exists is not generated again');

        /* October has an entry and no slip yet: the row is where the office
           generates one, and it is prefillable because the money went out */
        $this->assertNull($rows[1]['slip']);
        $this->assertTrue($rows[1]['owns_slip']);
        $this->assertTrue($rows[1]['generatable']);

        /* September is listed twice — the ledger paid twice — and carries its
           slip once, on the newer row */
        $this->assertSame('2026-09', $rows[2]['period']);
        $this->assertTrue($rows[2]['owns_slip']);
        $this->assertNotNull($rows[2]['slip']);
        $this->assertSame('2026-09', $rows[3]['period']);
        $this->assertFalse($rows[3]['owns_slip'], 'the month draws its slip on one row only');
        $this->assertNull($rows[3]['slip']);
        $this->assertFalse($rows[3]['generatable'], 'the month has one slip, generated on one row');

        /* a recovery is not a payment, so it is not a month to print a slip for */
        $this->assertFalse($rows[4]['generatable']);
    }

    /** The account number on the paper is masked, because paper travels. */
    public function test_the_bank_account_on_the_slip_is_masked(): void
    {
        $doc = (new PayslipDocument())->build($this->slip());

        $this->assertNotSame('50100123456789', $doc['employee']['account']);
        $this->assertStringContainsString('6789', $doc['employee']['account'], 'the last four are enough to check it');
        $this->assertStringNotContainsString('50100123456789', $doc['employee']['bank']);
    }
}
