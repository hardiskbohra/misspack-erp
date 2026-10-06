<?php

namespace App\Http\Controllers;

use App\Models\SavedView;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use App\Models\VendorAttachment;
use App\Models\VendorComment;
use App\Models\VendorContact;
use App\Models\VendorPaymentAttachment;
use App\Models\VendorPaymentEntry;
use App\Services\SavedViews;
use App\Services\VendorPaymentCashflowSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
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
        'commercial' => 'Commercial',
        'procurement' => 'Procurement',
        'money' => 'Money',
        'documents' => 'Documents',
        'comments' => 'Comments',
    ];

    /**
     * The tabs this record used to have, and the tab that answers the same
     * question now. A link somebody bookmarked, or a URL pasted into a mail
     * last month, still lands on the right panel instead of the overview.
     */
    private const TAB_ALIASES = [
        'projects' => 'procurement',
        'products' => 'procurement',
        'shipments' => 'procurement',
        'payments' => 'money',
        'statement' => 'money',
        'attachments' => 'documents',
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

        /* The one filter the office reaches for when it is chasing money: the
           vendors with a bill past its due date. */
        $overdue = $request->query('overdue') === '1' ? '1' : 'all';

        $vendors = Vendor::query()
            ->with('creator')
            ->when(Schema::hasTable('vendor_payment_entries'), function ($query) {
                /* Payable per vendor in that vendor's ledger currency, so a
                   page of 25 vendors does not load 25 ledgers for one column. */
                $query->withSum(['paymentEntries as billed_foreign' => function ($ledger) {
                    $ledger->where('transaction_type', 'credit');
                }], 'foreign_amount')
                    ->withSum(['paymentEntries as paid_foreign' => function ($ledger) {
                        $ledger->where('transaction_type', 'debit');
                    }], 'foreign_amount');
            })
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('vendor_type', $type))
            ->when($country !== 'all', fn ($q) => $q->where('country', $country))
            ->when($overdue === '1' && Schema::hasTable('vendor_payment_entries') && Schema::hasColumn('vendor_payment_entries', 'due_date'), function ($query) {
                $query->whereHas('paymentEntries', function ($ledger) {
                    $ledger->where('transaction_type', 'credit')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', Carbon::today());
                });
            })
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
            'owing_vendors' => $this->owingVendorCount(),
            'overdue_vendors' => $this->overdueVendorCount(),
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
            'overdue' => $overdue,
            'filtersActive' => filled($search) || $status !== 'all' || $type !== 'all' || $country !== 'all' || $overdue !== 'all',
            'statusOptions' => Vendor::statusOptions(),
            'typeOptions' => Vendor::typeOptions(),
            'currencyOptions' => Vendor::currencyOptions(),
            'contactOptions' => Schema::hasTable('vendor_contacts'),
            'statusActions' => [
                'activate' => 'Mark active',
                'deactivate' => 'Mark inactive',
                'hold' => 'Put on hold',
                'blacklist' => 'Blacklist',
            ],
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
        $tab = self::TAB_ALIASES[$tab] ?? $tab;
        $tab = array_key_exists($tab, self::SHOW_TABS) ? $tab : 'overview';

        $relations = ['creator'];
        if (Schema::hasTable('vendor_comments')) {
            $relations[] = 'comments.creator';
        }
        if (Schema::hasTable('vendor_attachments') && in_array($tab, ['documents', 'overview'], true)) {
            $relations[] = 'attachments.uploader';
        }
        if (Schema::hasTable('vendor_contacts')) {
            $relations[] = 'contacts';
        }
        $vendor->load($relations);

        $data = $this->dashboardData($vendor);

        return view('vendors.show', array_merge($this->formData($vendor), $data, [
            'tabs' => self::SHOW_TABS,
            'tab' => $tab,
            'tabCounts' => [
                'contacts' => $vendor->relationLoaded('contacts') ? $vendor->contacts->count() : 0,
                'procurement' => (int) ($data['summary']['project_products_count'] ?? 0) + (int) ($data['summary']['shipments_count'] ?? 0),
                'money' => (int) ($data['summary']['statement_count'] ?? 0) + (int) ($data['summary']['cashflow_count'] ?? 0),
                'documents' => $vendor->relationLoaded('attachments') ? $vendor->attachments->count() : (int) ($data['summary']['attachments_count'] ?? 0),
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

        return $this->backToTab($vendor, 'documents', 'Vendor document uploaded successfully.');
    }

    public function destroyAttachment(VendorAttachment $attachment): RedirectResponse
    {
        $vendor = $attachment->vendor;

        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return $vendor
            ? $this->backToTab($vendor, 'documents', 'Vendor document deleted successfully.')
            : redirect()->route('vendors.index')->with('success', 'Vendor document deleted successfully.');
    }

    public function storePayment(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable('vendor_payment_entries'), 404);

        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:transaction_date'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'purchase_invoice_id' => ['nullable', 'integer'],
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
        $data['due_date'] = $this->resolveDueDate($data, $vendor);

        if (! Schema::hasColumn('vendor_payment_entries', 'due_date')) {
            unset($data['due_date']);
        }

        unset($data['attachments'], $data['also_create_cashflow']);

        $this->assertCashflowMirrorPossible($data, $syncCashflow);

        $entry = DB::transaction(function () use ($request, $data, $vendor, $syncCashflow) {
            $entry = VendorPaymentEntry::create($data);
            $this->attachPurchaseDocument($entry);
            $this->assertVendorMoneyAllowed($entry);

            $this->cashflowSync()->sync($entry, $syncCashflow);

            $this->storeVendorPaymentAttachments($request, $entry);

            return $entry;
        });

        return $this->backToTab($vendor, 'money', $this->paymentSavedMessage($entry, 'added'));
    }
    
    public function updatePayment(Request $request, Vendor $vendor, VendorPaymentEntry $entry): RedirectResponse {
    
        // Make sure the entry actually belongs to this vendor
        abort_unless((int) $entry->vendor_id === (int) $vendor->id,404);
    
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'due_date' => ['nullable','date','after_or_equal:transaction_date'],
            'invoice_number' => ['nullable','string','max:255'],
            'purchase_invoice_id' => ['nullable', 'integer'],
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
        $data['due_date'] = $this->resolveDueDate($data, $vendor);

        if (! Schema::hasColumn('vendor_payment_entries', 'due_date')) {
            unset($data['due_date']);
        }

        // These are not columns in vendor_payment_entries
        unset($data['attachments'],$data['also_create_cashflow']);

        $this->assertCashflowMirrorPossible($data, $syncCashflow);

        DB::transaction(function () use ($request,$data,$vendor,$entry,$syncCashflow) {
            $entry->update($data);
            $this->attachPurchaseDocument($entry);
            $this->assertVendorMoneyAllowed($entry);
            $this->cashflowSync()->sync($entry, $syncCashflow);
            $this->storeVendorPaymentAttachments($request,$entry);
        });
    
        return $this->backToTab($vendor, 'money', $this->paymentSavedMessage($entry, 'updated'));
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

        return $this->backToTab($vendor, 'money', $hadCashflow
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
            ? $this->backToTab($vendor, 'money', 'Attachment deleted successfully.')
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

    /**
     * When a Money-tab payment names a purchase order or bill number, hang it
     * on that document so converting the order can take the advance with it.
     */
    private function attachPurchaseDocument(VendorPaymentEntry $entry): void
    {
        if (! Schema::hasTable('purchase_invoices')
            || ! Schema::hasColumn('vendor_payment_entries', 'purchase_invoice_id')
            || $entry->purchase_invoice_id
            || ! filled($entry->invoice_number)) {
            return;
        }

        $document = PurchaseInvoice::query()
            ->where('vendor_id', $entry->vendor_id)
            ->where(function ($query) use ($entry) {
                $query->where('invoice_number', $entry->invoice_number)
                    ->orWhere('vendor_bill_number', $entry->invoice_number);
            })
            ->orderByDesc('id')
            ->first();

        if (! $document) {
            return;
        }

        $entry->purchase_invoice_id = $document->id;
        $entry->save();
    }

    /**
     * Money out needs an approved PO or a raised bill. Credits (a bill
     * received) are the document; they do not wait on this gate.
     */
    private function assertVendorMoneyAllowed(VendorPaymentEntry $entry): void
    {
        if ($entry->transaction_type !== 'debit' || $entry->status === 'cancelled') {
            return;
        }

        if (! class_exists(PurchaseInvoice::class) || ! Schema::hasTable('purchase_invoices')) {
            return;
        }

        $document = $entry->purchase_invoice_id
            ? PurchaseInvoice::query()->find($entry->purchase_invoice_id)
            : null;

        if ($document && $document->canReceiveMoney()) {
            return;
        }

        $message = $document?->moneyGateMessage()
            ?: 'Pick an approved purchase order or a raised bill before paying this vendor. A sent PO still needs the checker to approve it.';

        throw ValidationException::withMessages([
            'purchase_invoice_id' => $message,
        ]);
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

    /**
     * A bill's due date. When the form leaves it blank the vendor's own terms
     * decide it — "30 days" means thirty days from the bill date, which is how
     * the terms are written on the file. Terms that name no number of days
     * leave the date empty rather than inventing one: an absent due date is
     * honest, a wrong one is a payment made late for no reason.
     */
    private function resolveDueDate(array $data, Vendor $vendor): ?string
    {
        if (($data['transaction_type'] ?? null) !== 'credit') {
            return null;
        }

        if (filled($data['due_date'] ?? null)) {
            return $data['due_date'];
        }

        if (($data['entry_category'] ?? null) === 'payment') {
            return null;
        }

        /* Only a term that reads as a number of days is a due date: "30 days"
           and "Net 30" are, "30% advance" is not — guessing the second from
           the first is how a ledger starts dating bills that were never
           agreed. */
        $terms = (string) ($vendor->payment_terms ?? '');

        if (preg_match('/(\d{1,3})\s*(?:calendar\s+)?days?\b/i', $terms, $matches)
            || preg_match('/\bnet\s*(\d{1,3})\b/i', $terms, $matches)) {
            return Carbon::parse($data['transaction_date'])->addDays((int) $matches[1])->toDateString();
        }

        return null;
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
        [$payableDocuments, $pendingApprovals] = $this->vendorPurchaseDocuments($vendor);
        $attachmentOptions = class_exists(VendorAttachment::class) ? VendorAttachment::categoryOptions() : [];
        $commentsAvailable = Schema::hasTable('vendor_comments');
        $attachmentsAvailable = Schema::hasTable('vendor_attachments');
        $contactsAvailable = Schema::hasTable('vendor_contacts');
        $vendorActivity = $this->vendorActivity($vendor, $vendorPaymentEntries, $projectProducts, $shipments);

        $projectValue = (float) $projectProducts->sum('total_amount');

        /* The payables settle the ledger first; spend and the ledger totals
           then read from that settlement, so no two cards can disagree. */
        $currencySummary = $this->vendorCurrencySummary($vendorPaymentEntries);
        $preferredCurrency = $vendor->preferred_currency
            ?: (array_key_first($currencySummary) ?: 'RMB');
        $payables = $this->vendorPayables($vendorPaymentEntries, $preferredCurrency);
        $performance = $this->vendorPerformance($vendor, $vendorPaymentEntries, $payables);
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
            'payables',
            'performance',
            'currencySummary',
            'shipments',
            'products',
            'cashflowAccounts',
            'projectsForPayment',
            'payableDocuments',
            'pendingApprovals',
            'paymentOptions',
            'attachmentOptions',
            'summary',
            'routes',
            'vendorActivity',
            'commentsAvailable',
            'attachmentsAvailable',
            'contactsAvailable'
        );
    }

    /* ------------------------------------------------------------------
       Payables: what is owed, what is late, and how late
       ------------------------------------------------------------------ */

    /**
     * The ledger as a payable book. A bill or an expense raises what is owed;
     * a payment settles it. Rows are matched oldest-first — the way a payment
     * is actually applied — so a partly-paid bill shows one still-open row
     * rather than a settled one and an unrelated credit.
     *
     * Totals on this page are in the vendor's own currency. Rupees belong on
     * the cashflow, not here.
     */
    private function vendorPayables($entries, string $preferredCurrency = 'RMB'): array
    {
        $today = Carbon::today();
        $open = [];

        foreach ($entries as $entry) {
            if ($entry->status === 'cancelled') {
                continue;
            }

            $currency = $entry->foreign_currency ?: 'RMB';
            $foreign = (float) $entry->foreign_amount;
            $rupees = (float) $entry->amount_in_inr;

            /* A bill raises what is owed: a credit entry, except a refund —
               money coming back from the vendor settles the account, it does
               not create a new debt. Everything else (a payment, an
               adjustment, a refund) is applied against the oldest bills in its
               own currency. */
            $isBill = $entry->transaction_type === 'credit' && $entry->entry_category !== 'refund';

            if ($isBill) {
                $open[] = [
                    'id' => $entry->id,
                    'purchase_invoice_id' => $entry->purchase_invoice_id ? (int) $entry->purchase_invoice_id : null,
                    'kind' => $entry->entry_category === 'order' ? 'order' : 'bill',
                    'particular' => (string) $entry->particular,
                    'invoice' => (string) ($entry->invoice_number ?: ''),
                    'date' => $entry->transaction_date,
                    'due' => $entry->due_date,
                    'currency' => $currency,
                    'foreign_total' => $foreign,
                    'rupee_total' => $rupees,
                    'foreign_left' => $foreign,
                    'rupee_left' => $rupees,
                    'is_expense' => $entry->entry_category === 'expense',
                ];

                continue;
            }

            $remaining = $foreign;

            foreach ($open as $index => $bill) {
                if ($remaining <= 0) {
                    break;
                }
                if ($bill['currency'] !== $currency) {
                    continue;
                }

                $appliedForeign = min($remaining, $bill['foreign_left']);
                $share = $bill['foreign_total'] > 0 ? $appliedForeign / $bill['foreign_total'] : 0;

                $open[$index]['foreign_left'] = round($bill['foreign_left'] - $appliedForeign, 4);
                $open[$index]['rupee_left'] = round($bill['rupee_left'] * (1 - $share), 2);
                $remaining = round($remaining - $appliedForeign, 4);
            }
        }

        $buckets = [
            'not_due' => ['label' => 'Not yet due', 'amount' => 0.0, 'count' => 0],
            'due_soon' => ['label' => 'Due within 7 days', 'amount' => 0.0, 'count' => 0],
            'overdue_1_30' => ['label' => '1–30 days late', 'amount' => 0.0, 'count' => 0],
            'overdue_31_60' => ['label' => '31–60 days late', 'amount' => 0.0, 'count' => 0],
            'overdue_60_plus' => ['label' => '60+ days late', 'amount' => 0.0, 'count' => 0],
        ];

        $rows = [];
        $overdueBills = 0;
        $outstanding = 0.0;
        $overdue = 0.0;
        $dueSoon = 0.0;

        foreach ($open as $bill) {
            if ((float) $bill['foreign_left'] <= 0.0001 && (float) $bill['rupee_left'] <= 0.009) {
                continue;
            }

            $inPreferred = ($bill['currency'] ?: $preferredCurrency) === $preferredCurrency;
            $left = (float) $bill['foreign_left'];

            $daysLeft = $this->daysUntil($bill['due'], $today);

            if ($daysLeft === null) {
                $bucket = 'not_due';
            } elseif ($daysLeft >= 0) {
                $bucket = 'due_soon';
                if ($inPreferred) {
                    $dueSoon += $left;
                }
            } elseif ($daysLeft >= -30) {
                $bucket = 'overdue_1_30';
            } elseif ($daysLeft >= -60) {
                $bucket = 'overdue_31_60';
            } else {
                $bucket = 'overdue_60_plus';
            }

            if ($inPreferred) {
                $outstanding += $left;
                $buckets[$bucket]['amount'] += $left;
                $buckets[$bucket]['count']++;
            }

            $isOverdue = $daysLeft !== null && $daysLeft < 0;
            if ($isOverdue && $inPreferred) {
                $overdue += $left;
            }

            $bill['days_left'] = $daysLeft;
            $bill['bucket'] = $bucket;
            $bill['is_overdue'] = $isOverdue;
            $bill['is_due_soon'] = $bucket === 'due_soon';
            $rows[] = $bill;

            if ($isOverdue) {
                $overdueBills++;
            }
        }

        /* Late money first, then what falls due soonest, then the rest — the
           Money tab's table is the chase list, so it reads in chasing order. */
        $order = fn ($row) => $row['is_overdue'] ? 0 : ($row['is_due_soon'] ? 1 : 2);

        usort($rows, function ($a, $b) use ($order) {
            return [$order($a), $a['due']?->timestamp ?? PHP_INT_MAX]
                <=> [$order($b), $b['due']?->timestamp ?? PHP_INT_MAX];
        });

        $nextDue = collect($rows)->filter(fn ($row) => $row['due'] && ! $row['is_overdue'])
            ->sortBy(fn ($row) => $row['due']->timestamp)
            ->first();

        return [
            'rows' => $rows,
            'buckets' => $buckets,
            'outstanding' => round($outstanding, 2),
            'overdue' => round($overdue, 2),
            'due_soon' => round($dueSoon, 2),
            'overdue_count' => $overdueBills,
            'next_due' => $nextDue['due'] ?? null,
        ];
    }

    /**
     * What this supplier has been worth to us: the last six months of billed
     * rupees as bars, and the ledger's own totals beside them. Everything here
     * is derived from the same settled payables the Money tab shows, so the two
     * can never report different numbers.
     */
    private function vendorPerformance(Vendor $vendor, $entries, array $payables): array
    {
        $bills = $entries->filter(function ($entry) {
            return $entry->transaction_type === 'credit'
                && $entry->status !== 'cancelled'
                && $entry->entry_category !== 'refund';
        });

        $payments = $entries->filter(function ($entry) {
            return $entry->transaction_type === 'debit' && $entry->status !== 'cancelled';
        });

        $billed = (float) $bills->sum('foreign_amount');
        $paid = (float) $payments->sum('foreign_amount');

        /* Six bars, oldest first; a month with no bill is a zero-height bar
           rather than a missing column, so the shape of the run is readable. */
        $series = [];
        $peak = 0.0;

        for ($monthsAgo = 5; $monthsAgo >= 0; $monthsAgo--) {
            $month = Carbon::today()->startOfMonth()->subMonths($monthsAgo);
            $amount = (float) $bills
                ->filter(fn ($entry) => $entry->transaction_date
                    && $entry->transaction_date->format('Y-m') === $month->format('Y-m'))
                ->sum('foreign_amount');

            $peak = max($peak, $amount);
            $series[] = ['label' => $month->format('M'), 'amount' => round($amount, 2)];
        }

        $billAmounts = $bills->pluck('foreign_amount')->map(fn ($value) => (float) $value)->filter();
        $dates = $entries->pluck('transaction_date')->filter();

        return [
            'series' => $series,
            'peak' => $peak > 0 ? $peak : 1,
            'billed' => round($billed, 2),
            'paid' => round($paid, 2),
            'settled_percent' => $billed > 0 ? (int) round(min($paid / $billed, 1) * 100) : ($paid > 0 ? 100 : 0),
            'bills' => $bills->count(),
            'payments' => $payments->count(),
            'average_bill' => $billAmounts->count() > 0 ? round($billAmounts->sum() / $billAmounts->count(), 2) : 0.0,
            'largest_bill' => round((float) ($billAmounts->max() ?: 0), 2),
            'open_bills' => count($payables['rows']),
            'first_entry' => $dates->min() ? Carbon::parse($dates->min()) : null,
            'last_entry' => $dates->max() ? Carbon::parse($dates->max()) : null,
        ];
    }

    /**
     * Whole days from today to a date: negative once the date has passed. Done
     * with timestamps rather than Carbon's diff helpers so the sign is this
     * module's, not the version's.
     */
    private function daysUntil(?Carbon $date, ?Carbon $from = null): ?int
    {
        if (! $date) {
            return null;
        }

        $from = ($from ?: Carbon::today())->copy()->startOfDay();

        return (int) round(($date->copy()->startOfDay()->timestamp - $from->timestamp) / 86400);
    }

    /** Vendors with an open ledger balance. Counted, not summed: the
     *  ledgers are in different currencies and must not be added together. */
    private function owingVendorCount(): int
    {
        if (! Schema::hasTable('vendor_payment_entries')) {
            return 0;
        }

        return (int) Vendor::query()
            ->whereRaw('(
                select coalesce(sum(case when transaction_type = \'credit\' then foreign_amount else -foreign_amount end), 0)
                from vendor_payment_entries
                where vendor_id = vendors.id
            ) > 0.009')
            ->count();
    }

    /**
     * Vendors with money past its due date. Counted, not listed: the list
     * filter is what turns this number into the rows behind it.
     */
    /** What is owed across every vendor and already past its due date. */
    private function moduleOverdue(): float
    {
        if (! Schema::hasTable('vendor_payment_entries') || ! Schema::hasColumn('vendor_payment_entries', 'due_date')) {
            return 0.0;
        }

        return round((float) VendorPaymentEntry::query()
            ->where('transaction_type', 'credit')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->sum('amount_in_inr'), 2);
    }

    private function overdueVendorCount(): int
    {
        if (! Schema::hasTable('vendor_payment_entries') || ! Schema::hasColumn('vendor_payment_entries', 'due_date')) {
            return 0;
        }

        return (int) VendorPaymentEntry::query()
            ->where('transaction_type', 'credit')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->distinct()
            ->count('vendor_id');
    }

    /* ------------------------------------------------------------------
       Contacts
       ------------------------------------------------------------------ */

    public function storeContact(Request $request, Vendor $vendor): RedirectResponse
    {
        abort_unless(Schema::hasTable('vendor_contacts'), 404);

        $data = $this->validatedContact($request);

        $vendor->contacts()->create($data);

        return $this->backToTab($vendor, 'contacts', 'Contact added to '.$vendor->vendor_name.'.');
    }

    public function updateContact(Request $request, Vendor $vendor, VendorContact $contact): RedirectResponse
    {
        abort_unless((int) $contact->vendor_id === (int) $vendor->id, 404);

        $contact->update($this->validatedContact($request));

        return $this->backToTab($vendor, 'contacts', 'Contact updated.');
    }

    public function destroyContact(Vendor $vendor, VendorContact $contact): RedirectResponse
    {
        abort_unless((int) $contact->vendor_id === (int) $vendor->id, 404);

        $contact->delete();

        return $this->backToTab($vendor, 'contacts', 'Contact removed.');
    }

    private function validatedContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /* ------------------------------------------------------------------
       Acting on many vendors at once, and on the whole list
       ------------------------------------------------------------------ */

    /**
     * The list's bulk bar: one status change across the rows the office
     * ticked. Ids are validated against the vendors table, so a tampered form
     * cannot touch anything but vendors.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:activate,deactivate,hold,blacklist'],
        ]);

        $map = [
            'activate' => Vendor::STATUS_ACTIVE,
            'deactivate' => Vendor::STATUS_INACTIVE,
            'hold' => Vendor::STATUS_ON_HOLD,
            'blacklist' => Vendor::STATUS_BLACKLISTED,
        ];

        $count = Vendor::query()->whereIn('id', $data['ids'])->update(['status' => $map[$data['action']]]);

        return back()->with('success', $count.' '.\Illuminate\Support\Str::plural('vendor', $count).' moved to '.Vendor::statusOptions()[$map[$data['action']]].'.');
    }

    /**
     * The list's Payables button: every dated bill, oldest due date first, so
     * the sheet opens on the money that is already late. It honours the
     * filters the list is wearing — exporting "all vendors" from a screen
     * showing one status is how a spreadsheet starts disagreeing with the
     * page it came from.
     */
    public function exportPayables(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(Schema::hasTable('vendor_payment_entries') && Schema::hasColumn('vendor_payment_entries', 'due_date'), 404);

        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';

        $status = $request->query('status', 'all');
        if (! is_string($status) || ! array_key_exists($status, ['all' => 'All'] + Vendor::statusOptions())) {
            $status = 'all';
        }

        $overdueOnly = $request->query('overdue') === '1';
        $vendorOnly = $request->integer('vendor');

        $rows = VendorPaymentEntry::query()
            ->with('vendor')
            ->where('transaction_type', 'credit')
            ->whereNotNull('due_date')
            ->when($vendorOnly > 0, fn ($query) => $query->where('vendor_id', $vendorOnly))
            ->when($status !== 'all', fn ($query) => $query->whereHas('vendor', fn ($vendor) => $vendor->where('status', $status)))
            ->when($search !== '', fn ($query) => $query->whereHas('vendor', fn ($vendor) => $vendor->search($search)))
            ->when($overdueOnly, fn ($query) => $query->whereDate('due_date', '<', Carbon::today()))
            ->orderBy('due_date')
            ->get();

        $filename = 'vendor-payables-'.Carbon::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Vendor', 'Invoice', 'Bill date', 'Due date', 'Days late', 'Currency', 'Amount', 'Rupees']);

            foreach ($rows as $row) {
                $late = $this->daysUntil($row->due_date);

                fputcsv($out, [
                    $row->vendor?->vendor_name ?? 'Unassigned vendor',
                    $row->invoice_number,
                    $row->transaction_date?->format('Y-m-d'),
                    $row->due_date?->format('Y-m-d'),
                    $late !== null && $late < 0 ? abs($late) : 0,
                    $row->foreign_currency,
                    (float) $row->foreign_amount,
                    (float) $row->amount_in_inr,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------------------------------------------
       The activity trail
       ------------------------------------------------------------------ */

    /**
     * Everything that has already happened to this vendor, newest first: the
     * ledger, the project rows, the documents and the comments. Four tabs
     * answer four questions; "what changed lately" is one question, so its
     * four lists are merged into one trail on the overview.
     *
     * The ledger is passed in because the money tab already queried it —
     * asking the database for the same rows twice to draw one card would be
     * the kind of quiet cost this module can avoid.
     */
    private function vendorActivity(Vendor $vendor, $paymentEntries, $projectProducts, $shipments): array
    {
        $events = [];

        foreach ($paymentEntries as $entry) {
            $events[] = [
                'at' => $entry->created_at ?: $entry->transaction_date,
                'icon' => $entry->transaction_type === 'credit' ? 'fa-solid fa-file-invoice-dollar' : 'fa-solid fa-arrow-up-right-dots',
                'title' => (string) $entry->particular,
                'meta' => ($entry->transaction_type === 'credit' ? 'Bill raised' : 'Payment made')
                    .' · '.$entry->categoryLabel().' · '.\App\Helpers\CommonHelper::amount($entry->foreign_amount, $entry->foreign_currency ?: 'RMB'),
                'url' => route('vendors.show', ['vendor' => $vendor, 'tab' => 'money']),
            ];
        }

        foreach ($projectProducts as $row) {
            $events[] = [
                'at' => $row->created_at,
                'icon' => 'fa-solid fa-diagram-project',
                'title' => (string) $row->product_name,
                'meta' => 'Mapped to '.($row->project?->project_number ?? 'a project').' · '.$row->statusLabel(),
                'url' => $row->project ? route('projects.show', $row->project) : route('vendors.show', ['vendor' => $vendor, 'tab' => 'procurement']),
            ];
        }

        foreach ($shipments as $shipment) {
            $events[] = [
                'at' => $shipment->created_at,
                'icon' => 'fa-solid fa-truck',
                'title' => (string) ($shipment->identity_name ?: $shipment->shipment_number ?? 'Shipment'),
                'meta' => 'Shipment · '.$shipment->statusLabel(),
                'url' => route('vendors.show', ['vendor' => $vendor, 'tab' => 'procurement']),
            ];
        }

        if (Schema::hasTable('vendor_attachments') && $vendor->relationLoaded('attachments')) {
            foreach ($vendor->attachments as $attachment) {
                $events[] = [
                    'at' => $attachment->created_at,
                    'icon' => 'fa-regular fa-folder-open',
                    'title' => (string) ($attachment->title ?: $attachment->original_name),
                    'meta' => 'Document filed',
                    'url' => route('vendors.show', ['vendor' => $vendor, 'tab' => 'documents']),
                ];
            }
        }

        if (Schema::hasTable('vendor_comments') && $vendor->relationLoaded('comments')) {
            foreach ($vendor->comments as $comment) {
                $events[] = [
                    'at' => $comment->created_at,
                    'icon' => $comment->is_pinned ? 'fa-solid fa-thumbtack' : 'fa-regular fa-comment',
                    'title' => \Illuminate\Support\Str::limit((string) $comment->body, 90),
                    'meta' => 'Comment by '.($comment->creator?->name ?: 'the internal team'),
                    'url' => route('vendors.show', ['vendor' => $vendor, 'tab' => 'comments']),
                ];
            }
        }

        return collect($events)
            ->filter(fn ($event) => $event['at'] !== null)
            ->sortByDesc(fn ($event) => $event['at']->timestamp)
            ->take(12)
            ->values()
            ->all();
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
            $entry->setAttribute('days_to_due', $this->daysUntil($entry->due_date));
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
                if (in_array($entry->entry_category, ['bill', 'order'], true)) {
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

    /** Approved POs and raised bills that may take money, plus POs still waiting on the checker. */
    private function vendorPurchaseDocuments(Vendor $vendor): array
    {
        if (! class_exists(PurchaseInvoice::class) || ! Schema::hasTable('purchase_invoices')) {
            return [collect(), collect()];
        }

        $documents = PurchaseInvoice::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $payable = $documents->filter(fn (PurchaseInvoice $document) => $document->canReceiveMoney())->values();
        $pending = $documents->filter(function (PurchaseInvoice $document) {
            return $document->isOrder()
                && ! $document->isSuperseded()
                && in_array($document->status, ['draft', 'sent'], true);
        })->values();

        return [$payable, $pending];
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

    /**
     * The next number in the vendor series: MP-VEN-001, MP-VEN-002, and on.
     *
     * One series for the whole file, not one per day — a supplier is looked up
     * by this number on a purchase order, so the number has to be a position in
     * the file rather than a date. The last code in the series is read and
     * incremented, and the loop skips anything already taken (including a
     * number somebody typed by hand), so two vendors created at the same minute
     * cannot share one.
     */
    private function makeVendorNumber(): string
    {
        $prefix = 'MP-VEN-';

        $last = Vendor::query()
            ->whereNotNull('vendor_number')
            ->where('vendor_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(vendor_number) DESC')
            ->orderByDesc('vendor_number')
            ->value('vendor_number');

        $next = $last ? ((int) preg_replace('/\D/', '', substr($last, strlen($prefix)))) + 1 : 1;
        $next = max($next, 1);

        do {
            $number = $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (Vendor::where('vendor_number', $number)->exists());

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