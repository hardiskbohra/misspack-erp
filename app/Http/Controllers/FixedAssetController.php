<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetDepreciation;
use App\Services\AssetFigures;
use App\Services\AssetFilters;
use App\Services\AssetIntake;
use App\Services\AssetVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The fixed asset register — what the company owns, what it is worth, and who
 * answers for it.
 *
 * Read the two migrations and `docs/fixed-assets.md` first; the short
 * version is that this module keeps a **register** (the compliance artefact a
 * private limited company has to maintain) and nothing else:
 *
 *   - the **register** (`index`) — every asset, with the company's totals above
 *     it and the office's own spreadsheet columns on it, filterable by the things
 *     an auditor asks about (class, place, person, warranty, verification);
 *   - one **asset** (`show`) — its facts, its hand-overs, its repairs and its
 *     depreciation schedule, each on its own tab with its own URL;
 *   - the **depreciation report** (`depreciation`) — the company's year, class by
 *     class, as the CA asks for it, with the CSV beside it;
 *   - the **doors** — hand it over, take it back, log a repair, verify it in
 *     person, dispose of it, delete it. Every one of them hands facts to
 *     `AssetIntake`, which is the only thing in the application that writes an
 *     asset, a hand-over or a repair.
 *
 * The controller validates, guards and produces sentences; it computes no
 * depreciation and stores nothing. Every money figure on these screens comes from
 * `AssetDepreciation` (one calculator) applied to the asset's own columns, which
 * is why the register has no "recalculate" button and cannot fall out of step
 * with itself.
 */
class FixedAssetController extends Controller
{
    public function __construct(
        private AssetFilters $filters,
        private AssetFigures $figures,
        private AssetIntake $intake,
        private AssetDepreciation $depreciation,
    ) {
    }

    /* ------------------------------------------------------------------ the register */

    public function index(Request $request): View
    {
        $filters = $this->filters->fromRequest($request);

        /* **One query.** The figures, the attention counts, the chip tallies and
           the table are this builder or a clone of it — so standing in the
           Ahmedabad view, every number above the list describes Ahmedabad. */
        $query = $this->filters->apply(
            FixedAsset::query()->with(['category', 'custodian', 'openAllocation.holder', 'vendor']),
            $filters
        );

        $figures = $this->figures->summary($query);

        /* The chip counts are the one reading that needs its own builder: they
           are counted with the state chip lifted, or each chip would report on
           the state it is standing in rather than the state it offers. */
        $stateCounts = $this->figures->stateCounts(
            $this->filters->apply(FixedAsset::query(), $this->filters->withoutStatus($filters))
        );

        $assets = $this->filters->page($query, $filters);
        $applied = $this->filters->applied($filters);

        return view('assets.index', array_merge($this->sharedData(), [
            'assets' => $assets,
            'figures' => $figures,
            'stateCounts' => $stateCounts,
            'stateOptions' => AssetVocabulary::STATE_LABELS,
            /* `statusOptions` (the four states, without "Everything") comes from
               `sharedData()` — the chips use `stateOptions` above, which carries
               the extra "Everything" entry the form must not offer, and the
               lists every dialog needs come from there too. */
            'warrantyOptions' => AssetFilters::WARRANTY_LABELS,
            'verificationOptions' => AssetFilters::VERIFICATION_LABELS,
            'sortOptions' => AssetFilters::SORT_LABELS,
            'applied' => $applied,
            'filtered' => $applied !== [],
            'departments' => $this->filters->departments(),
            ...$filters,
        ]));
    }

    /* ------------------------------------------------------------------- one asset */

    /**
     * An asset's record — four tabs, each with its own URL.
     *
     * The tabs are links rather than a client-side strip for the same reason the
     * project record's are: a hand-over is logged from the allocation tab, and a
     * form posted from a tab has to come back to that tab. `?tab=` is the
     * address, and an unknown one is the overview rather than an error.
     */
    public function show(Request $request, FixedAsset $asset): View
    {
        $asset->load([
            'category', 'vendor', 'custodian', 'creator', 'verifier',
            'openAllocation.holder',
            'allocations' => fn ($query) => $query->with(['holder', 'creator'])->orderByDesc('allocated_on'),
            'maintenances' => fn ($query) => $query->with(['vendor', 'creator'])->orderByDesc('performed_on'),
        ]);

        $tabs = [
            'overview' => 'Overview',
            'allocation' => 'Allocation',
            'maintenance' => 'Maintenance',
            'depreciation' => 'Depreciation',
        ];

        $tab = (string) $request->query('tab', 'overview');

        if (! array_key_exists($tab, $tabs)) {
            $tab = 'overview';
        }

        $tabCounts = [
            'allocation' => $asset->allocations->count(),
            'maintenance' => $asset->maintenances->count(),
            'depreciation' => count($asset->schedule()),
        ];

        return view('assets.show', array_merge($this->sharedData(), [
            'asset' => $asset,
            'tabs' => $tabs,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'schedule' => $asset->schedule(),
            'accumulated' => $asset->accumulatedDepreciation(),
            'netBookValue' => $asset->netBookValue(),
            'chargeThisYear' => $asset->chargeForYear($this->depreciation->currentKey()),
            'currentYear' => $this->depreciation->currentKey(),
            'maintenanceSpend' => $asset->maintenanceSpend(),
            'nextService' => $asset->maintenances
                ->whereNotNull('next_due_on')
                ->sortBy('next_due_on')
                ->first(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $asset = $this->intake->create($request->user(), $this->facts($request));

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Asset registered. Its depreciation starts from the purchase date.');
    }

    public function update(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->intake->update($asset, $this->facts($request, $asset));

        return redirect()
            ->route('assets.show', ['asset' => $asset, 'tab' => 'overview'])
            ->with('success', 'Asset updated.');
    }

    public function destroy(FixedAsset $asset): RedirectResponse
    {
        $code = $asset->asset_code;

        $this->intake->delete($asset);

        return redirect()
            ->route('assets.index')
            ->with('success', $code.' deleted — its hand-over and repair history went with it.');
    }

    /* -------------------------------------------------------------------- the doors */

    /** Hand it over: the person, the place, and the date it changed hands. */
    public function allocate(Request $request, FixedAsset $asset): RedirectResponse
    {
        $facts = $request->validate([
            'allocated_to' => ['nullable', 'integer', 'exists:users,id'],
            'holder_name' => ['nullable', 'string', 'max:160', 'required_without_all:allocated_to,location,department'],
            'location' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:80'],
            'allocated_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->intake->allocate($request->user(), $asset, $facts);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('assets.show', ['asset' => $asset, 'tab' => 'allocation'])
            ->with('success', 'Handed over. The asset\'s location, department and custodian moved with it.');
    }

    /** Take it back — the open hand-over closes and the asset has no holder. */
    public function takeBack(Request $request, FixedAsset $asset): RedirectResponse
    {
        $facts = $request->validate([
            'returned_on' => ['nullable', 'date'],
            'condition' => ['nullable', Rule::in(array_keys(AssetVocabulary::CONDITIONS))],
            'location' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $this->intake->returnAsset($request->user(), $asset, $facts);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('assets.show', ['asset' => $asset, 'tab' => 'allocation'])
            ->with('success', 'Taken back. The hand-over is closed with the condition it came back in.');
    }

    /** A service, a repair, an AMC payment — and the state, if the office says so. */
    public function maintain(Request $request, FixedAsset $asset): RedirectResponse
    {
        $facts = $request->validate([
            'kind' => ['required', Rule::in(array_keys(AssetVocabulary::KINDS))],
            'performed_on' => ['required', 'date'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:160'],
            'invoice_no' => ['nullable', 'string', 'max:80'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'downtime_from' => ['nullable', 'date'],
            'downtime_to' => ['nullable', 'date', 'after_or_equal:downtime_from'],
            'next_due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(array_keys(AssetVocabulary::STATUSES))],
        ]);

        $this->intake->maintain($request->user(), $asset, $facts);

        return redirect()
            ->route('assets.show', ['asset' => $asset, 'tab' => 'maintenance'])
            ->with('success', 'Logged. What it cost, how long it was down, and when it is next due.');
    }

    /** The physical verification: who looked, when, and what they found. */
    public function verify(Request $request, FixedAsset $asset): RedirectResponse
    {
        $facts = $request->validate([
            'last_verified_on' => ['nullable', 'date'],
            'condition' => ['nullable', Rule::in(array_keys(AssetVocabulary::CONDITIONS))],
        ]);

        try {
            $this->intake->verify($request->user(), $asset, $facts);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('assets.show', ['asset' => $asset, 'tab' => 'overview'])
            ->with('success', 'Verification recorded — the register now says who checked it and when.');
    }

    /** Off the books: the date, what it fetched, and why. */
    public function dispose(Request $request, FixedAsset $asset): RedirectResponse
    {
        $facts = $request->validate([
            'disposal_date' => ['required', 'date', 'after_or_equal:purchase_date'],
            'disposal_value' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->intake->dispose($request->user(), $asset, $facts);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $gain = $asset->fresh()->disposalGainLoss();

        /* The sentence says the one number the office will ask for next: what the
           sale made or lost against the book value on the day it left. */
        $message = 'Disposed. It leaves the books from that date.';

        if ($gain !== null) {
            $message = 'Disposed. It leaves the books from that date — a '
                .($gain >= 0 ? 'profit' : 'loss').' of '.CommonHelper::amount(abs($gain), 'INR')
                .' against the book value on that day.';
        }

        return redirect()
            ->route('assets.show', ['asset' => $asset, 'tab' => 'overview'])
            ->with('success', $message);
    }

    /* ------------------------------------------------------------------- the report */

    /**
     * The company's year, class by class: what the assets were worth at the start,
     * what was bought, what was written off, what was sold and what is left.
     *
     * The report is **company-wide by design** and the screen says so: a CA asks
     * about the year, not about the Ahmedabad view. Its totals are the sum of the
     * rows printed under them, because they are accumulated from that same array.
     */
    public function depreciation(Request $request): View
    {
        $earliest = FixedAsset::query()->min('purchase_date');
        $yearOptions = $this->depreciation->yearOptions($earliest ? Carbon::parse($earliest) : null);

        $requested = (string) $request->query('year', '');
        $financialYear = array_key_exists($requested, $yearOptions) ? $requested : array_key_first($yearOptions);

        $report = $this->figures->yearReport(FixedAsset::query()->with('category'), $financialYear);

        return view('assets.depreciation', array_merge($this->sharedData(), [
            'year' => $report['year'],
            'yearOptions' => $yearOptions,
            'groups' => $report['groups'],
            'totals' => $report['totals'],
            'rowCount' => count($report['rows']),
        ]));
    }

    /* -------------------------------------------------------------------- exports */

    /**
     * The register as the spreadsheet the office used to keep — the same filters,
     * the same columns, and the two figures that were always wrong on the
     * spreadsheet (accumulated depreciation and net book value) computed for the
     * day the file was taken.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters->fromRequest($request);

        $rows = $this->filters
            ->order(
                $this->filters->apply(FixedAsset::query()->with(['category', 'custodian', 'vendor']), $filters),
                $filters
            )
            ->get();

        $applied = $this->filters->applied($filters);
        $today = now();

        return response()->streamDownload(function () use ($rows, $applied, $today) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $row = fn (array $cells) => fputcsv($out, $cells);
            $money = fn ($value) => number_format((float) $value, 2, '.', '');

            $row(['Fixed asset register']);
            $row(['Taken', $today->format('d M Y H:i')]);
            $row(['Filtered by', $applied === [] ? 'Everything' : implode(', ', array_map(
                fn (array $item) => $item['label'].': '.$item['value'], $applied
            ))]);
            $row(['Net book value and accumulated depreciation are computed as at '.$today->format('d M Y')
                .' (depreciation is charged to the date of the file, the current year pro-rated).']);
            $row([]);

            $row([
                'Asset ID', 'Category', 'Asset Name', 'Description / Specifications', 'Make / Brand', 'Model',
                'Serial / IMEI / Identification No.', 'Purchase Date', 'Supplier / Vendor', 'Invoice No.',
                'Invoice Date', 'Purchase Cost (₹)', 'GST (₹)', 'Total Cost (₹)', 'Capitalised (₹)',
                'Location', 'Department', 'Custodian / Employee', 'Status', 'Warranty End Date',
                'Useful Life (Years)', 'Depreciation Method', 'Accumulated Depreciation (₹)',
                'Net Book Value (₹)', 'Insurance Details', 'Last Physical Verification', 'Condition',
                'Disposal Date', 'Disposal Value (₹)', 'Remarks',
            ]);

            foreach ($rows as $asset) {
                $row([
                    $asset->asset_code,
                    $asset->category?->name,
                    $asset->name,
                    $asset->description,
                    $asset->make,
                    $asset->model,
                    $asset->serial_no,
                    $asset->purchase_date?->format('Y-m-d'),
                    $asset->supplierLabel(),
                    $asset->invoice_no,
                    $asset->invoice_date?->format('Y-m-d'),
                    $money($asset->cost),
                    $money($asset->gst_amount),
                    $money($asset->totalCost()),
                    $money($asset->capitalisedCost()),
                    $asset->location,
                    $asset->department,
                    $asset->holderLabel(),
                    $asset->stateLabel(),
                    $asset->warranty_end_date?->format('Y-m-d'),
                    $asset->effectiveLifeYears(),
                    $asset->methodLabel(),
                    $money($asset->accumulatedDepreciation($today)),
                    $money($asset->netBookValue($today)),
                    $asset->insurance_details,
                    $asset->last_verified_on?->format('Y-m-d'),
                    $asset->conditionLabel(),
                    $asset->disposal_date?->format('Y-m-d'),
                    $asset->disposal_value === null ? '' : $money($asset->disposal_value),
                    $asset->remarks,
                ]);
            }

            $row([]);
            $row(['Assets', $rows->count()]);
            $row(['Capitalised cost', $money($rows->sum(fn (FixedAsset $asset) => $asset->capitalisedCost()))]);
            $row(['Accumulated depreciation', $money($rows->sum(fn (FixedAsset $asset) => $asset->accumulatedDepreciation($today)))]);
            $row(['Net book value', $money($rows->sum(fn (FixedAsset $asset) => $asset->netBookValue($today)))]);

            fclose($out);
        }, 'fixed-asset-register-'.$today->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** The year's depreciation schedule as a CSV — the same rows the page shows. */
    public function depreciationExport(Request $request): StreamedResponse
    {
        $earliest = FixedAsset::query()->min('purchase_date');
        $yearOptions = $this->depreciation->yearOptions($earliest ? Carbon::parse($earliest) : null);

        $requested = (string) $request->query('year', '');
        $financialYear = array_key_exists($requested, $yearOptions) ? $requested : array_key_first($yearOptions);

        $report = $this->figures->yearReport(FixedAsset::query()->with('category'), $financialYear);
        $totals = $report['totals'];

        return response()->streamDownload(function () use ($report, $totals) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $row = fn (array $cells) => fputcsv($out, $cells);
            $money = fn ($value) => number_format((float) $value, 2, '.', '');

            $row(['Depreciation schedule — '.$report['year']['label']]);
            $row([$report['year']['from']->format('d M Y').' to '.$report['year']['to']->format('d M Y')]);
            $row(['Rows are the assets the year touched. Opening is the net book value on '
                .$report['year']['from']->copy()->subDay()->format('d M Y')
                .'; the charge is the year\'s depreciation, pro-rated to the purchase or disposal date.']);
            $row([]);

            $row(['Class', 'Asset ID', 'Asset', 'Method', 'Life (years)', 'Capitalised (₹)', 'Opening (₹)',
                'Additions (₹)', 'Charge for the year (₹)', 'Closing (₹)', 'Disposed on',
                'Disposal value (₹)', 'Book value on disposal (₹)', 'Profit / (loss) (₹)']);

            foreach ($report['groups'] as $group) {
                foreach ($group['rows'] as $asset) {
                    $row([
                        $asset['category'],
                        $asset['code'],
                        $asset['name'],
                        $asset['method'],
                        $asset['life'],
                        $money($asset['basis']),
                        $money($asset['opening']),
                        $money($asset['additions']),
                        $money($asset['charge']),
                        $money($asset['closing']),
                        $asset['disposed_on']?->format('Y-m-d'),
                        $money($asset['disposal_value']),
                        $money($asset['disposal_book']),
                        $money($asset['disposal_gain']),
                    ]);
                }

                $row([
                    $group['category'].' — subtotal', '', $group['subtotal']['assets'].' assets', '', '', '',
                    $money($group['subtotal']['opening']), $money($group['subtotal']['additions']),
                    $money($group['subtotal']['charge']), $money($group['subtotal']['closing']),
                    '', $money($group['subtotal']['disposal_value']),
                    $money($group['subtotal']['disposal_book']), $money($group['subtotal']['disposal_gain']),
                ]);
            }

            $row([]);
            $row([
                'Total', '', $totals['assets'].' assets', '', '', '',
                $money($totals['opening']), $money($totals['additions']), $money($totals['charge']),
                $money($totals['closing']), '', $money($totals['disposal_value']),
                $money($totals['disposal_book']), $money($totals['disposal_gain']),
            ]);

            fclose($out);
        }, 'depreciation-'.$financialYear.'.csv', ['Content-Type' => 'text/csv']);
    }

    /* --------------------------------------------------------------------- private */

    /** The facts the asset form carries, checked — and nothing else reaches an asset. */
    private function facts(Request $request, ?FixedAsset $asset = null): array
    {
        return $request->validate([
            'asset_code' => [
                'required', 'string', 'max:40',
                Rule::unique('fixed_assets', 'asset_code')->ignore($asset?->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['nullable', 'integer', 'exists:fixed_asset_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'make' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['required', 'date'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'invoice_no' => ['nullable', 'string', 'max:80'],
            'invoice_date' => ['nullable', 'date'],
            'cost' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'],
            'depreciate_on_total' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:80'],
            'custodian_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(array_keys(AssetVocabulary::STATUSES))],
            'warranty_end_date' => ['nullable', 'date'],
            /* The recipe is optional on purpose: an empty field means "follow the
               class", which is the normal answer and not a hole. */
            'useful_life_years' => ['nullable', 'integer', 'min:1', 'max:100'],
            'depreciation_method' => ['nullable', Rule::in(array_keys(AssetVocabulary::METHODS))],
            'residual_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'insurance_details' => ['nullable', 'string', 'max:1000'],
            'insurance_expiry' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * The lists the asset form and the doors are filled from — classes, people,
     * vendors and the vocabulary — so every screen offers the same options the
     * module understands.
     *
     * @return array<string, mixed>
     */
    private function sharedData(): array
    {
        return [
            'categoryOptions' => FixedAssetCategory::query()->ordered()->get(),
            'methodOptions' => AssetVocabulary::METHODS,
            'peopleOptions' => User::query()->orderBy('name')->get(['id', 'name']),
            'vendorOptions' => Vendor::query()->orderBy('vendor_name')->get(['id', 'vendor_name']),
            'statusOptions' => AssetVocabulary::STATUSES,
            /* Every list the dialogs are filled from belongs here and not on one
               screen. The dialogs are shared — the register renders them and the
               record renders them — so a list that only one of those screens
               passes is a list the other one fatals on, in whichever dialog
               nobody opened while it was being built. */
            'conditionOptions' => AssetVocabulary::CONDITIONS,
            'kindOptions' => AssetVocabulary::KINDS,
            'locations' => $this->filters->locations(),
            'nextCode' => $this->intake->suggestCode(),
        ];
    }
}
