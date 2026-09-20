<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LeadQuoteController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $leadId = $request->query('lead_id', 'all');
        $currency = $request->query('currency', 'all');

        $quotes = LeadQuote::query()
            ->with(['lead', 'items', 'creator'])
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
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => LeadQuote::count(),
            'draft' => LeadQuote::where('status', 'draft')->count(),
            'sent' => LeadQuote::where('status', 'sent')->count(),
            'accepted' => LeadQuote::where('status', 'accepted')->count(),
            'rejected' => LeadQuote::where('status', 'rejected')->count(),
        ];

        return view('lead_quotes.index', array_merge($this->sharedData(), compact('quotes', 'stats', 'search', 'status', 'leadId', 'currency')));
    }

    public function create(Request $request): View
    {
        $lead = null;
        if ($request->query('lead_id')) {
            $lead = Lead::find($request->query('lead_id'));
        }

        $quote = new LeadQuote([
            'quote_number' => $this->makeQuoteNumber(),
            'lead_id' => $lead ? $lead->id : null,
            'client_id' => $lead ? $lead->client_id : null,
            'customer_company_name' => $lead ? $lead->client_company_name : null,
            'customer_contact_name' => $lead ? $lead->client_contact_name : null,
            'customer_email' => $lead ? $lead->client_email : null,
            'customer_mobile' => $lead ? $lead->client_mobile : null,
            'title' => $lead ? 'Quote for '.$lead->title : 'Customer Quote',
            'status' => 'draft',
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'currency' => 'INR',
            'discount_type' => 'amount',
            'tax_percent' => 18,
            'shipping_amount' => 0,
        ]);

        $quote->setRelation('items', collect());

        return view('lead_quotes.form', array_merge($this->sharedData(), compact('quote', 'lead')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $items = $data['items'] ?? [];
        unset($data['items']);
        $data['quote_number'] = $data['quote_number'] ?: $this->makeQuoteNumber();
        $data['created_by'] = Auth::id();

        $quote = DB::transaction(function () use ($data, $items) {
            $quote = LeadQuote::create($data);
            $this->syncItemsAndTotals($quote, $items);
            return $quote;
        });

        return redirect()->route('lead-quotes.show', $quote)->with('success', 'Customer quote created successfully.');
    }

    public function show(LeadQuote $leadQuote): View
    {
        $with = ['lead', 'items', 'creator'];
        if ($this->clientModelAvailable()) {
            $with[] = 'client';
        }
        $leadQuote->load($with);

        return view('lead_quotes.show', array_merge($this->sharedData(), ['quote' => $leadQuote]));
    }

    public function edit(LeadQuote $leadQuote): View
    {
        $leadQuote->load('items');
        $lead = $leadQuote->lead;

        return view('lead_quotes.form', array_merge($this->sharedData(), ['quote' => $leadQuote, 'lead' => $lead]));
    }

    public function update(Request $request, LeadQuote $leadQuote): RedirectResponse
    {
        $data = $this->validatedData($request, $leadQuote);
        $items = $data['items'] ?? [];
        unset($data['items']);

        DB::transaction(function () use ($leadQuote, $data, $items) {
            $leadQuote->update($data);
            $this->syncItemsAndTotals($leadQuote, $items);
        });

        return redirect()->route('lead-quotes.show', $leadQuote)->with('success', 'Customer quote updated successfully.');
    }

    public function destroy(LeadQuote $leadQuote): RedirectResponse
    {
        $leadQuote->delete();

        return redirect()->route('lead-quotes.index')->with('success', 'Customer quote deleted successfully.');
    }

    public function updateStatus(Request $request, LeadQuote $leadQuote): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,sent,accepted,rejected,revised,expired'],
        ]);

        $leadQuote->update(['status' => $data['status']]);

        return back()->with('success', 'Quote status updated successfully.');
    }

    private function validatedData(Request $request, ?LeadQuote $quote = null): array
    {
        return $request->validate([
            'quote_number' => ['nullable', 'string', 'max:255', 'unique:lead_quotes,quote_number,'.($quote ? $quote->id : 'NULL')],
            'lead_id' => ['nullable', 'exists:leads,id'],
            'client_id' => ['nullable', 'integer'],
            'customer_company_name' => ['nullable', 'string', 'max:255'],
            'customer_contact_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_mobile' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:draft,sent,accepted,rejected,revised,expired'],
            'quote_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['required', 'in:INR,USD,RMB'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['required', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.capacity' => ['nullable', 'string', 'max:255'],
            'items.*.finish_type' => ['nullable', 'string', 'max:255'],
            'items.*.printing_type' => ['nullable', 'string', 'max:255'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);
    }

    private function syncItemsAndTotals(LeadQuote $quote, array $items): void
    {
        $quote->items()->delete();
        $subtotal = 0;

        foreach ($items as $item) {
            if (blank($item['product_name'] ?? null) && blank($item['unit_price'] ?? null)) {
                continue;
            }

            $qty = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $amount = round($qty * $unitPrice, 2);
            $subtotal += $amount;

            $quote->items()->create([
                'product_name' => $item['product_name'] ?? 'Product',
                'description' => $item['description'] ?? null,
                'quantity' => $qty ?: 1,
                'unit' => $item['unit'] ?? 'pcs',
                'capacity' => $item['capacity'] ?? null,
                'finish_type' => $item['finish_type'] ?? null,
                'printing_type' => $item['printing_type'] ?? null,
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }

        $discountValue = (float) ($quote->discount_value ?? 0);
        $discountAmount = $quote->discount_type === 'percent'
            ? round($subtotal * ($discountValue / 100), 2)
            : min($discountValue, $subtotal);

        $taxable = max($subtotal - $discountAmount, 0);
        $taxAmount = round($taxable * (((float) ($quote->tax_percent ?? 0)) / 100), 2);
        $shipping = (float) ($quote->shipping_amount ?? 0);
        $total = round($taxable + $taxAmount + $shipping, 2);

        $quote->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
        ]);
    }

    private function makeQuoteNumber(): string
    {
        $prefix = 'SQ-'.now()->format('ymd').'-';
        $next = str_pad((string) (LeadQuote::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (LeadQuote::where('quote_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function sharedData(): array
    {
        return [
            'leads' => Lead::query()->latest('id')->get(),
            'clients' => $this->clients(),
            'statusOptions' => LeadQuote::statusOptions(),
            'currencyOptions' => LeadQuote::currencyOptions(),
        ];
    }

    private function clients()
    {
        if (! $this->clientModelAvailable()) {
            return collect();
        }

        return \App\Models\Client::query()->orderBy('company_name')->get();
    }

    private function clientModelAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }
}
