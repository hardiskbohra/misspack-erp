<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientPortalQuoteController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $quotes = collect();

        if ($this->quotesAvailable()) {
            $quotes = \App\Models\CustomerQuote::query()
                ->with('items.product')
                ->where('client_id', $this->client($request)->id)
                ->where('status', '!=', 'draft')
                ->when(Schema::hasColumn('customer_quotes', 'show_client_portal'), function ($query) {
                    $query->where('show_client_portal', true);
                })
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('quote_number', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%");
                    });
                })
                ->when($status !== 'all', function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->latest('id')
                ->paginate(10)
                ->withQueryString();
        }

        $statusOptions = $this->quotesAvailable() ? \App\Models\CustomerQuote::statusOptions() : [];

        return view('client_portal.quotes.index', compact('quotes', 'search', 'status', 'statusOptions'));
    }

    public function show(Request $request, int $quote): View
    {
        $quote = $this->findQuoteForClient($request, $quote);
        $quote->load(['items.product.media', 'items.product.priceLadders']);

        $comments = ClientPortalComment::where('client_id', $this->client($request)->id)
            ->where('related_type', 'quote')
            ->where('related_id', $quote->id)
            ->where('is_public_to_client', true)
            ->with('portalUser', 'internalUser')
            ->latest('id')
            ->get();

        return view('client_portal.quotes.show', compact('quote', 'comments'));
    }

    public function storeComment(Request $request, int $quote): RedirectResponse
    {
        $quote = $this->findQuoteForClient($request, $quote);
        $portalUser = $this->portalUser($request);
        $data = $request->validate(['body' => ['required', 'string']]);

        ClientPortalComment::create([
            'client_id' => $portalUser->client_id,
            'client_portal_user_id' => $portalUser->id,
            'related_type' => 'quote',
            'related_id' => $quote->id,
            'author_type' => 'client',
            'body' => $data['body'],
            'is_public_to_client' => true,
        ]);

        return back()->with('success', 'Comment submitted successfully.');
    }

    private function findQuoteForClient(Request $request, int $quoteId)
    {
        abort_unless($this->quotesAvailable(), 404);

        return \App\Models\CustomerQuote::where('client_id', $this->client($request)->id)
            ->where('status', '!=', 'draft')
            ->when(Schema::hasColumn('customer_quotes', 'show_client_portal'), function ($query) {
                $query->where('show_client_portal', true);
            })
            ->whereKey($quoteId)
            ->firstOrFail();
    }
}
