<?php

namespace App\Services;

use App\Helpers\DateRanges;
use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every dimension the invoice list can be filtered by, in one place.
 *
 * The screen that shows an invoice, the list that links to it and the CSV the
 * accountant takes away all have to mean the same thing by "overdue" and by
 * "paid", and a filter is exactly where that agreement breaks: the chip says
 * "Paid" and the query means the stored word `status = 'paid'`, which an invoice
 * that was marked paid a year ago still carries after its receipt was deleted.
 *
 * So the money questions are asked of the money. The rule is
 * `SalesInvoice::RECEIVED_SQL` — the invoice's opening figure plus the receipts
 * filed against it — which is the same rule `SalesInvoice::receivedAmount()`
 * applies to a single invoice, and `ageingBuckets()` is the same list of buckets
 * the rows are grouped by. One spelling of each rule, used by the query, the
 * row, the figures and the CSV.
 */
class SalesInvoiceFilters
{
    /** "Rows where this dimension has nothing in it." */
    public const NOT_SET = 'none';

    /** The filter keys, and what an unset one means. */
    public const DEFAULTS = [
        'search' => null,
        'invoice_type' => 'all',
        'status' => 'all',
        'client_id' => 'all',
        'project_id' => 'all',
        'currency' => 'all',
        'gst_type' => 'all',
        'portal' => 'all',
        'payment' => 'all',
        'ageing' => 'all',
        'chase' => 'all',
        'date_from' => null,
        'date_to' => null,
        'due_from' => null,
        'due_to' => null,
    ];

    /** The chip labels, in the order an accountant reads them. */
    public const LABELS = [
        'search' => 'Search',
        'type' => 'Type',
        'status' => 'Status',
        'clientId' => 'Client',
        'projectId' => 'Project',
        'currency' => 'Currency',
        'gstType' => 'GST',
        'portal' => 'Client portal',
        'payment' => 'Payment',
        'ageing' => 'How late',
        'chase' => 'Chase',
    ];

    /** The camelCase names the views use, mapped onto the query keys. */
    public const VIEW_KEYS = [
        'search' => 'search',
        'type' => 'invoice_type',
        'status' => 'status',
        'clientId' => 'client_id',
        'projectId' => 'project_id',
        'currency' => 'currency',
        'gstType' => 'gst_type',
        'portal' => 'portal',
        'payment' => 'payment',
        'ageing' => 'ageing',
        'chase' => 'chase',
        'dateFrom' => 'date_from',
        'dateTo' => 'date_to',
        'dueFrom' => 'due_from',
        'dueTo' => 'due_to',
    ];

    /**
     * The filters that are a *pair* of dates and read as one question. They are
     * not in `LABELS` because they are not one chip per key: a period is one
     * chip that carries — and removes — both of its ends.
     */
    public const RANGE_LABELS = [
        'dateFrom' => ['label' => 'Period', 'keys' => ['dateFrom', 'dateTo']],
        'dueFrom' => ['label' => 'Due', 'keys' => ['dueFrom', 'dueTo']],
    ];

    /**
     * The chase worklist: the money, and the last time anybody asked for it.
     *
     * `due_7` is the diary — what falls due this week; `stale` is the conscience —
     * what is owed and has gone a week without a word; `nudged` is the record of
     * what the office already chased this week, so nobody is rung twice.
     */
    public const CHASE_KEYS = ['due_7', 'stale', 'nudged'];

    public const CHASE_LABELS = [
        'due_7' => 'Due within 7 days',
        'stale' => 'Not nudged in a week',
        'nudged' => 'Nudged this week',
    ];

    /** The three money states, in the order money arrives. */
    public const PAYMENT_KEYS = ['unpaid', 'partial', 'paid'];

    public const PAYMENT_LABELS = [
        'unpaid' => 'Nothing received',
        'partial' => 'Partly received',
        'paid' => 'Paid in full',
    ];

    /**
     * Everything the request asked for, defaults filled in.
     *
     * The empty ones are read as empty: a blank search box is no search, and a
     * value that names a range rather than a day (which a link or a saved view
     * can carry) is no date. Either of them reaching Carbon as if it were a
     * date is a 500 over a filter.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $filters = [];

        foreach (self::VIEW_KEYS as $viewKey => $queryKey) {
            $value = $request->query($queryKey, self::default($queryKey));

            $filters[$viewKey] = match ($queryKey) {
                'date_from', 'date_to', 'due_from', 'due_to' => DateRanges::normalise($value),
                'search' => trim((string) $value) ?: null,
                default => $value,
            };
        }

        return $filters;
    }

    /** The value a filter takes when the request says nothing about it. */
    public static function default(string $key)
    {
        $queryKey = self::VIEW_KEYS[$key] ?? $key;

        return array_key_exists($queryKey, self::DEFAULTS) ? self::DEFAULTS[$queryKey] : 'all';
    }

    /**
     * The filters that are actually set, as a query string — what a saved view
     * stores and what one screen hands to another.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function toQuery(array $filters): array
    {
        $query = [];

        foreach (self::VIEW_KEYS as $viewKey => $queryKey) {
            $value = $filters[$viewKey] ?? null;

            if ($value === null || $value === '' || $value === self::default($queryKey)) {
                continue;
            }

            $query[$queryKey] = (string) $value;
        }

        return $query;
    }

    /**
     * The filters that are narrowing something, with the words their chip wears.
     *
     * @param  array<string, mixed>  $filters
     * Each chip carries the query keys it removes — one for a single filter,
     * two for a date range, so "remove the period" is one click and not two.
     *
     * @return array<int, array{key: string, query: array<int, string>, label: string, value: string}>
     */
    public function applied(array $filters): array
    {
        $labels = $this->labels($filters);
        $applied = [];

        foreach (self::LABELS as $viewKey => $label) {
            $value = $filters[$viewKey] ?? null;
            $queryKey = self::VIEW_KEYS[$viewKey] ?? $viewKey;

            if ($value === null || $value === '' || $value === self::default($queryKey)) {
                continue;
            }

            $applied[] = [
                'key' => $viewKey,
                'query' => [$queryKey],
                'label' => $label,
                'value' => $labels[$viewKey] ?? (string) $value,
            ];
        }

        foreach (self::RANGE_LABELS as $viewKey => $range) {
            [$fromKey, $toKey] = $range['keys'];

            if (blank($filters[$fromKey] ?? null) && blank($filters[$toKey] ?? null)) {
                continue;
            }

            $applied[] = [
                'key' => $viewKey,
                'query' => [self::VIEW_KEYS[$fromKey], self::VIEW_KEYS[$toKey]],
                'label' => $range['label'],
                'value' => $labels[$viewKey] ?? '',
            ];
        }

        return $applied;
    }

    /**
     * Turn the filters into SQL.
     *
     * Dates are compared as dates through Eloquent, never as SQL functions of
     * "now": the database and the office do not share a clock, and `CURDATE()`
     * and `date('now')` are two different dialects of the same wish.
     *
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $value = fn (string $key, ?string $default = null) => array_key_exists($key, $filters)
            ? $filters[$key]
            : ($default ?? self::default($key));
        $set = fn (string $key, string $default = 'all') => $value($key, $default) !== null
            && $value($key, $default) !== ''
            && (string) $value($key, $default) !== $default;

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
            'type' => 'invoice_type',
            'status' => 'status',
            'clientId' => 'client_id',
            'projectId' => 'project_id',
            'currency' => 'currency',
            'gstType' => 'gst_type',
        ] as $key => $name) {
            $query = $column($query, $key, $name);
        }

        $today = Carbon::today();

        /* "Still owed" is the same condition in every money question below: the
           invoice is not a draft or a cancellation, and the money rule is short
           of the total. Written once here, and `SalesInvoice::balanceDue()`
           asks it of a single row. */
        $open = fn (Builder $q) => $q
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereRaw(SalesInvoice::RECEIVED_SQL.' < sales_invoices.total_amount - 0.01');

        return $query
            ->when($set('portal'), fn (Builder $q) => $q->where('show_client_portal', $value('portal') === 'visible'))
            /* the money filter: the ledger's receipts plus the opening figure */
            ->when(in_array((string) $value('payment'), self::PAYMENT_KEYS, true), function (Builder $q) use ($value) {
                $received = SalesInvoice::RECEIVED_SQL;

                return match ((string) $value('payment')) {
                    'unpaid' => $q->where(fn (Builder $inner) => $inner
                        ->whereNull('amount_paid')->orWhere('amount_paid', '<=', 0.01))
                        ->whereDoesntHave('payments'),
                    'partial' => $q->whereRaw("{$received} > 0.01")
                        ->whereRaw("{$received} < sales_invoices.total_amount - 0.01"),
                    'paid' => $q->whereRaw("sales_invoices.total_amount > 0")
                        ->whereRaw("{$received} >= sales_invoices.total_amount - 0.01"),
                };
            })
            /* how late, in the buckets `SalesInvoice::ageingBuckets()` names */
            ->when($set('ageing'), function (Builder $q) use ($value, $open, $today) {
                /* The buckets are the model's list, and the word for all of them
                   is `overdue` — the value the chip sends. A value this module
                   does not know **widens** the list: an unknown filter is a
                   filter that failed, and showing everything is the honest
                   answer to a question nobody asked. */
                $bucket = (string) $value('ageing');

                if ($bucket === 'current') {
                    return $q->where($open)->where(fn (Builder $inner) => $inner
                        ->whereNull('due_date')->orWhereDate('due_date', '>=', $today));
                }

                if (! in_array($bucket, array_merge(['overdue'], array_keys(SalesInvoice::ageingBuckets())), true)) {
                    return $q;
                }

                $q->where($open)->whereNotNull('due_date');

                if ($bucket === 'overdue') {
                    return $q->whereDate('due_date', '<', $today);
                }

                /* One arm per bucket the model names — the guard in
                   `tools/checks/invoices-check.cjs` holds the two lists
                   together. `default` is unreachable (an unknown value widened
                   above) and stays unwidened by design: a bucket added to the
                   model must fail loudly here, not filter to nothing. */
                return match ($bucket) {
                    '1_30' => $q->whereDate('due_date', '<', $today)->whereDate('due_date', '>=', $today->copy()->subDays(30)),
                    '31_60' => $q->whereDate('due_date', '<', $today->copy()->subDays(30))->whereDate('due_date', '>=', $today->copy()->subDays(60)),
                    '61_90' => $q->whereDate('due_date', '<', $today->copy()->subDays(60))->whereDate('due_date', '>=', $today->copy()->subDays(90)),
                    '90_plus' => $q->whereDate('due_date', '<', $today->copy()->subDays(90)),
                    default => $q,
                };
            })
            /* who to chase today: still owed, and either due this week or a week
               without a word. "Asked" is the reminder log — a week is the office's
               own rhythm, and the same week the chip counts use. */
            ->when(in_array((string) $value('chase'), self::CHASE_KEYS, true), function (Builder $q) use ($value, $open, $today) {
                $q->where($open);

                $week = $today->copy()->subDays(7);

                return match ((string) $value('chase')) {
                    'due_7' => $q->whereNotNull('due_date')
                        ->whereDate('due_date', '>=', $today)
                        ->whereDate('due_date', '<=', $today->copy()->addDays(7)),
                    'stale' => $q->whereDoesntHave('reminders',
                        fn ($reminders) => $reminders->whereDate('reminded_at', '>=', $week)),
                    default => $q->whereHas('reminders',
                        fn ($reminders) => $reminders->whereDate('reminded_at', '>=', $week)),
                };
            })
            ->when($value('dateFrom'), fn (Builder $q) => $q->whereDate('invoice_date', '>=', $value('dateFrom')))
            ->when($value('dateTo'), fn (Builder $q) => $q->whereDate('invoice_date', '<=', $value('dateTo')))
            ->when($value('dueFrom'), fn (Builder $q) => $q->whereDate('due_date', '>=', $value('dueFrom')))
            ->when($value('dueTo'), fn (Builder $q) => $q->whereDate('due_date', '<=', $value('dueTo')));
    }

    /**
     * What each set filter's value should read as on a chip: the client's name,
     * not their id; "Paid in full", not `paid`.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function labels(array $filters): array
    {
        $notSet = fn ($value) => (string) $value === self::NOT_SET ? 'Not set' : (string) $value;
        $lookup = fn (array $options, $key) => (string) ($options[(string) $key] ?? '');

        return [
            'search' => (string) ($filters['search'] ?? ''),
            'type' => $lookup(SalesInvoice::typeOptions(), $filters['type'] ?? ''),
            'status' => $lookup(SalesInvoice::statusOptions(), $filters['status'] ?? ''),
            'clientId' => $this->label($notSet, $this->nameOf('clients', 'company_name', $filters['clientId'] ?? null), $filters['clientId'] ?? null),
            'projectId' => $this->label($notSet, $this->projectLabel($filters['projectId'] ?? null), $filters['projectId'] ?? null),
            'currency' => $notSet(strtoupper((string) ($filters['currency'] ?? ''))),
            'gstType' => $lookup(SalesInvoice::gstTypeOptions(), $filters['gstType'] ?? ''),
            'portal' => ($filters['portal'] ?? '') === 'visible' ? 'Visible to client' : 'Hidden from client',
            'payment' => $lookup(self::PAYMENT_LABELS, $filters['payment'] ?? ''),
            'ageing' => $lookup(SalesInvoice::ageingBuckets(), $filters['ageing'] ?? '') ?: 'Late',
            'chase' => $lookup(self::CHASE_LABELS, $filters['chase'] ?? '') ?: 'Chase',
            'dateFrom' => $this->rangeLabel($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null),
            'dueFrom' => $this->rangeLabel($filters['dueFrom'] ?? null, $filters['dueTo'] ?? null),
        ];
    }

    /**
     * What a date range reads as: the preset's name when the two ends are one
     * ("This month"), otherwise the two days it spans.
     */
    private function rangeLabel(?string $from, ?string $to): string
    {
        if ($preset = DateRanges::label(DateRanges::keyOf($from, $to))) {
            return $preset;
        }

        return DateRanges::display($from, 'start').' → '.DateRanges::display($to, 'today');
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
