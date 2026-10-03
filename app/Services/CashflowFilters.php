<?php

namespace App\Services;

use App\Helpers\DateRanges;
use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every dimension the cashflow ledger can be filtered by, in one place.
 *
 * Three screens now ask the same question of the same table — the ledger, the
 * archive's "missing documents" view and the analysis builder — and each of
 * them links to the others (a report cell drills into the ledger it was built
 * from). If each screen parsed its own query string, a filter would mean one
 * thing on the report and something slightly different in the rows it opens;
 * that is exactly the bug a drill-down makes visible.
 *
 * So: the *names* of the filters are parsed here, the *SQL* they turn into is
 * built here, and a screen only decides how to draw them. Adding a dimension
 * means adding it once, and every screen that can filter by it — including the
 * one that renders the chip that removes it — gets it together.
 *
 * `any()` is the honest default throughout: an unknown or empty value widens
 * the report rather than narrowing it to nothing.
 */
class CashflowFilters
{
    /**
     * "Rows where this dimension has nothing in it."
     *
     * A report has a "Not set" row — the entries nobody was named against — and
     * the ledger has to be able to open it. Sending the value `none` is how a
     * link asks for that row; no dimension has a real value spelled `none`, so
     * the word is free.
     */
    public const NOT_SET = 'none';

    /** The same thing as a row key inside the report, where 'none' is a string value. */
    public const NOT_SET_KEY = '__none';

    /**
     * The filter keys, and what an unset one means.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'search' => null,
        'account_id' => 'all',
        'account_type' => 'all',
        'category_id' => 'all',
        'transaction_type' => 'all',
        'accounting_status' => 'all',
        'related_party_type' => 'all',
        'client_id' => 'all',
        'vendor_id' => 'all',
        'employee_id' => 'all',
        'project_id' => 'all',
        'payment_mode' => 'all',
        'expense_head' => 'all',
        'currency' => 'all',
        'date_from' => null,
        'date_to' => null,
        'documents' => 'all',
    ];

    /** The chip labels, in the order an accountant reads them. */
    public const LABELS = [
        'search' => 'Search',
        'accountId' => 'Account',
        'accountType' => 'Account type',
        'categoryId' => 'Category',
        'transactionType' => 'Type',
        'accountingStatus' => 'Status',
        'relatedPartyType' => 'Related to',
        'clientId' => 'Client',
        'vendorId' => 'Vendor',
        'employeeId' => 'Employee',
        'projectId' => 'Project',
        'paymentMode' => 'Payment mode',
        'expenseHead' => 'Expense head',
        'currency' => 'Currency',
        'dateFrom' => 'Dates',
        'dateTo' => 'Dates',
        'documents' => 'Documents',
    ];

    /**
     * The camelCase names the views already use, mapped onto the query keys.
     *
     * A view keeps its own vocabulary (it has been reading `$accountId` for a
     * year); this is the one place the two spellings are tied together.
     *
     * @var array<string, string>
     */
    public const VIEW_KEYS = [
        'search' => 'search',
        'accountId' => 'account_id',
        'accountType' => 'account_type',
        'categoryId' => 'category_id',
        'transactionType' => 'transaction_type',
        'accountingStatus' => 'accounting_status',
        'relatedPartyType' => 'related_party_type',
        'clientId' => 'client_id',
        'vendorId' => 'vendor_id',
        'employeeId' => 'employee_id',
        'projectId' => 'project_id',
        'paymentMode' => 'payment_mode',
        'expenseHead' => 'expense_head',
        'currency' => 'currency',
        'dateFrom' => 'date_from',
        'dateTo' => 'date_to',
        'documents' => 'documents',
    ];

    /**
     * Everything the request asked for, defaults filled in, ready to be handed
     * to `apply()` or straight to a view.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $filters = [];

        foreach (self::VIEW_KEYS as $viewKey => $queryKey) {
            $value = $request->query($queryKey, self::DEFAULTS[$queryKey] ?? 'all');

            /* The two date filters are dates or nothing. Every other key has a
               sentinel that widens the query ("all"); for a date the sentinel is
               null, so a value that names a range — which a link or a saved view
               can carry — must not reach Carbon as if it were a day. */
            $filters[$viewKey] = in_array($queryKey, ['date_from', 'date_to'], true)
                ? DateRanges::normalise($value)
                : $value;
        }

        return $filters;
    }

    /**
     * The query-string form of the filters that are actually set — what travels
     * when one screen links to another (and what a drill-down carries).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function toQuery(array $filters): array
    {
        $query = [];

        foreach (self::VIEW_KEYS as $viewKey => $queryKey) {
            $value = $filters[$viewKey] ?? null;
            $default = self::DEFAULTS[$queryKey] ?? 'all';

            if ($value === null || $value === '' || $value === $default) {
                continue;
            }

            $query[$queryKey] = (string) $value;
        }

        return $query;
    }

    /**
     * The filters that are actually narrowing something — a label and the value
     * as it should read on an applied chip.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, query: string, label: string, value: string}>
     */
    public function applied(array $filters): array
    {
        $applied = [];

        foreach (self::LABELS as $viewKey => $label) {
            $value = $filters[$viewKey] ?? null;
            $queryKey = self::VIEW_KEYS[$viewKey] ?? $viewKey;

            if ($value === null || $value === '' || $value === (self::DEFAULTS[$queryKey] ?? 'all')) {
                continue;
            }

            $applied[] = [
                'key' => $viewKey,
                'query' => $queryKey,
                'label' => $label,
                'value' => (string) $value,
            ];
        }

        return $applied;
    }

    /**
     * Turn the filters into SQL.
     *
     * Relationships are `whereHas` rather than joins on purpose: a join would
     * duplicate rows the moment a party has more than one matching record, and a
     * report that counts money cannot afford a second copy of a row.
     *
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $value = fn (string $key, string $default = 'all') => $filters[$key] ?? $default;
        $set = fn (string $key, string $default = 'all') => $value($key, $default) !== null
            && $value($key, $default) !== ''
            && (string) $value($key, $default) !== $default;

        /* Each column filter goes through the same three-way question: is it
           set, is the value "nothing filled in", or is it a value. Writing it
           once means the employee link, the vendor link and the expense head
           all gained it together. */
        $column = function (Builder $query, string $key, string $column, string $default = 'all') use ($value, $set) {
            if (! $set($key, $default)) {
                return $query;
            }

            if ((string) $value($key, $default) === self::NOT_SET) {
                return $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''));
            }

            return $query->where($column, $value($key, $default));
        };

        $query = $query->search($value('search'));

        foreach ([
            'accountId' => 'account_id',
            'categoryId' => 'category_id',
            'transactionType' => 'transaction_type',
            'accountingStatus' => 'accounting_status',
            'relatedPartyType' => 'related_party_type',
            'clientId' => 'client_id',
            'vendorId' => 'vendor_id',
            'employeeId' => 'employee_id',
            'projectId' => 'project_id',
            'paymentMode' => 'payment_mode',
            'currency' => 'currency',
            'expenseHead' => 'expense_head',
        ] as $key => $name) {
            $query = $column($query, $key, $name);
        }

        return $query
            ->when($set('accountType'), fn (Builder $q) => $q->whereHas('account', fn ($a) => $a->where('account_type', $value('accountType'))))
            ->when($value('dateFrom'), fn (Builder $q) => $q->whereDate('entry_date', '>=', $value('dateFrom')))
            ->when($value('dateTo'), fn (Builder $q) => $q->whereDate('entry_date', '<=', $value('dateTo')))
            ->when($value('documents') === 'missing', fn (Builder $q) => $q->whereDoesntHave('attachments'))
            ->when($value('documents') === 'attached', fn (Builder $q) => $q->whereHas('attachments'));
    }

    /**
     * The label a filter's value should wear on a chip: the account's name, not
     * its id; the employee's name, not their id.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>  viewKey => display value
     */
    public function labels(array $filters): array
    {
        /* A currency filter holds a code (INR / USD / RMB), which is what the
           entries store and what the select offers — the rupee sign belongs on
           amounts, not on the name of a currency. */
        $code = fn ($value) => strtoupper((string) $value);
        $raw = fn ($value) => (string) $value;

        /* A filter that asks for the rows where nothing was filled in reads
           "Not set" — on the report row and on the ledger's chip alike. */
        $notSet = fn ($value) => (string) $value === self::NOT_SET ? 'Not set' : $value;

        return [
            'search' => $raw($filters['search'] ?? ''),
            'accountId' => $this->label($notSet, $this->nameOf('cashflow_accounts', 'account_name', $filters['accountId'] ?? null), $filters['accountId'] ?? null),
            'accountType' => (string) (CashflowAccount::typeOptions()[$filters['accountType'] ?? ''] ?? ''),
            'categoryId' => $this->label($notSet, $this->nameOf('cashflow_categories', 'name', $filters['categoryId'] ?? null), $filters['categoryId'] ?? null),
            'transactionType' => (string) (CashflowEntry::transactionTypeOptions()[$filters['transactionType'] ?? ''] ?? ''),
            'accountingStatus' => (string) (CashflowEntry::accountingStatusOptions()[$filters['accountingStatus'] ?? ''] ?? ''),
            'relatedPartyType' => (string) (CashflowEntry::relatedPartyOptions()[$filters['relatedPartyType'] ?? ''] ?? ''),
            'clientId' => $this->label($notSet, $this->nameOf('clients', 'company_name', $filters['clientId'] ?? null), $filters['clientId'] ?? null),
            'vendorId' => $this->label($notSet, $this->nameOf('vendors', 'vendor_name', $filters['vendorId'] ?? null), $filters['vendorId'] ?? null),
            'employeeId' => $this->label($notSet, $this->nameOf('users', 'name', $filters['employeeId'] ?? null), $filters['employeeId'] ?? null),
            'projectId' => $this->label($notSet, $this->projectLabel($filters['projectId'] ?? null), $filters['projectId'] ?? null),
            'paymentMode' => (string) (CashflowEntry::paymentModeOptions()[$filters['paymentMode'] ?? ''] ?? ''),
            'expenseHead' => $notSet((string) ($filters['expenseHead'] ?? '')),
            'currency' => $notSet($code($filters['currency'] ?? '')),
            'documents' => ($filters['documents'] ?? 'all') === 'missing' ? 'Missing' : 'Filed',
        ];
    }

    /** A chip's value: the name it resolved to, the sentinel's words, or the raw value. */
    private function label(callable $notSet, string $resolved, $raw): string
    {
        if ((string) $raw === self::NOT_SET) {
            return $notSet($raw);
        }

        return $resolved !== '' ? $resolved : (string) $raw;
    }

    /** A name from a table the module may or may not have — never a fatal query. */
    private function nameOf(string $table, string $column, $id): string
    {
        $id = (int) $id;

        if ($id <= 0 || ! Schema::hasTable($table)) {
            return '';
        }

        return (string) (DB::table($table)->where('id', $id)->value($column) ?? '');
    }

    private function projectLabel($id): string
    {
        $id = (int) $id;

        if ($id <= 0 || ! Schema::hasTable('projects')) {
            return '';
        }

        $project = DB::table('projects')->where('id', $id)->first(['name', 'project_number']);

        if (! $project) {
            return '';
        }

        return trim(($project->project_number ? $project->project_number.' — ' : '').($project->name ?? ''));
    }
}
