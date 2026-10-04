<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use App\Models\Vendor;
use App\Models\VendorAttachment;
use App\Models\VendorComment;
use App\Models\VendorPaymentAttachment;
use App\Models\VendorPaymentEntry;
use App\Services\SavedViews;
use App\Services\VendorPaymentCashflowSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VendorController extends Controller
{
    /**
     * The record's tabs: the questions an office asks about a supplier, in the
     * order it asks them. One list, read by the controller and drawn by the
     * view, so a `?tab=` guard can never disagree with the strip about what
     * exists.
     *
     * The profile facts are split the way a vendor file is: who they are, who
     * answers the phone, where they ship from, and what the money side needs
     * (tax, bank, terms). The three ledgers stay apart for the same reason the
     * statement module keeps them apart — a manual vendor-currency entry, the
     * INR cashflow mirror, and the statement a vendor can be sent are three
     * questions, not three renderings of one.
     */
    private const SHOW_TABS = [
        'overview' => 'Overview',
        'profile' => 'Profile',
        'contacts' => 'Contacts',
        'addresses' => 'Addresses',
        'commercial' => 'Commercial',
        'projects' => 'Projects',
        'products' => 'Products',
        'payments' => 'Payments',
        'statement' => 'Statement',
        'shipments' => 'Shipments',
        'attachments' => 'Attachments',
        'comments' => 'Comments',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if ($savedQuery = $this->resolveSavedView($request)) {
            return redirect()->route('vendors.index', $savedQuery);
        }

        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';
        $search = $search !== '' ? mb_substr($search, 0, 150) : null;

        $status = $request->query('status', 'all');
        if (! is_string($status) || ! array_key_exists($status, ['all' => 'All'] + Vendor::statusOptions())) {
            $status = 'all';
        }

        $type = $request->query('type', 'all');
        if (! is_string($type) || ! array_key_exists($type, ['all' => 'All'] + Vendor::typeOptions())) {
            $type = 'all';
        }

        $countries = Vendor::query()
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $country = $request->query('country', 'all');
        if (! is_string($country) || ($country !== 'all' && ! $countries->contains($country))) {
            $country = 'all';
        }

        $vendors = Vendor::query()
            ->with('creator')
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('vendor_type', $type))
            ->when($country !== 'all', fn ($q) => $q->where('country', $country))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        /* The status chips count the module, not the page — a chip that shows
           the number of rows it happens to be sitting above is a chip you
           cannot use to decide where to go. */
        $statusCounts = Vendor::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'active' => (int) ($statusCounts[Vendor::STATUS_ACTIVE] ?? 0),
            'inactive' => (int) ($statusCounts[Vendor::STATUS_INACTIVE] ?? 0),
            'on_hold' => (int) ($statusCounts[Vendor::STATUS_ON_HOLD] ?? 0),
            'blacklisted' => (int) ($statusCounts[Vendor::STATUS_BLACKLISTED] ?? 0),
            'international' => Vendor::query()
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->where('country', '!=', 'India')
                ->count(),
            'with_contact' => Vendor::query()->whereNotNull('contact_person_name')->where('contact_person_name', '!=', '')->count(),
        ];

        return view('vendors.index', [
            'vendors' => $vendors,
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'countries' => $countries,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'country' => $country,
            'filtersActive' => filled($search) || $status !== 'all' || $type !== 'all' || $country !== 'all',
            'statusOptions' => Vendor::statusOptions(),
            'typeOptions' => Vendor::typeOptions(),
            'currencyOptions' => Vendor::currencyOptions(),
            'savedViews' => app(SavedViews::class)->forUser(Auth::id(), 'vendors'),
        ]);
    }

    /** Restore a named filter set as the normal list URL and controls. */
    private function resolveSavedView(Request $request): array
    {
        $id = (int) $request->query('saved_view', 0);
        $savedViews = app(SavedViews::class);

        if (! $id || ! $savedViews->available()) {
            return [];
        }

        $view = SavedView::query()
            ->where('module', 'vendors')
            ->where(function ($query) {
                $query->where('user_id', Auth::id())->orWhere('is_shared', true);
            })
            ->find($id);

        return $view ? $savedViews->queryFor($view) : [];
    }

    public function storeSavedView(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'is_shared' => ['nullable', 'boolean'],
        ]);

        app(SavedViews::class)->save(
            (int) Auth::id(),
            'vendors',
            $data['name'],
            $request->query(),
            $request->boolean('is_shared')
        );

        return back()->with('success', 'View "'.$data['name'].'" saved.');
    }

    public function destroySavedView(SavedView $savedView): RedirectResponse
    {
        abort_unless($savedView->module === 'vendors' && (int) $savedView->user_id === (int) Auth::id(), 403);

        app(SavedViews::class)->delete((int) Auth::id(), (int) $savedView->id);

        return back()->with('success', 'Saved view removed.');
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
            'vendor_type' => ['nullable', 'in:manufacturer,trader,distributor,service_provider'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,on_hold,blacklisted'],
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
        $data['vendor_type'] = $data['vendor_type'] ?? 'manufacturer';
        $data['status'] = $data['status'] ?? Vendor::STATUS_ACTIVE;
        $data['preferred_currency'] = $data['preferred_currency'] ?? 'INR';

        if ($request->hasFile('vendor_image')) {
            $data['image_path'] = $request->file('vendor_image')->store('vendors', 'public');
        }

        $vendor = Vendor::create($data);

        return redirect()
            ->route('vendors.show', $vendor)
            ->with('success', 'Quick vendor created successfully.');
    }

    public function show(Request $request, Vendor $vendor): View
    {
        $tab = (string) $request->query('tab', 'overview');
        $tab = array_key_exists($tab, self::SHOW_TABS) ? $tab : 'overview';

        $relations = ['creator'];
        if (Schema::hasTable('vendor_comments')) {
            $relations[] = 'comments.creator';
        }
        if (Schema::hasTable('vendor_attachments') && in_array($tab, ['attachments', 'overview'], true)) {
            $relations[] = 'attachments.uploader';
        }
        $vendor->load($relations);

        $data = $this->dashboardData($vendor);

        return view('vendors.show', array_merge($this->formData($vendor), $data, [
            'tabs' => self::SHOW_TABS,
            'tab' => $tab,
            'tabCounts' => [
                'projects' => (int) ($data['summary']['project_products_count'] ?? 0),
                'products' => (int) ($data['summary']['products_count'] ?? 0),
                'payments' => (int) ($data['summary']['statement_count'] ?? 0),
                'shipments' => (int) ($data['summary']['shipments_count'] ?? 0),
                'attachments' => $vendor->relationLoaded('attachments') ? $vendor->attachments->count() : (int) ($data['summary']['attachments_count'] ?? 0),
                'comments' => $vendor->relationLoaded('comments') ? $vendor->comments->count() : 0,
            ],
            'recordUrl' => fn (string $key) => route('vendors.show', ['vendor' => $vendor, 'tab' => $key]),
        ]));
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

        return $this->backToTab($vendor, 'comments', 'Vendor comment added successfully.');
    }

    public function destroyComment(VendorComment $comment): RedirectResponse
    {
        $vendor = $comment->vendor;

        $comment->delete();

        return $vendor
            ? $this->backToTab($vendor, 'comments', 'Vendor comment deleted successfully.')
            : redirect()->route('vendors.index')->with('success', 'Vendor comment deleted successfully.');
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

        return $this->backToTab($vendor, 'attachments', 'Vendor document uploaded successfully.');
    }

    public function destroyAttachment(VendorAttachment $attachment): RedirectResponse
    {
        $vendor = $attachment->vendor;

        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return $vendor
            ? $this->backToTab($vendor, 'attachments', 'Vendor document deleted successfully.')
            : redirect()->route('vendors.index')->with('success', 'Vendor document deleted successfully.');
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

        $syncCashflow = $request->boolean('also_create_cashflow');

        $data['vendor_id'] = $vendor->id;
        $data['created_by'] = Auth::id();
        $data['amount_in_inr'] = $this->normalizeInrAmount($data);
        unset($data['attachments'], $data['also_create_cashflow']);

        $this->assertCashflowMirrorPossible($data, $syncCashflow);

        $entry = DB::transaction(function () use ($request, $data, $vendor, $syncCashflow) {
            $entry = VendorPaymentEntry::create($data);

            $this->cashflowSync()->sync($entry, $syncCashflow);

            $this->storeVendorPaymentAttachments($request, $entry);

            return $entry;
        });

        return $this->backToTab($vendor, 'payments', $this->paymentSavedMessage($entry, 'added'));
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
    
        $syncCashflow = $request->boolean('also_create_cashflow');

        $data['amount_in_inr'] = $this->normalizeInrAmount($data);
    
        // These are not columns in vendor_payment_entries
        unset($data['attachments'],$data['also_create_cashflow']);

        $this->assertCashflowMirrorPossible($data, $syncCashflow);

        DB::transaction(function () use ($request,$data,$vendor,$entry,$syncCashflow) {
            $entry->update($data);
            $this->cashflowSync()->sync($entry, $syncCashflow);
            $this->storeVendorPaymentAttachments($request,$entry);
        });
    
        return $this->backToTab($vendor, 'payments', $this->paymentSavedMessage($entry, 'updated'));
    }

    public function destroyPayment(VendorPaymentEntry $entry): RedirectResponse
    {
        $hadCashflow = (bool) $entry->cashflow_entry_id;

        DB::transaction(function () use ($entry) {
            // Remove the INR mirror too — one entry, both ledgers.
            $this->cashflowSync()->remove($entry);

            foreach ($entry->attachments as $attachment) {
                if ($attachment->file_path) {
                    Storage::disk('public')->delete($attachment->file_path);
                }
            }

            $entry->delete();
        });

        $vendor = $entry->vendor;

        if (! $vendor) {
            return redirect()->route('vendors.index')->with(
                'success',
                $hadCashflow
                    ? 'Vendor payment entry and its linked INR cashflow entry were deleted.'
                    : 'Vendor payment entry deleted successfully.'
            );
        }

        return $this->backToTab($vendor, 'payments', $hadCashflow
            ? 'Vendor payment entry and its linked INR cashflow entry were deleted.'
            : 'Vendor payment entry deleted successfully.');
    }

    public function destroyPaymentAttachment(VendorPaymentAttachment $attachment): RedirectResponse
    {
        $vendor = $attachment->entry?->vendor;

        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return $vendor
            ? $this->backToTab($vendor, 'payments', 'Attachment deleted successfully.')
            : redirect()->route('vendors.index')->with('success', 'Attachment deleted successfully.');
    }

    /**
     * Every write lands back on the tab it was made from. A redirect to
     * `back()` would depend on the referer, and a form posted from a modal on
     * the payments tab has to come home to the payments tab even when the
     * browser sent no referer at all.
     */
    private function backToTab(Vendor $vendor, string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->route('vendors.show', ['vendor' => $vendor, 'tab' => $tab])
            ->with('success', $message);
    }

    private function cashflowSync(): VendorPaymentCashflowSync
    {
        return app(VendorPaymentCashflowSync::class);
    }

    /**
     * A vendor payment entry that should be mirrored into INR cashflow needs
     * an account to debit and an INR value — without them the mirror cannot
     * exist, so the user is told here instead of silently losing the entry
     * in the cashflow module.
     */
    private function assertCashflowMirrorPossible(array $data, bool $requested): void
    {
        if (! $requested || ($data['transaction_type'] ?? 'credit') !== 'debit') {
            return;
        }

        $sync = $this->cashflowSync();
        if (! $sync->available()) {
            return; // cashflow module not installed/migrated — nothing to mirror
        }

        $errors = [];

        if (empty($data['paid_account_id'])) {
            $errors['paid_account_id'] = 'Select the paid account so the INR cashflow entry can be created.';
        }

        if ((float) ($data['amount_in_inr'] ?? 0) <= 0) {
            $errors['amount_in_inr'] = 'Enter the exchange rate (or the INR amount) so the cashflow entry can be created.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function paymentSavedMessage(VendorPaymentEntry $entry, string $verb): string
    {
        if ($entry->cashflow_entry_id) {
            return 'Vendor payment entry '.$verb.' and INR cashflow entry #'.$entry->cashflow_entry_id.' kept in sync.';
        }

        if ($entry->transaction_type === 'debit' && $entry->status !== 'cancelled') {
            return 'Vendor payment entry '.$verb.' (not mirrored to cashflow).';
        }

        return 'Vendor payment/statement entry '.$verb.' successfully.';
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

    private function dashboardData(Vendor $vendor): array
    {
        $projectProducts = $this->vendorProjectProducts($vendor);
        $statementEntries = $this->vendorStatementEntries($vendor); // cashflow entries
        $vendorPaymentEntries = $this->vendorPaymentEntries($vendor); // manual vendor-currency ledger
        $shipments = $this->vendorShipments($vendor);
        $products = $this->vendorProducts($projectProducts);
        $cashflowAccounts = $this->cashflowAccounts();
        $projectsForPayment = $this->projectsForVendorPayment($vendor);
        $attachmentOptions = class_exists(VendorAttachment::class) ? VendorAttachment::categoryOptions() : [];
        $commentsAvailable = Schema::hasTable('vendor_comments');
        $attachmentsAvailable = Schema::hasTable('vendor_attachments');

        $projectValue = (float) $projectProducts->sum('total_amount');

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
            $billedInr = $projectValue;
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
            'project_value' => $projectValue,
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

        $entries = VendorPaymentEntry::query()
            ->with($relations)
            ->where('vendor_id', $vendor->id)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        /* The statement reads as an account: each row carries the balance the
           account stood at after it. The balance is per currency — a vendor
           billed in RMB and paid in rupees holds two balances, and adding them
           is wrong by the exchange rate — so it is accumulated per currency
           rather than over the whole list. */
        $running = [];

        foreach ($entries as $entry) {
            $currency = $entry->foreign_currency ?: 'RMB';
            $movement = $entry->transaction_type === 'credit'
                ? (float) $entry->foreign_amount
                : -(float) $entry->foreign_amount;

            $running[$currency] = round(($running[$currency] ?? 0) + $movement, 4);
            $entry->setAttribute('running_balance', $running[$currency]);
        }

        return $entries;
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

    private function vendorProducts($projectProducts)
    {
        if (! class_exists(\App\Models\Product::class) || ! Schema::hasTable('products')) {
            return collect();
        }

        $ids = $projectProducts->pluck('product_id')
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

    private function validatedData(Request $request, ?Vendor $vendor = null): array
    {
        return $request->validate([
            'vendor_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'vendor_number' => ['nullable', 'string', 'max:255', 'unique:vendors,vendor_number,'.($vendor?->id ?? 'NULL')],
            'vendor_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'vendor_type' => ['nullable', 'in:manufacturer,trader,distributor,service_provider'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,on_hold,blacklisted'],

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