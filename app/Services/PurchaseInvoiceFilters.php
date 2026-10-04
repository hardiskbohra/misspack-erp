<?php

namespace App\Services;

use App\Helpers\DateRanges;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every dimension the purchase list can be filtered by, in one place.
 *
 * The listing, the chips that say what is on, the CSV the office takes to the
 * CA and the GST register all have to mean the same thing by "partly paid" and
 * by "overdue", and a filter is exactly where that agreement breaks: a chip
 * says "Nothing paid" while the query means `amount_paid = 0`, which is a
 * different set of rows the moment a payment is filed in the vendor ledger
 * without touching the opening figure.
 *
 * So the money questions are asked of the money, by the model's own rule —
 * `PurchaseInvoice::paidAmount()` for a row, the same expression in SQL for a
 * set — and this class owns only the vocabulary: the keys a URL may carry, the
 * words a chip wears, and which query keys removing a chip has to drop. The
 * SQL that applies them stays beside the controller's aggregates, because those
 * aggregates have to be written with the same expression.
 *
 * The words here are the purchase module's: a document has a **vendor**, and
 * the order that becomes a bill was **raised from** an order.
 */
class PurchaseInvoiceFilters
{
    /** The filter keys, and what an unset one means. */
    public const DEFAULTS = [
        'search' => null,
        'status' => 'all',
        'payment' => 'all',
        'currency' => 'all',
        'vendor' => 0,
        'project' => 0,
        'date_from' => null,
        'date_to' => null,
    ];

    /** The view key => the query key. The listing's own words, so a saved view speaks them. */
    public const VIEW_KEYS = [
        'search' => 'search',
        'status' => 'status',
        'payment' => 'payment',
        'currency' => 'currency',
        'vendor' => 'vendor',
        'project' => 'project',
        'dateFrom' => 'date_from',
        'dateTo' => 'date_to',
    ];

    /** The chip labels, in the order an accountant reads them. */
    public const LABELS = [
        'search' => 'Search',
        'status' => 'Status',
        'payment' => 'Payment',
        'currency' => 'Currency',
        'vendor' => 'Vendor',
        'project' => 'Project',
    ];

    /** A range: two query keys, one chip — so "remove the period" is one click. */
    public const RANGE_LABELS = [
        'period' => ['label' => 'Document date', 'keys' => ['dateFrom', 'dateTo']],
    ];

    /* `overdue` is not a stored word — the model derives it from the due date and
       the money — but it is a state the listing offers, so the strip needs a
       label for it that says the same thing `PurchaseInvoice::stateLabel()` does. */
    public const OVERDUE_LABEL = 'Overdue';

    /** What "paid" means, in the words the drawer offers. */
    public const PAYMENT_LABELS = [
        'nothing' => 'Nothing paid',
        'partial' => 'Part paid',
        'paid' => 'Fully paid',
    ];

    /**
     * The filters a request carries, with every value checked against what the
     * screen actually offers: a hand-typed `?status=garbage` is "all", not a
     * query that quietly returns nothing.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && ! array_key_exists($status, PurchaseInvoice::statusOptions() + ['overdue' => self::OVERDUE_LABEL])) {
            $status = 'all';
        }

        $payment = (string) $request->query('payment', 'all');
        if (! array_key_exists($payment, self::PAYMENT_LABELS)) {
            $payment = 'all';
        }

        $currency = (string) $request->query('currency', 'all');
        if ($currency !== 'all' && ! array_key_exists($currency, PurchaseInvoice::currencyOptions())) {
            $currency = 'all';
        }

        return [
            'search' => trim((string) $request->query('search', '')) ?: null,
            'status' => $status,
            'payment' => $payment,
            'currency' => $currency,
            'vendor' => max(0, (int) $request->query('vendor', 0)),
            'project' => max(0, (int) $request->query('project', 0)),
            'dateFrom' => DateRanges::normalise($request->query('date_from')),
            'dateTo' => DateRanges::normalise($request->query('date_to')),
        ];
    }

    /**
     * The filters that are actually narrowing something, as a query string —
     * what a saved view stores and what one screen hands to another.
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
     * The chips the "Filtered by" strip wears.
     *
     * Each carries the query keys that removing it drops — one for a filter,
     * both ends for a period — and the value in the office's own words, so the
     * strip, the drawer and the CSV cannot describe the same filter three ways.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, label: string, value: string, query: array<int, string>}>
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
                'label' => $label,
                'value' => $labels[$viewKey] ?? (string) $value,
                'query' => [$queryKey],
            ];
        }

        foreach (self::RANGE_LABELS as $viewKey => $range) {
            [$fromKey, $toKey] = $range['keys'];

            if (blank($filters[$fromKey] ?? null) && blank($filters[$toKey] ?? null)) {
                continue;
            }

            $applied[] = [
                'key' => $viewKey,
                'label' => $range['label'],
                'value' => $labels[$viewKey] ?? '',
                'query' => [self::VIEW_KEYS[$fromKey], self::VIEW_KEYS[$toKey]],
            ];
        }

        return $applied;
    }

    /**
     * What each set filter is, said the office's way — a vendor's name rather
     * than its id, a period rather than two dates.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function labels(array $filters): array
    {
        $labels = [];

        if (filled($filters['search'] ?? null)) {
            $labels['search'] = (string) $filters['search'];
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $statuses = PurchaseInvoice::statusOptions() + ['overdue' => self::OVERDUE_LABEL];
            $labels['status'] = $statuses[$filters['status']] ?? (string) $filters['status'];
        }

        if (($filters['payment'] ?? 'all') !== 'all') {
            $labels['payment'] = self::PAYMENT_LABELS[$filters['payment']] ?? (string) $filters['payment'];
        }

        if (($filters['currency'] ?? 'all') !== 'all') {
            $labels['currency'] = (string) $filters['currency'];
        }

        if ((int) ($filters['vendor'] ?? 0) > 0) {
            $labels['vendor'] = $this->nameOf('vendors', 'vendor_name', $filters['vendor']);
        }

        if ((int) ($filters['project'] ?? 0) > 0) {
            $labels['project'] = $this->projectLabel($filters['project']);
        }

        if (filled($filters['dateFrom'] ?? null) || filled($filters['dateTo'] ?? null)) {
            $labels['period'] = $this->rangeLabel($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null);
        }

        return $labels;
    }

    /**
     * The rows a listing had ticked, for "export the selected rows" — the same
     * files as the screen's, narrowed. Capped, because a GET URL is not a place
     * to put ten thousand ids.
     *
     * @return array<int, int>
     */
    public function selectedIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->query('ids', []));

        return array_slice(array_values(array_unique(array_filter($ids))), 0, 500);
    }

    /** The value a filter takes when the request says nothing about it. */
    public static function default(string $key)
    {
        $queryKey = self::VIEW_KEYS[$key] ?? $key;

        return array_key_exists($queryKey, self::DEFAULTS) ? self::DEFAULTS[$queryKey] : 'all';
    }

    /** A period, said as the two dates the office picked. */
    private function rangeLabel(?string $from, ?string $to): string
    {
        if (! $from && ! $to) {
            return '';
        }

        $format = fn (?string $date) => $date ? Carbon::parse($date)->format('d M Y') : null;

        return trim(($format($from) ?: 'The start').' → '.($format($to) ?: 'today'));
    }

    /**
     * A name for an id, asked of the table that holds it.
     *
     * The tables are optional parts of the install — an install without the
     * vendor module still lists purchase bills — so a missing table answers with
     * the id rather than failing the list.
     */
    private function nameOf(string $table, string $column, $id): string
    {
        if (! Schema::hasTable($table)) {
            return '#'.$id;
        }

        return (string) (DB::table($table)->where('id', $id)->value($column) ?: '#'.$id);
    }

    private function projectLabel($id): string
    {
        $project = Schema::hasTable('projects') ? Project::query()->find($id) : null;

        if (! $project) {
            return '#'.$id;
        }

        return trim(($project->project_number ? $project->project_number.' · ' : '').$project->name, ' ·');
    }
}
