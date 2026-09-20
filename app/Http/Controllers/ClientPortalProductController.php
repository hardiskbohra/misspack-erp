<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientPortalProductController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $category = $request->query('category', 'all');
        $productIds = $this->relatedProductIds($request);
        $products = collect();

        if ($this->productsAvailable() && $productIds) {
            $products = \App\Models\Product::query()
                ->with(['media', 'priceLadders'])
                ->whereIn('id', $productIds)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%");
                    });
                })
                ->when($category !== 'all', function ($query) use ($category) {
                    $query->where('category', $category);
                })
                ->orderBy('name')
                ->paginate(12)
                ->withQueryString();
        }

        $categories = $this->productsAvailable()
            ? \App\Models\Product::whereIn('id', $productIds ?: [0])->whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category')
            : collect();

        return view('client_portal.products.index', compact('products', 'search', 'category', 'categories'));
    }

    public function show(Request $request, int $product): View
    {
        abort_unless($this->productsAvailable(), 404);
        abort_unless(in_array($product, $this->relatedProductIds($request), true), 404);

        $product = \App\Models\Product::with(['media', 'priceLadders'])->findOrFail($product);

        return view('client_portal.products.show', compact('product'));
    }

    private function relatedProductIds(Request $request): array
    {
        $client = $this->client($request);
        $ids = [];

        if ($this->projectsAvailable() && Schema::hasTable('project_products')) {
            $projectIds = \App\Models\Project::where('client_id', $client->id)->where('show_client_portal', true)->pluck('id');
            $projectProductIds = \App\Models\ProjectProduct::whereIn('project_id', $projectIds)->whereNotNull('product_id')->pluck('product_id')->all();
            $ids = array_merge($ids, $projectProductIds);
        }

        if ($this->quotesAvailable() && Schema::hasTable('customer_quote_items')) {
            $quoteIds = \App\Models\CustomerQuote::where('client_id', $client->id)
                ->where('status', '!=', 'draft')
                ->when(Schema::hasColumn('customer_quotes', 'show_client_portal'), function ($query) {
                    $query->where('show_client_portal', true);
                })
                ->pluck('id');

            $quoteProductIds = \App\Models\CustomerQuoteItem::whereIn('customer_quote_id', $quoteIds)->whereNotNull('product_id')->pluck('product_id')->all();
            $ids = array_merge($ids, $quoteProductIds);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $ids;
    }
}
