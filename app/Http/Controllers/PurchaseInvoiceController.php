<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Vendor;
use App\Models\VendorPaymentAttachment;
use App\Models\VendorPaymentEntry;
use App\Services\PurchaseBillLedger;
use App\Services\VendorPaymentCashflowSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Buying, the mirror of `SalesInvoiceController`.
 *
 * One table, two documents: `invoice_type` is `order` (a Purchase Order) or
 * `bill` (the Purchase Bill raised from it). They share one UI under
 * `purchase-invoices.*`, the way a proforma and a tax invoice share
 * `/sales-invoices`. Everything else follows the sales module rule for rule:
 * the money is computed, never typed; the party details are snapshotted onto
 * the document; a conversion happens once and is locked; and the document that
 * is owed is the bill.
 *
 * The purchase side has one thing the sales side does not: a sent order and
 * then the bill post into the vendor ledger (`PurchaseBillLedger`), because
 * that ledger is where the office already reads payables, ageing and the
 * vendor statement. After conversion only the bill remains.
 */
class PurchaseInvoiceController extends Controller
{
    /** The orders a document can be in at each stage, from the model's own flow. */
    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);

        /* Figures and rows each get a query of their own: `withSum` writes a
           correlated subquery into the column list and `selectRaw` appends to
           that list, which is MySQL 1140 on a host running
           `only_full_group_by`. A query with no columns of its own takes one
           aggregate select cleanly — the same rule the invoices listing keeps. */
        $paid = $this->paidSql();
        $currencyBits = [];
        foreach (['INR', 'USD', 'RMB'] as $code) {
            $c = "purchase_invoices.currency = '".$code."'";
            $currencyBits[] = "coalesce(sum(case when $c and purchase_invoices.invoice_type = 'bill'"
                ." and purchase_invoices.status <> 'cancelled' then purchase_invoices.total_amount else 0 end), 0) as billed_".$code;
            $currencyBits[] = "coalesce(sum(case when $c and purchase_invoices.invoice_type = 'order'"
                ." and purchase_invoices.converted_invoice_id is null and purchase_invoices.status <> 'cancelled'"
                ." then purchase_invoices.total_amount else 0 end), 0) as open_".$code;
            $currencyBits[] = "coalesce(sum(case when $c and purchase_invoices.invoice_type = 'bill'"
                ." and purchase_invoices.status <> 'cancelled' then ".$paid." else 0 end), 0) as paid_".$code;
            $currencyBits[] = "coalesce(sum(case when $c and purchase_invoices.invoice_type = 'bill'"
                ." and purchase_invoices.status <> 'cancelled' and ".$paid." < purchase_invoices.total_amount - 0.01"
                ." then purchase_invoices.total_amount - ".$paid." else 0 end), 0) as outstanding_".$code;
        }
        $figures = $this->filteredQuery($filters)->reorder()->selectRaw(implode(', ', $currencyBits))->first();

        $byCurrency = [];
        foreach (['INR', 'USD', 'RMB'] as $code) {
            $byCurrency[$code] = [
                'billed' => (float) ($figures->{'billed_'.$code} ?? 0),
                'open' => (float) ($figures->{'open_'.$code} ?? 0),
                'paid' => (float) ($figures->{'paid_'.$code} ?? 0),
                'outstanding' => (float) ($figures->{'outstanding_'.$code} ?? 0),
            ];
        }

        $totals = collect([
            'byCurrency' => $byCurrency,
            'drafts' => PurchaseInvoice::query()->where('status', 'draft')->count(),
        ]);

        $invoices = $this->filteredQuery($filters)
            ->with(['vendor', 'convertedInvoice'])
            ->withPaid()
            ->withCount('payments')
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('purchase_invoices.index', array_merge($this->sharedData(), [
            'invoices' => $invoices,
            'stats' => $this->statCards($totals),
            'pageTotals' => $totals,
            'chipCounts' => $this->chipCounts($filters, $invoices->total()),
            'appliedChips' => $this->appliedChips($filters),
            'dateRanges' => DateRanges::presets(),
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => DateRanges::keyOf($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null),
            'paymentLabels' => self::PAYMENT_LABELS,
            ...$filters,
        ]));
    }

    /**
     * The rows the filters ask for — one builder for the list and the figures.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        return PurchaseInvoice::query()
            ->when(($filters['type'] ?? 'all') !== 'all', fn ($query) => $query->where('purchase_invoices.invoice_type', $filters['type']))
            ->when(($filters['search'] ?? '') !== '', fn ($query) => $query->search($filters['search']))
            ->when(($filters['status'] ?? 'all') !== 'all', function ($query) use ($filters) {
                if ($filters['status'] === 'overdue') {
                    return $query->where('purchase_invoices.due_date', '<', now()->toDateString())
                        ->whereNotIn('purchase_invoices.status', ['draft', 'cancelled'])
                        ->whereRaw($this->paidSql().' < purchase_invoices.total_amount - 0.01');
                }

                return $query->where('purchase_invoices.status', $filters['status']);
            })
            ->when((int) ($filters['vendor'] ?? 0) > 0, fn ($query) => $query->where('purchase_invoices.vendor_id', (int) $filters['vendor']))
            ->when((int) ($filters['project'] ?? 0) > 0, fn ($query) => $query->where('purchase_invoices.project_id', (int) $filters['project']))
            ->when(($filters['currency'] ?? 'all') !== 'all', fn ($query) => $query->where('purchase_invoices.currency', $filters['currency']))
            ->when(($filters['payment'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $paid = $this->paidSql();

                return match ($filters['payment']) {
                    'nothing' => $query->whereRaw($paid.' <= 0.01'),
                    'partial' => $query->whereRaw($paid.' > 0.01')
                        ->whereRaw($paid.' < purchase_invoices.total_amount - 0.01'),
                    'paid' => $query->whereRaw($paid.' >= purchase_invoices.total_amount - 0.01'),
                    default => $query,
                };
            })
            ->when($filters['dateFrom'] ?? null, fn ($query, $date) => $query->whereDate('purchase_invoices.invoice_date', '>=', $date))
            ->when($filters['dateTo'] ?? null, fn ($query, $date) => $query->whereDate('purchase_invoices.invoice_date', '<=', $date));
    }

    /** The filter vocabulary: what the drawer offers and what a chip can carry. */
    private function filtersFromRequest(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && ! array_key_exists($status, PurchaseInvoice::statusOptions() + ['overdue' => 'Overdue'])) {
            $status = 'all';
        }

        $payment = (string) $request->query('payment', 'all');
        if (! in_array($payment, ['all', 'nothing', 'partial', 'paid'], true)) {
            $payment = 'all';
        }

        $currency = (string) $request->query('currency', 'all');
        if ($currency !== 'all' && ! array_key_exists($currency, PurchaseInvoice::currencyOptions())) {
            $currency = 'all';
        }

        $type = (string) $request->query('invoice_type', 'all');
        if ($type !== 'all' && ! array_key_exists($type, PurchaseInvoice::DOC_LABELS)) {
            $type = 'all';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'type' => $type,
            'status' => $status,
            'payment' => $payment,
            'currency' => $currency,
            'vendor' => (int) $request->query('vendor', 0),
            'project' => (int) $request->query('project', 0),
            'dateFrom' => $request->query('date_from') ?: null,
            'dateTo' => $request->query('date_to') ?: null,
        ];
    }

    /** What is on, said the way the office said it. */
    private function appliedChips(array $filters): array
    {
        $statuses = PurchaseInvoice::statusOptions() + ['overdue' => 'Overdue'];
        $chips = [];

        if ($filters['search'] !== '') {
            $chips['search'] = ['label' => 'Search', 'value' => $filters['search'], 'query' => ['search']];
        }
        if ($filters['type'] !== 'all') {
            $chips['type'] = ['label' => 'Type', 'value' => PurchaseInvoice::DOC_LABELS[$filters['type']] ?? $filters['type'], 'query' => ['invoice_type']];
        }
        if ($filters['status'] !== 'all') {
            $chips['status'] = ['label' => 'Status', 'value' => $statuses[$filters['status']] ?? $filters['status'], 'query' => ['status']];
        }
        if ($filters['payment'] !== 'all') {
            $chips['payment'] = ['label' => 'Payment', 'value' => self::PAYMENT_LABELS[$filters['payment']] ?? $filters['payment'], 'query' => ['payment']];
        }
        if ($filters['currency'] !== 'all') {
            $chips['currency'] = ['label' => 'Currency', 'value' => $filters['currency'], 'query' => ['currency']];
        }
        if ($filters['vendor'] > 0) {
            $chips['vendor'] = ['label' => 'Vendor', 'value' => Vendor::query()->whereKey($filters['vendor'])->value('vendor_name') ?: '#'.$filters['vendor'], 'query' => ['vendor']];
        }
        if ($filters['project'] > 0) {
            $chips['project'] = ['label' => 'Project', 'value' => Project::query()->whereKey($filters['project'])->value('name') ?: '#'.$filters['project'], 'query' => ['project']];
        }
        if ($filters['dateFrom'] || $filters['dateTo']) {
            $chips['dates'] = ['label' => 'Document date', 'value' => trim(($filters['dateFrom'] ?: '…').' → '.($filters['dateTo'] ?: '…')), 'query' => ['date_from', 'date_to']];
        }

        return $chips;
    }

    /** How many rows each status chip would show, over the rest of the filters. */
    private function chipCounts(array $filters, int $all): array
    {
        $counts = ['all' => $all];

        foreach (['order', 'bill'] as $type) {
            $counts[$type] = $this->filteredQuery(array_merge($filters, ['type' => $type, 'status' => 'all']))->count();
        }

        foreach (array_keys(PurchaseInvoice::statusOptions()) as $status) {
            $counts[$status] = $this->filteredQuery(array_merge($filters, ['status' => $status]))->count();
        }

        $counts['overdue'] = $this->filteredQuery(array_merge($filters, ['status' => 'overdue']))->count();

        return $counts;
    }

    /** The header tiles: the same shape for both documents, in their own words. */
    private function statCards($totals): array
    {
        $tones = ['INR' => 'blue', 'USD' => 'teal', 'RMB' => 'orange'];
        $cards = [];

        foreach (['INR', 'USD', 'RMB'] as $code) {
            $row = $totals['byCurrency'][$code] ?? ['billed' => 0, 'open' => 0, 'paid' => 0, 'outstanding' => 0];
            $cards[] = [
                'label' => $code.' (filtered)',
                'value' => $row['billed'],
                'open' => $row['open'],
                'paid' => $row['paid'],
                'outstanding' => $row['outstanding'],
                'currency' => $code,
                'tone' => $tones[$code],
            ];
        }

        $cards[] = ['label' => 'Drafts', 'value' => $totals['drafts'], 'tone' => 'purple', 'money' => false];

        return $cards;
    }

    public function create(Request $request): View
    {
        $type = $this->requestType($request);
        $nextNumbers = [
            PurchaseInvoice::TYPE_ORDER => $this->makeInvoiceNumber(PurchaseInvoice::TYPE_ORDER),
            PurchaseInvoice::TYPE_BILL => $this->makeInvoiceNumber(PurchaseInvoice::TYPE_BILL),
        ];

        return view('purchase_invoices.form', array_merge($this->sharedData($type), [
            'docType' => $type,
            'nextNumbers' => $nextNumbers,
            'invoice' => new PurchaseInvoice([
                'invoice_type' => $type,
                'invoice_number' => $nextNumbers[$type],
                'currency' => 'INR',
                'gst_type' => 'export',
                'discount_type' => 'amount',
                'status' => 'draft',
                'invoice_date' => now()->toDateString(),
            ] + PurchaseInvoice::defaultBuyerDetails()),
            'sourceOrder' => $this->sourceOrder($request),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->requestType($request);
        $data = $this->prepareInvoiceData($request, $type, $this->validatedData($request));

        $invoice = DB::transaction(function () use ($data, $request) {
            unset($data['items']);
            $invoice = PurchaseInvoice::create($data);
            $this->syncItemsAndTotals($invoice, (array) $request->input('items', []));

            return $invoice;
        });

        $this->afterSave($invoice);

        return redirect()->route($invoice->routePrefix().'.show', $invoice)
            ->with('success', $invoice->typeLabel().' '.$invoice->invoice_number.' created.');
    }

    public function show(PurchaseInvoice $purchaseInvoice): View
    {
        $this->assertDocType(request(), $purchaseInvoice);

        $purchaseInvoice->load(['vendor', 'project', 'items', 'convertedInvoice', 'purchaseOrder']);
        $ledger = $this->ledgerAvailable() ? $purchaseInvoice->ledgerEntries()->get() : collect();

        return view('purchase_invoices.show', array_merge($this->sharedData($purchaseInvoice->invoice_type), [
            'docType' => $purchaseInvoice->invoice_type,
            'invoice' => $purchaseInvoice,
            'ledgerEntries' => $ledger,
            'payments' => $ledger->where('transaction_type', 'debit')->values(),
            'billBalance' => $purchaseInvoice->balanceDue(),
        ]));
    }

    public function edit(PurchaseInvoice $purchaseInvoice): View
    {
        $this->assertDocType(request(), $purchaseInvoice);

        $purchaseInvoice->load(['items', 'vendor', 'convertedInvoice']);

        return view('purchase_invoices.form', array_merge($this->sharedData($purchaseInvoice->invoice_type), [
            'docType' => $purchaseInvoice->invoice_type,
            'invoice' => $purchaseInvoice,
            'sourceOrder' => $purchaseInvoice->purchaseOrder,
        ]));
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->assertDocType($request, $purchaseInvoice);

        $data = $this->prepareInvoiceData($request, $purchaseInvoice->invoice_type, $this->validatedData($request, $purchaseInvoice));

        DB::transaction(function () use ($data, $purchaseInvoice, $request) {
            unset($data['items']);
            $purchaseInvoice->update($data);
            $this->syncItemsAndTotals($purchaseInvoice, (array) $request->input('items', []));
        });

        $this->afterSave($purchaseInvoice->fresh(['items']));

        return redirect()->route($purchaseInvoice->routePrefix().'.show', $purchaseInvoice)
            ->with('success', $purchaseInvoice->invoice_number.' updated.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->assertDocType(request(), $purchaseInvoice);

        if ($purchaseInvoice->isBill() && (float) $purchaseInvoice->ledgerPaid() > 0.01) {
            return back()->with('error', $purchaseInvoice->invoice_number
                .' has payments filed against it. Remove those from the vendor ledger first, then delete the bill.');
        }

        if ($purchaseInvoice->isSuperseded()) {
            return back()->with('error', $purchaseInvoice->invoice_number
                .' has become '.$purchaseInvoice->convertedInvoice?->invoice_number
                .'. Delete that bill first if the whole purchase was a mistake.');
        }

        $type = $purchaseInvoice->invoice_type;
        $number = $purchaseInvoice->invoice_number;
        $order = PurchaseInvoice::query()->where('converted_invoice_id', $purchaseInvoice->id)->first();

        DB::transaction(function () use ($purchaseInvoice, $order) {
            (new PurchaseBillLedger())->remove($purchaseInvoice);
            $purchaseInvoice->items()->delete();
            $purchaseInvoice->delete();

            /* The order the bill came from goes back to being an order: the
               link is what made it history, and the bill is gone. */
            if ($order) {
                $order->converted_invoice_id = null;
                $order->status = 'approved';
                $order->save();
            }
        });

        return redirect()->route('purchase-invoices.index')
            ->with('success', $number.' deleted.');
    }

    /**
     * The order becomes the bill, once.
     *
     * The same shape as a proforma becoming a tax invoice: the bill is a copy of
     * the order — its lines, its rates, its terms, its GST split — with our
     * number, the vendor's bill number and the dates the office still has to
     * fill in. The order keeps a link to what it became and is history from
     * there: it is not owed, and every total in the module reads it that way.
     *
     * Nothing was owed on the order, but money can have been paid against one,
     * so those ledger rows move across with the bill.
     */
    public function convert(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->assertDocType($request, $purchaseInvoice);

        if (! $purchaseInvoice->isOrder()) {
            return back()->with('error', $purchaseInvoice->invoice_number.' is already a purchase bill.');
        }

        if ($purchaseInvoice->status === 'cancelled') {
            return back()->with('error', $purchaseInvoice->invoice_number
                .' is cancelled — bring it back to life before raising a bill from it.');
        }

        if ($already = $purchaseInvoice->convertedInvoice) {
            return back()->with('error', $purchaseInvoice->invoice_number.' has already become '
                .$already->invoice_number.'. A purchase order becomes one bill — edit that one, or duplicate '
                .'the order if a second bill is really needed.');
        }

        $bill = DB::transaction(function () use ($purchaseInvoice) {
            $order = PurchaseInvoice::query()->whereKey($purchaseInvoice->getKey())->lockForUpdate()->first();

            if (! $order || ! $order->isOrder() || $order->converted_invoice_id) {
                return null;
            }

            $bill = $this->copyInvoice($order, [
                'invoice_type' => PurchaseInvoice::TYPE_BILL,
                'invoice_number' => $this->makeInvoiceNumber(PurchaseInvoice::TYPE_BILL),
                'status' => 'received',
                'purchase_order_id' => $order->id,
                'amount_paid' => 0,
                'public_token' => Str::random(48),
                'received_at' => now(),
                'notes' => trim('Raised from purchase order '.$order->invoice_number.'. '.($order->notes ?? '')),
            ]);

            (new PurchaseBillLedger())->movePayments($order, $bill);

            $order->converted_invoice_id = $bill->id;
            $order->status = 'billed';
            $order->save();

            return $bill;
        });

        if (! $bill) {
            return back()->with('error', 'A purchase bill already exists for '.$purchaseInvoice->invoice_number.'.');
        }

        $this->afterSave($bill);
        (new PurchaseBillLedger())->sync($purchaseInvoice->fresh());

        return redirect()->route('purchase-invoices.edit', $bill)
            ->with('success', $bill->invoice_number.' created from '.$purchaseInvoice->invoice_number
                .'. Fill in the vendor\'s bill number and date, check the quantities received, then save.');
    }

    /**
     * A payment against the bill.
     *
     * The ledger is the record — a debit in the vendor's own currency with its
     * rupee value — and the bill reads its balance from there. The INR cashflow
     * mirror is written by the shared vendor service when the office asks for
     * it, so a purchase payment lands in the cashflow the same way a payment
     * typed into the vendor module does.
     */
    public function recordPayment(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->assertDocType($request, $purchaseInvoice);

        if (! $purchaseInvoice->canReceiveMoney()) {
            return back()->with('error', $purchaseInvoice->moneyGateMessage()
                ?: 'An approved purchase order or a raised bill is required before money can go to this vendor.');
        }

        $data = $request->validate([
            'transaction_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'foreign_amount' => ['nullable', 'numeric', 'min:0.01'],
            'foreign_currency' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'amount_in_inr' => ['nullable', 'numeric', 'min:0'],
            'particular' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'in:pending,booked,paid,reconciled'],
            'project_id' => ['nullable', 'integer'],
            'payment_mode' => ['nullable', 'string', 'max:40'],
            'paid_account_id' => ['nullable', 'integer'],
            'bank_reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'record_cashflow' => ['nullable', 'boolean'],
            'also_create_cashflow' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
        ]);

        if (! $this->ledgerAvailable()) {
            return back()->with('error', 'The vendor ledger is not available on this install, so a payment cannot be filed.');
        }

        $amount = (float) ($data['foreign_amount'] ?? $data['amount'] ?? 0);
        if ($amount <= 0) {
            return back()->with('error', 'Enter the amount paid in the vendor\'s currency.');
        }

        $currency = strtoupper((string) ($data['foreign_currency'] ?? $purchaseInvoice->currency ?: 'INR'));
        $rate = $currency === 'INR'
            ? 1.0
            : (float) ($data['exchange_rate'] ?? $purchaseInvoice->exchange_rate ?: 0);
        $rupees = (float) ($data['amount_in_inr'] ?? 0);
        if ($rupees <= 0) {
            $rupees = $rate > 0 ? round($amount * $rate, 2) : 0.0;
        }
        if ($rate <= 0 && $rupees > 0 && $amount > 0) {
            $rate = round($rupees / $amount, 6);
        }

        $syncCashflow = $request->boolean('record_cashflow') || $request->boolean('also_create_cashflow');
        if ($syncCashflow && $rupees <= 0) {
            return back()->with('error', 'Enter the exchange rate (or the INR amount) so the cashflow entry can be created.');
        }

        $entry = VendorPaymentEntry::create([
            'vendor_id' => $purchaseInvoice->vendor_id,
            'purchase_invoice_id' => $purchaseInvoice->id,
            'project_id' => $data['project_id'] ?? $purchaseInvoice->project_id,
            'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
            'invoice_number' => $purchaseInvoice->referenceNumber(),
            'foreign_currency' => $currency,
            'foreign_amount' => $amount,
            'exchange_rate' => $rate ?: null,
            'transaction_type' => 'debit',
            'entry_category' => 'payment',
            'particular' => filled($data['particular'] ?? null)
                ? $data['particular']
                : ($purchaseInvoice->isOrder()
                    ? 'Advance against purchase order '.$purchaseInvoice->invoice_number
                    : 'Payment against purchase bill '.$purchaseInvoice->invoice_number),
            'status' => $data['status'] ?? 'booked',
            'paid_account_id' => $data['paid_account_id'] ?? null,
            'payment_mode' => $data['payment_mode'] ?? null,
            'bank_reference_number' => $data['bank_reference_number'] ?? null,
            'amount_in_inr' => round($rupees, 2),
            'remarks' => $data['remarks'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->storePaymentAttachments($request, $entry);
        (new VendorPaymentCashflowSync())->sync($entry, $syncCashflow);
        $entry->refresh();

        $this->refreshInvoiceMoney($purchaseInvoice->fresh());

        return back()->with('success', 'Payment of '.number_format($amount, 2)
            .' '.$purchaseInvoice->currency.' filed against '.$purchaseInvoice->invoice_number.'.');
    }

    public function updateStatus(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $this->assertDocType($request, $purchaseInvoice);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PurchaseInvoice::statusOptions()))],
        ]);

        $target = $data['status'];
        $allowed = PurchaseInvoice::STATUS_FLOW[$purchaseInvoice->invoice_type][$purchaseInvoice->status] ?? [];

        if (! in_array($target, $allowed, true)) {
            return back()->with('error', 'A '.$purchaseInvoice->typeLabel().' cannot go from '
                .$purchaseInvoice->statusLabel().' to '.PurchaseInvoice::statusOptions()[$target].'.');
        }

        $purchaseInvoice->status = $target;

        foreach ([
            'sent' => 'sent_at',
            'approved' => 'approved_at',
            'received' => 'received_at',
            'cancelled' => 'cancelled_at',
        ] as $status => $column) {
            if ($target === $status && ! $purchaseInvoice->{$column}) {
                $purchaseInvoice->{$column} = now();
            }
        }

        $purchaseInvoice->save();
        $this->afterSave($purchaseInvoice->fresh(['items']));

        return back()->with('success', $purchaseInvoice->invoice_number.' marked '.$purchaseInvoice->statusLabel().'.');
    }

    public function print(PurchaseInvoice $purchaseInvoice): View
    {
        $this->assertDocType(request(), $purchaseInvoice);

        $purchaseInvoice->load(['items', 'vendor', 'project', 'purchaseOrder', 'convertedInvoice']);

        return view('purchase_invoices.print', [
            'invoice' => $purchaseInvoice,
            'docType' => $purchaseInvoice->invoice_type,
        ]);
    }

    /** The document at its public link — no login, the token is the key. */
    public function publicShow(string $token): View
    {
        $invoice = PurchaseInvoice::query()->where('public_token', $token)->firstOrFail();
        $invoice->load(['items', 'vendor', 'purchaseOrder', 'convertedInvoice']);

        return view('purchase_invoices.print', [
            'invoice' => $invoice,
            'docType' => $invoice->invoice_type,
            'public' => true,
        ]);
    }

    /* ------------------------------------------------------------------
       Writing a document
       ------------------------------------------------------------------ */

    private function validatedData(Request $request, ?PurchaseInvoice $invoice = null): array
    {
        return $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:purchase_invoices,invoice_number,'.($invoice ? $invoice->id : 'NULL')],
            'invoice_type' => ['nullable', Rule::in(array_keys(PurchaseInvoice::DOC_LABELS))],
            'status' => ['nullable', Rule::in(array_keys(PurchaseInvoice::statusOptionsFor(PurchaseInvoice::TYPE_BILL)
                + PurchaseInvoice::statusOptionsFor(PurchaseInvoice::TYPE_ORDER)))],
            'vendor_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency' => ['required', 'in:INR,USD,RMB'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'gst_type' => ['required', 'in:intra_state,inter_state,export'],
            'place_of_supply' => ['nullable', 'string', 'max:255'],
            'vendor_bill_number' => ['nullable', 'string', 'max:255'],
            'vendor_bill_date' => ['nullable', 'date'],
            'our_reference' => ['nullable', 'string', 'max:255'],

            'buyer_company_name' => ['nullable', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string'],
            'buyer_city' => ['nullable', 'string', 'max:255'],
            'buyer_state' => ['nullable', 'string', 'max:255'],
            'buyer_country' => ['nullable', 'string', 'max:255'],
            'buyer_pincode' => ['nullable', 'string', 'max:30'],
            'buyer_gstin' => ['nullable', 'string', 'max:30'],
            'buyer_pan' => ['nullable', 'string', 'max:20'],
            'buyer_email' => ['nullable', 'string', 'max:255'],
            'buyer_mobile' => ['nullable', 'string', 'max:40'],
            'buyer_website' => ['nullable', 'string', 'max:255'],

            'vendor_company_name' => ['nullable', 'string', 'max:255'],
            'vendor_contact_name' => ['nullable', 'string', 'max:255'],
            'vendor_email' => ['nullable', 'string', 'max:255'],
            'vendor_mobile' => ['nullable', 'string', 'max:40'],
            'vendor_gstin' => ['nullable', 'string', 'max:30'],
            'vendor_pan' => ['nullable', 'string', 'max:20'],
            'vendor_address' => ['nullable', 'string'],
            'vendor_city' => ['nullable', 'string', 'max:255'],
            'vendor_state' => ['nullable', 'string', 'max:255'],
            'vendor_country' => ['nullable', 'string', 'max:255'],
            'vendor_pincode' => ['nullable', 'string', 'max:30'],

            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'dispatch_terms' => ['nullable', 'string', 'max:255'],
            'transport_mode' => ['nullable', 'string', 'max:255'],
            'purchase_person' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'packing_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'terms_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],

            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.project_product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:40'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);
    }

    /**
     * Fill what the office left blank, and take the vendor's snapshot.
     *
     * The buyer block is our own company (the defaults, overwritten by whatever
     * the form carried). The vendor block is copied from the vendor record at
     * the moment of saving, and only where the form did not already hold a
     * value — a document already raised keeps the address it was raised with.
     */
    private function prepareInvoiceData(Request $request, string $type, array $data): array
    {
        $data['invoice_type'] = $type;
        $data['public_token'] = $data['public_token'] ?? Str::random(48);
        $data = array_merge(PurchaseInvoice::defaultBuyerDetails(), $data);
        $data['status'] = $data['status'] ?: 'draft';
        $data['discount_type'] = $data['discount_type'] ?? 'amount';
        $data['discount_value'] = $data['discount_value'] ?? 0;
        $data['freight_amount'] = $data['freight_amount'] ?? 0;
        $data['packing_amount'] = $data['packing_amount'] ?? 0;
        $data['other_charges'] = $data['other_charges'] ?? 0;
        $data['round_off'] = $data['round_off'] ?? 0;
        $data['amount_paid'] = $data['amount_paid'] ?? 0;
        $data['terms_conditions'] = $data['terms_conditions'] ?: PurchaseInvoice::defaultTerms();
        $data['created_by'] = $data['created_by'] ?? Auth::id();

        if (! empty($data['vendor_id'])) {
            $vendor = $this->vendorById($data['vendor_id']);

            if ($vendor) {
                foreach ($this->vendorSnapshot($vendor) as $field => $value) {
                    if (blank($data[$field] ?? null) && filled($value)) {
                        $data[$field] = $value;
                    }
                }
            }
        }

        if ($data['status'] === 'sent' && empty($data['sent_at'])) {
            $data['sent_at'] = now();
        }
        if ($data['status'] === 'approved' && empty($data['approved_at'])) {
            $data['approved_at'] = now();
        }
        if ($data['status'] === 'received' && empty($data['received_at'])) {
            $data['received_at'] = now();
        }
        if ($data['status'] === 'cancelled' && empty($data['cancelled_at'])) {
            $data['cancelled_at'] = now();
        }

        if ($request->filled('invoice_number')) {
            $data['invoice_number'] = $request->input('invoice_number');
        }

        return $data;
    }

    /**
     * Rebuild the lines and roll the document's totals up from them.
     *
     * The same arithmetic as the sales invoice — gross, line discount, the GST
     * split by `gst_type`, then the document-level discount re-scaling the tax
     * so a trade discount is not taxed. Purchase documents carry an import
     * case where no GST applies at all: `gst_type = export` leaves the lines
     * untaxed, because the supplier's bill carries the customs duty instead.
     */
    private function syncItemsAndTotals(PurchaseInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $subtotal = 0;
        $taxableTotal = 0;
        $cgstTotal = 0;
        $sgstTotal = 0;
        $igstTotal = 0;
        $sort = 1;

        foreach ($items as $item) {
            if (blank($item['product_name'] ?? null) && blank($item['product_id'] ?? null)) {
                continue;
            }

            $productName = ($item['product_name'] ?? null) ?: $this->productName($item['product_id'] ?? null);
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $gross = round($qty * $unitPrice, 2);
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            $discountAmount = round($gross * ($discountPercent / 100), 2);
            $taxable = max($gross - $discountAmount, 0);
            $gstPercent = (float) ($item['gst_percent'] ?? 18);
            $cgst = 0;
            $sgst = 0;
            $igst = 0;

            if ($invoice->gst_type === 'inter_state') {
                $igst = round($taxable * ($gstPercent / 100), 2);
            } elseif ($invoice->gst_type === 'intra_state') {
                $cgst = round($taxable * (($gstPercent / 2) / 100), 2);
                $sgst = round($taxable * (($gstPercent / 2) / 100), 2);
            }

            $lineTotal = round($taxable + $cgst + $sgst + $igst, 2);

            $invoice->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'project_product_id' => $item['project_product_id'] ?? null,
                'product_name' => $productName ?: 'Item',
                'description' => $item['description'] ?? null,
                'hsn_sac' => $item['hsn_sac'] ?? null,
                'quantity' => $qty ?: 1,
                'unit' => $item['unit'] ?? 'pcs',
                'unit_price' => $unitPrice,
                'gross_amount' => $gross,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxable,
                'gst_percent' => $gstPercent,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'line_total' => $lineTotal,
                'sort_order' => $sort,
                'remarks' => $item['remarks'] ?? null,
            ]);

            $subtotal += $gross;
            $taxableTotal += $taxable;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;
            $sort++;
        }

        $invoiceDiscountValue = (float) ($invoice->discount_value ?? 0);
        $invoiceDiscount = $invoice->discount_type === 'percent'
            ? round($taxableTotal * ($invoiceDiscountValue / 100), 2)
            : min($invoiceDiscountValue, $taxableTotal);

        $taxableAfterInvoiceDiscount = max($taxableTotal - $invoiceDiscount, 0);
        $taxRatio = $taxableTotal > 0 ? ($taxableAfterInvoiceDiscount / $taxableTotal) : 1;
        $cgstTotal = round($cgstTotal * $taxRatio, 2);
        $sgstTotal = round($sgstTotal * $taxRatio, 2);
        $igstTotal = round($igstTotal * $taxRatio, 2);

        $charges = (float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges;
        $total = round($taxableAfterInvoiceDiscount + $cgstTotal + $sgstTotal + $igstTotal + $charges + (float) $invoice->round_off, 2);

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($invoiceDiscount, 2),
            'taxable_amount' => round($taxableAfterInvoiceDiscount, 2),
            'cgst_amount' => $cgstTotal,
            'sgst_amount' => $sgstTotal,
            'igst_amount' => $igstTotal,
            'total_amount' => $total,
            'amount_in_words' => $this->amountInWords($total, $invoice->currency),
        ]);

        $this->refreshInvoiceMoney($invoice);
    }

    /**
     * The stored balance, asked the way the model asks it.
     *
     * `balance_amount` is written **from** the money rule, never the other way
     * round: the vendor statement prints this figure and it has to agree with
     * the bill the office is looking at.
     */
    private function refreshInvoiceMoney(PurchaseInvoice $invoice): void
    {
        if (! $invoice->exists) {
            return;
        }

        $invoice->update(['balance_amount' => $invoice->balanceDue()]);

        /* A bill's stored status follows the money once it has left the office:
           a received bill with part of it paid is `partial`, and one paid in
           full is `paid`. A draft is not advanced — the office has not said the
           bill arrived yet — and a cancellation stands for itself. */
        if ($invoice->isBill() && ! in_array($invoice->status, ['draft', 'cancelled'], true)) {
            $payment = $invoice->paymentState();

            if ($payment !== 'unpaid' && $invoice->status !== $payment) {
                $invoice->update(['status' => $payment]);
            }
        }
    }

    /**
     * Everything that happens once the document is written.
     *
     * A sent order, then the bill, posts into the vendor ledger here — one
     * place, called after every write (store, update, conversion, status
     * change, payment), so there is no path that leaves the ledger behind. A
     * billed order drops its own row so the bill is the only credit.
     */
    private function afterSave(PurchaseInvoice $invoice): void
    {
        (new PurchaseBillLedger())->sync($invoice);
        $this->refreshInvoiceMoney($invoice->fresh(['items']));
    }

    /**
     * A copy of the document, with the caller's overrides.
     *
     * `copyInvoice` is what makes "the bill is the order" true: the lines, the
     * rates, the GST split and the vendor snapshot all travel, so the bill is
     * right the moment it is raised and the office only adds what the vendor's
     * paperwork carried (their number, their date).
     */
    private function copyInvoice(PurchaseInvoice $source, array $overrides): PurchaseInvoice
    {
        $copy = $source->replicate(['public_token', 'converted_invoice_id', 'sent_at', 'approved_at', 'received_at', 'cancelled_at']);
        $copy->fill($overrides);
        $copy->save();

        foreach ($source->items()->get() as $item) {
            $line = $item->replicate(['purchase_invoice_id']);
            $line->purchase_invoice_id = $copy->id;
            $line->save();
        }

        $copy->update([
            'subtotal' => $source->subtotal,
            'discount_amount' => $source->discount_amount,
            'taxable_amount' => $source->taxable_amount,
            'cgst_amount' => $source->cgst_amount,
            'sgst_amount' => $source->sgst_amount,
            'igst_amount' => $source->igst_amount,
            'total_amount' => $source->total_amount,
            'balance_amount' => $source->total_amount,
            'amount_in_words' => $source->amount_in_words,
        ]);

        return $copy->fresh(['items']);
    }

    /* ------------------------------------------------------------------
       The vendor, the products, the numbers
       ------------------------------------------------------------------ */

    /** What a vendor record contributes to a document, by field name. */
    private function vendorSnapshot(Vendor $vendor): array
    {
        return [
            'vendor_company_name' => $vendor->vendor_name,
            'vendor_contact_name' => $vendor->contact_person_name,
            'vendor_email' => $vendor->contact_person_email,
            'vendor_mobile' => $vendor->contact_person_mobile,
            'vendor_gstin' => $vendor->gstin,
            'vendor_pan' => $vendor->pan,
            'vendor_address' => $vendor->address,
            'vendor_city' => $vendor->city,
            'vendor_state' => $vendor->state,
            'vendor_country' => $vendor->country,
            'vendor_pincode' => $vendor->pincode,
            'currency' => $vendor->preferred_currency ?: 'INR',
        ];
    }

    /** The lines a new bill starts with: the order's, or one blank line. */
    private function sourceOrder(Request $request): ?PurchaseInvoice
    {
        $id = (int) $request->query('from_order', 0);

        return $id > 0
            ? PurchaseInvoice::query()->whereKey($id)->where('invoice_type', 'order')->with('items')->first()
            : null;
    }

    private function productName($productId): ?string
    {
        if (! $productId) {
            return null;
        }

        return Product::query()->whereKey($productId)->value('name');
    }

    /**
     * The document number, in its own series.
     *
     * `MP/PO/{FY}/001` for orders and `MP/BILL/{FY}/001` for bills, the same
     * financial-year shape the sales invoices use, continuing after the highest
     * number already issued rather than counting rows — a deleted draft must
     * never hand out a number twice.
     */
    private function makeInvoiceNumber(string $type): string
    {
        $financialYear = now()->month >= 4
            ? now()->format('y').'-'.now()->addYear()->format('y')
            : now()->subYear()->format('y').'-'.now()->format('y');

        $prefix = $type === PurchaseInvoice::TYPE_ORDER
            ? "MP/PO/{$financialYear}/"
            : "MP/BILL/{$financialYear}/";

        $last = PurchaseInvoice::query()
            ->where('invoice_type', $type)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $next = $last ? ((int) last(explode('/', $last))) + 1 : 1;
        $number = $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);

        while (PurchaseInvoice::query()->where('invoice_number', $number)->exists()) {
            $number = $prefix.str_pad((string) (++$next), 3, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    private function amountInWords(float $amount, string $currency): string
    {
        $number = (int) floor($amount);
        $paise = (int) round(($amount - $number) * 100);

        $text = ($currency === 'INR' ? 'Rupees' : $currency).' '.$this->numberToWords($number);

        if ($paise > 0) {
            $text .= ' and '.$this->numberToWords($paise).' Paise';
        }

        return $text.' Only';
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $belowHundred = function ($n) use ($ones, $tens) {
            if ($n < 20) {
                return $ones[$n];
            }

            return trim($tens[(int) floor($n / 10)].' '.$ones[$n % 10]);
        };

        $belowThousand = function ($n) use ($ones, $belowHundred) {
            $words = '';

            if ($n >= 100) {
                $words .= $ones[(int) floor($n / 100)].' Hundred ';
                $n %= 100;
            }

            return trim($words.($n > 0 ? $belowHundred($n) : ''));
        };

        $parts = [];

        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$unit, $label]) {
            if ($number >= $unit) {
                $parts[] = $belowThousand((int) floor($number / $unit)).' '.$label;
                $number %= $unit;
            }
        }

        if ($number > 0) {
            $parts[] = $belowThousand($number);
        }

        return implode(' ', array_filter($parts));
    }

    /* ------------------------------------------------------------------
       Shared data and small helpers
       ------------------------------------------------------------------ */

    private function sharedData(?string $type = null): array
    {
        $vendors = $this->vendors();

        $snapshot = $vendors->isNotEmpty() ? array_keys($this->vendorSnapshot($vendors->first())) : [];

        return [
            'vendors' => $vendors,
            'vendorSnapshots' => $vendors->mapWithKeys(fn ($vendor) => [$vendor->id => $this->vendorSnapshot($vendor)])->all(),
            'vendorSnapshotFields' => $snapshot,
            'projects' => $this->projects(),
            'products' => $this->products(),
            'accounts' => $this->cashflowAccounts(),
            'paymentModeOptions' => class_exists(VendorPaymentEntry::class)
                ? VendorPaymentEntry::paymentModeOptions()
                : (class_exists(CashflowEntry::class) ? CashflowEntry::paymentModeOptions() : []),
            'ledgerStatusOptions' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::statusOptions() : [],
            'ledgerCurrencyOptions' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::currencyOptions() : PurchaseInvoice::currencyOptions(),
            'statusOptions' => PurchaseInvoice::statusOptionsFor($type ?: PurchaseInvoice::TYPE_BILL)
                + PurchaseInvoice::statusOptionsFor(PurchaseInvoice::TYPE_ORDER),
            'allStatusOptions' => PurchaseInvoice::statusOptions(),
            'currencyOptions' => PurchaseInvoice::currencyOptions(),
            'gstTypeOptions' => PurchaseInvoice::gstTypeOptions(),
            'buyerDefaults' => PurchaseInvoice::defaultBuyerDetails(),
            'defaultTerms' => PurchaseInvoice::defaultTerms(),
            'docLabels' => PurchaseInvoice::DOC_LABELS,
            'typeOptions' => PurchaseInvoice::DOC_LABELS,
            'routePrefix' => 'purchase-invoices',
        ];
    }

    private function vendors()
    {
        return $this->vendorAvailable() ? Vendor::query()->orderBy('vendor_name')->get() : collect();
    }

    private function vendorById($id): ?Vendor
    {
        return $this->vendorAvailable() && $id ? Vendor::query()->find($id) : null;
    }

    private function projects()
    {
        return $this->projectAvailable() ? Project::query()->orderBy('name')->get() : collect();
    }

    private function products()
    {
        return $this->productAvailable() ? Product::query()->orderBy('name')->get() : collect();
    }

    private function vendorAvailable(): bool
    {
        return class_exists(Vendor::class) && Schema::hasTable('vendors');
    }

    private function projectAvailable(): bool
    {
        return class_exists(Project::class) && Schema::hasTable('projects');
    }

    private function productAvailable(): bool
    {
        return class_exists(Product::class) && Schema::hasTable('products');
    }

    private function ledgerAvailable(): bool
    {
        return (new PurchaseBillLedger())->available();
    }

    private function storePaymentAttachments(Request $request, VendorPaymentEntry $entry): void
    {
        if (! class_exists(VendorPaymentAttachment::class) || ! Schema::hasTable('vendor_payment_attachments')) {
            return;
        }

        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('vendor-payments/'.$entry->vendor_id, 'public');

            $entry->attachments()->create([
                'title' => $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => strtolower((string) $file->getClientOriginalExtension()),
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function cashflowAccounts()
    {
        if (! class_exists(CashflowAccount::class) || ! Schema::hasTable('cashflow_accounts')) {
            return collect();
        }

        return CashflowAccount::query()->orderBy('account_name')->get();
    }

    /**
     * The paid figure for a raw SQL aggregate, guarded for an install whose
     * ledger table predates this module: without it, the column that stands for
     * "paid" is the opening figure alone.
     */
    private function paidSql(): string
    {
        return $this->ledgerAvailable()
            ? PurchaseInvoice::PAID_SQL
            : 'coalesce(purchase_invoices.amount_paid, 0)';
    }

    /** The document type the form asked for (order or bill). */
    private function requestType(Request $request): string
    {
        $type = (string) $request->input('invoice_type', $request->query('type', $request->query('invoice_type', PurchaseInvoice::TYPE_ORDER)));

        return array_key_exists($type, PurchaseInvoice::DOC_LABELS)
            ? $type
            : PurchaseInvoice::TYPE_ORDER;
    }

    private function assertDocType(Request $request, PurchaseInvoice $invoice): void
    {
        // One UI: any document is reachable under purchase-invoices.*.
    }

    private const PAYMENT_LABELS = [
        'nothing' => 'Nothing paid',
        'partial' => 'Part paid',
        'paid' => 'Fully paid',
    ];
}
