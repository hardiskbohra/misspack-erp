<?php

namespace App\Services;

use App\Helpers\CommonHelper;
use App\Models\CashflowEntry;
use App\Models\EmployeePayslip;
use App\Models\SalesInvoice;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One payslip, assembled — the single definition of what the paper says.
 *
 * A payslip is read in three places: on the office's record page for the person,
 * on the employee's own page inside their workspace, and on paper (printed, or
 * rendered to PDF when dompdf is installed). Three renderings of one document,
 * built from one array, is the only way the figure on the paper and the figure
 * on the screen can be guaranteed to be the same figure.
 *
 * Everything here is derived from the row and the person it belongs to. Nothing
 * is stored twice: the breakdown is the stored lines, the totals are the
 * stored totals, and where the two could disagree the lines win (the controller
 * derives the totals from the lines when a slip is saved, so this is a reading
 * of one decision rather than a second one).
 */
class PayslipDocument
{
    /** The environment's own name for itself, used when no address is set. */
    private const FALLBACK_ISSUER = ['name' => 'MissPack India Pvt Ltd'];

    /**
     * The whole document.
     *
     * @return array<string, mixed>
     */
    public function build(EmployeePayslip $payslip, ?string $context = 'app'): array
    {
        $payslip->loadMissing('user');
        $user = $payslip->user;
        $entry = $payslip->entry;

        $earnings = $payslip->earnings();
        $deductions = $payslip->deductionLines();

        $earned = round(array_sum(array_column($earnings, 'amount')), 2);
        $deducted = round(array_sum(array_column($deductions, 'amount')), 2);
        $currency = (string) ($payslip->currency ?: 'INR');

        return [
            'issuer' => $this->issuer(),
            'slip' => [
                'number' => $payslip->slipNumber(),
                'period' => $payslip->periodLabel(),
                'period_key' => (string) $payslip->period,
                'from' => $payslip->periodDate()?->format('d M Y'),
                'to' => $payslip->periodDate()?->copy()->endOfMonth()->format('d M Y'),
                'status' => $payslip->statusLabel(),
                'issued' => $payslip->isIssued(),
                'notes' => $payslip->notes,
                'generated_at' => now(),
                'has_breakdown' => $payslip->hasBreakdown(),
                'matches_ledger' => (bool) $entry,
            ],
            'employee' => [
                'name' => (string) ($user?->name ?? 'Employee'),
                'code' => (string) ($user?->employee_code ?: '—'),
                'designation' => (string) ($user?->designation ?: '—'),
                'department' => (string) ($user?->department ?: '—'),
                'type' => $user?->employmentTypeLabel() ?? '—',
                'status' => $user?->employmentStatusLabel() ?? '—',
                'joined' => $user?->date_of_joining?->format('d M Y') ?? '—',
                'pan' => (string) ($user?->pan_number ?: '—'),
                'bank' => $this->bankBlock($user),
                /* the account number is masked exactly where the record masks
                   it — a payslip is a printed document that travels */
                'account' => $user?->bank_account_number
                    ? (new EmployeeProfile())->mask($user->bank_account_number)
                    : '—',
            ],
            'attendance' => [
                'working_days' => $payslip->working_days,
                'paid_days' => $payslip->paid_days,
                'lop' => $payslip->lopDays(),
            ],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'totals' => [
                'earned' => $earned,
                'deducted' => $deducted,
                'net' => (float) $payslip->net_amount,
                'currency' => $currency,
                'net_in_words' => CommonHelper::inWords((float) $payslip->net_amount, $currency),
            ],
            'payment' => $this->payment($payslip, $entry, $currency),
            /* The office sees the internal vocabulary; the employee does not. */
            'internal' => $context === 'app',
        ];
    }

    /**
     * The slip as a file.
     *
     * Generated from the row every time it is asked for, never stored: a stored
     * PDF is a second copy of the figures that can be older than the record it
     * came from, and the first person to notice would be the employee holding
     * the paper. Where dompdf is not installed the same document is served as a
     * printable page — the project's own convention for every other document.
     */
    public function download(EmployeePayslip $payslip): Response
    {
        $doc = $this->build($payslip, 'pdf');
        $name = 'payslip-'.Str::slug($payslip->slipNumber() ?: 'slip', '-').'.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('employees.payslip-pdf', ['doc' => $doc])
                ->setPaper('a4')
                ->download($name);
        }

        return response()->view('employees.payslip-pdf', [
            'doc' => $doc,
            'pdfFallbackMessage' => 'Install barryvdh/laravel-dompdf for a direct PDF download. This page prints — use Print > Save as PDF.',
        ]);
    }

    /**
     * Who the slip is from — the company's own details, from the single place
     * the application keeps them (the sales-invoice header), so a payslip and an
     * invoice never disagree about the address or the GSTIN.
     *
     * @return array<string, string>
     */
    private function issuer(): array
    {
        $seller = class_exists(SalesInvoice::class) ? SalesInvoice::defaultSellerDetails() : [];

        return [
            'name' => (string) ($seller['seller_company_name'] ?? self::FALLBACK_ISSUER['name']),
            'address' => (string) ($seller['seller_address'] ?? ''),
            'city' => (string) ($seller['seller_city'] ?? ''),
            'state' => (string) ($seller['seller_state'] ?? ''),
            'pincode' => (string) ($seller['seller_pincode'] ?? ''),
            'country' => (string) ($seller['seller_country'] ?? ''),
            'gstin' => (string) ($seller['seller_gstin'] ?? ''),
            'pan' => (string) ($seller['seller_pan'] ?? ''),
            'email' => (string) ($seller['seller_email'] ?? ''),
            'mobile' => (string) ($seller['seller_mobile'] ?? ''),
            'website' => (string) ($seller['seller_website'] ?? ''),
            'bank_name' => (string) ($seller['seller_bank_name'] ?? ''),
            'swift' => (string) ($seller['seller_swift'] ?? ''),
        ];
    }

    /** The bank line a payslip carries, in the order a clerk reads it. */
    private function bankBlock(?\App\Models\User $user): string
    {
        if (! $user) {
            return '—';
        }

        $parts = array_filter([
            $user->bank_name,
            $user->bank_account_number ? (new EmployeeProfile())->mask($user->bank_account_number) : null,
            $user->bank_ifsc ? 'IFSC '.$user->bank_ifsc : null,
        ]);

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /**
     * How the money actually moved — the ledger entry behind the slip when there
     * is one, so a query about a month can be answered from the paper itself.
     *
     * @return array<string, mixed>
     */
    private function payment(EmployeePayslip $payslip, ?CashflowEntry $entry, string $currency): array
    {
        return [
            'paid_on' => $payslip->paid_on,
            'paid_on_label' => $payslip->paidOnLabel(),
            'mode' => $entry
                ? (CashflowEntry::paymentModeOptions()[$entry->payment_mode] ?? null)
                : null,
            'reference' => $entry?->bank_reference_number,
            'ledger_date' => $entry?->entry_date,
            'ledger_particular' => $entry?->particular,
            'ledger_amount' => $entry ? $entry->amountMoved() : null,
            'currency' => $currency,
        ];
    }
}
