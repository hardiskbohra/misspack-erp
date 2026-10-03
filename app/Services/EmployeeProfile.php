<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\EmployeeDocument;
use App\Models\EmployeePayslip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * One employee, assembled — the single definition of "their record".
 *
 * Two audiences read it and they must never disagree: the office's page for a
 * person (profile, what they were paid, their papers) and the employee's own
 * workspace (the same figures, minus anything internal). If each page built its
 * own queries, the employee's "salary this year" and the office's would drift —
 * and the first person to notice would be the employee.
 *
 * So the queries live here: the salary rows, the monthly totals, the payslips,
 * the document checklist and what is missing from the profile.
 *
 * Everything is guarded against a database that has not been migrated yet
 * (`Schema::hasTable`), because a page that says "no documents yet" is a better
 * failure than a 500 in front of somebody's payroll.
 */
class EmployeeProfile
{
    /** Which cashflow entries count as this employee's pay. */
    public function salaryQuery(User $user)
    {
        return CashflowEntry::query()
            ->where('employee_id', $user->id)
            /* Money *to* the employee. A debit filed against somebody is a
               recovery or a reimbursement, not a salary credit, and adding the
               two into one "paid" figure would overstate what they earned. */
            ->where('transaction_type', 'credit');
    }

    public function salaryEntries(User $user, ?string $from = null, ?string $to = null): Collection
    {
        if (! $this->salaryColumnExists()) {
            return collect();
        }

        return $this->salaryQuery($user)
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * What was credited, month by month — the shape of a year's pay.
     *
     * The months are walked in PHP rather than grouped in SQL: `DATE_FORMAT` is
     * MySQL-only and the same page has to work on sqlite (the test suite) and on
     * whatever the office runs.
     *
     * @return array<int, array{period: string, label: string, credit: float, entries: int, days: array<int, string>}>
     */
    public function salaryByMonth(User $user, int $year): array
    {
        $entries = $this->salaryEntries($user, $year.'-01-01', $year.'-12-31');
        $months = [];

        foreach ($entries as $entry) {
            $date = $entry->entry_date ? Carbon::parse($entry->entry_date) : null;
            $key = $date ? $date->format('Y-m') : 'unknown';

            $months[$key] ??= [
                'period' => $key,
                'label' => $date ? $date->format('F Y') : 'Undated',
                'credit' => 0.0,
                'entries' => 0,
                'days' => [],
            ];

            $months[$key]['credit'] = round($months[$key]['credit'] + (float) $entry->credit_amount, 2);
            $months[$key]['entries']++;
            $months[$key]['days'][] = $date ? $date->format('d M') : '';
        }

        /* Newest month first, and months with no pay are not invented: an empty
           month is a fact the employee can see from the gaps. */
        return array_values($months);
    }

    /** The months of a year, newest first, each with the pay that landed in it. */
    public function monthlyPay(User $user, int $year): array
    {
        $byMonth = collect($this->salaryByMonth($user, $year))->keyBy('period');
        $payslips = $this->payslips($user)->keyBy('period');
        $rows = [];

        for ($month = 12; $month >= 1; $month--) {
            $key = sprintf('%04d-%02d', $year, $month);
            $pay = $byMonth->get($key);
            $slip = $payslips->get($key);

            $rows[] = [
                'period' => $key,
                'label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'credit' => (float) ($pay['credit'] ?? 0),
                'entries' => (int) ($pay['entries'] ?? 0),
                'payslip' => $slip,
                'paid_on' => $slip?->paid_on,
            ];
        }

        return $rows;
    }

    /** The year's total pay for this employee. */
    public function yearTotal(User $user, ?int $year = null): array
    {
        $year = $year ?: (int) date('Y');
        $entries = $this->salaryEntries($user, $year.'-01-01', $year.'-12-31');

        return [
            'year' => $year,
            'total' => round((float) $entries->sum('credit_amount'), 2),
            'entries' => $entries->count(),
            'months_paid' => $entries
                ->map(fn ($entry) => $entry->entry_date ? Carbon::parse($entry->entry_date)->format('Y-m') : null)
                ->filter()->unique()->count(),
        ];
    }

    public function payslips(User $user): Collection
    {
        if (! Schema::hasTable('employee_payslips')) {
            return collect();
        }

        return EmployeePayslip::query()
            ->where('user_id', $user->id)
            ->orderByDesc('period')
            ->get();
    }

    /** The payslips an employee is allowed to open: the issued ones. */
    public function issuedPayslips(User $user): Collection
    {
        return $this->payslips($user)
            ->filter(fn (EmployeePayslip $slip) => $slip->isIssued())
            ->values();
    }

    public function documents(User $user): Collection
    {
        if (! Schema::hasTable('employee_documents')) {
            return collect();
        }

        $order = array_flip(array_keys(EmployeeDocument::TYPES));

        return EmployeeDocument::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            /* The file reads in the order the office builds it — identity first,
               "other" last — rather than by whichever was uploaded most
               recently. Sorted in PHP so the order comes from TYPES and not from
               a column that would have to be kept in step with it. */
            ->sortBy(fn (EmployeeDocument $document) => $order[$document->document_type] ?? 99)
            ->values();
    }

    /**
     * The document checklist: every type the office asks for, whether it is
     * here, and whether anybody has checked it.
     *
     * @return array{rows: array<int, array<string, mixed>>, required: int, present: int, verified: int, complete: bool}
     */
    public function documentChecklist(User $user): array
    {
        $documents = $this->documents($user)->groupBy('document_type');
        $rows = [];
        $required = 0;
        $present = 0;
        $verified = 0;

        foreach (EmployeeDocument::TYPES as $type => $definition) {
            $held = $documents->get($type, collect());
            $here = $held->isNotEmpty();
            $checked = $held->contains(fn (EmployeeDocument $document) => $document->isVerified());

            if ($definition['required']) {
                $required++;
                if ($here) {
                    $present++;
                }
            }
            if ($checked) {
                $verified++;
            }

            $rows[] = [
                'type' => $type,
                'label' => $definition['label'],
                'hint' => $definition['hint'],
                'required' => $definition['required'],
                'documents' => $held->values(),
                'count' => $held->count(),
                'present' => $here,
                'verified' => $checked,
            ];
        }

        return [
            'rows' => $rows,
            'required' => $required,
            'present' => $present,
            'verified' => $verified,
            'complete' => $required > 0 && $present === $required,
        ];
    }

    /**
     * The profile, grouped the way a personnel file is read, with the gaps
     * named.
     *
     * @return array{groups: array<int, array{title: string, fields: array<int, array{label: string, value: string}>}>, missing: array<int, string>, complete: bool}
     */
    public function profile(User $user): array
    {
        $user->loadMissing(['payslips', 'employeeDocuments']);

        $rows = [
            [
                'title' => 'Identity',
                'fields' => [
                    ['label' => 'Employee code', 'value' => $user->employee_code, 'required' => true],
                    ['label' => 'Date of joining', 'value' => $this->date($user->date_of_joining), 'required' => true],
                    ['label' => 'Date of birth', 'value' => $this->date($user->date_of_birth), 'required' => false],
                    ['label' => 'PAN', 'value' => $user->pan_number, 'required' => false],
                ],
            ],
            [
                'title' => 'Contact',
                'fields' => [
                    ['label' => 'Email', 'value' => $user->email, 'required' => true],
                    ['label' => 'Mobile', 'value' => $user->mobile, 'required' => true],
                    ['label' => 'Address', 'value' => $user->address, 'required' => false],
                    ['label' => 'Emergency contact', 'value' => trim(($user->emergency_contact_name ?? '').' '.($user->emergency_contact_mobile ?? '')), 'required' => false],
                ],
            ],
            [
                'title' => 'Employment',
                'fields' => [
                    ['label' => 'Department', 'value' => $user->department, 'required' => false],
                    ['label' => 'Designation', 'value' => $user->designation, 'required' => true],
                    ['label' => 'Employment type', 'value' => $user->employmentTypeLabel(), 'required' => false],
                    ['label' => 'Status', 'value' => $user->employmentStatusLabel(), 'required' => true],
                ],
            ],
            [
                'title' => 'Bank',
                'fields' => [
                    ['label' => 'Bank', 'value' => $user->bank_name, 'required' => false],
                    ['label' => 'Account name', 'value' => $user->bank_account_name, 'required' => false],
                    ['label' => 'Account number', 'value' => $user->bank_account_number ? $this->mask($user->bank_account_number) : null, 'required' => false],
                    ['label' => 'IFSC', 'value' => $user->bank_ifsc, 'required' => false],
                ],
            ],
        ];

        $missing = [];

        foreach ($rows as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['required'] && blank($field['value'])) {
                    $missing[] = $field['label'];
                }
            }
        }

        return [
            'groups' => $rows,
            'missing' => $missing,
            'complete' => $missing === [],
        ];
    }

    /** Only the last four digits: a page that can be screenshotted should not carry the number. */
    public function mask(?string $number): string
    {
        $number = trim((string) $number);

        if ($number === '') {
            return '';
        }

        return strlen($number) <= 4 ? $number : str_repeat('•', strlen($number) - 4).substr($number, -4);
    }

    /**
     * Everything the workspace's headline row shows, in one call.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user, ?int $year = null): array
    {
        $year = $year ?: (int) date('Y');
        $checklist = $this->documentChecklist($user);
        $profile = $this->profile($user);
        $payslips = $this->issuedPayslips($user);

        return [
            'user' => $user,
            'year' => $year,
            'total' => $this->yearTotal($user, $year),
            'payslips' => $payslips,
            'last_payslip' => $payslips->first(),
            'checklist' => $checklist,
            'profile' => $profile,
            'documents' => $this->documents($user),
        ];
    }

    /** The next code in the series, for a new employee. */
    public function nextEmployeeCode(): string
    {
        if (! Schema::hasColumn('users', 'employee_code')) {
            return '';
        }

        $prefix = 'EMP-';
        $last = User::query()
            ->whereNotNull('employee_code')
            ->where('employee_code', 'like', $prefix.'%')
            ->orderByDesc('employee_code')
            ->value('employee_code');

        $next = $last ? ((int) preg_replace('/\D/', '', substr($last, strlen($prefix)))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function salaryColumnExists(): bool
    {
        return Schema::hasTable('cashflow_entries') && Schema::hasColumn('cashflow_entries', 'employee_id');
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value)->format('d M Y') : null;
    }
}
