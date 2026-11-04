<?php

namespace App\Http\Controllers;

use App\Models\StockCount;
use App\Models\StockItem;
use App\Services\StockReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockCountController extends Controller
{
    private const QUANTITY = '/\A[0-9]{1,12}(?:\.[0-9]{1,3})?\z/';

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'draft', 'submitted', 'posted', 'cancelled'], true)) $status = 'all';
        return view('stock.counts.index', ['status' => $status,
            'counts' => StockCount::withCount('lines')->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString()]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('operations.manage'), 403);
        return view('stock.counts.create', ['items' => StockItem::with('product')->orderBy('id')->get()]);
    }

    public function store(Request $request, StockReconciliation $service): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'notes' => ['nullable', 'string', 'max:2000'],
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['required', 'integer', 'distinct', Rule::exists('stock_items', 'id')]]);
        $count = $service->create($data, $request->user());
        return redirect()->route('stock.counts.show', $count)->with('success', 'Count created with current book balances. Enter the physical count for every selected item.');
    }

    public function show(Request $request, StockCount $stockCount): View
    {
        $stockCount->load(['lines.item.product', 'events']);
        $canManage = $request->user()->hasPermission('operations.manage');
        return view('stock.counts.show', ['count' => $stockCount,
            'isCounter' => $canManage && $stockCount->created_by && (int) $stockCount->created_by === (int) $request->user()->id,
            'canReview' => $canManage && $stockCount->created_by && (int) $stockCount->created_by !== (int) $request->user()->id,
            'canManage' => $canManage]);
    }

    public function update(Request $request, StockCount $stockCount, StockReconciliation $service): RedirectResponse
    {
        $data = $this->validatedLines($request);
        $service->save($stockCount, $data['lines'], $request->user(), false, (int) $data['revision']);
        return redirect()->route('stock.counts.show', $stockCount)->with('success', 'Draft count saved.');
    }

    public function submit(Request $request, StockCount $stockCount, StockReconciliation $service): RedirectResponse
    {
        $data = $this->validatedLines($request);
        $service->save($stockCount, $data['lines'], $request->user(), true, (int) $data['revision']);
        return redirect()->route('stock.counts.show', $stockCount)->with('success', 'Count submitted for independent review.');
    }

    public function approve(Request $request, StockCount $stockCount, StockReconciliation $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'revision' => ['required', 'integer', 'min:0']]);
        $service->approve($stockCount, $request->user(), $data['reason'], (int) $data['revision']);
        return redirect()->route('stock.counts.show', $stockCount)->with('success', 'Count approved and any differences posted to the ledger.');
    }

    public function reject(Request $request, StockCount $stockCount, StockReconciliation $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'revision' => ['required', 'integer', 'min:0']]);
        $service->reject($stockCount, $request->user(), $data['reason'], (int) $data['revision']);
        return redirect()->route('stock.counts.show', $stockCount)->with('success', 'Count returned to the counter for correction.');
    }

    public function refresh(Request $request, StockCount $stockCount, StockReconciliation $service): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:0']]);
        $service->refresh($stockCount, $request->user(), (int) $data['revision']);
        return redirect()->route('stock.counts.show', $stockCount)->with('success', 'Book balances refreshed. Recount and enter every physical quantity again.');
    }

    public function cancel(Request $request, StockCount $stockCount, StockReconciliation $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'revision' => ['required', 'integer', 'min:0']]);
        $service->cancel($stockCount, $request->user(), $data['reason'], (int) $data['revision']);
        return redirect()->route('stock.counts.show', $stockCount)->with('success', 'Count cancelled; no stock was changed.');
    }

    private function validatedLines(Request $request): array
    {
        return $request->validate(['revision' => ['required', 'integer', 'min:0'],
            'lines' => ['required', 'array', 'max:100'], 'lines.*' => ['required', 'array'],
            'lines.*.counted_qty' => ['nullable', 'string', 'regex:'.self::QUANTITY],
            'lines.*.reason' => ['nullable', 'string', 'max:1000']]);
    }
}
