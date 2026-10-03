<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowAccount;
use App\Models\CashflowCategory;
use App\Models\CashflowAttachment;
use App\Models\CashflowEntry;
use App\Models\CashflowMasterOption;
use App\Models\SavedView;
use App\Models\User;
use App\Services\CashflowAnalysis;
use App\Services\CashflowFilters;
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

        /* A report opens its rows in the order it read them — oldest first — so
           a drill-down lands on the same rows the figure was summed over. Every
           other way into the ledger keeps its own newest-first order. */
        $oldestFirst = $request->query('sort') === 'oldest';

        $entries = $this->baseEntryQuery($filters)
            ->when($oldestFirst, fn ($query) => $query->oldest('entry_date')->oldest('id'), fn ($query) => $query->latest('entry_date')->latest('id'))
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
            /* The applied strip is drawn from the same list of dimensions that
               filtered the query, so a filter that arrives by link — a report
               cell, a saved view — is always visible and always removable. */
            'appliedFilters' => app(CashflowFilters::class)->applied($filters),
            'appliedFilterLabels' => app(CashflowFilters::class)->labels($filters),
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
            /* A party is a linked client, a linked vendor, a linked employee, or
               a name typed in by hand. Quick Entry takes the link as well: the
               person being paid is exactly what a cash line often is. */
            'client_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
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
            /* the newest documents filed without an entry, so this page can
               claim one: a bill that arrived before its payment did */
            'unlinkedDocuments' => CashflowAttachment::query()
                ->unlinked()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
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
        $fileName = 'cashflow-report-'.$reportData['from'].'-'.$reportData['to'].'.pdf';

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
            /* A party is a linked client, a linked vendor, a linked employee, or
               a name typed in by hand: an entry needs one of them, and the row's
               label reads whichever is present. */
            'client_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'employee_id' => ['nullable', 'integer', 'exists:users,id'],
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

    /**
     * The report as a spreadsheet.
     *
     * A CSV is data, so the numbers are raw (two decimals, no symbols and no
     * grouping) and the header says what was asked for — which dimension, which
     * bucket, which figure, against what. A file that leaves the app has to
     * explain itself a year later, and the columns are the periods on screen in
     * the same order, so the sheet and the page can be read against each other.
     */
    public function exportReport(Request $request)
    {
        $data = $this->reportData($request);
        $report = $data['report'];
        $fileName = 'cashflow-report-'.$report['from'].'-'.$report['to'].'.csv';

        return response()->streamDownload(function () use ($data, $report) {
            $out = fopen('php://output', 'w');

            /* Excel reads a UTF-8 CSV only when it starts with a byte-order
               mark; without it a currency sign in a group name arrives as
               mojibake. */
            fwrite($out, "\xEF\xBB\xBF");

            $row = function (array $cells) use ($out) {
                fputcsv($out, $cells);
            };

            $row(['Cashflow report']);
            $row(['Grouped by', $data['dimensions'][$report['dimension']]['label'] ?? $report['dimension']]);
            $row(['Period', $data['units'][$report['unit']] ?? $report['unit']]);
            $row(['Figure', $data['measures'][$report['measure']] ?? $report['measure']]);
            $row(['Compared with', $data['comparisons'][$report['comparison']] ?? $report['comparison']]);
            $row(['Range', $report['from'], $report['to']]);
            $row(['Currency', $report['currencies'] === [] ? '-' : implode(' / ', $report['currencies'])]);

            $header = ['Group', 'Value'];
            foreach ($report['periods'] as $period) {
                $header[] = $period['label'];

                if ($report['comparison'] !== 'none') {
                    $header[] = $period['label'].' ('.($period['compare_label'] ?: 'compared').')';
                }
            }
            $header[] = 'Money in';
            $header[] = 'Money out';
            $header[] = 'Net';
            $row($header);

            $money = fn ($value) => number_format((float) $value, 2, '.', '');

            foreach ($report['rows'] as $reportRow) {
                $cells = [$data['dimensions'][$report['dimension']]['label'] ?? '', $reportRow['label']];

                foreach ($report['periods'] as $index => $period) {
                    $cell = $reportRow['cells'][$index] ?? null;
                    $cells[] = $cell ? $money($cell['measure_value']) : '';

                    if ($report['comparison'] !== 'none') {
                        $cells[] = $cell ? $money($cell['compare_value']) : '';
                    }
                }

                $cells[] = $money($reportRow['credit']);
                $cells[] = $money($reportRow['debit']);
                $cells[] = $money($reportRow['net']);
                $row($cells);
            }

            $totals = ['Total', ''];
            foreach ($report['periods'] as $index => $period) {
                $cell = $report['totals']['cells'][$index] ?? null;
                $totals[] = $cell ? $money($cell['measure_value']) : '';

                if ($report['comparison'] !== 'none') {
                    $totals[] = $cell ? $money($cell['compare_value']) : '';
                }
            }
            $totals[] = $money($report['totals']['credit']);
            $totals[] = $money($report['totals']['debit']);
            $totals[] = $money($report['totals']['net']);
            $row($totals);

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeSavedView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_shared' => ['nullable', 'boolean'],
            /* A view belongs to the screen that saved it: the ledger's views
               filter rows, the report's views describe a grouping, and mixing
               the two would apply one screen's vocabulary to the other. */
            'module' => ['nullable', Rule::in(['cashflows', self::REPORT_VIEW_MODULE])],
        ]);

        app(SavedViews::class)->save(
            Auth::id(),
            $data['module'] ?? 'cashflows',
            $data['name'],
            $request->query(),
            $request->boolean('is_shared')
        );

        return back()->with('success', 'View "'.$data['name'].'" saved.');
    }

    public function destroySavedView(SavedView $savedView): RedirectResponse
    {
        abort_unless((int) $savedView->user_id === (int) Auth::id(), 403);

        app(SavedViews::class)->delete((int) Auth::id(), (int) $savedView->id);

        return back()->with('success', 'Saved view removed.');
    }

    /**
     * The request's filters, in the one vocabulary the module speaks.
     *
     * The parsing lives in `CashflowFilters` because the analysis builder links
     * back into this list: a report cell opens the ledger filtered by the
     * dimension and the period it was built from, and a filter that meant one
     * thing on the report and another here would make the drill-down a lie.
     */
    private function filters(Request $request): array
    {
        return app(CashflowFilters::class)->fromRequest($request);
    }

    private function baseEntryQuery(array $filters, bool $withRelations = true)
    {
        $with = ['account', 'category'];
        if ($withRelations) {
            $with[] = 'creator';
            if ($this->clientModelAvailable()) $with[] = 'client';
            if ($this->vendorModelAvailable()) $with[] = 'vendor';
            /* the employee the entry was filed against: one relation, loaded
               with the page rather than looked up per row */
            $with[] = 'employee';
        }

        $query = CashflowEntry::query()
            ->when($withRelations, fn ($q) => $q->with($with), fn ($q) => $q->with(['account', 'category']))
            /* The paperclip on a row is this count, fetched with the page
               instead of one query per row. Only the list asks for relations,
               so the reports and the chip counts never pay for it. */
            ->when($withRelations, fn ($q) => $q->withCount('attachments'));

        return app(CashflowFilters::class)->apply($query, $filters);
    }

    /**
     * The analysis builder's payload.
     *
     * Everything the report page draws — the matrix, the chips, the drill links,
     * the CSV — comes out of this one method, so a figure on the screen and the
     * rows behind it cannot be built from two different queries.
     */
    private function reportData(Request $request): array
    {
        $analysis = app(CashflowAnalysis::class);
        $filters = $this->filters($request);

        $dimension = $this->reportDimension($request);
        $unit = $this->reportUnit($request);
        $measure = $analysis->measure((string) $request->query('measure', 'net'));
        $comparison = $analysis->comparison((string) $request->query('comparison', 'none'));

        [$from, $to] = $this->reportWindow($request, $unit);

        /* The window is a filter like any other: the report's rows, its counts,
           its drill links and the ledger it opens all read these two dates, so a
           cell can never mean a different set of rows than it counted. */
        $filters['dateFrom'] = $from;
        $filters['dateTo'] = $to;

        $report = $analysis->build($filters, $dimension, $unit, $measure, $comparison, $from, $to);
        $report = $this->decorateReport($report, $filters);

        $query = $request->query();
        $query['dimension'] = $dimension;
        $query['period_unit'] = $unit;
        $query['measure'] = $measure;
        $query['comparison'] = $comparison;
        $query['date_from'] = $report['from'];
        $query['date_to'] = $report['to'];
        unset($query['page'], $query['saved_view'], $query['period'], $query['date'], $query['report_type']);

        return [
            ...$filters,
            'filterState' => $filters,
            'report' => $report,
            'reportQuery' => $query,
            'dimensions' => CashflowAnalysis::DIMENSIONS,
            'units' => CashflowAnalysis::UNITS,
            'measures' => CashflowAnalysis::MEASURES,
            'comparisons' => CashflowAnalysis::COMPARISONS,
            'dimension' => $dimension,
            'unit' => $unit,
            'measure' => $measure,
            'comparison' => $comparison,
            'dateFrom' => Carbon::parse($report['from']),
            'dateTo' => Carbon::parse($report['to']),
            'appliedFilters' => app(CashflowFilters::class)->applied($filters),
            'appliedFilterLabels' => app(CashflowFilters::class)->labels($filters),
            'dateRanges' => DateRanges::presets(),
            'dateRangeLabels' => DateRanges::LABELS,
            'activeRange' => DateRanges::keyOf($report['from'], $report['to']),
            'savedViews' => app(SavedViews::class)->forUser(Auth::id(), self::REPORT_VIEW_MODULE),
        ];
    }

    /** The module name saved report views are stored under. */
    public const REPORT_VIEW_MODULE = 'cashflow-reports';

    /**
     * Which column the rows are grouped by.
     *
     * The page used to offer four hard-coded report types; a link from that
     * version still names one of them, so the old words keep working while the
     * picker offers everything the module can group by.
     */
    private function reportDimension(Request $request): string
    {
        $dimension = (string) $request->query('dimension', '');

        if (array_key_exists($dimension, CashflowAnalysis::DIMENSIONS)) {
            return $dimension;
        }

        return match ((string) $request->query('report_type', '')) {
            'client' => 'client',
            'vendor' => 'vendor',
            'cash_expense' => 'expense_head',
            default => 'none',
        };
    }

    /**
     * How the range is cut up. The old page's "period" meant both the bucket and
     * the window around a base date; when a link keeps only that, it is read as
     * the bucket and the window is derived the way that page derived it.
     */
    private function reportUnit(Request $request): string
    {
        $unit = (string) $request->query('period_unit', '');

        if (array_key_exists($unit, CashflowAnalysis::UNITS)) {
            return $unit;
        }

        return match ((string) $request->query('period', '')) {
            'quarter' => 'quarter',
            'year' => 'year',
            default => 'month',
        };
    }

    /**
     * The range the report covers.
     *
     * Explicit dates win. Otherwise a link carrying the old page's period and
     * base date is honoured exactly as that page honoured it, and failing both,
     * the report opens on the year to date — a year of months is what an
     * accountant reads, and a single month answers almost nothing.
     *
     * @return array{0: string, 1: string}
     */
    private function reportWindow(Request $request, string $unit): array
    {
        /* Through DateRanges, never straight into Carbon: a link or a saved view
           can carry "all" where a day belongs, and a filter must not be able to
           take the page down. A value that is not a date is no date, so the
           window falls back to its own default instead of throwing. */
        $from = DateRanges::normalise($request->query('date_from'));
        $to = DateRanges::normalise($request->query('date_to'));

        if ($from || $to) {
            $today = DateRanges::today();

            return [
                $from ?: $today->copy()->startOfYear()->toDateString(),
                $to ?: $today->toDateString(),
            ];
        }

        $legacy = (string) $request->query('period', '');
        $base = $request->query('date');

        $base = DateRanges::normalise($base);

        if ($base || in_array($legacy, ['day', 'week', 'month', 'quarter', 'year'], true)) {
            $date = $base ? Carbon::parse($base) : DateRanges::today();

            [$start, $end] = match ($legacy) {
                'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
                'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
                'quarter' => [$date->copy()->startOfQuarter(), $date->copy()->endOfQuarter()],
                'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
                default => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            };

            return [$start->toDateString(), $end->toDateString()];
        }

        $today = DateRanges::today();

        return [
            $unit === 'year'
                ? $today->copy()->subYears(4)->startOfYear()->toDateString()
                : $today->copy()->startOfYear()->toDateString(),
            $today->toDateString(),
        ];
    }

    /**
     * Give every cell the link that opens the rows behind it.
     *
     * This is the difference between a report and a print-out: the accountant
     * does not have to believe the total, they can open it — filtered to the
     * same dimension value and the same dates the figure was summed over.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function decorateReport(array $report, array $filters): array
    {
        $analysis = app(CashflowAnalysis::class);
        $dimension = $report['dimension'];
        $ledger = fn (array $query) => route('cashflows.index', $query);

        foreach ($report['rows'] as $rowIndex => $row) {
            $report['rows'][$rowIndex]['url'] = $ledger(
                $analysis->ledgerQuery($filters, $dimension, null, $row['value'])
            );

            foreach ($row['cells'] as $cellIndex => $cell) {
                $report['rows'][$rowIndex]['cells'][$cellIndex]['url'] = $ledger(
                    $analysis->ledgerQuery($filters, $dimension, $cell['period'], $row['value'])
                );
            }
        }

        foreach ($report['totals']['cells'] as $cellIndex => $cell) {
            $report['totals']['cells'][$cellIndex]['url'] = $ledger(
                $analysis->ledgerQuery($filters, $dimension, $cell['period'], null, false)
            );
        }

        $report['url'] = $ledger($analysis->ledgerQuery($filters, $dimension, null, null, false));
        $report['filters'] = app(CashflowFilters::class)->toQuery($filters);

        /* Which currency the figures on this sheet are in — '' when the window
           holds more than one, because a range that adds rupees to dollars has
           no single unit to sign, and the page says so instead. */
        $report['money_currency'] = count($report['currencies']) === 1 ? $report['currencies'][0] : '';

        return $report;
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
            'employees' => $this->employees(),
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

    /**
     * The people an entry can be filed against. The app keeps one user table, so
     * this is the user list; department and designation come along for the
     * report's grouping and for the label on the row.
     */
    private function employees()
    {
        if (! class_exists(User::class) || ! Schema::hasTable('users')) {
            return collect();
        }

        /* Employees first, the office underneath — the ledger's Employee field
           is about somebody being paid, and the order is the model's answer
           (`User::employeePicker`), not this page's. */
        return User::employeePicker();
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
