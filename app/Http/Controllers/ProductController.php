<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $category = $request->query('category', 'all');

        $products = Product::query()
            ->with(['media', 'priceLadders'])
            ->withCount('vendorQuotes')
            ->search($search)
            ->when($status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($category !== 'all', function ($q) use ($category) {
                $q->where('category', $category);
            })
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'total' => Product::count(),
            'active' => Product::where('status', 'active')->count(),
            'ready_stock' => Product::where('ready_stock_available', true)->count(),
            'public_price' => Product::where('show_price_ladder_public', true)->count(),
        ];

        $categories = Product::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');

        return view('products.index', compact('products', 'stats', 'categories', 'search', 'status', 'category'));
    }

    public function create(): View
    {
        $product = new Product([
            'product_number' => $this->makeProductNumber(),
            'status' => 'active',
        ]);
        $product->setRelation('priceLadders', collect());
        $product->setRelation('media', collect());

        return view('products.form', compact('product'));
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,discontinued'],
            'ml_capacities_text' => ['nullable', 'string', 'max:255'],
            'finish_details' => ['nullable', 'string'],
            'printing_details' => ['nullable', 'string'],
            'ready_stock_available' => ['nullable', 'boolean'],
            'ready_stock_moq' => ['nullable', 'integer', 'min:0'],
            'customisation_moq' => ['nullable', 'integer', 'min:0'],
            'show_price_ladder_public' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'media_files.*' => ['nullable', 'file', 'max:20480'],
            'ladder_quantity' => ['nullable', 'integer', 'min:0'],
            'ladder_selling_cost_inr' => ['nullable', 'numeric', 'min:0'],
            'ladder_landing_cost_inr' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ladder = [];
        if (! empty($data['ladder_quantity']) || ! empty($data['ladder_selling_cost_inr'])) {
            $ladder[] = [
                'quantity' => $data['ladder_quantity'] ?? null,
                'unit' => 'pcs',
                'capacity' => $data['ml_capacities_text'] ?? null,
                'landing_cost_inr' => $data['ladder_landing_cost_inr'] ?? null,
                'selling_cost_inr' => $data['ladder_selling_cost_inr'] ?? null,
            ];
        }

        unset($data['media_files'], $data['ladder_quantity'], $data['ladder_selling_cost_inr'], $data['ladder_landing_cost_inr']);
        $data = $this->prepareData($request, $data);
        $data['product_number'] = $this->makeProductNumber();
        $data['created_by'] = Auth::id();
        $data['status'] = "active";

        $product = DB::transaction(function () use ($request, $data, $ladder) {
            $product = Product::create($data);
            $this->syncLadders($product, $ladder);
            $this->storeMedia($request, $product);
            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Quick product created successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $ladders = $data['price_ladders'] ?? [];
        unset($data['price_ladders'], $data['media_files'], $data['media_links']);
        $data = $this->prepareData($request, $data);
        $data['product_number'] = $data['product_number'] ?: $this->makeProductNumber();
        $data['created_by'] = Auth::id();

        $product = DB::transaction(function () use ($request, $data, $ladders) {
            $product = Product::create($data);
            $this->syncLadders($product, $ladders);
            $this->storeMedia($request, $product);
            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $product->load(['media', 'priceLadders', 'creator']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $product->load(['media', 'priceLadders']);

        return view('products.form', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validatedData($request, $product);
        $ladders = $data['price_ladders'] ?? [];
        unset($data['price_ladders'], $data['media_files'], $data['media_links']);
        $data = $this->prepareData($request, $data);

        DB::transaction(function () use ($request, $product, $data, $ladders) {
            $product->update($data);
            $this->syncLadders($product, $ladders);
            
            if ($request->filled('remove_attachments')) {

                $attachments = $product->media()
                    ->whereIn('id', $request->remove_attachments)
                    ->get();
            
                foreach ($attachments as $attachment) {
            
                    Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                }
            }
            
            $this->storeMedia($request, $product);
        });

        return redirect()->route('products.show', $product)->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        foreach ($product->media as $media) {
            if ($media->file_path) {
                Storage::disk('public')->delete($media->file_path);
            }
        }

        $product->vendorQuotes()->delete();
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

    public function publicShow(string $token): View
    {
        $product = Product::where('public_token', $token)->with(['media', 'priceLadders'])->firstOrFail();

        return view('products.public', compact('product'));
    }

    private function validatedData(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'product_number' => ['nullable', 'string', 'max:255', 'unique:products,product_number,'.($product ? $product->id : 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,discontinued'],
            'ml_capacities_text' => ['nullable', 'string', 'max:255'],
            'finish_details' => ['nullable', 'string'],
            'printing_details' => ['nullable', 'string'],
            'ready_stock_available' => ['nullable', 'boolean'],
            'ready_stock_moq' => ['nullable', 'integer', 'min:0'],
            'customisation_moq' => ['nullable', 'integer', 'min:0'],
            'available_stock_colors' => ['nullable', 'string'],
            'customisation_details' => ['nullable', 'string'],
            'size_measurements' => ['nullable', 'string'],
            'weight_measurements' => ['nullable', 'string'],
            'material_details' => ['nullable', 'string'],
            'packaging_details' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'show_price_ladder_public' => ['nullable', 'boolean'],
            'media_files.*' => ['nullable', 'file', 'max:20480'],
            'media_links' => ['nullable', 'string'],
            'price_ladders' => ['nullable', 'array'],
            'price_ladders.*.quantity' => ['nullable', 'integer', 'min:0'],
            'price_ladders.*.unit' => ['nullable', 'string', 'max:30'],
            'price_ladders.*.capacity' => ['nullable', 'string', 'max:255'],
            'price_ladders.*.finish_type' => ['nullable', 'string', 'max:255'],
            'price_ladders.*.printing_type' => ['nullable', 'string', 'max:255'],
            'price_ladders.*.landing_cost_inr' => ['nullable', 'numeric', 'min:0'],
            'price_ladders.*.selling_cost_inr' => ['nullable', 'numeric', 'min:0'],
            'price_ladders.*.remarks' => ['nullable', 'string'],
        ]);
    }

    private function prepareData(Request $request, array $data): array
    {
        $capacities = collect(explode(',', (string) ($data['ml_capacities_text'] ?? '')))
            ->map(function ($value) { return trim($value); })
            ->filter(function ($value) { return $value !== ''; })
            ->values()
            ->all();

        unset($data['ml_capacities_text']);
        $data['ml_capacities'] = $capacities ?: null;
        $data['status'] = "active";
        $data['ready_stock_available'] = $request->boolean('ready_stock_available');
        $data['show_price_ladder_public'] = $request->boolean('show_price_ladder_public');

        return $data;
    }

    private function syncLadders(Product $product, array $ladders): void
    {
        $product->priceLadders()->delete();

        foreach ($ladders as $ladder) {
            if (blank($ladder['quantity'] ?? null) && blank($ladder['selling_cost_inr'] ?? null)) {
                continue;
            }

            $product->priceLadders()->create([
                'quantity' => $ladder['quantity'] ?? null,
                'unit' => $ladder['unit'] ?? 'pcs',
                'capacity' => $ladder['capacity'] ?? null,
                'finish_type' => $ladder['finish_type'] ?? null,
                'printing_type' => $ladder['printing_type'] ?? null,
                'landing_cost_inr' => $ladder['landing_cost_inr'] ?? null,
                'selling_cost_inr' => $ladder['selling_cost_inr'] ?? null,
                'remarks' => $ladder['remarks'] ?? null,
            ]);
        }
    }

    private function storeMedia(Request $request, Product $product): void
    {
        foreach ((array) $request->file('media_files', []) as $file) {
            if (! $file) {
                continue;
            }

            $mime = (string) $file->getMimeType();
            $type = str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'video/') ? 'video' : 'document');

            $product->media()->create([
                'media_type' => $type,
                'title' => $file->getClientOriginalName(),
                'file_path' => $file->store('products/media', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'is_primary' => $product->media()->count() === 0,
                'uploaded_by' => Auth::id(),
            ]);
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('media_links')) as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }

            $product->media()->create([
                'media_type' => 'link',
                'title' => $url,
                'external_url' => $url,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function makeProductNumber(): string
    {
        $prefix = 'PRD-';
    
        $lastProduct = Product::where('product_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();
    
        $nextNumber = 1;
    
        if ($lastProduct) {
            $lastNumber = (int) str_replace($prefix, '', $lastProduct->product_number);
            $nextNumber = $lastNumber + 1;
        }
    
        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
