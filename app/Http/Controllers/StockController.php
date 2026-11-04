<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Services\StockLedger;
use App\Services\StockRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockController extends Controller
{
    private const QUANTITY = '/\A[0-9]{1,12}(?:\.[0-9]{1,3})?\z/';

    public function index(Request $request): View
    {
        $search = $request->query('search', '');
        $filter = $request->query('filter', 'all');
        $status = $request->query('status', 'active');
        $search = is_string($search) ? trim($search) : '';
        if (! is_string($filter) || ! in_array($filter, ['all', 'in_stock', 'zero', 'expired', 'expiring'], true)) $filter = 'all';
        if (! is_string($status) || ! in_array($status, ['active', 'archived', 'all'], true)) $status = 'active';
        $query = StockItem::query()->with('product');
        if ($status === 'active') $query->whereNull('archived_at');
        if ($status === 'archived') $query->whereNotNull('archived_at');
        $query->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
            $q->where('variant', 'like', '%'.$search.'%')->orWhere('batch_code', 'like', '%'.$search.'%')
                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', '%'.$search.'%')
                    ->orWhere('product_number', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
        }));
        if ($filter === 'in_stock') $query->where('quantity_milli', '>', 0);
        if ($filter === 'zero') $query->where('quantity_milli', '=', 0);
        if ($filter === 'expired') $query->where('quantity_milli', '>', 0)->whereDate('expires_on', '<', today());
        if ($filter === 'expiring') $query->where('quantity_milli', '>', 0)->whereDate('expires_on', '>=', today())
            ->whereDate('expires_on', '<=', today()->addDays(30));
        return view('stock.index', ['items' => $query->latest('id')->paginate(30)->withQueryString(), 'search' => $search,
            'filter' => $filter, 'status' => $status, 'stats' => [
                'items' => StockItem::whereNull('archived_at')->count(),
                'in_stock' => StockItem::whereNull('archived_at')->where('quantity_milli', '>', 0)->count(),
                'expired' => StockItem::whereNull('archived_at')->where('quantity_milli', '>', 0)->whereDate('expires_on', '<', today())->count(),
                'pending' => StockCount::where('status', 'submitted')->count(),
                'archived_with_stock' => StockItem::whereNotNull('archived_at')->where('quantity_milli', '>', 0)->count(),
            ]]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('operations.manage'), 403);
        return view('stock.create', ['products' => Product::orderBy('name')->orderBy('id')->get(['id', 'product_number', 'name', 'sku', 'status'])]);
    }

    public function store(Request $request, StockLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'variant' => ['nullable', 'string', 'max:200'],
            'batch_code' => ['nullable', 'required_with:expires_on', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'unit' => ['required', 'string', 'max:30'],
            'opening_quantity' => ['required', 'string', 'regex:'.self::QUANTITY],
            'reason' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $item = $ledger->createItem($data, $request->user());
        return redirect()->route('stock.items.show', $item)->with('success', 'Stock item and opening balance recorded.');
    }

    public function show(StockItem $stockItem): View
    {
        $stockItem->load(['product', 'archiver']);
        return view('stock.show', ['item' => $stockItem,
            'movements' => $stockItem->movements()->with('countLine.count')->paginate(30)->withQueryString(),
            'countHistory' => $stockItem->countLines()->with('count')->orderByDesc('id')->paginate(20, ['*'], 'counts_page')->withQueryString(),
            'events' => $stockItem->events()->with('stockCount')->paginate(20, ['*'], 'events_page')->withQueryString(),
            'openLines' => $stockItem->countLines()->whereHas('count', fn ($q) => $q->whereIn('status', ['draft', 'submitted']))
                ->with('count')->get(), 'requestKey' => (string) Str::uuid()]);
    }

    public function edit(Request $request, StockItem $stockItem): View|RedirectResponse
    {
        abort_unless($request->user()->hasPermission('operations.manage'), 403);
        if ($stockItem->archived_at) {
            // An edit submitted from an older tab may have just been rejected
            // after another user archived the item. Keep its unsaved input
            // and validation errors across this extra redirect to the ledger.
            $request->session()->keep(['_old_input', 'errors']);
            return redirect()->route('stock.items.show', $stockItem)->with('error', 'Restore this item before editing it.');
        }
        $stockItem->load('product');
        return view('stock.edit', ['item' => $stockItem,
            'products' => Product::orderBy('name')->orderBy('id')->get(['id', 'product_number', 'name', 'sku', 'status'])]);
    }

    public function update(Request $request, StockItem $stockItem, StockRegistry $registry): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'variant' => ['nullable', 'string', 'max:200'],
            'batch_code' => ['nullable', 'required_with:expires_on', 'string', 'max:120'],
            'expires_on' => ['nullable', 'date_format:Y-m-d'],
            'unit' => ['required', 'string', 'max:30'],
            'requested_quantity' => ['required', 'string', 'regex:'.self::QUANTITY],
            'reason' => ['required', 'string', 'max:1000'],
            'record_revision' => ['required', 'integer', 'min:0'],
            'ledger_version' => ['required', 'integer', 'min:1'],
        ]);
        $count = $registry->update($stockItem, $data, $request->user());
        if ($count) return redirect()->route('stock.counts.show', $count)->with('success', 'Any detail corrections were saved. The requested quantity is awaiting independent review; stock has not changed yet.');
        return redirect()->route('stock.items.show', $stockItem)->with('success', 'Stock item details updated and recorded in the history.');
    }

    public function destroy(Request $request, StockItem $stockItem, StockRegistry $registry): RedirectResponse
    {
        $data = $this->validatedDecision($request) + $request->validate(['acknowledge_nonzero' => ['sometimes', 'accepted']]);
        $registry->archive($stockItem, $data, $request->user());
        return redirect()->route('stock.items.show', $stockItem)->with('success', 'Item archived. Its stock balance and history are retained.');
    }

    public function restore(Request $request, StockItem $stockItem, StockRegistry $registry): RedirectResponse
    {
        $registry->restore($stockItem, $this->validatedDecision($request), $request->user());
        return redirect()->route('stock.items.show', $stockItem)->with('success', 'Item restored to the active stock register.');
    }

    private function validatedDecision(Request $request): array
    {
        return $request->validate(['reason' => ['required', 'string', 'max:1000'],
            'record_revision' => ['required', 'integer', 'min:0'], 'ledger_version' => ['required', 'integer', 'min:1']]);
    }

    public function movement(Request $request, StockItem $stockItem, StockLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['receipt', 'issue'])],
            'quantity' => ['required', 'string', 'regex:'.self::QUANTITY],
            'reason' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'request_key' => ['required', 'uuid'],
            'record_revision' => ['required', 'integer', 'min:0'],
        ]);
        $ledger->manualMovement($stockItem, $data, $request->user());
        return redirect()->route('stock.items.show', $stockItem)->with('success', 'Stock movement recorded.');
    }
}
