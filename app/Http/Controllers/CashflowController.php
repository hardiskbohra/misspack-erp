<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowAccount;
use App\Models\CashflowCategory;
use App\Models\CashflowAttachment;
use App\Models\CashflowEntry;
use App\Models\CashflowMasterOption;
use App\Models\SavedView;
use App\Services\CashflowLedger;
use App\Services\SavedViews;
use App\Services\VendorPaymentCashflowSync;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashflowController extends Controller
{
    public function index(Request $request): View
    {
        // Jumping back into a saved view simply re-runs its filters.
        if ($savedQuery = $this->resolveSavedView($request)) {
            return redirect()->route('cashflows.index', $savedQuery);
        }

        $filters = $this->filters($request);

        $entries = $this->baseEntryQuery($filters)
            ->latest('entry_date')
            ->latest('id')
            ->paginate(100)
            ->withQueryString();

        $summaryQuery = $this->baseEntryQuery($filters, false);
        $stats = [
            'credit' => (clone $summaryQuery)->sum('credit_amount'),
            'debit' => (clone $summaryQuery)->sum('debit_amount'),
            'balance' => CashflowAccount::sum('current_balance'),
            'pending' => CashflowEntry::where('accounting_status', 'pending')->count(),
            'current_balance' => CashflowAccount::where('account_type', 'current')->sum('current_balance'),
            'saving_balance' => CashflowAccount::where('account_type', 'saving')->sum('current_balance'),
            'cash_balance' => CashflowAccount::where('account_type', 'cash')->sum('current_balance'),
        ];
        $stats['net'] = $stats['credit'] - $stats['debit'];

        // Which rows on this page are mirrors of a vendor payment / shipment
        // cost (one query each, never one per row).
        $mirroredPayments = app(VendorPaymentCashflowSync::class)->linkedMapFor($entries->pluck('id'));
        $mirroredShipmentCosts = app(\App\Services\ShipmentCostCashflowSync::class)->linkedShipmentMapFor($entries->pluck('id'));

        // The totals row: what the rows on this page add up to, next to the
        // same figures for the whole filtered set.
        $pageTotals = [
            'credit' => round((float) $entries->sum('credit_amount'), 2),
            'debit' => round((float) $entries->sum('debit_amount'), 2),
        ];
        $pageTotals['net'] = round($pageTotals['credit'] - $pageTotals['debit'], 2);
        $stats['net'] = round((float) $stats['credit'] - (float) $stats['debit'], 2);

        return view('cashflows.index', array_merge($this->sharedData(), [
            'entries' => $entries,
            'stats' => $stats,
            'pageTotals' => $pageTotals,
            'chipCounts' => $this->chipCounts($filters),
            'dateRanges' => DateRanges::presets(),
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => DateRanges::keyOf($filters['dateFrom'] ?? null, $filters['dateTo'] ?? null),
            'savedViews' => app(SavedViews::class)->forUser(Auth::id(), 'cashflows'),
            'mirroredPayments' => $mirroredPayments,
            'mirroredShipmentCosts' => $mirroredShipmentCosts,
            ...$filters,
        ]));
    }

    public function create(): View
    {
        $entry = new CashflowEntry([
            'entry_date' => now()->toDateString(),
            'transaction_type' => 'debit',
            'currency' => '₹',
            'accounting_status' => 'pending',
            'payment_mode' => 'neft',
            'related_party_type' => 'other',
        ]);

        return view('cashflows.form', array_merge($this->sharedData(), compact('entry')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->normalizeAmounts($data);
        $data['created_by'] = Auth::id();

        $entry = DB::transaction(function () use ($data) {
            $entry = CashflowEntry::create($data);
            $this->recalculateAccountLedger($entry->account_id);
            return $entry;
        });

        return redirect()->route('cashflows.show', $entry)->with('success', 'Cashflow entry created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'particular' => ['required', 'string'],
            'project_id' => ['nullable', 'integer'],
            'sales_invoice_id' => ['nullable', 'integer'],
            'invoice_bill_number' => ['nullable', 'string', 'max:255'],
            'bank_reference_number' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', Rule::in($this->masterKeys('currency', array_keys(CashflowEntry::currencyOptions())))],
            'account_id' => ['required', 'exists:cashflow_accounts,id'],
            'category_id' => ['nullable', 'exists:cashflow_categories,id'],
            'accounting_status' => ['nullable', Rule::in($this->masterKeys('accounting_status', array_keys(CashflowEntry::accountingStatusOptions())))],
            'payment_mode' => ['nullable', Rule::in($this->masterKeys('payment_mode', array_keys(CashflowEntry::paymentModeOptions())))],
            'related_party_type' => ['required', Rule::in($this->masterKeys('related_party_type', array_keys(CashflowEntry::relatedPartyOptions())))],
            'client_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'expense_head' => ['nullable', 'string', 'max:255'],
            'related_party_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['credit_amount'] = $data['transaction_type'] === 'credit' ? $data['amount'] : 0;
        $data['debit_amount'] = $data['transaction_type'] === 'debit' ? $data['amount'] : 0;
        unset($data['amount']);
        $data['created_by'] = Auth::id();

        $entry = DB::transaction(function () use ($data) {
            $entry = CashflowEntry::create($data);
            $this->recalculateAccountLedger($entry->account_id);
            return $entry;
        });

        return redirect()->route('cashflows.show', $entry)->with('success', 'Quick cashflow entry created successfully.');
    }

    public function show(CashflowEntry $cashflow): View
    {
        $with = ['account', 'category', 'creator', 'attachments.uploader'];
        if ($this->clientModelAvailable()) $with[] = 'client';
        if ($this->vendorModelAvailable()) $with[] = 'vendor';
        $cashflow->load($with);

        return view('cashflows.show', array_merge($this->sharedData(), [
            'entry' => $cashflow,
            'documentTypeOptions' => CashflowAttachment::documentTypeOptions(),
            'linkedVendorPayment' => app(VendorPaymentCashflowSync::class)->linkedPaymentFor($cashflow),
            'linkedShipmentCost' => app(\App\Services\ShipmentCostCashflowSync::class)->linkedCostFor($cashflow),
        ]));
    }

    public function edit(CashflowEntry $cashflow): View
    {
        return view('cashflows.form', array_merge($this->sharedData(), [
            'entry' => $cashflow,
            'linkedVendorPayment' => app(VendorPaymentCashflowSync::class)->linkedPaymentFor($cashflow),
        ]));
    }

    public function update(Request $request, CashflowEntry $cashflow): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->normalizeAmounts($data);
        $oldAccountId = $cashflow->account_id;

        DB::transaction(function () use ($cashflow, $data, $oldAccountId) {
            $cashflow->update($data);
            $this->recalculateAccountLedger($oldAccountId);
            $this->recalculateAccountLedger($cashflow->account_id);
        });

        return redirect()->route('cashflows.show', $cashflow)->with('success', 'Cashflow entry updated successfully.');
    }

    public function destroy(CashflowEntry $cashflow): RedirectResponse
    {
        $accountId = $cashflow->account_id;
        $detached = DB::transaction(function () use ($cashflow, $accountId) {
            // Never leave a vendor payment or shipment cost pointing at a deleted entry.
            $detached = app(VendorPaymentCashflowSync::class)->detach($cashflow);
            $detached += app(\App\Services\ShipmentCostCashflowSync::class)->detach($cashflow);
            $cashflow->delete();
            $this->recalculateAccountLedger($accountId);

            return $detached;
        });

        return redirect()->route('cashflows.index')->with(
            'success',
            $detached
                ? 'Cashflow entry deleted. The linked vendor payment / shipment cost was unlinked — set its paid account again if the payment still stands.'
                : 'Cashflow entry deleted successfully.'
        );
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in($this->masterKeys('account_type', array_keys(CashflowAccount::typeOptions())))],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in($this->masterKeys('currency', array_keys(CashflowEntry::currencyOptions())))],
            'opening_balance' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['opening_balance'] = $data['opening_balance'] ?? 0;
        $data['current_balance'] = $data['opening_balance'];
        CashflowAccount::create($data);

        return back()->with('success', 'Cashflow account created successfully.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($this->masterKeys('category_type', array_keys(CashflowCategory::typeOptions())))],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        CashflowCategory::firstOrCreate(['name' => $data['name'], 'type' => $data['type']], $data);

        return back()->with('success', 'Cashflow category created successfully.');
    }

    public function reports(Request $request): View
    {
        $reportData = $this->reportData($request);

        return view('cashflows.reports', array_merge($this->sharedData(), $reportData));
    }

    public function downloadPdf(Request $request)
    {
        $reportData = $this->reportData($request);
        $viewData = array_merge($this->sharedData(), $reportData, ['printMode' => true]);
        $fileName = 'cashflow-report-'.$reportData['dateFrom']->format('Ymd').'-'.$reportData['dateTo']->format('Ymd').'.pdf';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('cashflows.pdf', $viewData)
                ->setPaper('a4', 'landscape')
                ->download($fileName);
        }

        return response()->view('cashflows.pdf', $viewData + [
            'pdfFallbackMessage' => 'Install barryvdh/laravel-dompdf for direct PDF download. Use browser Print > Save as PDF for now.',
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date'],
            'particular' => ['required', 'string'],
            'project_id' => ['nullable', 'integer'],
            'sales_invoice_id' => ['nullable', 'integer'],
            'invoice_bill_number' => ['nullable', 'string', 'max:255'],
            'bank_reference_number' => ['nullable', 'string', 'max:255'],
            'transaction_type' => ['required', 'in:credit,debit'],
            'credit_amount' => ['nullable', 'numeric', 'min:0'],
            'debit_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', Rule::in($this->masterKeys('currency', array_keys(CashflowEntry::currencyOptions())))],
            'account_id' => ['required', 'exists:cashflow_accounts,id'],
            'category_id' => ['nullable', 'exists:cashflow_categories,id'],
            'accounting_status' => ['required', Rule::in($this->masterKeys('accounting_status', array_keys(CashflowEntry::accountingStatusOptions())))],
            'payment_mode' => ['nullable', Rule::in($this->masterKeys('payment_mode', array_keys(CashflowEntry::paymentModeOptions())))],
            'client_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'expense_head' => ['nullable', 'string', 'max:255'],
            'related_party_type' => ['required', Rule::in($this->masterKeys('related_party_type', array_keys(CashflowEntry::relatedPartyOptions())))],
            'related_party_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function normalizeAmounts(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);
    
        unset($data['amount']);
    
        if (($data['transaction_type'] ?? 'debit') === 'credit') {
            $data['credit_amount'] = $amount;
            $data['debit_amount'] = 0;
        } else {
            $data['debit_amount'] = $amount;
            $data['credit_amount'] = 0;
        }
    
        return $data;
    }

    /**
     * What each quick-view chip would show: the current filters plus that
     * chip's own dimension, so the number on a chip is the number of rows you
     * would actually get by clicking it.
     *
     * @return array<string, int>
     */
    private function chipCounts(array $filters): array
    {
        $base = array_merge($filters, [
            'transactionType' => 'all',
            'accountingStatus' => 'all',
            'dateFrom' => null,
            'dateTo' => null,
            'documents' => 'all',
        ]);

        $count = function (array $overrides) use ($base) {
            return $this->baseEntryQuery(array_merge($base, $overrides), false)->count();
        };

        $counts = [
            'all' => $count([]),
            'credit' => $count(['transactionType' => 'credit']),
            'debit' => $count(['transactionType' => 'debit']),
            'pending' => $count(['accountingStatus' => 'pending']),
            // the month-end to-do list: entries with no bill on file
            'missing_documents' => $count(['documents' => 'missing']),
        ];

        /* The period chips. The ranges come from the shared helper, so the
           number on "Last month" is the number of rows that chip would show —
           in the same month the chip is labelled with. */
        foreach (DateRanges::presets() as $key => $range) {
            $counts[$key] = $count([
                'dateFrom' => $range['from'],
                'dateTo' => $range['to'],
            ]);
        }

        return $counts;
    }

    /**
     * When the request carries ?saved_view=ID, the saved query is what should
     * be rendered — this turns it back into the URL the list already speaks.
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
            ->where('module', 'cashflows')
            ->where(function ($query) {
                $query->where('user_id', Auth::id())->orWhere('is_shared', true);
            })
            ->find($id);

        return $view ? app(SavedViews::class)->queryFor($view) : [];
    }

    public function storeSavedView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        app(SavedViews::class)->save(Auth::id(), 'cashflows', $data['name'], $request->query(), $request->boolean('is_shared'));

        return back()->with('success', 'View "'.$data['name'].'" saved.');
    }

    public function destroySavedView(SavedView $savedView): RedirectResponse
    {
        abort_unless((int) $savedView->user_id === (int) Auth::id(), 403);

        app(SavedViews::class)->delete((int) Auth::id(), (int) $savedView->id);

        return back()->with('success', 'Saved view removed.');
    }

    private function filters(Request $request): array
    {
        return [
            'search' => $request->query('search'),
            'accountId' => $request->query('account_id', 'all'),
            'accountType' => $request->query('account_type', 'all'),
            'categoryId' => $request->query('category_id', 'all'),
            'transactionType' => $request->query('transaction_type', 'all'),
            'accountingStatus' => $request->query('accounting_status', 'all'),
            'relatedPartyType' => $request->query('related_party_type', 'all'),
            'clientId' => $request->query('client_id', 'all'),
            'vendorId' => $request->query('vendor_id', 'all'),
            'dateFrom' => $request->query('date_from'),
            'dateTo' => $request->query('date_to'),
            // all · missing (no document filed) · attached
            'documents' => $request->query('documents', 'all'),
        ];
    }

    private function baseEntryQuery(array $filters, bool $withRelations = true)
    {
        $with = ['account', 'category'];
        if ($withRelations) {
            $with[] = 'creator';
            if ($this->clientModelAvailable()) $with[] = 'client';
            if ($this->vendorModelAvailable()) $with[] = 'vendor';
        }

        return CashflowEntry::query()
            ->when($withRelations, fn ($q) => $q->with($with), fn ($q) => $q->with(['account', 'category']))
            /* The paperclip on a row is this count, fetched with the page
               instead of one query per row. Only the list asks for relations,
               so the reports and the chip counts never pay for it. */
            ->when($withRelations, fn ($q) => $q->withCount('attachments'))
            ->search($filters['search'] ?? null)
            ->when(($filters['accountId'] ?? 'all') !== 'all', fn ($q) => $q->where('account_id', $filters['accountId']))
            ->when(($filters['accountType'] ?? 'all') !== 'all', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('account_type', $filters['accountType'])))
            ->when(($filters['categoryId'] ?? 'all') !== 'all', fn ($q) => $q->where('category_id', $filters['categoryId']))
            ->when(($filters['transactionType'] ?? 'all') !== 'all', fn ($q) => $q->where('transaction_type', $filters['transactionType']))
            ->when(($filters['accountingStatus'] ?? 'all') !== 'all', fn ($q) => $q->where('accounting_status', $filters['accountingStatus']))
            ->when(($filters['relatedPartyType'] ?? 'all') !== 'all', fn ($q) => $q->where('related_party_type', $filters['relatedPartyType']))
            ->when(($filters['clientId'] ?? 'all') !== 'all', fn ($q) => $q->where('client_id', $filters['clientId']))
            ->when(($filters['vendorId'] ?? 'all') !== 'all', fn ($q) => $q->where('vendor_id', $filters['vendorId']))
            ->when($filters['dateFrom'] ?? null, fn ($q) => $q->whereDate('entry_date', '>=', $filters['dateFrom']))
            ->when($filters['dateTo'] ?? null, fn ($q) => $q->whereDate('entry_date', '<=', $filters['dateTo']))
            ->when(($filters['documents'] ?? 'all') === 'missing', fn ($q) => $q->whereDoesntHave('attachments'))
            ->when(($filters['documents'] ?? 'all') === 'attached', fn ($q) => $q->whereHas('attachments'));
    }

    private function reportData(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->reportDateRange($request);
        $filters = $this->filters($request);
        $filters['dateFrom'] = $dateFrom->toDateString();
        $filters['dateTo'] = $dateTo->toDateString();
        $filters['relatedPartyType'] = $request->query('report_type', $filters['relatedPartyType'] ?? 'all') === 'cash_expense'
            ? 'expense'
            : ($filters['relatedPartyType'] ?? 'all');

        if ($request->query('report_type') === 'client') {
            $filters['relatedPartyType'] = 'client';
        }
        if ($request->query('report_type') === 'vendor') {
            $filters['relatedPartyType'] = 'vendor';
        }

        $entries = $this->baseEntryQuery($filters)
            ->oldest('entry_date')
            ->oldest('id')
            ->get();

        $summary = [
            'credit' => $entries->sum(fn ($entry) => (float) $entry->credit_amount),
            'debit' => $entries->sum(fn ($entry) => (float) $entry->debit_amount),
            'count' => $entries->count(),
        ];
        $summary['net'] = $summary['credit'] - $summary['debit'];

        $accountSummary = $entries->groupBy('account_id')->map(function ($rows) {
            $account = $rows->first()->account;
            return [
                'name' => $account?->account_name ?? 'Unknown',
                'type' => $account?->typeLabel() ?? '-',
                'credit' => $rows->sum(fn ($entry) => (float) $entry->credit_amount),
                'debit' => $rows->sum(fn ($entry) => (float) $entry->debit_amount),
            ];
        });

        $categorySummary = $entries->groupBy('category_id')->map(function ($rows) {
            $category = $rows->first()->category;
            return [
                'name' => $category?->name ?? 'Uncategorized',
                'type' => $category?->typeLabel() ?? '-',
                'credit' => $rows->sum(fn ($entry) => (float) $entry->credit_amount),
                'debit' => $rows->sum(fn ($entry) => (float) $entry->debit_amount),
            ];
        });

        return [
            ...$filters,
            'entries' => $entries,
            'summary' => $summary,
            'accountSummary' => $accountSummary,
            'categorySummary' => $categorySummary,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'period' => $request->query('period', 'month'),
            'reportType' => $request->query('report_type', 'overall'),
        ];
    }

    private function reportDateRange(Request $request): array
    {
        $period = $request->query('period', 'month');
        $date = Carbon::parse($request->query('date', now()->toDateString()));

        if ($request->query('date_from') && $request->query('date_to')) {
            return [Carbon::parse($request->query('date_from'))->startOfDay(), Carbon::parse($request->query('date_to'))->endOfDay()];
        }

        return match ($period) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'quarter' => [$date->copy()->startOfQuarter(), $date->copy()->endOfQuarter()],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
            default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
        };
    }

    private function recalculateAccountLedger(int $accountId): void
    {
        CashflowLedger::recalculateAccount($accountId);
    }

    private function sharedData(): array
    {
        return [
            'accounts' => CashflowAccount::where('is_active', true)->orderBy('account_type')->orderBy('account_name')->get(),
            'categories' => CashflowCategory::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'clients' => $this->clients(),
            'vendors' => $this->vendors(),
            'accountTypeOptions' => $this->masterOptions('account_type', CashflowAccount::typeOptions()),
            'categoryTypeOptions' => $this->masterOptions('category_type', CashflowCategory::typeOptions()),
            'transactionTypeOptions' => CashflowEntry::transactionTypeOptions(),
            'accountingStatusOptions' => $this->masterOptions('accounting_status', CashflowEntry::accountingStatusOptions()),
            'paymentModeOptions' => $this->masterOptions('payment_mode', CashflowEntry::paymentModeOptions()),
            'relatedPartyOptions' => $this->masterOptions('related_party_type', CashflowEntry::relatedPartyOptions()),
            'currencyOptions' => $this->masterOptions('currency', CashflowEntry::currencyOptions()),
        ];
    }


    private function masterOptions(string $group, array $fallback = [], bool $activeOnly = true): array
    {
        if (! Schema::hasTable('cashflow_master_options')) {
            return $fallback;
        }

        $query = CashflowMasterOption::query()->where('group', $group);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $options = $query->orderBy('sort_order')->orderBy('label')->pluck('label', 'key')->toArray();

        return $options ?: $fallback;
    }

    private function masterKeys(string $group, array $fallback): array
    {
        return array_keys($this->masterOptions($group, array_combine($fallback, $fallback), true));
    }

    private function clients()
    {
        if (! $this->clientModelAvailable()) return collect();
        return \App\Models\Client::query()->orderBy('company_name')->get();
    }

    private function vendors()
    {
        if (! $this->vendorModelAvailable()) return collect();
        return \App\Models\Vendor::query()->orderBy('vendor_name')->get();
    }

    private function clientModelAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }

    private function vendorModelAvailable(): bool
    {
        return class_exists(\App\Models\Vendor::class) && Schema::hasTable('vendors');
    }
}
