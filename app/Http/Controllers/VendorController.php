<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorAttachment;
use App\Models\VendorComment;
use App\Models\VendorPaymentAttachment;
use App\Models\VendorPaymentEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $country = $request->query('country', 'all');

        $vendors = Vendor::query()
            ->with('creator')
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('vendor_type', $type))
            ->when($country !== 'all', fn ($q) => $q->where('country', $country))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stats = [
            'total' => Vendor::count(),
            'active' => Vendor::where('status', Vendor::STATUS_ACTIVE)->count(),
            'on_hold' => Vendor::where('status', Vendor::STATUS_ON_HOLD)->count(),
            'blacklisted' => Vendor::where('status', Vendor::STATUS_BLACKLISTED)->count(),
            'international' => Vendor::whereNotNull('country')->where('country', '!=', 'India')->count(),
        ];

        $countries = Vendor::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return view('vendors.index', [
            'vendors' => $vendors,
            'stats' => $stats,
            'countries' => $countries,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'country' => $country,
            'statusOptions' => Vendor::statusOptions(),
            'typeOptions' => Vendor::typeOptions(),
            'currencyOptions' => Vendor::currencyOptions(),
        ]);
    }

    public function create(): View
    {
        $vendor = new Vendor([
            'vendor_number' => $this->makeVendorNumber(),
            'vendor_type' => 'manufacturer',
            'status' => Vendor::STATUS_ACTIVE,
            'preferred_currency' => 'INR',
            'country' => 'India',
            'rating' => 3,
        ]);

        return view('vendors.form', $this->formData($vendor));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        unset($data['vendor_image']);

        $data['vendor_number'] = $data['vendor_number'] ?: $this->makeVendorNumber();
        $data['created_by'] = Auth::id();

        if ($request->hasFile('vendor_image')) {
            $data['image_path'] = $request->file('vendor_image')->store('vendors', 'public');
        }

        $vendor = Vendor::create($data);

        return redirect()
            ->route('vendors.show', $vendor)
            ->with('success', 'Vendor created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vendor_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'vendor_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'vendor_type' => ['required', 'in:manufacturer,trader,distributor,service_provider'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive,on_hold,blacklisted'],
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],
            'contact_person_mobile' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:255'],
            'alibaba_link' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'preferred_currency' => ['nullable', 'in:INR,USD,RMB'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        unset($data['vendor_image']);
        $data['vendor_number'] = $this->makeVendorNumber();
        $data['created_by'] = Auth::id();
        $data['preferred_currency'] = $data['preferred_currency'] ?? 'INR';

        if ($request->hasFile('vendor_image')) {
            $data['image_path'] = $request->file('vendor_image')->store('vendors', 'public');
        }

        $vendor = Vendor::create($data);

        return redirect()
            ->route('vendors.show', $vendor)
            ->with('success', 'Quick vendor created successfully.');
    }

    public function show(Vendor $vendor): View
    {
        $relations = ['creator'];
        if (Schema::hasTable('vendor_comments')) {
            $relations[] = 'comments.creator';
        }
        if (Schema::hasTable('vendor_attachments')) {
            $relations[] = 'attachments.uploader';
        }
        $vendor->load($relations);

        return view('vendors.show', array_merge($this->formData($vendor), $this->dashboardData($vendor)));
    }

    public function edit(Vendor $vendor): View
    {
        return view('vendors.form', $this->formData($vendor));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $this->validatedData($request, $vendor);
        unset($data['vendor_image']);

        if ($request->hasFile('vendor_image')) {
            if ($vendor->image_path) {
                Storage::disk('public')->delete($vendor->image_path);
            }
            $data['image_path'] = $request->file('vendor_image')->store('vendors', 'public');
        }

        $vendor->update($data);

        return redirect()
            ->route('vendors.show', $vendor)
            ->with('success', 'Vendor updated successfully.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        if ($vendor->image_path) {
            Storage::disk('public')->delete($vendor->image_path);
        }

        $vendor->delete();

        return redirect()
            ->route('vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }

    public function storeComment(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable('vendor_comments'), 404);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        VendorComment::create([
            'vendor_id' => $vendor->id,
            'body' => $data['body'],
            'is_pinned' => $request->boolean('is_pinned'),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Vendor comment added successfully.');
    }

    public function destroyComment(VendorComment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('success', 'Vendor comment deleted successfully.');
    }

    public function storeAttachment(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable('vendor_attachments'), 404);

        $data = $request->validate([
            'category' => ['required', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:20480'],
        ]);

        foreach ((array) $request->file('attachments', []) as $file) {
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('vendor-documents/'.$vendor->id, 'public');

            VendorAttachment::create([
                'vendor_id' => $vendor->id,
                'category' => $data['category'],
                'title' => $data['title'] ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Vendor document uploaded successfully.');
    }

    public function destroyAttachment(VendorAttachment $attachment): RedirectResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return back()->with('success', 'Vendor document deleted successfully.');
    }

    public function storePayment(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable('vendor_payment_entries'), 404);

        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'foreign_amount' => ['required', 'numeric', 'min:0'],
            'foreign_currency' => ['required', 'in:RMB,USD,INR'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'particular' => ['required', 'string'],
            'status' => ['required', 'in:pending,booked,paid,reconciled,cancelled'],
            'transaction_type' => ['required', 'in:credit,debit'],
            'entry_category' => ['required', 'in:bill,payment,expense,adjustment,refund'],
            'project_id' => ['nullable', 'integer'],
            'paid_account_id' => ['nullable', 'integer'],
            'payment_mode' => ['nullable', 'string', 'max:40'],
            'bank_reference_number' => ['nullable', 'string', 'max:255'],
            'amount_in_inr' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'also_create_cashflow' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
        ]);

        $data['vendor_id'] = $vendor->id;
        $data['created_by'] = Auth::id();
        $data['amount_in_inr'] = $this->normalizeInrAmount($data);
        unset($data['attachments'], $data['also_create_cashflow']);

        $entry = DB::transaction(function () use ($request, $data, $vendor) {
            $entry = VendorPaymentEntry::create($data);

            if ($request->boolean('also_create_cashflow')) {
                $cashflowId = $this->createCashflowFromVendorPayment($entry, $vendor);
                if ($cashflowId) {
                    $entry->update(['cashflow_entry_id' => $cashflowId]);
                }
            }

            $this->storeVendorPaymentAttachments($request, $entry);

            return $entry;
        });

        return back()->with('success', 'Vendor payment/statement entry added successfully.');
    }
    
    public function updatePayment(Request $request, Vendor $vendor, VendorPaymentEntry $entry): RedirectResponse {
    
        // Make sure the entry actually belongs to this vendor
        abort_unless((int) $entry->vendor_id === (int) $vendor->id,404);
    
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'invoice_number' => ['nullable','string','max:255'],
            'foreign_amount' => ['required','numeric','min:0'],
            'foreign_currency' => ['required','in:RMB,USD,INR'],
            'exchange_rate' => ['nullable','numeric','min:0'],
            'particular' => ['required','string'],
            'status' => ['required','in:pending,booked,paid,reconciled,cancelled'],
            'transaction_type' => ['required','in:credit,debit'],
            'entry_category' => ['required','in:bill,payment,expense,adjustment,refund'],
            'project_id' => ['nullable','integer'],
            'paid_account_id' => ['nullable','integer'],
            'payment_mode' => ['nullable','string','max:40'],
            'bank_reference_number' => ['nullable','string','max:255'],
            'amount_in_inr' => ['nullable','numeric','min:0'],
            'remarks' => ['nullable','string'],
            'also_create_cashflow' => ['nullable','boolean'],
            'attachments' => ['nullable','array'],
            'attachments.*' => ['nullable','file','max:20480'],
        ]);
    
        $data['amount_in_inr'] = $this->normalizeInrAmount($data);
    
        // These are not columns in vendor_payment_entries
        unset($data['attachments'],$data['also_create_cashflow']);
    
        DB::transaction(function () use ($request,$data,$vendor,$entry) {
            $entry->update($data);
            $this->storeVendorPaymentAttachments($request,$entry);
        });
    
        return back()->with(
            'success',
            'Vendor payment/statement entry updated successfully.'
        );
    }

    public function destroyPayment(VendorPaymentEntry $entry): RedirectResponse
    {
        foreach ($entry->attachments as $attachment) {
            if ($attachment->file_path) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        $entry->delete();

        return back()->with('success', 'Vendor payment entry deleted successfully.');
    }

    public function destroyPaymentAttachment(VendorPaymentAttachment $attachment): RedirectResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully.');
    }

    private function normalizeInrAmount(array $data): float
    {
        $amountInInr = (float) ($data['amount_in_inr'] ?? 0);
        $foreignAmount = (float) ($data['foreign_amount'] ?? 0);
        $exchangeRate = (float) ($data['exchange_rate'] ?? 0);

        if ($amountInInr <= 0 && ($data['foreign_currency'] ?? null) === 'INR' && $foreignAmount > 0) {
            return round($foreignAmount, 2);
        }

        if ($amountInInr <= 0 && $foreignAmount > 0 && $exchangeRate > 0) {
            return round($foreignAmount * $exchangeRate, 2);
        }

        return round($amountInInr, 2);
    }

    private function storeVendorPaymentAttachments(Request $request, VendorPaymentEntry $entry): void
    {
        foreach ((array) $request->file('attachments', []) as $file) {
            if (! $file) {
                continue;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('vendor-payments/'.$entry->vendor_id, 'public');

            $entry->attachments()->create([
                'title' => $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'uploaded_by' => Auth::id(),
            ]);
        }
    }

    private function createCashflowFromVendorPayment(VendorPaymentEntry $entry, Vendor $vendor): ?int
    {
        if (! class_exists(\App\Models\CashflowEntry::class) || ! Schema::hasTable('cashflow_entries') || ! $entry->paid_account_id) {
            return null;
        }

        $entryClass = \App\Models\CashflowEntry::class;
        $cashflow = new $entryClass;
        $cashflow->entry_date = $entry->transaction_date;
        $cashflow->particular = $entry->particular;
        $cashflow->invoice_bill_number = $entry->invoice_number;
        $cashflow->bank_reference_number = $entry->bank_reference_number;
        $cashflow->transaction_type = $entry->transaction_type === 'debit' ? 'debit' : 'credit';
        $cashflow->credit_amount = $entry->transaction_type === 'credit' ? $entry->amount_in_inr : 0;
        $cashflow->debit_amount = $entry->transaction_type === 'debit' ? $entry->amount_in_inr : 0;
        $cashflow->balance = null;
        $cashflow->currency = 'INR';
        $cashflow->account_id = $entry->paid_account_id;
        $cashflow->category_id = null;
        $cashflow->accounting_status = in_array($entry->status, ['paid', 'reconciled'], true) ? 'booked' : 'pending';
        $cashflow->payment_mode = $entry->payment_mode;
        $cashflow->client_id = null;
        $cashflow->vendor_id = $vendor->id;
        $cashflow->expense_head = $entry->entry_category;
        $cashflow->related_party_type = 'vendor';
        $cashflow->related_party_name = $vendor->vendor_name;
        $cashflow->notes = trim(($entry->remarks ?: '').' Vendor currency: '.$entry->foreign_currency.' '.number_format((float) $entry->foreign_amount, 4));
        $cashflow->created_by = Auth::id();

        if (Schema::hasColumn('cashflow_entries', 'project_id')) {
            $cashflow->project_id = $entry->project_id;
        }

        $cashflow->save();

        return $cashflow->id;
    }

    private function dashboardData(Vendor $vendor): array
    {
        $projectProducts = $this->vendorProjectProducts($vendor);
        $vendorQuotes = $this->vendorQuotes($vendor);
        $statementEntries = $this->vendorStatementEntries($vendor); // cashflow entries
        $vendorPaymentEntries = $this->vendorPaymentEntries($vendor); // manual vendor-currency ledger
        $shipments = $this->vendorShipments($vendor);
        $products = $this->vendorProducts($projectProducts, $vendorQuotes);
        $cashflowAccounts = $this->cashflowAccounts();
        $projectsForPayment = $this->projectsForVendorPayment($vendor);
        $attachmentOptions = class_exists(VendorAttachment::class) ? VendorAttachment::categoryOptions() : [];
        $commentsAvailable = Schema::hasTable('vendor_comments');
        $attachmentsAvailable = Schema::hasTable('vendor_attachments');

        $projectValue = (float) $projectProducts->sum('total_amount');
        $quoteValue = (float) $vendorQuotes->sum(function ($quote) {
            return $this->vendorQuoteValue($quote);
        });

        $currencySummary = $this->vendorCurrencySummary($vendorPaymentEntries);
        $preferredCurrency = $vendor->preferred_currency ?: 'RMB';
        $preferredCurrencySummary = $currencySummary[$preferredCurrency] ?? [
            'bill' => 0, 'expense' => 0, 'credit' => 0, 'debit' => 0, 'balance' => 0,
            'inr_credit' => 0, 'inr_debit' => 0,
        ];

        $paidToVendor = (float) $vendorPaymentEntries->where('transaction_type', 'debit')->sum('amount_in_inr');
        $billedInr = (float) $vendorPaymentEntries->where('transaction_type', 'credit')->sum('amount_in_inr');
        $otherExpensesInr = (float) $vendorPaymentEntries->where('entry_category', 'expense')->sum('amount_in_inr');
        $needToPayInr = max($billedInr - $paidToVendor, 0);

        // Backward-compatible cashflow totals for vendors that have not started manual vendor ledger yet.
        if ($vendorPaymentEntries->isEmpty()) {
            $paidToVendor = (float) $statementEntries->sum(function ($entry) { return (float) ($entry->debit_amount ?? 0); });
            $billedInr = $projectValue > 0 ? $projectValue : $quoteValue;
            $needToPayInr = max($billedInr - $paidToVendor, 0);
            $otherExpensesInr = (float) $statementEntries->filter(function ($entry) {
                return (float) ($entry->debit_amount ?? 0) > 0 && (! empty($entry->expense_head) || ($entry->related_party_type ?? null) === 'vendor');
            })->sum(function ($entry) { return (float) ($entry->debit_amount ?? 0); });
        }

        $runningProjects = $projectProducts->filter(function ($row) {
            return ! in_array($row->status, ['delivered', 'cancelled'], true);
        })->pluck('project_id')->filter()->unique()->count();

        $summary = [
            'project_products_count' => $projectProducts->count(),
            'running_projects' => $runningProjects,
            'products_count' => $products->count(),
            'vendor_quotes_count' => $vendorQuotes->count(),
            'project_value' => $projectValue,
            'quote_value' => $quoteValue,
            'expected_payable' => $billedInr,
            'paid_to_vendor' => $paidToVendor,
            'received_from_vendor' => (float) $vendorPaymentEntries->where('transaction_type', 'credit')->where('entry_category', 'refund')->sum('amount_in_inr'),
            'net_paid' => $paidToVendor,
            'need_to_pay' => $needToPayInr,
            'expenses_on_behalf' => $otherExpensesInr,
            'statement_count' => $vendorPaymentEntries->count(),
            'cashflow_count' => $statementEntries->count(),
            'shipments_count' => $shipments->count(),
            'attachments_count' => $vendor->relationLoaded('attachments') ? $vendor->attachments->count() : 0,
            'vendor_currency' => $preferredCurrency,
            'vendor_bill_foreign' => $preferredCurrencySummary['bill'],
            'vendor_expense_foreign' => $preferredCurrencySummary['expense'],
            'vendor_credit_foreign' => $preferredCurrencySummary['credit'],
            'vendor_paid_foreign' => $preferredCurrencySummary['debit'],
            'vendor_balance_foreign' => $preferredCurrencySummary['balance'],
        ];

        $routes = [
            'projects' => Route::has('projects.index') ? route('projects.index') : '#',
            'products' => Route::has('products.index') ? route('products.index') : '#',
            'cashflows' => Route::has('cashflows.index') ? route('cashflows.index') : '#',
            'shipments' => Route::has('shipments.index') ? route('shipments.index') : '#',
            'vendorQuotes' => Route::has('vendor-quotes.index') ? route('vendor-quotes.index') : '#',
        ];

        $paymentOptions = [
            'type' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::transactionTypeOptions() : [],
            'category' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::categoryOptions() : [],
            'status' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::statusOptions() : [],
            'currency' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::currencyOptions() : [],
            'mode' => class_exists(VendorPaymentEntry::class) ? VendorPaymentEntry::paymentModeOptions() : [],
        ];

        return compact(
            'projectProducts',
            'vendorQuotes',
            'statementEntries',
            'vendorPaymentEntries',
            'currencySummary',
            'shipments',
            'products',
            'cashflowAccounts',
            'projectsForPayment',
            'paymentOptions',
            'attachmentOptions',
            'summary',
            'routes',
            'commentsAvailable',
            'attachmentsAvailable'
        );
    }

    private function vendorProjectProducts(Vendor $vendor)
    {
        if (! class_exists(\App\Models\ProjectProduct::class) || ! Schema::hasTable('project_products') || ! Schema::hasColumn('project_products', 'vendor_id')) {
            return collect();
        }

        $relations = [];
        if (class_exists(\App\Models\Project::class) && Schema::hasTable('projects')) {
            $relations[] = 'project';
        }
        if (class_exists(\App\Models\Product::class) && Schema::hasTable('products')) {
            $relations[] = 'product';
        }

        return \App\Models\ProjectProduct::query()
            ->with($relations)
            ->where('vendor_id', $vendor->id)
            ->latest('id')
            ->get();
    }

    private function vendorQuotes(Vendor $vendor)
    {
        if (! class_exists(\App\Models\VendorQuote::class) || ! Schema::hasTable('vendor_quotes')) {
            return collect();
        }

        $relations = [];
        if (class_exists(\App\Models\VendorQuotePrice::class) && Schema::hasTable('vendor_quote_prices')) {
            $relations[] = 'prices';
        }
        if (class_exists(\App\Models\Product::class) && Schema::hasTable('products')) {
            $relations[] = 'product';
        }
        if (class_exists(\App\Models\Lead::class) && Schema::hasTable('leads')) {
            $relations[] = 'lead';
        }

        $hasVendorId = Schema::hasColumn('vendor_quotes', 'vendor_id');
        $hasVendorName = Schema::hasColumn('vendor_quotes', 'vendor_name');

        if (! $hasVendorId && ! $hasVendorName) {
            return collect();
        }

        return \App\Models\VendorQuote::query()
            ->with($relations)
            ->where(function ($query) use ($vendor, $hasVendorId, $hasVendorName) {
                if ($hasVendorId) {
                    $query->where('vendor_id', $vendor->id);
                }
                if ($hasVendorName) {
                    $method = $hasVendorId ? 'orWhere' : 'where';
                    $query->{$method}('vendor_name', 'like', '%'.$vendor->vendor_name.'%');
                }
            })
            ->latest('id')
            ->get();
    }

    private function vendorPaymentEntries(Vendor $vendor)
    {
        if (! class_exists(VendorPaymentEntry::class) || ! Schema::hasTable('vendor_payment_entries')) {
            return collect();
        }

        $relations = ['creator'];
        if (Schema::hasTable('vendor_payment_attachments')) {
            $relations[] = 'attachments';
        }
        if (class_exists(\App\Models\Project::class) && Schema::hasTable('projects')) {
            $relations[] = 'project';
        }
        if (class_exists(\App\Models\CashflowAccount::class) && Schema::hasTable('cashflow_accounts')) {
            $relations[] = 'paidAccount';
        }
        if (class_exists(\App\Models\CashflowEntry::class) && Schema::hasTable('cashflow_entries')) {
            $relations[] = 'cashflowEntry';
        }

        return VendorPaymentEntry::query()
            ->with($relations)
            ->where('vendor_id', $vendor->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    private function vendorCurrencySummary($entries): array
    {
        $summary = [];

        foreach ($entries as $entry) {
            $currency = $entry->foreign_currency ?: 'RMB';
            if (! isset($summary[$currency])) {
                $summary[$currency] = [
                    'bill' => 0,
                    'expense' => 0,
                    'credit' => 0,
                    'debit' => 0,
                    'balance' => 0,
                    'inr_credit' => 0,
                    'inr_debit' => 0,
                ];
            }

            $foreignAmount = (float) $entry->foreign_amount;
            $inrAmount = (float) $entry->amount_in_inr;

            if ($entry->transaction_type === 'credit') {
                $summary[$currency]['credit'] += $foreignAmount;
                $summary[$currency]['inr_credit'] += $inrAmount;
                if ($entry->entry_category === 'bill') {
                    $summary[$currency]['bill'] += $foreignAmount;
                }
                if ($entry->entry_category === 'expense') {
                    $summary[$currency]['expense'] += $foreignAmount;
                }
            } else {
                $summary[$currency]['debit'] += $foreignAmount;
                $summary[$currency]['inr_debit'] += $inrAmount;
            }

            $summary[$currency]['balance'] = $summary[$currency]['credit'] - $summary[$currency]['debit'];
        }

        return $summary;
    }

    private function cashflowAccounts()
    {
        if (! class_exists(\App\Models\CashflowAccount::class) || ! Schema::hasTable('cashflow_accounts')) {
            return collect();
        }

        return \App\Models\CashflowAccount::query()->orderBy('account_name')->get();
    }

    private function projectsForVendorPayment(Vendor $vendor)
    {
        if (! class_exists(\App\Models\Project::class) || ! Schema::hasTable('projects')) {
            return collect();
        }

        $projectIds = collect();
        if (class_exists(\App\Models\ProjectProduct::class) && Schema::hasTable('project_products') && Schema::hasColumn('project_products', 'vendor_id')) {
            $projectIds = \App\Models\ProjectProduct::where('vendor_id', $vendor->id)->pluck('project_id');
        }

        $query = \App\Models\Project::query()->orderByDesc('id');
        if ($projectIds->isNotEmpty()) {
            $query->whereIn('id', $projectIds->filter()->unique()->values());
        }

        return $query->limit(100)->get();
    }

    private function vendorStatementEntries(Vendor $vendor)
    {
        if (! class_exists(\App\Models\CashflowEntry::class) || ! Schema::hasTable('cashflow_entries')) {
            return collect();
        }

        $hasVendorId = Schema::hasColumn('cashflow_entries', 'vendor_id');
        $hasRelatedType = Schema::hasColumn('cashflow_entries', 'related_party_type');
        $hasRelatedName = Schema::hasColumn('cashflow_entries', 'related_party_name');

        if (! $hasVendorId && ! ($hasRelatedType && $hasRelatedName)) {
            return collect();
        }

        $relations = [];
        if (class_exists(\App\Models\CashflowAccount::class) && Schema::hasTable('cashflow_accounts')) {
            $relations[] = 'account';
        }
        if (class_exists(\App\Models\CashflowCategory::class) && Schema::hasTable('cashflow_categories')) {
            $relations[] = 'category';
        }

        return \App\Models\CashflowEntry::query()
            ->with($relations)
            ->where(function ($query) use ($vendor, $hasVendorId, $hasRelatedType, $hasRelatedName) {
                if ($hasVendorId) {
                    $query->where('vendor_id', $vendor->id);
                }
                if ($hasRelatedType && $hasRelatedName) {
                    $method = $hasVendorId ? 'orWhere' : 'where';
                    $query->{$method}(function ($nested) use ($vendor) {
                        $nested->where('related_party_type', 'vendor')
                            ->where('related_party_name', 'like', '%'.$vendor->vendor_name.'%');
                    });
                }
            })
            ->latest('entry_date')
            ->latest('id')
            ->limit(250)
            ->get();
    }

    private function vendorShipments(Vendor $vendor)
    {
        if (! class_exists(\App\Models\Shipment::class) || ! Schema::hasTable('shipments')) {
            return collect();
        }

        $columns = ['vendor_id', 'from_name', 'to_name', 'logistic_partner'];
        $available = array_filter($columns, function ($column) {
            return Schema::hasColumn('shipments', $column);
        });

        if (! $available) {
            return collect();
        }

        return \App\Models\Shipment::query()
            ->where(function ($query) use ($vendor, $available) {
                $hasCondition = false;

                if (in_array('vendor_id', $available, true)) {
                    $query->where('vendor_id', $vendor->id);
                    $hasCondition = true;
                }

                foreach (['from_name', 'to_name', 'logistic_partner'] as $column) {
                    if (in_array($column, $available, true)) {
                        $method = $hasCondition ? 'orWhere' : 'where';
                        $query->{$method}($column, 'like', '%'.$vendor->vendor_name.'%');
                        $hasCondition = true;
                    }
                }
            })
            ->latest('id')
            ->limit(80)
            ->get();
    }

    private function vendorProducts($projectProducts, $vendorQuotes)
    {
        if (! class_exists(\App\Models\Product::class) || ! Schema::hasTable('products')) {
            return collect();
        }

        $ids = $projectProducts->pluck('product_id')
            ->merge($vendorQuotes->pluck('product_id'))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $relations = [];
        if (class_exists(\App\Models\ProductMedia::class) && Schema::hasTable('product_media')) {
            $relations[] = 'media';
        }
        if (class_exists(\App\Models\ProductPriceLadder::class) && Schema::hasTable('product_price_ladders')) {
            $relations[] = 'priceLadders';
        }

        return \App\Models\Product::query()
            ->with($relations)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    private function vendorQuoteValue($quote): float
    {
        $value = (float) ($quote->landing_cost_inr ?: 0);

        if ($value <= 0 && $quote->vendor_unit_price && $quote->quantity) {
            $value = (float) $quote->vendor_unit_price * (float) $quote->quantity;
        }

        if ($value <= 0 && $quote->relationLoaded('prices')) {
            $value = (float) $quote->prices->sum(function ($price) {
                $priceValue = (float) ($price->landing_cost_inr ?: 0);
                if ($priceValue <= 0 && $price->vendor_unit_price && $price->quantity) {
                    $priceValue = (float) $price->vendor_unit_price * (float) $price->quantity;
                }
                return $priceValue;
            });
        }

        return $value;
    }

    private function validatedData(Request $request, ?Vendor $vendor = null): array
    {
        return $request->validate([
            'vendor_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'vendor_number' => ['nullable', 'string', 'max:255', 'unique:vendors,vendor_number,'.($vendor?->id ?? 'NULL')],
            'vendor_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'vendor_type' => ['required', 'in:manufacturer,trader,distributor,service_provider'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive,on_hold,blacklisted'],

            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],
            'contact_person_mobile' => ['nullable', 'string', 'max:40'],
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'alternate_contact' => ['nullable', 'string', 'max:40'],

            'website' => ['nullable', 'string', 'max:255'],
            'alibaba_link' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],

            'gstin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'import_export_code' => ['nullable', 'string', 'max:255'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'swift_code' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],

            'preferred_currency' => ['nullable', 'in:INR,USD,RMB'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function makeVendorNumber(): string
    {
        $prefix = 'VEN-'.now()->format('ymd').'-';
        $next = str_pad((string) (Vendor::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (Vendor::where('vendor_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function formData(Vendor $vendor): array
    {
        return [
            'vendor' => $vendor,
            'statusOptions' => Vendor::statusOptions(),
            'typeOptions' => Vendor::typeOptions(),
            'currencyOptions' => Vendor::currencyOptions(),
        ];
    }
}