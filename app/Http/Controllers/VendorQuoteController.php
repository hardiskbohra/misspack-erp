<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadMasterOption;
use App\Models\VendorQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorQuoteController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $leadId = $request->query('lead_id', 'all');
        $currency = $request->query('currency', 'all');
        $vendorId = $request->query('vendor_id', 'all');

        $quotes = VendorQuote::query()
            ->with($this->quoteRelations())
            ->search($search)
            ->when($status !== 'all', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->when($leadId !== 'all', function ($q) use ($leadId) {
                $q->where('lead_id', $leadId);
            })
            ->when($currency !== 'all', function ($q) use ($currency) {
                $q->where('currency', $currency);
            })
            ->when($vendorId !== 'all', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => VendorQuote::count(),
            'requested' => VendorQuote::where('status', 'requested')->count(),
            'received' => VendorQuote::where('status', 'received')->count(),
            'shortlisted' => VendorQuote::where('status', 'shortlisted')->count(),
            'approved' => VendorQuote::where('status', 'approved')->count(),
        ];

        return view('vendor_quotes.index', array_merge($this->sharedData(), compact('quotes', 'stats', 'search', 'status', 'leadId', 'currency', 'vendorId')));
    }

    public function create(Request $request): View
    {
        $lead = null;
        if ($request->query('lead_id')) {
            $lead = Lead::find($request->query('lead_id'));
        }

        $product = $this->productFromRequest($request);

        $quote = new VendorQuote([
            'quote_number' => $this->makeQuoteNumber(),
            'lead_id' => $lead?->id,
            'product_id' => $product?->id,
            'status' => 'requested',
            'currency' => 'RMB',
            'incoterm' => 'FOB',
            'product_name' => $product?->name ?: $lead?->product_name,
            'quantity' => $lead?->required_quantity,
            'unit' => 'pcs',
        ]);
        $quote->setRelation('prices', collect());

        return view('vendor_quotes.form', array_merge($this->sharedData(), compact('quote', 'lead')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $prices = $data['prices'] ?? [];
        unset($data['prices'], $data['product_image']);
        $data = $this->handleProductImage($request, $data);
        $data = $this->applyProductDefaults($data);
        $data['quote_number'] = $data['quote_number'] ?: $this->makeQuoteNumber();
        $data['created_by'] = Auth::id();
        $data = $this->normalizeBooleans($request, $data);

        $quote = DB::transaction(function () use ($data, $prices) {
            $quote = VendorQuote::create($data);
            $this->syncPrices($quote, $prices);
            return $quote;
        });

        return redirect()->route('vendor-quotes.show', $quote)->with('success', 'Vendor quote created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'lead_id' => ['nullable', 'exists:leads,id'],
            'product_id' => ['nullable', 'integer'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'vendor_id' => ['nullable', 'integer'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_contact_name' => ['nullable', 'string', 'max:255'],
            'vendor_email' => ['nullable', 'email', 'max:255'],
            'vendor_mobile' => ['nullable', 'string', 'max:40'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in($this->masterKeys('quote_status', array_keys(VendorQuote::statusOptions())))],
            'currency' => ['nullable', Rule::in($this->masterKeys('currency', array_keys(VendorQuote::currencyOptions())))],
            'incoterm' => ['nullable', Rule::in($this->masterKeys('incoterm', array_keys(VendorQuote::incotermOptions())))],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'vendor_unit_price' => ['nullable', 'numeric', 'min:0'],
            'landing_cost_inr' => ['nullable', 'numeric', 'min:0'],
            'selling_price_inr' => ['nullable', 'numeric', 'min:0'],
            'moq' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'ready_stock_available' => ['nullable', 'boolean'],
            'available_colors' => ['nullable', 'string'],
            'finish_options' => ['nullable', 'string'],
            'printing_options' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        unset($data['product_image']);
        $data = $this->handleProductImage($request, $data);
        $data = $this->applyProductDefaults($data);
        $data['quote_number'] = $this->makeQuoteNumber();
        $data['created_by'] = Auth::id();
        $data['sample_available'] = false;
        $data['status'] = 'requested';
        $data['currency'] = 'RMB';
        $data['ready_stock_available'] = $request->boolean('ready_stock_available');
        $data['unit'] = $data['unit'] ?? 'pcs';

        $quote = VendorQuote::create($data);

        return redirect()->route('vendor-quotes.show', $quote)->with('success', 'Quick vendor quote created successfully.');
    }

    public function show(VendorQuote $vendorQuote): View
    {
        $vendorQuote->load($this->quoteRelations());

        return view('vendor_quotes.show', array_merge($this->sharedData(), ['quote' => $vendorQuote]));
    }

    public function image(VendorQuote $vendorQuote): View
    {
        $vendorQuote->load($this->quoteRelations());
        $productMedia = $vendorQuote->product?->primaryMedia();
        $imagePath = $vendorQuote->product_image_path ?: ($productMedia?->file_path ?: $vendorQuote->lead?->product_image_path);

        return view('vendor_quotes.image', [
            'quote' => $vendorQuote,
            'imagePath' => $imagePath,
            'title' => $vendorQuote->quote_number,
            'subtitle' => ($vendorQuote->lead?->lead_number ?: 'No Lead').' · '.($vendorQuote->vendor?->vendor_name ?? $vendorQuote->vendor_name ?? 'Vendor Quote Product Image'),
            'backUrl' => route('vendor-quotes.show', $vendorQuote),
        ]);
    }

    public function edit(VendorQuote $vendorQuote): View
    {
        $vendorQuote->load($this->quoteRelations());
        $lead = $vendorQuote->lead;

        return view('vendor_quotes.form', array_merge($this->sharedData(), ['quote' => $vendorQuote, 'lead' => $lead]));
    }

    public function update(Request $request, VendorQuote $vendorQuote): RedirectResponse
    {
        $data = $this->validatedData($request, $vendorQuote);
        $prices = $data['prices'] ?? [];
        unset($data['prices'], $data['product_image']);
        $data = $this->handleProductImage($request, $data, $vendorQuote);
        $data = $this->applyProductDefaults($data);
        $data = $this->normalizeBooleans($request, $data);

        DB::transaction(function () use ($vendorQuote, $data, $prices) {
            $vendorQuote->update($data);
            $this->syncPrices($vendorQuote, $prices);
        });

        return redirect()->route('vendor-quotes.show', $vendorQuote)->with('success', 'Vendor quote updated successfully.');
    }

    public function destroy(VendorQuote $vendorQuote): RedirectResponse
    {
        if ($vendorQuote->product_image_path) {
            Storage::disk('public')->delete($vendorQuote->product_image_path);
        }

        $vendorQuote->delete();

        return redirect()->route('vendor-quotes.index')->with('success', 'Vendor quote deleted successfully.');
    }

    private function validatedData(Request $request, ?VendorQuote $quote = null): array
    {
        return $request->validate([
            'quote_number' => ['nullable', 'string', 'max:255', 'unique:vendor_quotes,quote_number,'.($quote?->id ?? 'NULL')],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'product_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_contact_name' => ['nullable', 'string', 'max:255'],
            'vendor_email' => ['nullable', 'email', 'max:255'],
            'vendor_mobile' => ['nullable', 'string', 'max:40'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'status' => ['required', Rule::in($this->masterKeys('quote_status', array_keys(VendorQuote::statusOptions())))],
            'currency' => ['nullable', Rule::in($this->masterKeys('currency', array_keys(VendorQuote::currencyOptions())))],
            'incoterm' => ['nullable', Rule::in($this->masterKeys('incoterm', array_keys(VendorQuote::incotermOptions())))],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'vendor_unit_price' => ['nullable', 'numeric', 'min:0'],
            'landing_cost_inr' => ['nullable', 'numeric', 'min:0'],
            'selling_price_inr' => ['nullable', 'numeric', 'min:0'],
            'moq' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'sample_available' => ['nullable', 'boolean'],
            'ready_stock_available' => ['nullable', 'boolean'],
            'available_colors' => ['nullable', 'string'],
            'finish_options' => ['nullable', 'string'],
            'printing_options' => ['nullable', 'string'],
            'size_details' => ['nullable', 'string'],
            'weight_details' => ['nullable', 'string'],
            'packaging_details' => ['nullable', 'string'],
            'photo_video_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],

            'prices' => ['nullable', 'array'],
            'prices.*.quantity' => ['nullable', 'integer', 'min:0'],
            'prices.*.unit' => ['nullable', 'string', 'max:30'],
            'prices.*.finish_type' => ['nullable', 'string', 'max:255'],
            'prices.*.printing_type' => ['nullable', 'string', 'max:255'],
            'prices.*.vendor_unit_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.landing_cost_inr' => ['nullable', 'numeric', 'min:0'],
            'prices.*.selling_price_inr' => ['nullable', 'numeric', 'min:0'],
            'prices.*.moq' => ['nullable', 'integer', 'min:0'],
            'prices.*.remarks' => ['nullable', 'string'],
        ]);
    }

    private function handleProductImage(Request $request, array $data, ?VendorQuote $quote = null): array
    {
        if ($request->hasFile('product_image')) {
            if ($quote?->product_image_path) {
                Storage::disk('public')->delete($quote->product_image_path);
            }

            $data['product_image_path'] = $request->file('product_image')->store('vendor-quotes/products', 'public');
        }

        return $data;
    }

    private function normalizeBooleans(Request $request, array $data): array
    {
        $data['sample_available'] = $request->boolean('sample_available');
        $data['ready_stock_available'] = $request->boolean('ready_stock_available');
        $data['unit'] = $data['unit'] ?? 'pcs';

        return $data;
    }

    private function syncPrices(VendorQuote $quote, array $prices): void
    {
        $quote->prices()->delete();

        foreach ($prices as $price) {
            if (blank($price['quantity'] ?? null) && blank($price['vendor_unit_price'] ?? null) && blank($price['selling_price_inr'] ?? null)) {
                continue;
            }

            $quote->prices()->create([
                'quantity' => $price['quantity'] ?? null,
                'unit' => $price['unit'] ?? 'pcs',
                'finish_type' => $price['finish_type'] ?? null,
                'printing_type' => $price['printing_type'] ?? null,
                'vendor_unit_price' => $price['vendor_unit_price'] ?? null,
                'landing_cost_inr' => $price['landing_cost_inr'] ?? null,
                'selling_price_inr' => $price['selling_price_inr'] ?? null,
                'moq' => $price['moq'] ?? null,
                'remarks' => $price['remarks'] ?? null,
            ]);
        }
    }

    private function makeQuoteNumber(): string
    {
        $prefix = 'VQ-'.now()->format('ymd').'-';
        $next = str_pad((string) (VendorQuote::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (VendorQuote::where('quote_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function sharedData(): array
    {
        return [
            'leads' => Lead::query()->latest('id')->get(),
            'vendors' => $this->vendors(),
            'products' => $this->products(),
            'statusOptions' => $this->masterOptions('quote_status', VendorQuote::statusOptions()),
            'currencyOptions' => $this->masterOptions('currency', VendorQuote::currencyOptions()),
            'incotermOptions' => $this->masterOptions('incoterm', VendorQuote::incotermOptions()),
        ];
    }

    private function masterOptions(string $group, array $fallback = [], bool $activeOnly = true): array
    {
        if (! Schema::hasTable('lead_master_options')) {
            return $fallback;
        }

        $query = LeadMasterOption::query()->where('group', $group);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $options = $query->orderBy('sort_order')->orderBy('label')->pluck('label', 'key')->toArray();

        return $options ?: $fallback;
    }

    private function masterKeys(string $group, array $fallback): array
    {
        $fallbackOptions = array_combine($fallback, $fallback);

        return array_keys($this->masterOptions($group, $fallbackOptions ?: [], true));
    }


    private function quoteRelations(): array
    {
        $relations = ['lead', 'prices', 'creator'];

        if ($this->vendorModelAvailable()) {
            $relations[] = 'vendor';
        }

        if ($this->productModelAvailable()) {
            $relations[] = 'product.media';
            $relations[] = 'product.priceLadders';
        }

        return $relations;
    }

    private function productFromRequest(Request $request)
    {
        if (! $this->productModelAvailable() || ! $request->query('product_id')) {
            return null;
        }

        return \App\Models\Product::find($request->query('product_id'));
    }

    private function applyProductDefaults(array $data): array
    {
        if (empty($data['product_id']) || ! $this->productModelAvailable()) {
            return $data;
        }

        $product = \App\Models\Product::find($data['product_id']);

        if (! $product) {
            return $data;
        }

        if (empty($data['product_name'])) {
            $data['product_name'] = $product->name;
        }

        if (empty($data['finish_options'])) {
            $data['finish_options'] = $product->finish_details;
        }

        if (empty($data['printing_options'])) {
            $data['printing_options'] = $product->printing_details;
        }

        if (empty($data['size_details'])) {
            $data['size_details'] = $product->size_measurements;
        }

        if (empty($data['weight_details'])) {
            $data['weight_details'] = $product->weight_measurements;
        }

        if (empty($data['packaging_details'])) {
            $data['packaging_details'] = $product->packaging_details;
        }

        return $data;
    }

    private function products()
    {
        if (! $this->productModelAvailable()) {
            return collect();
        }

        return \App\Models\Product::query()->where('status', 'active')->orderBy('name')->get();
    }

    private function productModelAvailable(): bool
    {
        return class_exists(\App\Models\Product::class) && Schema::hasTable('products');
    }

    private function vendors()
    {
        if (! $this->vendorModelAvailable()) {
            return collect();
        }

        return \App\Models\Vendor::query()->orderBy('vendor_name')->get();
    }

    private function vendorModelAvailable(): bool
    {
        return class_exists(\App\Models\Vendor::class) && Schema::hasTable('vendors');
    }
}
