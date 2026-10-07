<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceAttachment;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceReminder;
use App\Models\SavedView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\ProjectProducts;
use App\Services\SalesInvoiceFilters;
use App\Services\SavedViews;

class SalesInvoiceController extends Controller
{
    /** Saved views belong to the screen that saved them. */
    private const VIEW_MODULE = 'sales-invoices';

    /**
     * What the listing's bulk bar can do to a selection.
     *
     * Each is the same change a row action makes, applied to many rows — there is
     * no second implementation of "mark sent" or "show in portal" here, only a
     * loop around the same one. `delete_drafts` is the odd one out and says so:
     * it refuses anything that has left draft, because a sent invoice is a
     * document the client already has.
     */
    private const BULK_ACTIONS = [
        'remind' => 'Log a reminder',
        'mark_sent' => 'Mark sent (and show in portal)',
        'portal_on' => 'Show in client portal',
        'portal_off' => 'Hide from client portal',
        'delete_drafts' => 'Delete drafts only',
    ];

    /** The saved view a link asked for, as the query string it was saved with. */
    public function index(Request $request): View
    {
        // Jumping back into a saved view re-runs its filters.
        if ($savedQuery = $this->resolveSavedView($request)) {
            return redirect()->route('sales-invoices.index', $savedQuery);
        }

        $filters = app(SalesInvoiceFilters::class)->fromRequest($request);

        /* The figures, the page totals and the rows each get a query of their own.
           That is not tidiness: `withCount`/`withMax`/`withSum` write
           `sales_invoices.*` and correlated subqueries into the **column list**, and
           `selectRaw` appends to that list — an aggregate beside non-aggregated
           columns is MySQL 1140 (`only_full_group_by`) on any host that runs with
           the mode on, which the office's does. A query with no columns of its own
           takes one aggregate select cleanly.

           The money rule is the model's (`RECEIVED_SQL`), so the tile, the chip, the
           row and the CSV all count the same money. */
        /* One document, one claim on the money: a proforma that has become a
           tax invoice is not a receivable, and this clause is what every sum
           below says that with. */
        $live = "(sales_invoices.invoice_type <> 'proforma' or sales_invoices.converted_invoice_id is null)";

        $figures = $this->filteredQuery($filters)->reorder()->selectRaw(
            'coalesce(sum(case when sales_invoices.invoice_type = \'tax\''
                ." and sales_invoices.status <> 'cancelled' then sales_invoices.total_amount else 0 end), 0) as sales"
            .', coalesce(sum(case when sales_invoices.invoice_type = \'proforma\''
                ." and sales_invoices.converted_invoice_id is null and sales_invoices.status <> 'cancelled'"
                .' then sales_invoices.total_amount else 0 end), 0) as potential'
            .', coalesce(sum(case when '.$live.' then '.SalesInvoice::RECEIVED_SQL.' else 0 end), 0) as received'

            /* What is owed is the Balance column, summed — the same rule as the
               row beside it. A draft is money the office has recorded, not money
               that vanishes: the tax invoice a conversion creates starts as one
               with the advance already on it. Only a cancellation takes a document
               out of what is owed. */
            .', coalesce(sum(case when '.$live.' and '.SalesInvoice::RECEIVED_SQL.' < sales_invoices.total_amount - 0.01'
                ." and sales_invoices.status <> 'cancelled' then sales_invoices.total_amount - ".SalesInvoice::RECEIVED_SQL.' else 0 end), 0) as outstanding'
            .', coalesce(sum(case when sales_invoices.due_date is not null and sales_invoices.due_date < ?'
                ." and sales_invoices.status not in ('draft', 'cancelled')"
                .' and '.$live
                .' and '.SalesInvoice::RECEIVED_SQL.' < sales_invoices.total_amount - 0.01'
                .' then sales_invoices.total_amount - '.SalesInvoice::RECEIVED_SQL.' else 0 end), 0) as overdue'
        )
            ->addBinding([now()->toDateString()], 'select')
            ->first();

        $stats = [
            /* Sales are the tax invoices. A proforma is potential revenue until
               one is raised from it, and neither after that. */
            'sales' => (float) ($figures->sales ?? 0),
            'potential' => (float) ($figures->potential ?? 0),
            'received' => (float) ($figures->received ?? 0),
            'outstanding' => (float) ($figures->outstanding ?? 0),
            'overdue' => (float) ($figures->overdue ?? 0),
            /* How many are still drafts — across the module, not the filtered
               set, because the chip that asks for drafts is the one that makes
               them the filtered set. */
            'drafts' => SalesInvoice::query()->where('status', 'draft')->count(),
        ];

        /* What the footer says is owed: one query of its own, so the listing's own
           reads (`withReceived`, the payment count, the reminder count) never stand
           in front of it. */
        $totals = $this->filteredQuery($filters)->reorder()->selectRaw(
            'coalesce(sum(case when '.$live.' then sales_invoices.total_amount else 0 end), 0) as counted'
            .', coalesce(sum(case when '.$live.' then '.SalesInvoice::RECEIVED_SQL.' else 0 end), 0) as received'
            .', coalesce(sum(case when '.$live.' and '.SalesInvoice::RECEIVED_SQL.' < sales_invoices.total_amount - 0.01'
                ." and sales_invoices.status <> 'cancelled' then sales_invoices.total_amount - ".SalesInvoice::RECEIVED_SQL.' else 0 end), 0) as outstanding'
        )->first();

        $pageTotals = [
            'counted' => (float) ($totals->counted ?? 0),
            'received' => (float) ($totals->received ?? 0),
            'outstanding' => (float) ($totals->outstanding ?? 0),
        ];

        $invoices = $this->filteredQuery($filters)
            ->withReceived()
            ->withCount('payments')
            ->withReminders()
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('sales_invoices.index', array_merge($this->sharedData(), [
            'invoices' => $invoices,
            'stats' => $stats,
            'pageTotals' => $pageTotals,
            'chipCounts' => $this->chipCounts($filters, $invoices->total()),
            'appliedChips' => app(SalesInvoiceFilters::class)->applied($filters),
            'dateRanges' => DateRanges::presets(),
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => DateRanges::keyOf($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null),
            'savedViews' => app(SavedViews::class)->forUser(Auth::id(), self::VIEW_MODULE),
            'ageingBuckets' => SalesInvoice::ageingBuckets(),
            'paymentLabels' => SalesInvoiceFilters::PAYMENT_LABELS,
            'chaseLabels' => SalesInvoiceFilters::CHASE_LABELS,
            'reminderChannels' => SalesInvoiceReminder::channelOptions(),
            'accounts' => $this->accounts(),
            'bulkActions' => self::BULK_ACTIONS,
            ...$filters,
        ]));
    }

    /**
     * The rows the filters ask for.
     *
     * One query builder, used by the list, the figures and the CSV — a file
     * exported from a screen has to contain the rows that screen is showing.
     *
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters)
    {
        $with = ['items'];
        if ($this->clientAvailable()) { $with[] = 'client'; }
        if ($this->projectAvailable()) { $with[] = 'project'; }

        return app(SalesInvoiceFilters::class)->apply(SalesInvoice::query()->with($with), $filters);
    }

    /**
     * The list as the accountant's spreadsheet.
     *
     * Raw numbers (two decimals, no symbols, no grouping), a UTF-8 byte-order
     * mark so Excel reads the rupee sign, and a header that says what the file
     * is and which filters were on when it was taken — a file that leaves the
     * app has to explain itself a year later.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = app(SalesInvoiceFilters::class)->fromRequest($request);
        $selected = $this->selectedIds($request);

        $query = $this->filteredQuery($filters)->withReceived();

        /* "Export the selected rows" is the same file as the screen's, narrowed to
           what was ticked — a second exporter is how the two files start disagreeing. */
        if ($selected !== []) {
            $query->whereIn('sales_invoices.id', $selected);
        }

        $rows = $query->latest('invoice_date')->latest('id')->get();
        $labels = app(SalesInvoiceFilters::class)->labels($filters);
        $applied = app(SalesInvoiceFilters::class)->applied($filters);

        return response()->streamDownload(function () use ($rows, $applied, $labels, $selected) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $row = fn (array $cells) => fputcsv($out, $cells);

            $row(['Sales invoices']);
            $row(['Taken', now()->format('d M Y H:i')]);
            $row(['Rows', $rows->count()]);
            $row(['Filtered by', $this->filteredByLine($selected, $applied, $labels)]);
            $row([]);

            $row([
                'Invoice', 'Type', 'Date', 'Due', 'Client', 'GSTIN', 'Project', 'Currency',
                'Total', 'Received', 'Balance', 'State', 'Days late', 'Portal',
            ]);

            $money = fn ($value) => number_format((float) $value, 2, '.', '');

            foreach ($rows as $invoice) {
                $row([
                    $invoice->invoice_number,
                    $invoice->typeLabel(),
                    optional($invoice->invoice_date)->format('Y-m-d'),
                    optional($invoice->due_date)->format('Y-m-d'),
                    $invoice->client_company_name,
                    $invoice->client_gstin,
                    $invoice->project?->project_number,
                    $invoice->currency,
                    $money($invoice->total_amount),
                    $money($invoice->receivedAmount()),
                    $money($invoice->balanceDue()),
                    $invoice->stateLabel(),
                    $invoice->daysOverdue() ?: '',
                    $invoice->show_client_portal ? 'Visible' : 'Hidden',
                ]);
            }
        }, 'sales-invoices-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Record a receipt against the invoice.
     *
     * The money lives in the cashflow ledger — that is where the bank line is
     * reconciled and where the client's statement is built from — so this writes
     * an entry linked to the invoice (`sales_invoice_id`) instead of a second,
     * private number on the invoice itself. The invoice's balance is then what
     * the ledger says, here and on the client's statement.
     */
    public function recordPayment(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'entry_date' => ['required', 'date'],
            'account_id' => ['nullable', 'integer'],
            'payment_mode' => ['nullable', 'string', 'max:40'],
            'bank_reference_number' => ['nullable', 'string', 'max:191'],
            'particular' => ['nullable', 'string', 'max:191'],
        ]);

        $entry = new CashflowEntry();
        $entry->entry_date = $data['entry_date'];
        $entry->particular = $data['particular'] ?: 'Receipt against '.$salesInvoice->invoice_number;
        $entry->transaction_type = 'credit';
        $entry->credit_amount = round((float) $data['amount'], 2);
        $entry->debit_amount = 0;
        $entry->currency = in_array($salesInvoice->currency, array_keys(CashflowEntry::currencyOptions()), true)
            ? $salesInvoice->currency
            : 'INR';
        $entry->account_id = $data['account_id'] ?? null;
        $entry->payment_mode = CashflowEntry::normalisePaymentMode($data['payment_mode'] ?? null);
        $entry->bank_reference_number = $data['bank_reference_number'] ?? null;
        $entry->invoice_bill_number = $salesInvoice->invoice_number;
        $entry->client_id = $salesInvoice->client_id;
        $entry->related_party_type = 'client';
        $entry->related_party_name = $salesInvoice->client_company_name;
        $entry->sales_invoice_id = $salesInvoice->id;
        $entry->accounting_status = 'pending';
        $entry->created_by = Auth::id();

        if (Schema::hasColumn('cashflow_entries', 'project_id')) {
            $entry->project_id = $salesInvoice->project_id;
        }

        $entry->save();

        $this->refreshInvoiceMoney($salesInvoice);

        return back()->with('success', 'Receipt of '.number_format($entry->credit_amount, 2)
            .' recorded against '.$salesInvoice->invoice_number.'.');
    }

    /** The list's own "make it visible / hide it" switch. */
    public function togglePortal(SalesInvoice $salesInvoice): RedirectResponse
    {
        $salesInvoice->update(['show_client_portal' => ! $salesInvoice->show_client_portal]);

        return back()->with('success', $salesInvoice->show_client_portal
            ? $salesInvoice->invoice_number.' is visible in the client portal.'
            : $salesInvoice->invoice_number.' is hidden from the client portal.');
    }

    /**
     * A copy of the invoice, as a fresh draft.
     *
     * Same client, same items, same terms — a new number and no money against
     * it. This is how a repeating order is billed, and how a wrong invoice is
     * re-issued without retyping eight line items.
     */
    public function duplicate(SalesInvoice $salesInvoice): RedirectResponse
    {
        $copy = $this->copyInvoice($salesInvoice, [
            'invoice_number' => $this->makeInvoiceNumber($salesInvoice->invoice_type),
            'status' => 'draft',
            'amount_paid' => 0,
            'balance_amount' => (float) $salesInvoice->total_amount,
            'public_token' => null,
            'sent_at' => null,
            'accepted_at' => null,
            'cancelled_at' => null,
            'notes' => trim('Copy of '.$salesInvoice->invoice_number.'. '.($salesInvoice->notes ?? '')),
        ]);

        return redirect()->route('sales-invoices.edit', $copy)
            ->with('success', $copy->invoice_number.' created as a draft copy of '.$salesInvoice->invoice_number.'.');
    }

    /**
     * A proforma becomes a tax invoice.
     *
     * The office raises a proforma to ask for the money and a tax invoice once
     * it is agreed; making the second one by hand is where the two start
     * disagreeing about quantities. The proforma is left alone (it is the
     * document the client was shown) and the tax invoice opens for review.
     */
    public function convert(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        if ($salesInvoice->invoice_type === 'tax') {
            return back()->with('error', $salesInvoice->invoice_number.' is already a tax invoice.');
        }

        /* A cancelled document is not a document: it owes nothing and stands for
           nothing, so there is nothing to re-issue. */
        if ($salesInvoice->status === 'cancelled') {
            return back()->with('error', $salesInvoice->invoice_number
                .' is cancelled — bring it back to life before raising a tax invoice from it.');
        }

        if ($already = $salesInvoice->convertedInvoice) {
            return back()->with('error', $salesInvoice->invoice_number.' has already become '
                .$already->invoice_number.'. A proforma becomes one tax invoice — edit that one, '
                .'or duplicate this proforma as a draft if a second document is really needed.');
        }

        /* The check above is the message; this one is the rule. A conversion is
           two clicks apart at worst, and the lock is what stops the second one
           from raising a second tax invoice from the same proforma. */
        $tax = DB::transaction(function () use ($salesInvoice) {
            $proforma = SalesInvoice::query()->whereKey($salesInvoice->getKey())->lockForUpdate()->first();

            if (! $proforma || $proforma->invoice_type === 'tax' || $proforma->converted_invoice_id) {
                return null;
            }

            $tax = $this->copyInvoice($proforma, [
                'invoice_type' => 'tax',
                'invoice_number' => $this->makeInvoiceNumber('tax'),
                /* The conversion continues the document rather than restarting
                   it: whatever the proforma had reached — sent, accepted — the
                   tax invoice has reached too, and it is chased on the same due
                   date. A draft proforma still becomes a draft tax invoice. */
                'status' => $proforma->status,
                'amount_paid' => 0,
                'public_token' => null,
                'sent_at' => $proforma->sent_at,
                'accepted_at' => $proforma->accepted_at,
                'cancelled_at' => null,
                'notes' => trim('Converted from '.$proforma->invoice_number.'. '.($proforma->notes ?? '')),
            ]);

            $this->moveAdvanceTo($proforma, $tax);

            $proforma->converted_invoice_id = $tax->id;
            $proforma->save();

            return $tax;
        });

        if (! $tax) {
            return back()->with('error', 'A tax invoice already exists for '.$salesInvoice->invoice_number.'.');
        }

        return redirect()->route('sales-invoices.edit', $tax)
            ->with('success', $tax->invoice_number.' created from proforma '.$salesInvoice->invoice_number
                .'. The advance and every receipt filed against the proforma moved to it.');
    }

    /**
     * One advance, one document.
     *
     * The proforma asked for the money; the tax invoice is what is owed. So the
     * opening figure the office typed on the proforma and every receipt filed
     * against it in the ledger move across, and both balances are recomputed from
     * the one money rule (`receivedAmount()`), because a stored balance that
     * disagrees with the rule is the whole bug this module keeps having.
     */
    private function moveAdvanceTo(SalesInvoice $proforma, SalesInvoice $tax): void
    {
        $opening = round((float) $proforma->amount_paid, 2);

        if ($opening > 0) {
            $tax->amount_paid = round((float) $tax->amount_paid + $opening, 2);
            $proforma->amount_paid = 0;
            $tax->save();
            $proforma->save();
        }

        if (Schema::hasColumn('cashflow_entries', 'sales_invoice_id')) {
            CashflowEntry::query()
                ->where('sales_invoice_id', $proforma->id)
                ->update(['sales_invoice_id' => $tax->id]);
        }

        $this->refreshInvoiceMoney($tax);
        $this->refreshInvoiceMoney($proforma);
    }

    /** Everything but the money and the documents, carried onto a new invoice. */
    private function copyInvoice(SalesInvoice $source, array $overrides): SalesInvoice
    {
        /* The link is never copied: a duplicate is a new document, and a
           proforma copy is not "already converted" to anything. */
        $copy = $source->replicate(['created_at', 'updated_at', 'converted_invoice_id']);
        $copy->fill($overrides);
        $copy->created_by = Auth::id();
        $copy->save();

        foreach ($source->items as $item) {
            $line = $item->replicate(['created_at', 'updated_at']);
            $line->sales_invoice_id = $copy->id;
            $line->save();
        }

        /* A copy is a document like any other: a proforma that becomes a tax
           invoice, and a duplicate the office is about to edit, both carry
           lines the project's products follow. Materialised here rather than
           left to the copy's first save, because the office may never make
           one — the document already exists. */
        app(ProjectProducts::class)->fromSalesInvoice($copy);

        return $copy;
    }

    /** Keep the stored balance in step with the money rule. */
    private function refreshInvoiceMoney(SalesInvoice $invoice): void
    {
        $invoice->unsetRelation('payments')->refresh();

        $balance = $invoice->balanceDue();

        if (abs((float) $invoice->balance_amount - $balance) > 0.001) {
            $invoice->forceFill(['balance_amount' => $balance])->saveQuietly();
        }
    }

    public function storeSavedView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        app(SavedViews::class)->save(
            Auth::id(),
            self::VIEW_MODULE,
            $data['name'],
            $request->query(),
            $request->boolean('is_shared')
        );

        return back()->with('success', 'View "'.$data['name'].'" saved.');
    }

    public function destroySavedView(Request $request, SavedView $savedView): RedirectResponse
    {
        abort_unless((int) $savedView->user_id === (int) Auth::id(), 403);

        app(SavedViews::class)->delete(Auth::id(), $savedView->id);

        return back()->with('success', 'Saved view removed.');
    }

    /**
     * When the request carries ?saved_view=ID, the saved query is what should be
     * rendered — this turns it back into the URL the list already speaks.
     *
     * @return array<string, string>
     */
    private function resolveSavedView(Request $request): array
    {
        $id = (int) $request->query('saved_view', 0);

        if (! $id) {
            return [];
        }

        $view = SavedView::query()
            ->where('module', self::VIEW_MODULE)
            ->where(function ($query) {
                $query->where('user_id', Auth::id())->orWhere('is_shared', true);
            })
            ->find($id);

        return $view ? app(SavedViews::class)->queryFor($view) : [];
    }

    /**
     * How many rows each chip would show — asked of the same query the rows
     * come from, with that chip's own dimension reset.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    /**
     * The number each chip in the strip carries.
     *
     * A chip's count is the number of rows that chip would show, asked with the
     * rest of the view kept: "Proforma 12" on a month view is twelve proformas
     * in that month. Only the dimension the chip itself replaces is reset, so
     * the counts and the click agree. The strip is four chips plus the periods,
     * so this is four count queries — the payment, ageing and chase chips were
     * removed from the strip, and the thirteen counts behind them with them.
     *
     * @param  array<string, mixed>  $filters
     */
    private function chipCounts(array $filters, int $all): array
    {
        $count = fn (array $overrides) => $this->filteredQuery(array_merge($filters, $overrides))->count();

        $counts = [
            'all' => $all,
            'proforma' => $count(['type' => 'proforma']),
            'tax' => $count(['type' => 'tax']),
            'draft' => $count(['status' => 'draft']),
        ];

        /* The period chips replace the dates they own, and keep everything else. */
        foreach (DateRanges::presets() as $key => $range) {
            $counts[$key] = $count([
                'dateFrom' => $range['from'],
                'dateTo' => $range['to'],
            ]);
        }

        return $counts;
    }

    /** The accounts a receipt can land in. */
    private function accounts()
    {
        if (! Schema::hasTable('cashflow_accounts')) {
            return collect();
        }

        return CashflowAccount::query()->orderBy('account_name')->get();
    }

    public function create(Request $request): View
    {
        $invoiceType = $request->query('type', 'proforma');
        if (! in_array($invoiceType, ['proforma', 'tax'], true)) {
            $invoiceType = 'proforma';
        }

        $client = $this->clientFromRequest($request);
        $project = $this->projectFromRequest($request);

        if (! $client && $project && isset($project->client_id)) {
            $client = $this->clientById($project->client_id);
        }

        $invoice = new SalesInvoice(array_merge(SalesInvoice::defaultSellerDetails(), [
            'invoice_number' => $this->makeInvoiceNumber($invoiceType),
            'invoice_type' => $invoiceType,
            'status' => 'draft',
            'client_id' => $client ? $client->id : null,
            'project_id' => $project ? $project->id : null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'currency' => $client ? ($client->preferred_currency ?? 'INR') : 'INR',
            'gst_type' => 'intra_state',
            'payment_terms' => '50% advance, balance before dispatch',
            'delivery_terms' => 'As mutually discussed',
            'dispatch_terms' => 'Dispatch after payment and approval confirmation',
            'terms_conditions' => SalesInvoice::defaultTerms(),
            'show_client_portal' => false,
        ]));

        if ($client) {
            $invoice->fill($this->clientSnapshot($client));
        }

        $items = $this->itemsFromSource($project);
        $invoice->setRelation('items', collect($items));
        $invoice->setRelation('attachments', collect());

        return view('sales_invoices.form', array_merge($this->sharedData(), compact('invoice')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $items = $data['items'] ?? [];
        unset($data['items'], $data['attachments']);

        $data = $this->prepareInvoiceData($request, $data);
        $data['invoice_number'] = $data['invoice_number'] ?: $this->makeInvoiceNumber($data['invoice_type']);
        $data['created_by'] = Auth::id();

        $invoice = DB::transaction(function () use ($request, $data, $items) {
            $invoice = SalesInvoice::create($data);
            $this->syncItemsAndTotals($invoice, $items);
            $this->storeAttachments($request, $invoice);
            return $invoice;
        });

        return redirect()->route('sales-invoices.show', $invoice)->with('success', 'Sales invoice created successfully.');
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items.product', 'attachments', 'creator', 'reminders.creator']);
        if ($this->clientAvailable()) { $salesInvoice->load('client'); }
        if ($this->projectAvailable()) { $salesInvoice->load('project'); }

        /* `accounts` is not decoration: the record page carries the receipt
           dialog, and a dialog whose account list is missing is a 500. */
        return view('sales_invoices.show', [
            'invoice' => $salesInvoice,
            'accounts' => $this->accounts(),
            'reminderChannels' => SalesInvoiceReminder::channelOptions(),
        ]);
    }

    public function edit(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items', 'attachments']);
        return view('sales_invoices.form', array_merge($this->sharedData(), ['invoice' => $salesInvoice]));
    }

    public function update(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $this->validatedData($request, $salesInvoice);
        $items = $data['items'] ?? [];
        unset($data['items'], $data['attachments']);
        $data = $this->prepareInvoiceData($request, $data);

        DB::transaction(function () use ($request, $salesInvoice, $data, $items) {
            $salesInvoice->update($data);
            $this->syncItemsAndTotals($salesInvoice, $items);
            $this->storeAttachments($request, $salesInvoice);
        });

        return redirect()->route('sales-invoices.show', $salesInvoice)->with('success', 'Sales invoice updated successfully.');
    }

    public function destroy(SalesInvoice $salesInvoice): RedirectResponse
    {
        foreach ($salesInvoice->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }
        $salesInvoice->delete();

        return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice deleted successfully.');
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items', 'attachments']);
        return view('sales_invoices.print', ['invoice' => $salesInvoice, 'publicMode' => false]);
    }

    public function publicShow(string $token): View
    {
        $invoice = SalesInvoice::where('public_token', $token)
            ->where('show_client_portal', true)
            ->where('status', '!=', 'draft')
            ->with(['items', 'publicAttachments'])
            ->firstOrFail();
        return view('sales_invoices.print', ['invoice' => $invoice, 'publicMode' => true]);
    }

    public function markSent(SalesInvoice $salesInvoice): RedirectResponse
    {
        $salesInvoice->update(['status' => 'sent', 'sent_at' => now(), 'show_client_portal' => true]);
        return back()->with('success', 'Invoice marked as sent and visible to client portal.');
    }

    public function destroyAttachment(SalesInvoiceAttachment $attachment): RedirectResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    private function validatedData(Request $request, ?SalesInvoice $invoice = null): array
    {
        return $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:sales_invoices,invoice_number,'.($invoice ? $invoice->id : 'NULL')],
            'invoice_type' => ['required', 'in:proforma,tax'],
            'status' => ['required', Rule::in(array_keys(SalesInvoice::statusOptions()))],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['required', 'in:INR,USD,RMB'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'gst_type' => ['required', 'in:intra_state,inter_state,export'],
            'place_of_supply' => ['nullable', 'string', 'max:255'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],

            'seller_company_name' => ['nullable', 'string', 'max:255'],
            'seller_address' => ['nullable', 'string'],
            'seller_city' => ['nullable', 'string', 'max:255'],
            'seller_state' => ['nullable', 'string', 'max:255'],
            'seller_country' => ['nullable', 'string', 'max:255'],
            'seller_pincode' => ['nullable', 'string', 'max:30'],
            'seller_gstin' => ['nullable', 'string', 'max:30'],
            'seller_pan' => ['nullable', 'string', 'max:20'],
            'seller_email' => ['nullable', 'string', 'max:255'],
            'seller_mobile' => ['nullable', 'string', 'max:40'],
            'seller_website' => ['nullable', 'string', 'max:255'],
            'seller_bank_name' => ['nullable', 'string', 'max:255'],
            'seller_account_holder' => ['nullable', 'string', 'max:255'],
            'seller_account_number' => ['nullable', 'string', 'max:255'],
            'seller_ifsc' => ['nullable', 'string', 'max:255'],
            'seller_branch' => ['nullable', 'string', 'max:255'],
            'seller_swift' => ['nullable', 'string', 'max:255'],

            'client_company_name' => ['nullable', 'string', 'max:255'],
            'client_brand_name' => ['nullable', 'string', 'max:255'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'max:255'],
            'client_mobile' => ['nullable', 'string', 'max:40'],
            'client_gstin' => ['nullable', 'string', 'max:30'],
            'client_pan' => ['nullable', 'string', 'max:20'],
            'billing_address' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:255'],
            'billing_pincode' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:255'],
            'shipping_pincode' => ['nullable', 'string', 'max:30'],

            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'dispatch_terms' => ['nullable', 'string', 'max:255'],
            'transport_mode' => ['nullable', 'string', 'max:255'],
            'sales_person' => ['nullable', 'string', 'max:255'],
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
            'show_client_portal' => ['nullable', 'boolean'],

            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.project_product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
        ]);
    }

    private function prepareInvoiceData(Request $request, array $data): array
    {
        $data = array_merge(SalesInvoice::defaultSellerDetails(), $data);
        $data['discount_type'] = $data['discount_type'] ?? 'amount';
        $data['discount_value'] = $data['discount_value'] ?? 0;
        $data['freight_amount'] = $data['freight_amount'] ?? 0;
        $data['packing_amount'] = $data['packing_amount'] ?? 0;
        $data['other_charges'] = $data['other_charges'] ?? 0;
        $data['round_off'] = $data['round_off'] ?? 0;
        $data['amount_paid'] = $data['amount_paid'] ?? 0;
        $data['terms_conditions'] = $data['terms_conditions'] ?: SalesInvoice::defaultTerms();
        $data['show_client_portal'] = $request->boolean('show_client_portal');

        if (! empty($data['client_id'])) {
            $client = $this->clientById($data['client_id']);
            if ($client) {
                $data = array_merge($data, array_filter($this->clientSnapshot($client), function ($value) {
                    return $value !== null && $value !== '';
                }));
            }
        }

        if ($data['status'] === 'sent' && empty($data['sent_at'])) {
            $data['sent_at'] = now();
        }
        if ($data['status'] === 'accepted' && empty($data['accepted_at'])) {
            $data['accepted_at'] = now();
        }
        if ($data['status'] === 'cancelled' && empty($data['cancelled_at'])) {
            $data['cancelled_at'] = now();
        }

        return $data;
    }

    private function syncItemsAndTotals(SalesInvoice $invoice, array $items): void
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

            $productName = $item['product_name'] ?: $this->productName($item['product_id'] ?? null);
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
                'product_name' => $productName ?: 'Product',
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
        $taxAdjustmentRatio = $taxableTotal > 0 ? ($taxableAfterInvoiceDiscount / $taxableTotal) : 1;
        $cgstTotal = round($cgstTotal * $taxAdjustmentRatio, 2);
        $sgstTotal = round($sgstTotal * $taxAdjustmentRatio, 2);
        $igstTotal = round($igstTotal * $taxAdjustmentRatio, 2);

        $charges = (float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges;
        $total = round($taxableAfterInvoiceDiscount + $cgstTotal + $sgstTotal + $igstTotal + $charges + (float) $invoice->round_off, 2);
        /* The balance is the money rule's: what the invoice says was received
           before it was recorded in the ledger, plus every receipt filed against
           it. `PartyStatement` prints this column on a client's statement, so it
           has to agree with the list the client is chased from. */
        $balance = max($total - round((float) $invoice->amount_paid + $invoice->ledgerReceived(), 2), 0);

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($invoiceDiscount, 2),
            'taxable_amount' => round($taxableAfterInvoiceDiscount, 2),
            'cgst_amount' => $cgstTotal,
            'sgst_amount' => $sgstTotal,
            'igst_amount' => $igstTotal,
            'total_amount' => $total,
            'balance_amount' => $balance,
            'amount_in_words' => $this->amountInWords($total, $invoice->currency),
        ]);

        /* The lines are the client's own facts, so they are what the project is
           making: the products tab reads them from here rather than from a
           second entry the office has to keep in step. A row the office edits
           later is updated, never duplicated — the service matches the line to
           its product row before it writes. */
        app(ProjectProducts::class)->fromSalesInvoice($invoice);
    }

    private function storeAttachments(Request $request, SalesInvoice $invoice): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('sales-invoices/'.$invoice->id, 'public');
            $invoice->attachments()->create([
                'title' => $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'is_public' => true,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function clientSnapshots($clients): array
    {
        return $clients->mapWithKeys(fn ($client) => [
            $client->id => array_filter($this->clientSnapshot($client), fn ($value) => filled($value)),
        ])->all();
    }

    /* The invoice's own copy of the client. Every field here is a value the
       printed invoice shows, so every field here has an input on the form: a
       value the office cannot see is a value it cannot correct before it prints. */
    private function clientSnapshot($client): array
    {
        return [
            'client_company_name' => $client->company_name ?? null,
            'client_brand_name' => $client->brand_name ?? null,
            'client_contact_name' => $client->account_person_name ?: ($client->ceo_name ?? null),
            'client_email' => $client->account_person_email ?: ($client->ceo_email ?? null),
            'client_mobile' => $client->account_person_contact ?: ($client->ceo_contact ?? null),
            'client_gstin' => $client->gstin ?? null,
            'client_pan' => $client->pan ?? null,
            'billing_address' => $client->billing_address ?? null,
            'billing_city' => $client->billing_city ?? null,
            'billing_state' => $client->billing_state ?? null,
            'billing_country' => $client->billing_country ?? null,
            'billing_pincode' => $client->billing_pincode ?? null,
            'shipping_address' => $client->shipping_address ?: ($client->billing_address ?? null),
            'shipping_city' => $client->shipping_city ?: ($client->billing_city ?? null),
            'shipping_state' => $client->shipping_state ?: ($client->billing_state ?? null),
            'shipping_country' => $client->shipping_country ?: ($client->billing_country ?? null),
            'shipping_pincode' => $client->shipping_pincode ?: ($client->billing_pincode ?? null),
            'place_of_supply' => $client->billing_state ?? null,
        ];
    }

    private function itemsFromSource($project = null): array
    {
        $items = [];

        if ($project && method_exists($project, 'products')) {
            $project->load('products');
            foreach ($project->products as $row) {
                $items[] = [
                    'project_product_id' => $row->id,
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'description' => $row->notes,
                    'quantity' => $row->quantity ?: 1,
                    'unit' => $row->unit ?: 'pcs',
                    'unit_price' => $row->unit_price ?: 0,
                    'gst_percent' => 18,
                    'discount_percent' => 0,
                ];
            }
        }

        if (! $items) {
            $items[] = ['product_name' => '', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 0, 'gst_percent' => 18, 'discount_percent' => 0];
        }

        return $items;
    }

    private function productName($productId): ?string
    {
        if (! $productId || ! $this->productAvailable()) {
            return null;
        }

        $product = \App\Models\Product::find($productId);
        return $product ? $product->name : null;
    }

    private function makeInvoiceNumber(string $type): string
    {
        $financialYear = now()->month >= 4
            ? now()->format('y') . '-' . now()->addYear()->format('y')
            : now()->subYear()->format('y') . '-' . now()->format('y');
            
        $prefix = ($type === 'tax' ? "MP/INV/{$financialYear}/" : "MP/PI/{$financialYear}/");
        $next = str_pad((string) (SalesInvoice::where('invoice_type', $type)->whereYear('created_at', now()->year)->count() + 1), 3, '0', STR_PAD_LEFT);
        
        $lastInvoice = SalesInvoice::where('invoice_type', $type)
            ->whereYear('created_at', now()->year)
            ->latest('id')
            ->value('invoice_number');
        
        $lastNumber = $lastInvoice ? (int) last(explode('/', $lastInvoice)) : 0;
        $next = str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
        
        $number = $prefix.$next;

        while (SalesInvoice::where('invoice_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 3, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function amountInWords(float $amount, string $currency): string
    {
        $number = (int) floor($amount);
        $paise = (int) round(($amount - $number) * 100);

        $words = $this->numberToWords($number);
        $currencyLabel = $currency === 'INR' ? 'Rupees' : $currency;

        $text = $currencyLabel.' '.$words;
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

        $convertBelowHundred = function ($n) use ($ones, $tens) {
            if ($n < 20) {
                return $ones[$n];
            }
            return trim($tens[(int) floor($n / 10)].' '.$ones[$n % 10]);
        };

        $convertBelowThousand = function ($n) use ($ones, $convertBelowHundred) {
            $words = '';
            if ($n >= 100) {
                $words .= $ones[(int) floor($n / 100)].' Hundred ';
                $n = $n % 100;
            }
            if ($n > 0) {
                $words .= $convertBelowHundred($n);
            }
            return trim($words);
        };

        $parts = [];
        $crore = (int) floor($number / 10000000);
        if ($crore) {
            $parts[] = $convertBelowThousand($crore).' Crore';
            $number %= 10000000;
        }
        $lakh = (int) floor($number / 100000);
        if ($lakh) {
            $parts[] = $convertBelowThousand($lakh).' Lakh';
            $number %= 100000;
        }
        $thousand = (int) floor($number / 1000);
        if ($thousand) {
            $parts[] = $convertBelowThousand($thousand).' Thousand';
            $number %= 1000;
        }
        if ($number) {
            $parts[] = $convertBelowThousand($number);
        }

        return implode(' ', array_filter($parts));
    }


    /**
     * Record a chase.
     *
     * The office rings, WhatsApps and emails all month; this is where that gets
     * written down — which invoice, which channel, which day, what was said and
     * what came back. The list reads "last nudged" from these rows, so a chase
     * nobody logged is a client who will be rung twice.
     */
    public function logReminder(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', Rule::in(array_keys(SalesInvoiceReminder::channelOptions()))],
            'reminded_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:4000'],
        ]);

        $reminder = new SalesInvoiceReminder();
        $reminder->sales_invoice_id = $salesInvoice->id;
        $reminder->channel = $data['channel'] ?? 'whatsapp';
        $reminder->reminded_at = $data['reminded_at'] ?? now()->toDateString();
        // what was sent, unless the office says it sent something else
        $reminder->message = $data['message'] ?? $salesInvoice->reminderMessage();
        $reminder->note = $data['note'] ?? null;
        $reminder->created_by = Auth::id();
        $reminder->save();

        return back()->with('success', 'Reminder logged against '.$salesInvoice->invoice_number
            .' ('.$reminder->channelLabel().'). '.$salesInvoice->reminderCount().' in total.');
    }

    /**
     * One action, many rows — the month-end sweep.
     *
     * Each action is the same change the row menu makes, applied in a loop: there
     * is no second implementation of "mark sent" here. `delete_drafts` refuses
     * anything that has left draft, because a sent invoice is a document the
     * client already holds.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::BULK_ACTIONS))],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'channel' => ['nullable', Rule::in(array_keys(SalesInvoiceReminder::channelOptions()))],
        ]);

        $invoices = SalesInvoice::query()->whereIn('id', $data['ids'])->get();
        $done = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $invoices, &$done, &$skipped) {
            foreach ($invoices as $invoice) {
                switch ($data['action']) {
                    case 'mark_sent':
                        /* a cancelled document was withdrawn on purpose: it is not
                           dragged back to sent by a sweep of the page */
                        if ($invoice->status === 'cancelled') {
                            $skipped++;
                            break;
                        }

                        $invoice->update(['status' => 'sent', 'sent_at' => now(), 'show_client_portal' => true]);
                        $done++;
                        break;

                    case 'portal_on':
                    case 'portal_off':
                        $invoice->update(['show_client_portal' => $data['action'] === 'portal_on']);
                        $done++;
                        break;

                    case 'remind':
                        $reminder = new SalesInvoiceReminder();
                        $reminder->sales_invoice_id = $invoice->id;
                        $reminder->channel = $data['channel'] ?? 'whatsapp';
                        $reminder->reminded_at = now()->toDateString();
                        $reminder->message = $invoice->reminderMessage();
                        $reminder->created_by = Auth::id();
                        $reminder->save();
                        $done++;
                        break;

                    case 'delete_drafts':
                        // only what never left the desk
                        if ($invoice->status === 'draft') {
                            $invoice->delete();
                            $done++;
                        } else {
                            $skipped++;
                        }
                        break;
                }
            }
        });

        $message = $done.' '.Str::plural('invoice', $done).' — '
            .mb_strtolower(self::BULK_ACTIONS[$data['action']]).' done.';

        if ($skipped) {
            $why = $data['action'] === 'delete_drafts'
                ? 'left alone — already past draft, so the client holds it'
                : 'left alone — cancelled';

            $message .= ' '.$skipped.' '.Str::plural('invoice', $skipped).' '.$why.'.';
        }

        return back()->with('success', $message);
    }

    /**
     * The file the CA asks for: HSN and rate-wise, for the period on screen.
     *
     * Drafts and cancellations are left out on purpose — they are not tax
     * documents, and a summary that counted them would state a GST liability the
     * office never incurred. The rows come from the invoice **items**, which is
     * where HSN and rate actually live; the invoice header only carries the
     * totals.
     */
    public function gstExport(Request $request): StreamedResponse
    {
        $filters = app(SalesInvoiceFilters::class)->fromRequest($request);
        $selected = $this->selectedIds($request);

        $invoices = $this->filteredQuery($filters)
            ->whereNotIn('sales_invoices.status', ['draft', 'cancelled']);

        if ($selected !== []) {
            $invoices->whereIn('sales_invoices.id', $selected);
        }

        $rows = SalesInvoiceItem::query()
            ->whereIn('sales_invoice_id', (clone $invoices)->reorder()->select('sales_invoices.id'))
            ->selectRaw('coalesce(hsn_sac, "") as hsn_sac, gst_percent, count(distinct sales_invoice_id) as invoices'
                .', sum(quantity) as quantity, sum(taxable_amount) as taxable, sum(cgst_amount) as cgst'
                .', sum(sgst_amount) as sgst, sum(igst_amount) as igst, sum(line_total) as total')
            ->groupBy('hsn_sac', 'gst_percent')
            ->orderBy('hsn_sac')
            ->orderBy('gst_percent')
            ->get();

        $labels = app(SalesInvoiceFilters::class)->labels($filters);
        $applied = app(SalesInvoiceFilters::class)->applied($filters);
        $filteredBy = $this->filteredByLine($selected, $applied, $labels);

        return response()->streamDownload(function () use ($rows, $filteredBy) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $row = fn (array $cells) => fputcsv($out, $cells);
            $money = fn ($value) => number_format((float) $value, 2, '.', '');

            $row(['GST summary — sales invoices']);
            $row(['Taken', now()->format('d M Y H:i')]);
            $row(['Filtered by', $filteredBy]);
            $row(['Rows are the invoice items, grouped by HSN/SAC and rate. Drafts and cancelled invoices are excluded.']);
            $row([]);

            $row(['HSN / SAC', 'GST %', 'Invoices', 'Quantity', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total']);

            $totals = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0, 'total' => 0.0];

            foreach ($rows as $line) {
                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + (float) $line->{$key};
                }

                $row([
                    $line->hsn_sac,
                    number_format((float) $line->gst_percent, 2, '.', ''),
                    $line->invoices,
                    number_format((float) $line->quantity, 3, '.', ''),
                    $money($line->taxable),
                    $money($line->cgst),
                    $money($line->sgst),
                    $money($line->igst),
                    $money($line->total),
                ]);
            }

            $row([]);
            $row(['Total', '', '', '', $money($totals['taxable']), $money($totals['cgst']),
                $money($totals['sgst']), $money($totals['igst']), $money($totals['total'])]);
        }, 'gst-summary-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The row of ids a listing had ticked, for "export the selected rows" — the
     * same exporter as the screen's, narrowed. Capped, because a GET URL is not a
     * place to put ten thousand ids.
     *
     * @return array<int, int>
     */
    private function selectedIds(Request $request): array
    {
        $ids = array_map('intval', (array) $request->query('ids', []));

        return array_slice(array_values(array_unique(array_filter($ids))), 0, 500);
    }

    /**
     * One spelling of "what this file was filtered by", used by the invoice CSV
     * and the GST summary — two files that describe the same screen must describe
     * it in the same words.
     *
     * @param  array<int, int>  $selected
     * @param  array<int, array{key: string, label: string, value: string}>  $applied
     * @param  array<string, string>  $labels
     */
    private function filteredByLine(array $selected, array $applied, array $labels): string
    {
        if ($selected !== []) {
            return 'Selected on screen ('.count($selected).')';
        }

        if ($applied === []) {
            return 'Everything';
        }

        return implode(' · ', array_map(
            fn ($chip) => $chip['label'].': '.($labels[$chip['key']] ?? $chip['value']),
            $applied
        ));
    }

    private function sharedData(): array
    {
        $clients = $this->clients();

        return [
            'clients' => $clients,
            /* What each client's record contributes to an invoice, keyed by id.
               The form hands it to the screen as JSON on the option itself, so
               the values a pick lands and the values a `?client_id=` open copies
               come off one list and cannot drift. Only what the client actually
               holds travels; the field list below keeps its shape either way, so
               changing client clears what the last one had. */
            'clientSnapshots' => $this->clientSnapshots($clients),
            'clientSnapshotFields' => $clients->isNotEmpty()
                ? array_keys($this->clientSnapshot($clients->first()))
                : [],
            'projects' => $this->projects(),
            'products' => $this->products(),
            'typeOptions' => SalesInvoice::typeOptions(),
            'statusOptions' => SalesInvoice::statusOptions(),
            'currencyOptions' => SalesInvoice::currencyOptions(),
            'gstTypeOptions' => SalesInvoice::gstTypeOptions(),
            'sellerDefaults' => SalesInvoice::defaultSellerDetails(),
            'defaultTerms' => SalesInvoice::defaultTerms(),
        ];
    }

    private function clients()
    {
        return $this->clientAvailable() ? \App\Models\Client::query()->orderBy('company_name')->get() : collect();
    }

    private function projects()
    {
        return $this->projectAvailable() ? \App\Models\Project::query()->latest('id')->get() : collect();
    }

    private function products()
    {
        return $this->productAvailable() ? \App\Models\Product::query()->where('status', 'active')->orderBy('name')->get() : collect();
    }

    private function clientFromRequest(Request $request)
    {
        return $request->query('client_id') ? $this->clientById($request->query('client_id')) : null;
    }

    private function clientById($id)
    {
        return ($id && $this->clientAvailable()) ? \App\Models\Client::find($id) : null;
    }

    private function projectFromRequest(Request $request)
    {
        return ($request->query('project_id') && $this->projectAvailable()) ? \App\Models\Project::find($request->query('project_id')) : null;
    }

    private function clientAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }

    private function projectAvailable(): bool
    {
        return class_exists(\App\Models\Project::class) && Schema::hasTable('projects');
    }

    private function productAvailable(): bool
    {
        return class_exists(\App\Models\Product::class) && Schema::hasTable('products');
    }
}
