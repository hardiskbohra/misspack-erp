<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Services\StockLedger;
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
        $search = trim((string) $request->query('search', ''));
        $filter = (string) $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'in_stock', 'zero', 'expired', 'expiring'], true)) $filter = 'all';
        $query = StockItem::query()->with('product')->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
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
            'filter' => $filter, 'stats' => [
                'items' => StockItem::count(),
                'in_stock' => StockItem::where('quantity_milli', '>', 0)->count(),
                'expired' => StockItem::where('quantity_milli', '>', 0)->whereDate('expires_on', '<', today())->count(),
                'pending' => StockCount::where('status', 'submitted')->count(),
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
        $stockItem->load('product');
        return view('stock.show', ['item' => $stockItem, 'movements' => $stockItem->movements()->with('countLine.count')->paginate(30),
            'openLines' => $stockItem->countLines()->whereHas('count', fn ($q) => $q->whereIn('status', ['draft', 'submitted']))
                ->with('count')->get(), 'requestKey' => (string) Str::uuid()]);
    }

    public function movement(Request $request, StockItem $stockItem, StockLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['receipt', 'issue'])],
            'quantity' => ['required', 'string', 'regex:'.self::QUANTITY],
            'reason' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'request_key' => ['required', 'uuid'],
        ]);
        $ledger->manualMovement($stockItem, $data, $request->user());
        return redirect()->route('stock.items.show', $stockItem)->with('success', 'Stock movement recorded.');
    }
}
