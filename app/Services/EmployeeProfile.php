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
    /**
     * Every cashflow entry the ledger filed against this person — both ways.
     *
     * Not "the credits": the ledger is the company's cash book, so a **debit**
     * is money that left it and that is what paying somebody looks like. The
     * office's own ledger defaults to a debit, and a ₹1,00,000 salary debit sat
     * in it while this record said ₹0 — the direction was assumed instead of
     * read. A **credit** filed against a person is money that came back (an
     * advance repaid, a recovery), so it is read too, with the opposite sign.
     */
    public function salaryQuery(User $user)
    {
        return CashflowEntry::query()->where('employee_id', $user->id);
    }

    /**
     * What was paid to this person, and what came back — one definition, read
     * by every figure on both sides of the module (the office's record page and
     * the employee's own pages), so the two can never disagree.
     *
     * @return array{paid: float, recovered: float, total: float, entries: int, payments: int, months_paid: int, pending: int}
     */
    public function payTotals(User $user, ?string $from = null, ?string $to = null): array
    {
        return $this->payFrom($this->salaryEntries($user, $from, $to));
    }

    /**
     * The arithmetic of pay, over rows already in hand.
     *
     * Split from the query on purpose: this is the part that was wrong (a
     * credit-only reading of a debit-only ledger) and it is the part a test can
     * run without a database — see tests/Unit/EmployeePayTest.php, which builds
     * the rows in memory and asserts what the figures come to.
     *
     * @param  Collection<int, CashflowEntry>  $entries
     * @return array{paid: float, recovered: float, total: float, entries: int, payments: int, months_paid: int, pending: int}
     */
    public function payFrom(Collection $entries): array
    {
        $paid = 0.0;
        $recovered = 0.0;

        foreach ($entries as $entry) {
            $signed = $entry->signedAmount();

            $signed >= 0 ? $paid += $signed : $recovered += -$signed;
        }

        return [
            'paid' => round($paid, 2),
            'recovered' => round($recovered, 2),
            /* net, because both are real movements of the office's money */
            'total' => round($paid - $recovered, 2),
            'entries' => $entries->count(),
            'payments' => $entries->filter(fn ($entry) => $entry->signedAmount() > 0)->count(),
            'months_paid' => $entries
                ->filter(fn ($entry) => $entry->signedAmount() > 0 && $entry->entry_date)
                ->map(fn ($entry) => Carbon::parse($entry->entry_date)->format('Y-m'))
                ->unique()
                ->count(),
            'pending' => $entries
                ->filter(fn ($entry) => in_array($entry->accounting_status, ['pending', 'disputed'], true))
                ->count(),
        ];
    }

    /**
     * What left the company for the whole team between two days — the users
     * list's "paid this month" tile. The same query a person's record reads,
     * without the person: a tile and a page cannot mean two different things.
     */
    public function paidToTeam(?string $from = null, ?string $to = null): float
    {
        if (! $this->salaryColumnExists()) {
            return 0.0;
        }

        return round((float) CashflowEntry::query()
            ->moneyOut()
            ->whereNotNull('employee_id')
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->sum('debit_amount'), 2);
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
     * What was paid, month by month — the shape of a year's pay.
     *
     * The months are walked in PHP rather than grouped in SQL: `DATE_FORMAT` is
     * MySQL-only and the same page has to work on sqlite (the test suite) and on
     * whatever the office runs.
     *
     * @return array<int, array{period: string, label: string, paid: float, recovered: float, net: float, entries: int, payments: int, days: array<int, string>}>
     */
    public function salaryByMonth(User $user, int $year): array
    {
        return $this->monthsFrom($this->salaryEntries($user, $year.'-01-01', $year.'-12-31'));
    }

    /**
     * The month strip's own arithmetic, over rows already in hand — the same
     * split as payFrom(), for the same reason.
     *
     * @param  Collection<int, CashflowEntry>  $entries
     * @return array<int, array{period: string, label: string, paid: float, recovered: float, net: float, entries: int, payments: int, days: array<int, string>}>
     */
    public function monthsFrom(Collection $entries): array
    {
        $months = [];

        foreach ($entries as $entry) {
            $date = $entry->entry_date ? Carbon::parse($entry->entry_date) : null;
            $key = $date ? $date->format('Y-m') : 'unknown';

            $months[$key] ??= [
                'period' => $key,
                'label' => $date ? $date->format('F Y') : 'Undated',
                'paid' => 0.0,
                'recovered' => 0.0,
                'net' => 0.0,
                'entries' => 0,
                'payments' => 0,
                'days' => [],
            ];

            $signed = $entry->signedAmount();

            if ($signed >= 0) {
                $months[$key]['paid'] = round($months[$key]['paid'] + $signed, 2);
                $months[$key]['payments']++;
            } else {
                $months[$key]['recovered'] = round($months[$key]['recovered'] - $signed, 2);
            }

            $months[$key]['net'] = round($months[$key]['paid'] - $months[$key]['recovered'], 2);
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
                'paid' => (float) ($pay['paid'] ?? 0),
                'recovered' => (float) ($pay['recovered'] ?? 0),
                'net' => (float) ($pay['net'] ?? 0),
                'entries' => (int) ($pay['entries'] ?? 0),
                'payments' => (int) ($pay['payments'] ?? 0),
                'payslip' => $slip,
                'paid_on' => $slip?->paid_on,
            ];
        }

        return $rows;
    }

    /**
     * The year's total pay for this employee — the same rows the page lists,
     * added up the same way, because a total that disagrees with the table
     * under it is a total nobody trusts.
     */
    public function yearTotal(User $user, ?int $year = null): array
    {
        $year = $year ?: (int) date('Y');

        return ['year' => $year] + $this->payTotals($user, $year.'-01-01', $year.'-12-31');
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

    /** The years this person has any pay in, newest first (this year always). */
    public function salaryYears(User $user): array
    {
        $years = $this->salaryEntries($user)
            ->map(fn ($entry) => $entry->entry_date ? (int) date('Y', strtotime((string) $entry->entry_date)) : null)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $current = (int) date('Y');

        if (! in_array($current, $years, true)) {
            array_unshift($years, $current);
        }

        return $years;
    }

    /**
     * The bills filed against this person's pay — a bank slip, a signed
     * voucher, whatever the office attached to the entry that paid them.
     *
     * Read through the ledger on purpose: the attachment belongs to the entry,
     * and an employee who can see the entry but not its paper would be looking
     * at half the record.
     */
    public function attachments(User $user, int $limit = 24): Collection
    {
        if (! $this->salaryColumnExists() || ! Schema::hasTable('cashflow_attachments')) {
            return collect();
        }

        return \App\Models\CashflowAttachment::query()
            ->whereIn('cashflow_entry_id', $this->salaryQuery($user)->select('id'))
            ->with('cashflowEntry')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** The work assigned to this person, open first then newest. */
    public function tasks(User $user, int $limit = 25): Collection
    {
        if (! Schema::hasTable('tasks')) {
            return collect();
        }

        return \App\Models\Task::query()
            ->where('assignee_id', $user->id)
            ->orderByRaw("case when status = 'completed' then 1 else 0 end")
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->limit($limit)
            ->get();
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
