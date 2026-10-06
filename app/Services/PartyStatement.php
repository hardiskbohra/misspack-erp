<?php

namespace App\Services;

use App\Helpers\DateRanges;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\ProjectPayment;
use App\Models\SalesInvoice;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * A statement of account for one party, one period, one currency.
 *
 * The four jobs this exists to remove are the statements the office writes by
 * hand: open the ledger, filter to the client, copy the rows into Excel, add up
 * the balance, convert the vendor's RMB, and paste it into an email. Every one
 * of those steps is a chance to send a number that does not match the books, so
 * the statement is **computed from the ledger at the moment it is opened** —
 * never stored, never a copy that goes stale when a bill is booked late.
 *
 * ## What each side is built from
 *
 * A client's statement pairs the two halves of a receivable: the sales invoices
 * raised (debit) and the money received (credit). Balance = debit − credit, so
 * a positive balance is what the client still owes — a receivable.
 *
 * A vendor's statement is the vendor's **own currency ledger**
 * (`vendor_payment_entries`: credit = bill raised, debit = paid). Balance =
 * credit − debit, so a positive balance is what we still owe — a payable, in
 * the currency the vendor quoted. When a vendor has no currency-ledger rows at
 * all (everything filed only as cashflow), the statement falls back to the
 * cashflow entries rather than printing an empty page.
 *
 * ## Why a statement is per currency
 *
 * A client billed in USD and paid in rupees has two accounts, not one, and
 * adding them together is how a statement becomes wrong by the exchange rate.
 * So a statement covers exactly one currency: rows in it carry the running
 * balance, rows in any other currency are reported separately at the foot of
 * the statement — "also in USD: 2 invoices, 1 receipt" — with a link to issue
 * that statement instead. Nothing is hidden and nothing is added up twice.
 *
 * Money is converted for what is printed beside it, never for the arithmetic:
 * the running balance of a foreign statement is in that foreign currency.
 */
class PartyStatement
{
    /**
     * Ageing buckets, in the order an accountant reads them.
     *
     * "Not due" first, then days *past due* — 31–60 means the due date has
     * passed by a month, not that the bill is a month old. For a vendor, whose
     * bills carry no due date, the age is counted from the bill date and the
     * block says so.
     */
    public const BUCKETS = [
        'current' => 'Not due',
        'd1_30' => '1–30 days',
        'd31_60' => '31–60 days',
        'd61_90' => '61–90 days',
        'd90_plus' => '90+ days',
    ];

    private Carbon $today;

    public function __construct(?Carbon $today = null)
    {
        /* The office's today, not the server's: an ageing bucket that flips at
           UTC midnight would age a bill a day early for a business in IST. */
        $this->today = ($today ?: DateRanges::today())->copy()->startOfDay();
    }

    /* ------------------------------------------------------------- parties */

    /** client · vendor — the same words the rest of the app uses. */
    public static function partyTypes(): array
    {
        return \App\Models\PartyStatementShare::PARTY_TYPES;
    }

    /**
     * The parties that can be sent a statement, for a picker. Capped: a select
     * with ten thousand names is slower than typing three letters.
     */
    public function parties(string $type, ?string $search = null, int $limit = 400): Collection
    {
        $term = trim((string) $search);

        if ($type === 'vendor') {
            if (! class_exists(Vendor::class) || ! Schema::hasTable('vendors')) {
                return collect();
            }

            return Vendor::query()
                ->when($term !== '', fn ($q) => $q->where('vendor_name', 'like', '%'.$term.'%'))
                ->orderBy('vendor_name')
                ->limit($limit)
                ->pluck('vendor_name', 'id');
        }

        if (! class_exists(Client::class) || ! Schema::hasTable('clients')) {
            return collect();
        }

        return Client::query()
            ->when($term !== '', fn ($q) => $q->where('company_name', 'like', '%'.$term.'%'))
            ->orderBy('company_name')
            ->limit($limit)
            ->pluck('company_name', 'id');
    }

    public function findParty(string $type, int $id): Client|Vendor|null
    {
        return $type === 'vendor' ? Vendor::find($id) : Client::find($id);
    }

    public function partyName(string $type, $party): string
    {
        return (string) ($type === 'vendor' ? ($party?->vendor_name ?? '') : ($party?->company_name ?? ''));
    }

    /**
     * The currencies this party keeps accounts in, the one they prefer first.
     *
     * A statement is offered in each of them because each is a different
     * statement — not because one is a conversion of the other.
     */
    public function currencies(string $type, int $id, bool $clientPortal = false): array
    {
        $preferred = strtoupper((string) ($this->findParty($type, $id)?->preferred_currency ?: ''));
        $found = [];

        if ($type === 'vendor') {
            if (Schema::hasTable('vendor_payment_entries')) {
                $found = VendorPaymentEntry::where('vendor_id', $id)
                    ->select('foreign_currency')->distinct()->pluck('foreign_currency')->all();
            }
        } elseif ($clientPortal) {
            $client = $this->findParty('client', $id);
            if ($client instanceof Client) {
                $invoices = $this->clientPortalInvoices($client);
                $payments = $this->clientPortalProjectPayments($id);
                $excludedCashflowIds = $this->projectPaymentCashflowIds($payments);
                $receipts = $this->clientPortalInvoiceCashflowEntries(
                    $id,
                    $invoices->modelKeys(),
                    $excludedCashflowIds
                );

                $found = array_merge(
                    $invoices->pluck('currency')->all(),
                    $payments->pluck('currency')->all(),
                    $receipts->pluck('currency')->all()
                );
            }
        } else {
            if (Schema::hasTable('sales_invoices')) {
                $found = SalesInvoice::where('client_id', $id)
                    ->select('currency')->distinct()->pluck('currency')->all();
            }
            if (Schema::hasTable('cashflow_entries')) {
                $found = array_merge($found, CashflowEntry::where('client_id', $id)
                    ->select('currency')->distinct()->pluck('currency')->all());
            }
        }

        $found = array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper((string) $code),
            $found
        ))));

        sort($found);

        if ($preferred === '') {
            $preferred = $type === 'vendor'
                ? (string) ($found[0] ?? 'RMB')
                : 'INR';
        }

        return array_values(array_unique(array_merge([$preferred], $found)));
    }

    /** The statement currency when nobody has chosen: what the party quotes in. */
    public function defaultCurrency(string $type, int $id): string
    {
        return $this->currencies($type, $id)[0] ?? ($type === 'vendor' ? 'RMB' : 'INR');
    }

    /** The month the office is closing when the screen opens. */
    public function defaultPeriod(): array
    {
        $preset = DateRanges::presets($this->today)['this_month'];

        return [Carbon::parse($preset['from']), Carbon::parse($preset['to'])];
    }

    /* ------------------------------------------------------------ the list */

    /**
     * One line per party **and currency**: what the statements surface shows.
     *
     * Grouping by currency rather than by party is deliberate. A vendor billed
     * in RMB and paid in rupees has two balances, and a single line would have
     * to either add them (wrong) or pick one (a lie by omission). Two lines is
     * what the ledger actually says.
     *
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float|int>}
     */
    public function activity(string $type, ?string $from, ?string $to, ?string $search = null, ?string $currency = null): array
    {
        $currency = strtoupper((string) ($currency ?: 'INR'));
        $parties = $this->parties($type, $search);
        $ids = $parties->keys()->map(fn ($id) => (int) $id)->all();

        $lines = [];

        if ($ids !== []) {
            $lines = $type === 'vendor'
                ? $this->vendorActivity($ids, $from, $to)
                : $this->clientActivity($ids, $from, $to);
        }

        $rows = [];
        $totals = ['debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0, 'parties' => 0, 'balanced' => 0];

        foreach ($lines as $key => $line) {
            if ($line['currency'] !== $currency) {
                continue;
            }

            /* The columns are the ledger's own — client: debit = invoice
               raised, credit = receipt; vendor: credit = bill, debit = payment
               — so the balance moves the way that party's account moves, and
               the sign is decided once, by type. */
            $movement = $type === 'vendor'
                ? $line['credit'] - $line['debit']
                : $line['debit'] - $line['credit'];
            $closing = round($line['opening'] + $movement, 2);

            /* A party with no movement this period but a balance carried into
               it still belongs on the list — that is exactly the statement
               somebody forgot to send. */
            if ($line['count'] === 0 && abs($closing) < 0.005) {
                continue;
            }

            [$partyId, $code] = explode('|', $key);

            $rows[] = [
                'party_id' => (int) $partyId,
                'currency' => $line['currency'],
                'name' => (string) ($parties[(int) $partyId] ?? 'Unknown'),
                'opening' => round($line['opening'], 2),
                'debit' => round($line['debit'], 2),
                'credit' => round($line['credit'], 2),
                'closing' => $closing,
                'count' => (int) $line['count'],
                'last_date' => $line['last_date'],
            ];

            $totals['debit'] += $line['debit'];
            $totals['credit'] += $line['credit'];
            $totals['closing'] += $closing;
            $totals['balanced'] += abs($closing) >= 0.005 ? 1 : 0;
        }

        usort($rows, fn ($a, $b) => [$b['last_date'] ?? '', $a['name']] <=> [$a['last_date'] ?? '', $b['name']]);

        $totals['parties'] = count($rows);
        $totals['debit'] = round($totals['debit'], 2);
        $totals['credit'] = round($totals['credit'], 2);
        $totals['closing'] = round($totals['closing'], 2);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** @param array<int, int> $ids */
    private function clientActivity(array $ids, ?string $from, ?string $to): array
    {
        $lines = [];

        $add = function (int $partyId, string $code, string $date, float $debit, float $credit, ?int $count) use (&$lines, $from, $to) {
            $key = $partyId.'|'.$code;
            $lines[$key] ??= [
                'currency' => $code, 'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0,
                'count' => 0, 'last_date' => null,
            ];

            $before = $from !== null && $date < $from;
            $after = $to !== null && $date > $to;

            if ($before) {
                $lines[$key]['opening'] += $debit - $credit;

                return;
            }

            if ($after) {
                return;
            }

            $lines[$key]['debit'] += $debit;
            $lines[$key]['credit'] += $credit;
            $lines[$key]['count'] += $count ?? 1;
            $lines[$key]['last_date'] = max((string) $lines[$key]['last_date'], $date) ?: $date;
        };

        if (Schema::hasTable('sales_invoices')) {
            SalesInvoice::query()
                ->whereIn('client_id', $ids)
                ->where('status', '!=', 'cancelled')
                /* A proforma that has become a tax invoice is not a second
                   receivable: the tax invoice stands for that money. */
                ->notSuperseded()
                ->selectRaw('client_id, currency, invoice_date, total_amount')
                ->chunk(500, function ($invoices) use ($add) {
                    foreach ($invoices as $invoice) {
                        $add(
                            (int) $invoice->client_id,
                            strtoupper((string) ($invoice->currency ?: 'INR')),
                            (string) $invoice->invoice_date,
                            (float) $invoice->total_amount,
                            0.0,
                            1
                        );
                    }
                });
        }

        if (Schema::hasTable('cashflow_entries')) {
            foreach ($this->clientReceipts($ids) as $entry) {
                /* Columns as the statement prints them — a refund sits in the
                   debit column on both screens, so the list and the statement
                   cannot disagree about where the money went. */
                $add(
                    (int) $entry->client_id,
                    strtoupper((string) ($entry->currency ?: 'INR')),
                    (string) $entry->entry_date,
                    (float) $entry->debit_amount,
                    (float) $entry->credit_amount,
                    1
                );
            }
        }

        return $lines;
    }

    /**
     * A client's statement rows from the cashflow side. A refund paid out
     * (debit_amount) is money moving the other way on the same account, so it
     * is signed here and unwound into the right column when the statement is
     * built.
     */
    private function clientReceipts(array $ids, ?int $limit = null): Collection
    {
        $query = CashflowEntry::query()
            ->whereIn('client_id', $ids)
            ->selectRaw('client_id, currency, entry_date, credit_amount, debit_amount')
            ->orderBy('entry_date');

        return $limit ? $query->limit($limit)->get() : $query->get();
    }

    /** @param array<int, int> $ids */
    private function vendorActivity(array $ids, ?string $from, ?string $to): array
    {
        $lines = [];
        $withLedger = [];

        $add = function (int $partyId, string $code, string $date, float $debit, float $credit) use (&$lines, &$withLedger, $from, $to) {
            $key = $partyId.'|'.$code;
            $withLedger[$partyId] = true;

            $lines[$key] ??= [
                'currency' => $code, 'opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0,
                'count' => 0, 'last_date' => null,
            ];

            /* Vendor balance runs the other way: a bill (credit) increases what
               we owe, a payment (debit) reduces it. */
            $movement = $credit - $debit;

            if ($from !== null && $date < $from) {
                $lines[$key]['opening'] += $movement;

                return;
            }

            if ($to !== null && $date > $to) {
                return;
            }

            $lines[$key]['debit'] += $debit;
            $lines[$key]['credit'] += $credit;
            $lines[$key]['count']++;
            $lines[$key]['last_date'] = max((string) $lines[$key]['last_date'], $date) ?: $date;
        };

        if (Schema::hasTable('vendor_payment_entries')) {
            VendorPaymentEntry::query()
                ->whereIn('vendor_id', $ids)
                ->selectRaw('vendor_id, foreign_currency, transaction_date, transaction_type, foreign_amount')
                ->chunk(500, function ($entries) use ($add) {
                    foreach ($entries as $entry) {
                        $amount = (float) $entry->foreign_amount;
                        $isBill = $entry->transaction_type !== 'debit';

                        $add(
                            (int) $entry->vendor_id,
                            strtoupper((string) ($entry->foreign_currency ?: 'RMB')),
                            (string) $entry->transaction_date,
                            $isBill ? 0.0 : $amount,
                            $isBill ? $amount : 0.0
                        );
                    }
                });
        }

        /* The fallback for a vendor whose money was only ever filed as a
           cashflow row: a statement built from nothing would read as "you owe
           nothing", which is worse than one built from the ledger we do have. */
        $withoutLedger = array_values(array_diff($ids, array_keys($withLedger)));

        if ($withoutLedger !== [] && Schema::hasTable('cashflow_entries')) {
            foreach ($this->vendorCashflowRows($withoutLedger) as $entry) {
                $add(
                    (int) $entry->vendor_id,
                    strtoupper((string) ($entry->currency ?: 'INR')),
                    (string) $entry->entry_date,
                    (float) $entry->debit_amount,
                    (float) $entry->credit_amount
                );
            }
        }

        return $lines;
    }

    private function vendorCashflowRows(array $ids): Collection
    {
        return CashflowEntry::query()
            ->whereIn('vendor_id', $ids)
            ->selectRaw('vendor_id, currency, entry_date, credit_amount, debit_amount')
            ->orderBy('entry_date')
            ->get();
    }

    /* --------------------------------------------------------- the statement */

    /**
     * The statement itself.
     *
     * @return array<string, mixed>|null null when the party does not exist
     */
    public function build(string $type, int $id, ?string $from, ?string $to, array $options = []): ?array
    {
        $party = $this->findParty($type, $id);

        if (! $party) {
            return null;
        }

        /* Dates arrive from a query string; they are compared as Y-m-d strings
           further down, so they are read once, here — and a value that is not a
           date (a range name, an empty box, a stale link) is no date at all,
           rather than a 500 in the middle of a statement. */
        $from = DateRanges::normalise($from);
        $to = DateRanges::normalise($to);

        $requested = strtoupper(trim((string) ($options['currency'] ?? '')));
        $currency = $requested !== '' ? $requested : $this->defaultCurrency($type, $id);
        $clientPortal = $type === 'client' && (bool) ($options['client_portal'] ?? false);
        $rows = $this->statementRows($type, $party, $currency, $clientPortal);

        [$opening, $moving] = $this->splitOpening($rows, $from, $to);

        $sign = $type === 'vendor' ? -1 : 1; // balance = sign × (debit − credit)
        $running = $opening;
        $debit = 0.0;
        $credit = 0.0;

        foreach ($moving as $index => $row) {
            $running += $sign * ($row['debit'] - $row['credit']);
            $debit += $row['debit'];
            $credit += $row['credit'];
            $moving[$index]['balance'] = round($running, 2);
        }

        $statement = [
            'party' => $this->partyProfile($type, $party),
            'party_type' => $type,
            'party_id' => $id,
            'currency' => $currency,
            'has_foreign' => $currency !== 'INR',
            'direction' => $type === 'vendor' ? 'payable' : 'receivable',
            'period' => [
                'from' => $from ? Carbon::parse($from)->startOfDay() : null,
                'to' => $to ? Carbon::parse($to)->endOfDay() : null,
                'label' => $this->periodLabel($from, $to),
                'key' => DateRanges::keyOf($from, $to, $this->today),
            ],
            'rows' => array_values($moving),
            'opening' => round($opening, 2),
            'totals' => [
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
                'closing' => round($running, 2),
                'count' => count($moving),
            ],
            'other_currencies' => $this->otherCurrencies($type, $party, $currency, $from, $to, $clientPortal),
            'generated_at' => Carbon::now(config('app.business_timezone', 'Asia/Kolkata')),
            'issuer' => SalesInvoice::defaultSellerDetails(),
        ];

        $statement['ageing'] = ($options['ageing'] ?? true) && ! $clientPortal
            ? $this->ageing($type, $id, $currency)
            : null;

        $statement['uses_vendor_ledger'] = $type === 'vendor'
            && Schema::hasTable('vendor_payment_entries')
            && VendorPaymentEntry::where('vendor_id', $id)->exists();
        $statement['source_note'] = $this->sourceNote($statement);

        return $statement;
    }

    /** @return array<int, array<string, mixed>> */
    private function statementRows(string $type, $party, string $currency, bool $clientPortal = false): array
    {
        return $type === 'vendor'
            ? $this->vendorStatementRows($party, $currency)
            : $this->clientStatementRows($party, $currency, $clientPortal);
    }

    /** @return array<int, array<string, mixed>> */
    private function clientStatementRows(Client $client, string $currency, bool $clientPortal = false): array
    {
        $rows = [];
        $projectPayments = collect();

        if ($clientPortal) {
            $invoices = $this->clientPortalInvoices($client);
            $projectPayments = $this->clientPortalProjectPayments($client->id);
            $entries = $this->clientPortalInvoiceCashflowEntries(
                $client->id,
                $invoices->modelKeys(),
                $this->projectPaymentCashflowIds($projectPayments)
            );
        } else {
            $invoices = Schema::hasTable('sales_invoices')
                ? SalesInvoice::query()
                    ->where('client_id', $client->id)
                    ->where('status', '!=', 'cancelled')
                    ->notSuperseded()
                    ->orderBy('invoice_date')->orderBy('id')
                    ->get()
                : collect();
            $entries = Schema::hasTable('cashflow_entries')
                ? $this->partyCashflow($client->id, 'client', (string) $client->company_name)
                : collect();
        }

        foreach ($invoices as $invoice) {
            if (strtoupper((string) ($invoice->currency ?: 'INR')) !== $currency) {
                continue;
            }

            $rows[] = [
                'date' => $invoice->invoice_date,
                'particular' => 'Sales invoice'.($invoice->invoice_type ? ' ('.$invoice->invoice_type.')' : ''),
                'reference' => $invoice->invoice_number,
                'status' => method_exists($invoice, 'statusLabel') ? $invoice->statusLabel() : null,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.0,
                'kind' => 'invoice',
            ];

            $openingReceipt = (float) $invoice->amount_paid;
            if ($openingReceipt > 0) {
                $rows[] = [
                    'date' => $invoice->invoice_date,
                    'particular' => 'Advance recorded on invoice',
                    'reference' => $invoice->invoice_number,
                    'status' => null,
                    'debit' => 0.0,
                    'credit' => $openingReceipt,
                    'kind' => 'receipt',
                ];
            }
        }

        foreach ($entries as $entry) {
            if (strtoupper((string) ($entry->currency ?: 'INR')) !== $currency) {
                continue;
            }

            $credit = (float) $entry->credit_amount;
            $debit = (float) $entry->debit_amount;

            $rows[] = [
                'date' => $entry->entry_date,
                'particular' => $credit > 0 ? 'Receipt' : 'Refund / payment out',
                'reference' => $entry->invoice_bill_number ?: $entry->bank_reference_number,
                'status' => method_exists($entry, 'statusLabel') ? $entry->statusLabel() : null,
                'debit' => $debit,
                'credit' => $credit,
                'kind' => 'receipt',
            ];
        }

        foreach ($projectPayments as $payment) {
            if (strtoupper((string) ($payment->currency ?: 'INR')) !== $currency) {
                continue;
            }

            $projectName = $payment->project?->name;
            $rows[] = [
                'date' => $payment->payment_date,
                'particular' => $projectName ? 'Project receipt · '.$projectName : 'Project receipt',
                'reference' => $payment->reference_number,
                'status' => $payment->statusLabel(),
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
                'kind' => 'receipt',
            ];
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['kind'] === 'invoice' ? 0 : 1] <=> [$b['date'], $b['kind'] === 'invoice' ? 0 : 1]);

        return $rows;
    }

    /** ERP invoices that the office has explicitly published to this client. */
    private function clientPortalInvoices(Client $client): Collection
    {
        if (! Schema::hasTable('sales_invoices') || ! Schema::hasColumn('sales_invoices', 'show_client_portal')) {
            return collect();
        }

        return SalesInvoice::query()
            ->where('client_id', $client->id)
            ->where('show_client_portal', true)
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'cancelled')
            ->notSuperseded()
            ->orderBy('invoice_date')->orderBy('id')
            ->get();
    }

    /** Receipts explicitly published against projects visible to this client. */
    private function clientPortalProjectPayments(int $clientId): Collection
    {
        if (! Schema::hasTable('project_payments')
            || ! Schema::hasTable('projects')
            || ! Schema::hasColumn('projects', 'client_id')
            || ! Schema::hasColumn('projects', 'show_client_portal')) {
            return collect();
        }

        return ProjectPayment::query()
            ->with('project')
            ->whereHas('project', fn ($projects) => $projects
                ->where('client_id', $clientId)
                ->where('show_client_portal', true))
            ->visibleToClient()
            ->orderBy('payment_date')->orderBy('id')
            ->get();
    }

    /** Ledger receipts linked to a published invoice and fully booked. */
    private function clientPortalInvoiceCashflowEntries(int $clientId, array $invoiceIds, array $excludeIds = []): Collection
    {
        if ($invoiceIds === []
            || ! Schema::hasTable('cashflow_entries')
            || ! Schema::hasColumn('cashflow_entries', 'client_id')
            || ! Schema::hasColumn('cashflow_entries', 'sales_invoice_id')
            || ! Schema::hasColumn('cashflow_entries', 'accounting_status')
            || ! Schema::hasColumn('cashflow_entries', 'transaction_type')) {
            return collect();
        }

        return CashflowEntry::query()
            ->where('client_id', $clientId)
            ->whereIn('sales_invoice_id', $invoiceIds)
            ->whereIn('accounting_status', ['booked', 'reconciled'])
            ->whereIn('transaction_type', ['credit', 'debit'])
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->orderBy('entry_date')->orderBy('id')
            ->get();
    }

    /** Cashflow IDs already represented by visible project receipt rows. */
    private function projectPaymentCashflowIds(Collection $payments): array
    {
        return $payments->pluck('cashflow_entry_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function vendorStatementRows(Vendor $vendor, string $currency): array
    {
        $rows = [];

        $hasVendorLedger = false;

        if (Schema::hasTable('vendor_payment_entries')) {
            $entries = VendorPaymentEntry::query()
                ->where('vendor_id', $vendor->id)
                ->orderBy('transaction_date')->orderBy('id')
                ->get();
            $hasVendorLedger = $entries->isNotEmpty();
            $ledgerCurrency = strtoupper((string) ($vendor->preferred_currency ?: 'RMB'));

            foreach ($entries as $entry) {
                $entryCurrency = strtoupper((string) ($entry->foreign_currency ?: $ledgerCurrency));
                if ($entryCurrency !== $currency) {
                    continue;
                }

                $amount = (float) $entry->foreign_amount;
                $isBill = $entry->transaction_type !== 'debit';

                $rows[] = [
                    'date' => $entry->transaction_date,
                    'particular' => $this->vendorParticular($entry),
                    'reference' => $entry->invoice_number ?: $entry->bank_reference_number,
                    'status' => method_exists($entry, 'statusLabel') ? $entry->statusLabel() : null,
                    'debit' => $isBill ? 0.0 : $amount,
                    'credit' => $isBill ? $amount : 0.0,
                    'kind' => $isBill ? 'bill' : 'payment',
                    'rate' => (float) $entry->exchange_rate,
                    'inr' => (float) $entry->amount_in_inr,
                ];
            }
        }

        /* Cashflow is rupees. If this vendor has a currency ledger, an empty
           INR statement must not be filled with those rupee rows. */
        if ($rows === [] && ! $hasVendorLedger && Schema::hasTable('cashflow_entries')) {
            foreach ($this->partyCashflow($vendor->id, 'vendor', (string) $vendor->vendor_name) as $entry) {
                if (strtoupper((string) ($entry->currency ?: 'INR')) !== $currency) {
                    continue;
                }

                $rows[] = [
                    'date' => $entry->entry_date,
                    'particular' => (string) $entry->particular,
                    'reference' => $entry->invoice_bill_number ?: $entry->bank_reference_number,
                    'status' => method_exists($entry, 'statusLabel') ? $entry->statusLabel() : null,
                    'debit' => (float) $entry->debit_amount,
                    'credit' => (float) $entry->credit_amount,
                    'kind' => 'entry',
                ];
            }
        }

        return $rows;
    }

    private function vendorParticular(VendorPaymentEntry $entry): string
    {
        if (filled($entry->particular)) {
            return (string) $entry->particular;
        }

        $kind = $entry->transaction_type === 'debit'
            ? 'Paid to vendor'
            : ($entry->entry_category === 'order' ? 'Purchase order' : 'Bill received');
        $category = $entry->entry_category ? ' · '.ucfirst((string) $entry->entry_category) : '';

        return $kind.$category;
    }

    /**
     * Cashflow rows for a party by id **or** by the name written on them — the
     * same both-ways match the vendor page and the archive already use, because
     * older rows were filed before the id columns were filled in.
     */
    private function partyCashflow(int $partyId, string $type, string $name): Collection
    {
        return CashflowEntry::query()
            ->where(function ($query) use ($partyId, $type, $name) {
                /* The id column the party type owns — never both. A client with
                   id 5 and a vendor with id 5 are two different parties, and
                   matching either column would put one's money on the other's
                   statement. */
                $query->where($type === 'vendor' ? 'vendor_id' : 'client_id', $partyId)
                    ->orWhere(function ($nested) use ($type, $name) {
                        $nested->where('related_party_type', $type)
                            ->where('related_party_name', 'like', '%'.$name.'%');
                    });
            })
            ->orderBy('entry_date')->orderBy('id')
            ->get();
    }

    /** Earlier rows open the period; only rows within it run the balance. */
    private function splitOpening(array $rows, ?string $from, ?string $to): array
    {
        $opening = 0.0;
        $moving = [];

        foreach ($rows as $row) {
            $date = $row['date'] instanceof Carbon ? $row['date']->toDateString() : (string) $row['date'];

            if ($from !== null && $date < $from) {
                $opening += (float) $row['debit'] - (float) $row['credit'];

                continue;
            }

            if ($to !== null && $date !== '' && $date > $to) {
                continue;
            }

            $moving[] = $row;
        }

        return [$opening, $moving];
    }

    /** Rows in a currency other than this statement's, so the foot can own up. */
    private function otherCurrencies(string $type, $party, string $currency, ?string $from, ?string $to, bool $clientPortal = false): array
    {
        $found = [];

        $collect = function (array $candidateRows) use (&$found, $currency, $from, $to) {
            foreach ($candidateRows as $row) {
                if ($row['currency'] === $currency) {
                    continue;
                }

                $date = $row['date'] instanceof Carbon ? $row['date']->toDateString() : (string) $row['date'];

                if ($from !== null && $date < $from) {
                    continue;
                }
                if ($to !== null && $date > $to) {
                    continue;
                }

                $found[$row['currency']] ??= ['currency' => $row['currency'], 'debit' => 0.0, 'credit' => 0.0, 'rows' => 0];
                $found[$row['currency']]['debit'] += (float) $row['debit'];
                $found[$row['currency']]['credit'] += (float) $row['credit'];
                $found[$row['currency']]['rows']++;
            }
        };

        /* The same rows the statement uses, in every currency — one pass per
           source, so the foot cannot disagree with the body. The vendor
           fallback is decided by whether the vendor has *any* currency-ledger
           rows, exactly as `vendorStatementRows` decides it. */
        if ($type === 'vendor') {
            $hasLedgerRows = Schema::hasTable('vendor_payment_entries')
                && VendorPaymentEntry::where('vendor_id', $party->id)->exists();

            if ($hasLedgerRows) {
                $collect(VendorPaymentEntry::where('vendor_id', $party->id)->get()
                    ->map(fn ($entry) => [
                        'currency' => strtoupper((string) ($entry->foreign_currency ?: 'INR')),
                        'date' => $entry->transaction_date,
                        'debit' => $entry->transaction_type === 'debit' ? (float) $entry->foreign_amount : 0.0,
                        'credit' => $entry->transaction_type === 'debit' ? 0.0 : (float) $entry->foreign_amount,
                    ])->all());
            } elseif (Schema::hasTable('cashflow_entries')) {
                $collect($this->partyCashflow($party->id, 'vendor', (string) $party->vendor_name)
                    ->map(fn ($entry) => [
                        'currency' => strtoupper((string) ($entry->currency ?: 'INR')),
                        'date' => $entry->entry_date,
                        'debit' => (float) $entry->debit_amount,
                        'credit' => (float) $entry->credit_amount,
                    ])->all());
            }
        } elseif ($clientPortal) {
            $invoices = $this->clientPortalInvoices($party);
            $payments = $this->clientPortalProjectPayments((int) $party->id);
            $entries = $this->clientPortalInvoiceCashflowEntries(
                (int) $party->id,
                $invoices->modelKeys(),
                $this->projectPaymentCashflowIds($payments)
            );

            $collect($invoices->map(fn ($invoice) => [
                'currency' => strtoupper((string) ($invoice->currency ?: 'INR')),
                'date' => $invoice->invoice_date,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.0,
            ])->all());

            $collect($invoices->filter(fn ($invoice) => (float) $invoice->amount_paid > 0)
                ->map(fn ($invoice) => [
                    'currency' => strtoupper((string) ($invoice->currency ?: 'INR')),
                    'date' => $invoice->invoice_date,
                    'debit' => 0.0,
                    'credit' => (float) $invoice->amount_paid,
                ])->all());

            $collect($entries->map(fn ($entry) => [
                'currency' => strtoupper((string) ($entry->currency ?: 'INR')),
                'date' => $entry->entry_date,
                'debit' => (float) $entry->debit_amount,
                'credit' => (float) $entry->credit_amount,
            ])->all());

            $collect($payments->map(fn ($payment) => [
                'currency' => strtoupper((string) ($payment->currency ?: 'INR')),
                'date' => $payment->payment_date,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ])->all());
        } else {
            if (Schema::hasTable('sales_invoices')) {
                $collect(SalesInvoice::where('client_id', $party->id)->where('status', '!=', 'cancelled')
                    ->notSuperseded()->get()
                    ->map(fn ($invoice) => [
                        'currency' => strtoupper((string) ($invoice->currency ?: 'INR')),
                        'date' => $invoice->invoice_date,
                        'debit' => (float) $invoice->total_amount,
                        'credit' => 0.0,
                    ])->all());

                $collect(SalesInvoice::where('client_id', $party->id)
                    ->where('status', '!=', 'cancelled')->notSuperseded()->get()
                    ->filter(fn ($invoice) => (float) $invoice->amount_paid > 0)
                    ->map(fn ($invoice) => [
                        'currency' => strtoupper((string) ($invoice->currency ?: 'INR')),
                        'date' => $invoice->invoice_date,
                        'debit' => 0.0,
                        'credit' => (float) $invoice->amount_paid,
                    ])->all());
            }

            if (Schema::hasTable('cashflow_entries')) {
                $collect($this->partyCashflow($party->id, 'client', (string) $party->company_name)
                    ->map(fn ($entry) => [
                        'currency' => strtoupper((string) ($entry->currency ?: 'INR')),
                        'date' => $entry->entry_date,
                        'debit' => (float) $entry->debit_amount,
                        'credit' => (float) $entry->credit_amount,
                    ])->all());
            }
        }

        usort($found, fn ($a, $b) => $a['currency'] <=> $b['currency']);

        return array_values($found);
    }

    /* ------------------------------------------------------------- ageing */

    /**
     * What is still outstanding, by how late it is.
     *
     * A client is aged from each invoice's **due date** against the balance the
     * invoice module keeps (`balance_amount` = total − paid), which is the only
     * figure that knows a part payment. A vendor's bills carry no due date in
     * this schema, so the age is counted from the bill's own date and the block
     * says so rather than inventing terms.
     */
    private function ageing(string $type, int $id, string $currency): ?array
    {
        $items = $type === 'vendor'
            ? $this->vendorAgeing($id, $currency)
            : $this->clientAgeing($id, $currency);

        if ($items === []) {
            return null;
        }

        $buckets = [];
        foreach (array_keys(self::BUCKETS) as $key) {
            $buckets[$key] = ['label' => self::BUCKETS[$key], 'amount' => 0.0, 'count' => 0];
        }

        $total = 0.0;
        foreach ($items as $item) {
            $buckets[$item['bucket']]['amount'] += $item['amount'];
            $buckets[$item['bucket']]['count']++;
            $total += $item['amount'];
        }

        foreach ($buckets as $key => $bucket) {
            $buckets[$key]['amount'] = round($bucket['amount'], 2);
        }

        return [
            'title' => $type === 'vendor' ? 'Bills outstanding, by age' : 'Invoices outstanding, by age',
            'note' => $type === 'vendor'
                ? 'Aged from the bill date — vendor bills do not carry a due date here.'
                : 'Aged from the due date; the amount is the invoice balance after payments.',
            'buckets' => $buckets,
            'total' => round($total, 2),
            'rows' => $items,
        ];
    }

    private function clientAgeing(int $id, string $currency): array
    {
        if (! Schema::hasTable('sales_invoices')) {
            return [];
        }

        $items = [];

        foreach (SalesInvoice::where('client_id', $id)->where('status', '!=', 'cancelled')
            ->notSuperseded()
            ->orderBy('invoice_date')->orderBy('id')->get() as $invoice) {
            if (strtoupper((string) ($invoice->currency ?: 'INR')) !== $currency) {
                continue;
            }

            $balance = (float) $invoice->balance_amount;
            if ($balance <= 0) {
                continue;
            }

            /* Days *past due*: a due date still in the future is not late, so it
               lands in "Not due" with every other invoice that is not late yet. */
            $due = $invoice->due_date ?: $invoice->invoice_date;
            $days = $due && ! $due->isFuture()
                ? (int) $due->copy()->startOfDay()->diffInDays($this->today, false)
                : 0;

            $bucket = $this->bucketFor($days);

            $items[] = [
                'date' => $invoice->invoice_date,
                'due' => $due,
                'reference' => $invoice->invoice_number,
                'particular' => 'Sales invoice',
                'amount' => round($balance, 2),
                'days' => $days,
                'bucket' => $bucket,
                'bucket_label' => self::BUCKETS[$bucket],
            ];
        }

        return $items;
    }

    private function vendorAgeing(int $id, string $currency): array
    {
        if (! Schema::hasTable('vendor_payment_entries')) {
            return [];
        }

        $items = [];
        $ledgerCurrency = strtoupper((string) (Vendor::find($id)?->preferred_currency ?: 'RMB'));

        foreach (VendorPaymentEntry::where('vendor_id', $id)
            ->orderBy('transaction_date')->orderBy('id')->get() as $entry) {
            $entryCurrency = strtoupper((string) ($entry->foreign_currency ?: $ledgerCurrency));
            if ($entryCurrency !== $currency) {
                continue;
            }

            /* A bill is outstanding until somebody marks it paid or reconciled.
               Cancelled rows and payments are not bills. */
            if ($entry->transaction_type === 'debit' || ! in_array($entry->status, ['pending', 'booked'], true)) {
                continue;
            }

            $date = $entry->transaction_date ?: $entry->created_at;
            $days = $date ? (int) $date->copy()->startOfDay()->diffInDays($this->today, false) : 0;
            $bucket = $this->bucketFor($days);

            $items[] = [
                'date' => $date,
                'due' => null,
                'reference' => $entry->invoice_number,
                'particular' => $this->vendorParticular($entry),
                'amount' => round((float) $entry->foreign_amount, 2),
                'days' => max($days, 0),
                'bucket' => $bucket,
                'bucket_label' => self::BUCKETS[$bucket],
            ];
        }

        return $items;
    }

    private function bucketFor(int $days): string
    {
        if ($days <= 0) {
            return 'current';
        }
        if ($days <= 30) {
            return 'd1_30';
        }
        if ($days <= 60) {
            return 'd31_60';
        }
        if ($days <= 90) {
            return 'd61_90';
        }

        return 'd90_plus';
    }

    /* ------------------------------------------------------------ profiles */

    private function partyProfile(string $type, $party): array
    {
        if ($type === 'vendor') {
            return [
                'id' => $party->id,
                'name' => (string) $party->vendor_name,
                'kind' => 'Vendor',
                'code' => $party->vendor_number ?? null,
                'contact_person' => $party->contact_person_name ?? null,
                'email' => $party->contact_person_email ?? null,
                'phone' => $party->contact_person_mobile ?? $party->whatsapp_number ?? null,
                'gstin' => $party->gstin ?? null,
                'tax_id' => $party->tax_id ?? null,
                'country' => $party->country ?? null,
                'currency' => strtoupper((string) ($party->preferred_currency ?: 'INR')),
                'payment_terms' => $party->payment_terms ?? null,
                'address_lines' => array_values(array_filter([
                    $party->address ?? null,
                    trim(implode(', ', array_filter([$party->city ?? null, $party->state ?? null, $party->pincode ?? null]))),
                    $party->country ?? null,
                ])),
            ];
        }

        return [
            'id' => $party->id,
            'name' => (string) $party->company_name,
            'kind' => 'Client',
            'code' => $party->client_number ?? null,
            'contact_person' => $party->account_person_name ?? $party->ceo_name ?? null,
            'email' => $party->account_person_email ?? $party->ceo_email ?? null,
            'phone' => $party->account_person_contact ?? $party->ceo_contact ?? null,
            'gstin' => $party->gstin ?? null,
            'tax_id' => $party->pan ?? null,
            'country' => $party->billing_country ?? null,
            'currency' => strtoupper((string) ($party->preferred_currency ?: 'INR')),
            'payment_terms' => $party->payment_terms ?? null,
            'credit_days' => $party->credit_days ?? null,
            'address_lines' => array_values(array_filter([
                $party->billing_address ?? null,
                trim(implode(', ', array_filter([$party->billing_city ?? null, $party->billing_state ?? null, $party->billing_pincode ?? null]))),
                $party->billing_country ?? null,
            ])),
        ];
    }

    public function periodLabel(?string $from, ?string $to): string
    {
        if ($key = DateRanges::keyOf($from, $to, $this->today)) {
            return DateRanges::LABELS[$key];
        }

        if (! $from && ! $to) {
            return 'All time';
        }

        $fromLabel = $from ? Carbon::parse($from)->format('d M Y') : 'Beginning';
        $toLabel = $to ? Carbon::parse($to)->format('d M Y') : 'Today';

        return $fromLabel.' – '.$toLabel;
    }

    /** What the statement is built from — printed under the table, not guessed at. */
    private function sourceNote(array $statement): string
    {
        if ($statement['party_type'] === 'vendor') {
            return $statement['uses_vendor_ledger']
                ? 'Built from the vendor currency ledger: bills raised and payments made, in '.$statement['currency'].'.'
                : 'Built from the cashflow ledger for this vendor — no vendor-currency rows filed yet.';
        }

        return 'Built from sales invoices and receipts filed against this client.';
    }
}
