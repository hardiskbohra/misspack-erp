<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $type = $request->query('type', 'all');
        $status = $request->query('status', 'all');
        $clientId = $request->query('client_id', 'all');
        $projectId = $request->query('project_id', 'all');

        $with = ['items', 'creator'];
        if ($this->clientAvailable()) { $with[] = 'client'; }
        if ($this->projectAvailable()) { $with[] = 'project'; }

        $invoices = SalesInvoice::query()
            ->with($with)
            ->search($search)
            ->when($type !== 'all', function ($query) use ($type) { $query->where('invoice_type', $type); })
            ->when($status !== 'all', function ($query) use ($status) { $query->where('status', $status); })
            ->when($clientId !== 'all', function ($query) use ($clientId) { $query->where('client_id', $clientId); })
            ->when($projectId !== 'all', function ($query) use ($projectId) { $query->where('project_id', $projectId); })
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => SalesInvoice::where('status', '!=', 'draft')->count(),
            'proforma' => SalesInvoice::where('status', '!=', 'draft')->where('invoice_type', 'proforma')->count(),
            'tax' => SalesInvoice::where('status', '!=', 'draft')->where('invoice_type', 'tax')->count(),
            'sent' => (float) SalesInvoice::where('status', '!=', 'draft')
                ->with('payments')
                ->get()
                ->sum(function ($invoice) {
                    return (float) $invoice->total_amount;
                }),
            'paid' => (float) SalesInvoice::where('status', '!=', 'draft')
                ->with('payments')
                ->get()
                ->sum(function ($invoice) {
                    $paidAmount = $invoice->payments->sum('credit_amount')
                        - $invoice->payments->sum('debit_amount');
            
                    return (float) $paidAmount;
                }),
            'outstanding' => (float) SalesInvoice::where('status', '!=', 'draft')
                ->with('payments')
                ->get()
                ->sum(function ($invoice) {
                    $paidAmount = $invoice->payments->sum('credit_amount')
                        - $invoice->payments->sum('debit_amount');
            
                    return (float) $invoice->total_amount - (float) $paidAmount;
                }),
        ];

        return view('sales_invoices.index', array_merge($this->sharedData(), compact('invoices', 'stats', 'search', 'type', 'status', 'clientId', 'projectId')));
    }

    public function create(Request $request): View
    {
        $invoiceType = $request->query('type', 'proforma');
        if (! in_array($invoiceType, ['proforma', 'tax'], true)) {
            $invoiceType = 'proforma';
        }

        $client = $this->clientFromRequest($request);
        $project = $this->projectFromRequest($request);
        $quote = $this->quoteFromRequest($request);

        if (! $client && $project && isset($project->client_id)) {
            $client = $this->clientById($project->client_id);
        }
        if (! $client && $quote && isset($quote->client_id)) {
            $client = $this->clientById($quote->client_id);
        }

        $invoice = new SalesInvoice(array_merge(SalesInvoice::defaultSellerDetails(), [
            'invoice_number' => $this->makeInvoiceNumber($invoiceType),
            'invoice_type' => $invoiceType,
            'status' => 'draft',
            'client_id' => $client ? $client->id : null,
            'project_id' => $project ? $project->id : null,
            'customer_quote_id' => $quote ? $quote->id : null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'valid_until' => now()->addDays(10)->toDateString(),
            'currency' => $client ? ($client->preferred_currency ?? 'INR') : 'INR',
            'gst_type' => 'intra_state',
            'payment_terms' => '50% advance, balance before dispatch',
            'delivery_terms' => 'As mutually discussed',
            'dispatch_terms' => 'Dispatch after payment and approval confirmation',
            'terms_conditions' => SalesInvoice::defaultTerms(),
            'show_client_portal' => false,
        ]));

        if ($client) {
            $invoice->fill($this->clientSnapshot($client));
        }

        $items = $this->itemsFromSource($project, $quote);
        $invoice->setRelation('items', collect($items));
        $invoice->setRelation('attachments', collect());

        return view('sales_invoices.form', array_merge($this->sharedData(), compact('invoice')));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $items = $data['items'] ?? [];
        unset($data['items'], $data['attachments']);

        $data = $this->prepareInvoiceData($request, $data);
        $data['invoice_number'] = $data['invoice_number'] ?: $this->makeInvoiceNumber($data['invoice_type']);
        $data['created_by'] = Auth::id();

        $invoice = DB::transaction(function () use ($request, $data, $items) {
            $invoice = SalesInvoice::create($data);
            $this->syncItemsAndTotals($invoice, $items);
            $this->storeAttachments($request, $invoice);
            return $invoice;
        });

        return redirect()->route('sales-invoices.show', $invoice)->with('success', 'Sales invoice created successfully.');
    }

    public function show(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items.product', 'attachments', 'creator']);
        if ($this->clientAvailable()) { $salesInvoice->load('client'); }
        if ($this->projectAvailable()) { $salesInvoice->load('project'); }

        return view('sales_invoices.show', ['invoice' => $salesInvoice]);
    }

    public function edit(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items', 'attachments']);
        return view('sales_invoices.form', array_merge($this->sharedData(), ['invoice' => $salesInvoice]));
    }

    public function update(Request $request, SalesInvoice $salesInvoice): RedirectResponse
    {
        $data = $this->validatedData($request, $salesInvoice);
        $items = $data['items'] ?? [];
        unset($data['items'], $data['attachments']);
        $data = $this->prepareInvoiceData($request, $data);

        DB::transaction(function () use ($request, $salesInvoice, $data, $items) {
            $salesInvoice->update($data);
            $this->syncItemsAndTotals($salesInvoice, $items);
            $this->storeAttachments($request, $salesInvoice);
        });

        return redirect()->route('sales-invoices.show', $salesInvoice)->with('success', 'Sales invoice updated successfully.');
    }

    public function destroy(SalesInvoice $salesInvoice): RedirectResponse
    {
        foreach ($salesInvoice->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }
        $salesInvoice->delete();

        return redirect()->route('sales-invoices.index')->with('success', 'Sales invoice deleted successfully.');
    }

    public function print(SalesInvoice $salesInvoice): View
    {
        $salesInvoice->load(['items', 'attachments']);
        return view('sales_invoices.print', ['invoice' => $salesInvoice, 'publicMode' => false]);
    }

    public function publicShow(string $token): View
    {
        $invoice = SalesInvoice::where('public_token', $token)->where('show_client_portal', true)->with(['items', 'publicAttachments'])->firstOrFail();
        return view('sales_invoices.print', ['invoice' => $invoice, 'publicMode' => true]);
    }

    public function markSent(SalesInvoice $salesInvoice): RedirectResponse
    {
        $salesInvoice->update(['status' => 'sent', 'sent_at' => now(), 'show_client_portal' => true]);
        return back()->with('success', 'Invoice marked as sent and visible to client portal.');
    }

    public function destroyAttachment(SalesInvoiceAttachment $attachment): RedirectResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }
        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    private function validatedData(Request $request, ?SalesInvoice $invoice = null): array
    {
        return $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:sales_invoices,invoice_number,'.($invoice ? $invoice->id : 'NULL')],
            'invoice_type' => ['required', 'in:proforma,tax'],
            'status' => ['required', Rule::in(array_keys(SalesInvoice::statusOptions()))],
            'client_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'customer_quote_id' => ['nullable', 'integer'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'currency' => ['required', 'in:INR,USD,RMB'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'gst_type' => ['required', 'in:intra_state,inter_state,export'],
            'place_of_supply' => ['nullable', 'string', 'max:255'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'po_date' => ['nullable', 'date'],

            'seller_company_name' => ['nullable', 'string', 'max:255'],
            'seller_address' => ['nullable', 'string'],
            'seller_city' => ['nullable', 'string', 'max:255'],
            'seller_state' => ['nullable', 'string', 'max:255'],
            'seller_country' => ['nullable', 'string', 'max:255'],
            'seller_pincode' => ['nullable', 'string', 'max:30'],
            'seller_gstin' => ['nullable', 'string', 'max:30'],
            'seller_pan' => ['nullable', 'string', 'max:20'],
            'seller_email' => ['nullable', 'string', 'max:255'],
            'seller_mobile' => ['nullable', 'string', 'max:40'],
            'seller_website' => ['nullable', 'string', 'max:255'],
            'seller_bank_name' => ['nullable', 'string', 'max:255'],
            'seller_account_holder' => ['nullable', 'string', 'max:255'],
            'seller_account_number' => ['nullable', 'string', 'max:255'],
            'seller_ifsc' => ['nullable', 'string', 'max:255'],
            'seller_branch' => ['nullable', 'string', 'max:255'],
            'seller_swift' => ['nullable', 'string', 'max:255'],

            'client_company_name' => ['nullable', 'string', 'max:255'],
            'client_brand_name' => ['nullable', 'string', 'max:255'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'string', 'max:255'],
            'client_mobile' => ['nullable', 'string', 'max:40'],
            'client_gstin' => ['nullable', 'string', 'max:30'],
            'client_pan' => ['nullable', 'string', 'max:20'],
            'billing_address' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:255'],
            'billing_state' => ['nullable', 'string', 'max:255'],
            'billing_country' => ['nullable', 'string', 'max:255'],
            'billing_pincode' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:255'],
            'shipping_pincode' => ['nullable', 'string', 'max:30'],

            'payment_terms' => ['nullable', 'string', 'max:255'],
            'delivery_terms' => ['nullable', 'string', 'max:255'],
            'dispatch_terms' => ['nullable', 'string', 'max:255'],
            'transport_mode' => ['nullable', 'string', 'max:255'],
            'sales_person' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'packing_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'terms_conditions' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'show_client_portal' => ['nullable', 'boolean'],

            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.project_product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
        ]);
    }

    private function prepareInvoiceData(Request $request, array $data): array
    {
        $data = array_merge(SalesInvoice::defaultSellerDetails(), $data);
        $data['discount_type'] = $data['discount_type'] ?? 'amount';
        $data['discount_value'] = $data['discount_value'] ?? 0;
        $data['freight_amount'] = $data['freight_amount'] ?? 0;
        $data['packing_amount'] = $data['packing_amount'] ?? 0;
        $data['other_charges'] = $data['other_charges'] ?? 0;
        $data['round_off'] = $data['round_off'] ?? 0;
        $data['amount_paid'] = $data['amount_paid'] ?? 0;
        $data['terms_conditions'] = $data['terms_conditions'] ?: SalesInvoice::defaultTerms();
        $data['show_client_portal'] = $request->boolean('show_client_portal');

        if (! empty($data['client_id'])) {
            $client = $this->clientById($data['client_id']);
            if ($client) {
                $data = array_merge($data, array_filter($this->clientSnapshot($client), function ($value) {
                    return $value !== null && $value !== '';
                }));
            }
        }

        if ($data['status'] === 'sent' && empty($data['sent_at'])) {
            $data['sent_at'] = now();
        }
        if ($data['status'] === 'accepted' && empty($data['accepted_at'])) {
            $data['accepted_at'] = now();
        }
        if ($data['status'] === 'cancelled' && empty($data['cancelled_at'])) {
            $data['cancelled_at'] = now();
        }

        return $data;
    }

    private function syncItemsAndTotals(SalesInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $subtotal = 0;
        $taxableTotal = 0;
        $cgstTotal = 0;
        $sgstTotal = 0;
        $igstTotal = 0;
        $sort = 1;

        foreach ($items as $item) {
            if (blank($item['product_name'] ?? null) && blank($item['product_id'] ?? null)) {
                continue;
            }

            $productName = $item['product_name'] ?: $this->productName($item['product_id'] ?? null);
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $gross = round($qty * $unitPrice, 2);
            $discountPercent = (float) ($item['discount_percent'] ?? 0);
            $discountAmount = round($gross * ($discountPercent / 100), 2);
            $taxable = max($gross - $discountAmount, 0);
            $gstPercent = (float) ($item['gst_percent'] ?? 18);
            $cgst = 0;
            $sgst = 0;
            $igst = 0;

            if ($invoice->gst_type === 'inter_state') {
                $igst = round($taxable * ($gstPercent / 100), 2);
            } elseif ($invoice->gst_type === 'intra_state') {
                $cgst = round($taxable * (($gstPercent / 2) / 100), 2);
                $sgst = round($taxable * (($gstPercent / 2) / 100), 2);
            }

            $lineTotal = round($taxable + $cgst + $sgst + $igst, 2);

            $invoice->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'project_product_id' => $item['project_product_id'] ?? null,
                'product_name' => $productName ?: 'Product',
                'description' => $item['description'] ?? null,
                'hsn_sac' => $item['hsn_sac'] ?? null,
                'quantity' => $qty ?: 1,
                'unit' => $item['unit'] ?? 'pcs',
                'unit_price' => $unitPrice,
                'gross_amount' => $gross,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxable,
                'gst_percent' => $gstPercent,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'line_total' => $lineTotal,
                'sort_order' => $sort,
                'remarks' => $item['remarks'] ?? null,
            ]);

            $subtotal += $gross;
            $taxableTotal += $taxable;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;
            $sort++;
        }

        $invoiceDiscountValue = (float) ($invoice->discount_value ?? 0);
        $invoiceDiscount = $invoice->discount_type === 'percent'
            ? round($taxableTotal * ($invoiceDiscountValue / 100), 2)
            : min($invoiceDiscountValue, $taxableTotal);

        $taxableAfterInvoiceDiscount = max($taxableTotal - $invoiceDiscount, 0);
        $taxAdjustmentRatio = $taxableTotal > 0 ? ($taxableAfterInvoiceDiscount / $taxableTotal) : 1;
        $cgstTotal = round($cgstTotal * $taxAdjustmentRatio, 2);
        $sgstTotal = round($sgstTotal * $taxAdjustmentRatio, 2);
        $igstTotal = round($igstTotal * $taxAdjustmentRatio, 2);

        $charges = (float) $invoice->freight_amount + (float) $invoice->packing_amount + (float) $invoice->other_charges;
        $total = round($taxableAfterInvoiceDiscount + $cgstTotal + $sgstTotal + $igstTotal + $charges + (float) $invoice->round_off, 2);
        $balance = max($total - (float) $invoice->amount_paid, 0);

        $invoice->update([
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($invoiceDiscount, 2),
            'taxable_amount' => round($taxableAfterInvoiceDiscount, 2),
            'cgst_amount' => $cgstTotal,
            'sgst_amount' => $sgstTotal,
            'igst_amount' => $igstTotal,
            'total_amount' => $total,
            'balance_amount' => $balance,
            'amount_in_words' => $this->amountInWords($total, $invoice->currency),
        ]);
    }

    private function storeAttachments(Request $request, SalesInvoice $invoice): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('sales-invoices/'.$invoice->id, 'public');
            $invoice->attachments()->create([
                'title' => $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'is_public' => true,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function clientSnapshot($client): array
    {
        return [
            'client_company_name' => $client->company_name ?? null,
            'client_brand_name' => $client->brand_name ?? null,
            'client_contact_name' => $client->account_person_name ?: ($client->ceo_name ?? null),
            'client_email' => $client->account_person_email ?: ($client->ceo_email ?? null),
            'client_mobile' => $client->account_person_contact ?: ($client->ceo_contact ?? null),
            'client_gstin' => $client->gstin ?? null,
            'client_pan' => $client->pan ?? null,
            'billing_address' => $client->billing_address ?? null,
            'billing_city' => $client->billing_city ?? null,
            'billing_state' => $client->billing_state ?? null,
            'billing_country' => $client->billing_country ?? null,
            'billing_pincode' => $client->billing_pincode ?? null,
            'shipping_address' => $client->shipping_address ?: ($client->billing_address ?? null),
            'shipping_city' => $client->shipping_city ?: ($client->billing_city ?? null),
            'shipping_state' => $client->shipping_state ?: ($client->billing_state ?? null),
            'shipping_country' => $client->shipping_country ?: ($client->billing_country ?? null),
            'shipping_pincode' => $client->shipping_pincode ?: ($client->billing_pincode ?? null),
            'place_of_supply' => $client->billing_state ?? null,
        ];
    }

    private function itemsFromSource($project = null, $quote = null): array
    {
        $items = [];

        if ($project && method_exists($project, 'products')) {
            $project->load('products');
            foreach ($project->products as $row) {
                $items[] = [
                    'project_product_id' => $row->id,
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'description' => $row->notes,
                    'quantity' => $row->quantity ?: 1,
                    'unit' => $row->unit ?: 'pcs',
                    'unit_price' => $row->unit_price ?: 0,
                    'gst_percent' => 18,
                    'discount_percent' => 0,
                ];
            }
        }

        if (! $items && $quote && method_exists($quote, 'items')) {
            $quote->load('items');
            foreach ($quote->items as $row) {
                $items[] = [
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'description' => $row->description,
                    'quantity' => $row->quantity ?: 1,
                    'unit' => $row->unit ?: 'pcs',
                    'unit_price' => $row->unit_price ?: 0,
                    'gst_percent' => 18,
                    'discount_percent' => 0,
                ];
            }
        }

        if (! $items) {
            $items[] = ['product_name' => '', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => 0, 'gst_percent' => 18, 'discount_percent' => 0];
        }

        return $items;
    }

    private function productName($productId): ?string
    {
        if (! $productId || ! $this->productAvailable()) {
            return null;
        }

        $product = \App\Models\Product::find($productId);
        return $product ? $product->name : null;
    }

    private function makeInvoiceNumber(string $type): string
    {
        $financialYear = now()->month >= 4
            ? now()->format('y') . '-' . now()->addYear()->format('y')
            : now()->subYear()->format('y') . '-' . now()->format('y');
            
        $prefix = ($type === 'tax' ? "MP/INV/{$financialYear}/" : "MP/PI/{$financialYear}/");
        $next = str_pad((string) (SalesInvoice::where('invoice_type', $type)->whereYear('created_at', now()->year)->count() + 1), 3, '0', STR_PAD_LEFT);
        
        $lastInvoice = SalesInvoice::where('invoice_type', $type)
            ->whereYear('created_at', now()->year)
            ->latest('id')
            ->value('invoice_number');
        
        $lastNumber = $lastInvoice ? (int) last(explode('/', $lastInvoice)) : 0;
        $next = str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
        
        $number = $prefix.$next;

        while (SalesInvoice::where('invoice_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 3, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function amountInWords(float $amount, string $currency): string
    {
        $number = (int) floor($amount);
        $paise = (int) round(($amount - $number) * 100);

        $words = $this->numberToWords($number);
        $currencyLabel = $currency === 'INR' ? 'Rupees' : $currency;

        $text = $currencyLabel.' '.$words;
        if ($paise > 0) {
            $text .= ' and '.$this->numberToWords($paise).' Paise';
        }

        return $text.' Only';
    }

    private function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $convertBelowHundred = function ($n) use ($ones, $tens) {
            if ($n < 20) {
                return $ones[$n];
            }
            return trim($tens[(int) floor($n / 10)].' '.$ones[$n % 10]);
        };

        $convertBelowThousand = function ($n) use ($ones, $convertBelowHundred) {
            $words = '';
            if ($n >= 100) {
                $words .= $ones[(int) floor($n / 100)].' Hundred ';
                $n = $n % 100;
            }
            if ($n > 0) {
                $words .= $convertBelowHundred($n);
            }
            return trim($words);
        };

        $parts = [];
        $crore = (int) floor($number / 10000000);
        if ($crore) {
            $parts[] = $convertBelowThousand($crore).' Crore';
            $number %= 10000000;
        }
        $lakh = (int) floor($number / 100000);
        if ($lakh) {
            $parts[] = $convertBelowThousand($lakh).' Lakh';
            $number %= 100000;
        }
        $thousand = (int) floor($number / 1000);
        if ($thousand) {
            $parts[] = $convertBelowThousand($thousand).' Thousand';
            $number %= 1000;
        }
        if ($number) {
            $parts[] = $convertBelowThousand($number);
        }

        return implode(' ', array_filter($parts));
    }

    private function sharedData(): array
    {
        return [
            'clients' => $this->clients(),
            'projects' => $this->projects(),
            'products' => $this->products(),
            'quotes' => $this->quotes(),
            'typeOptions' => SalesInvoice::typeOptions(),
            'statusOptions' => SalesInvoice::statusOptions(),
            'currencyOptions' => SalesInvoice::currencyOptions(),
            'gstTypeOptions' => SalesInvoice::gstTypeOptions(),
            'sellerDefaults' => SalesInvoice::defaultSellerDetails(),
            'defaultTerms' => SalesInvoice::defaultTerms(),
            'routes' => [
                'clients' => Route::has('clients.index') ? route('clients.index') : '#',
                'projects' => Route::has('projects.index') ? route('projects.index') : '#',
                'products' => Route::has('products.index') ? route('products.index') : '#',
            ],
        ];
    }

    private function clients()
    {
        return $this->clientAvailable() ? \App\Models\Client::query()->orderBy('company_name')->get() : collect();
    }

    private function projects()
    {
        return $this->projectAvailable() ? \App\Models\Project::query()->latest('id')->get() : collect();
    }

    private function products()
    {
        return $this->productAvailable() ? \App\Models\Product::query()->where('status', 'active')->orderBy('name')->get() : collect();
    }

    private function quotes()
    {
        return $this->quoteAvailable() ? \App\Models\CustomerQuote::query()->whereIn('status', ['sent', 'accepted', 'revised'])->latest('id')->get() : collect();
    }

    private function clientFromRequest(Request $request)
    {
        return $request->query('client_id') ? $this->clientById($request->query('client_id')) : null;
    }

    private function clientById($id)
    {
        return ($id && $this->clientAvailable()) ? \App\Models\Client::find($id) : null;
    }

    private function projectFromRequest(Request $request)
    {
        return ($request->query('project_id') && $this->projectAvailable()) ? \App\Models\Project::find($request->query('project_id')) : null;
    }

    private function quoteFromRequest(Request $request)
    {
        return ($request->query('customer_quote_id') && $this->quoteAvailable()) ? \App\Models\CustomerQuote::find($request->query('customer_quote_id')) : null;
    }

    private function clientAvailable(): bool
    {
        return class_exists(\App\Models\Client::class) && Schema::hasTable('clients');
    }

    private function projectAvailable(): bool
    {
        return class_exists(\App\Models\Project::class) && Schema::hasTable('projects');
    }

    private function productAvailable(): bool
    {
        return class_exists(\App\Models\Product::class) && Schema::hasTable('products');
    }

    private function quoteAvailable(): bool
    {
        return class_exists(\App\Models\CustomerQuote::class) && Schema::hasTable('customer_quotes');
    }
}
