<?php

namespace App\Http\Controllers;

use App\Helpers\DateRanges;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\ClientPortalConversation;
use App\Models\ClientPortalDocument;
use App\Models\ClientPortalNotification;
use App\Models\ClientPortalUser;
use App\Models\SavedView;
use App\Models\SalesInvoice;
use App\Services\PartyStatement;
use App\Services\SavedViews;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientController extends Controller
{
    private const SHOW_TABS = [
        'overview' => 'Overview',
        'contacts' => 'Contacts',
        'addresses' => 'Addresses',
        'commercial' => 'Commercial',
        'kyc' => 'KYC',
        'portal' => 'Manage Portal',
        'notifications' => 'Notifications',
        'documents' => 'Documents',
        'invoices' => 'Invoices',
        'payments' => 'Payments',
        'statement' => 'Statement',
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if ($savedQuery = $this->resolveSavedView($request)) {
            return redirect()->route('clients.index', $savedQuery);
        }

        $search = $request->query('search');
        $search = is_string($search) ? trim($search) : '';
        $search = $search !== '' ? mb_substr($search, 0, 150) : null;

        $status = $request->query('status', 'all');
        if (! is_string($status) || ! array_key_exists($status, ['all' => 'All'] + Client::statusOptions())) {
            $status = 'all';
        }

        $type = $request->query('type', 'all');
        if (! is_string($type) || ! array_key_exists($type, ['all' => 'All'] + Client::typeOptions())) {
            $type = 'all';
        }

        $portalInstalled = Schema::hasTable('client_portal_users');
        $query = Client::query()
            ->when($portalInstalled, fn ($q) => $q->with('portalUser'))
            ->search($search)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('client_type', $type))
            ->latest('id');

        $clients = $query->paginate(25)->withQueryString();

        $statusCounts = Client::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'draft' => (int) ($statusCounts[Client::STATUS_DRAFT] ?? 0),
            'under_review' => (int) ($statusCounts[Client::STATUS_UNDER_REVIEW] ?? 0),
            'approved' => (int) ($statusCounts[Client::STATUS_APPROVED] ?? 0),
            'revision' => (int) ($statusCounts[Client::STATUS_REVISION] ?? 0),
            'rejected' => (int) ($statusCounts[Client::STATUS_REJECTED] ?? 0),
            'portal_enabled' => Schema::hasColumn('clients', 'portal_enabled')
                ? Client::where('portal_enabled', true)->count()
                : 0,
        ];

        return view('clients.index', [
            'clients' => $clients,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'statusOptions' => Client::statusOptions(),
            'typeOptions' => Client::typeOptions(),
            'currencyOptions' => Client::currencyOptions(),
            'portalInstalled' => $portalInstalled,
            'savedViews' => app(SavedViews::class)->forUser(Auth::id(), 'clients'),
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
            ->where('module', 'clients')
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
            'clients',
            $data['name'],
            $request->query(),
            $request->boolean('is_shared')
        );

        return back()->with('success', 'View "'.$data['name'].'" saved.');
    }

    public function destroySavedView(SavedView $savedView): RedirectResponse
    {
        abort_unless($savedView->module === 'clients' && (int) $savedView->user_id === (int) Auth::id(), 403);

        app(SavedViews::class)->delete((int) Auth::id(), (int) $savedView->id);

        return back()->with('success', 'Saved view removed.');
    }

    public function create(): View
    {
        $client = new Client([
            'client_number' => $this->makeClientNumber(),
            'client_type' => 'customer',
            'status' => Client::STATUS_DRAFT,
            'preferred_currency' => 'INR',
            'billing_country' => 'India',
            'shipping_country' => 'India',
        ]);

        return view('clients.form', $this->formData($client));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $client = DB::transaction(function () use ($data) {
            $data['client_number'] = $data['client_number'] ?: $this->makeClientNumber();
            $data['public_token'] = Str::random(48);
            $data['created_by'] = Auth::id();
            $data['shipping_same_as_billing'] = request()->boolean('shipping_same_as_billing');
            $this->copyBillingToShippingIfNeeded($data);

            return Client::create($data);
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    public function quickStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', 'in:customer,vendor,both'],
            'ceo_name' => ['nullable', 'string', 'max:255'],
            'ceo_email' => ['nullable', 'email', 'max:255'],
            'ceo_contact' => ['nullable', 'string', 'max:40'],
            'account_person_name' => ['nullable', 'string', 'max:255'],
            'account_person_email' => ['nullable', 'email', 'max:255'],
            'account_person_contact' => ['nullable', 'string', 'max:40'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = Client::create([
            ...$data,
            'client_number' => $this->makeClientNumber(),
            'public_token' => Str::random(48),
            'preferred_currency' => 'INR',
            'status' => Client::STATUS_DRAFT,
            'created_by' => Auth::id(),
            'kyc_submitted_at' => null,
        ]);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Quick client created successfully.');
    }

    public function show(Request $request, Client $client, PartyStatement $statements): View
    {
        $client->load('reviewer');
        $tab = $request->query('tab', 'overview');
        $tab = is_string($tab) && array_key_exists($tab, self::SHOW_TABS) ? $tab : 'overview';
        $portalInstalled = Schema::hasTable('client_portal_users');
        $portalUsers = $portalInstalled && $tab === 'portal'
            ? ClientPortalUser::query()->where('client_id', $client->id)->orderBy('id')->get()
            : collect();
        $portalUser = $portalUsers->first();

        $selectedUserId = $request->query('portal_user_id', session('portal_plain_password_user_id'));
        $credentialUser = $portalUsers->firstWhere('id', $selectedUserId) ?: $portalUser;
        $oneTimePassword = (int) session('portal_plain_password_user_id') === (int) ($credentialUser?->id)
            ? session('portal_plain_password')
            : null;
        $portalLoginUrl = \Illuminate\Support\Facades\Route::has('client-portal.login')
            ? route('client-portal.login')
            : null;
        $shareMessage = $this->portalShareMessage($client, $credentialUser, $oneTimePassword, $portalLoginUrl);

        $documentsAvailable = $portalInstalled && Schema::hasTable('client_portal_documents');
        $documents = $documentsAvailable && $tab === 'documents'
            ? ClientPortalDocument::query()
                ->where('client_id', $client->id)
                ->with('portalUser')
                ->latest('id')
                ->limit(100)
                ->get()
            : collect();

        $notificationsAvailable = $portalInstalled && Schema::hasTable('client_portal_notifications');
        $notifications = $notificationsAvailable && $tab === 'notifications'
            ? ClientPortalNotification::query()
                ->where('client_id', $client->id)
                ->with('portalUser')
                ->latest('id')
                ->limit(50)
                ->get()
            : collect();

        $supportCount = $portalInstalled && $tab === 'portal' && Schema::hasTable('client_portal_conversations')
            ? ClientPortalConversation::where('client_id', $client->id)->count()
            : 0;
        $invoiceData = $tab === 'invoices'
            ? $this->clientInvoiceData($request, $client, $statements)
            : [];
        $paymentData = $tab === 'payments'
            ? $this->clientPaymentData($request, $client, $statements)
            : [];
        $statementData = $tab === 'statement'
            ? $this->clientStatementData($request, $client, $statements)
            : [];

        return view('clients.show', [
            ...$this->formData($client),
            'portalInstalled' => $portalInstalled,
            'portalUser' => $portalUser,
            'portalUsers' => $portalUsers,
            'credentialUser' => $credentialUser,
            'oneTimePassword' => $oneTimePassword,
            'portalLoginUrl' => $portalLoginUrl,
            'shareMessage' => $shareMessage,
            'documentsAvailable' => $documentsAvailable,
            'documents' => $documents,
            'notificationsAvailable' => $notificationsAvailable,
            'notifications' => $notifications,
            'supportCount' => $supportCount,
            'tabs' => self::SHOW_TABS,
            'tab' => $tab,
            ...$invoiceData,
            ...$paymentData,
            ...$statementData,
        ]);
    }

    /** Client-scoped ERP invoices with optional filters for the invoices tab. */
    private function clientInvoiceData(Request $request, Client $client, PartyStatement $statements): array
    {
        $available = Schema::hasTable('sales_invoices');
        $search = $request->query('invoice_search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 150) : '';
        $dateFrom = DateRanges::normalise($request->query('invoice_date_from'));
        $dateTo = DateRanges::normalise($request->query('invoice_date_to'));
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $statusOptions = SalesInvoice::statusOptions();
        $typeOptions = SalesInvoice::typeOptions();
        $status = $request->query('invoice_status', 'all');
        $status = is_string($status) && array_key_exists($status, $statusOptions) ? $status : 'all';
        $type = $request->query('invoice_type', 'all');
        $type = is_string($type) && array_key_exists($type, $typeOptions) ? $type : 'all';
        $currencyOptions = $this->clientCurrencyOptions($client, $statements);
        $currency = $request->query('invoice_currency', 'all');
        $currency = is_string($currency) && in_array(strtoupper($currency), $currencyOptions, true)
            ? strtoupper($currency)
            : 'all';

        $query = $available
            ? SalesInvoice::query()
                ->where('client_id', $client->id)
                ->withReceived()
                ->when($search !== '', fn ($builder) => $builder->search($search))
                ->when($dateFrom, fn ($builder) => $builder->whereDate('invoice_date', '>=', $dateFrom))
                ->when($dateTo, fn ($builder) => $builder->whereDate('invoice_date', '<=', $dateTo))
                ->when($status !== 'all', fn ($builder) => $builder->where('status', $status))
                ->when($type !== 'all', fn ($builder) => $builder->where('invoice_type', $type))
                ->when($currency !== 'all', fn ($builder) => $builder->where('currency', $currency))
                ->latest('invoice_date')->latest('id')
            : null;
        $invoiceEntries = $query
            ? $query->paginate(25, ['*'], 'invoice_page')->withQueryString()
            : collect();

        return [
            'invoiceEntriesAvailable' => $available,
            'invoiceEntries' => $invoiceEntries,
            'invoiceSearch' => $search,
            'invoiceDateFrom' => $dateFrom,
            'invoiceDateTo' => $dateTo,
            'invoiceStatus' => $status,
            'invoiceStatusOptions' => $statusOptions,
            'invoiceType' => $type,
            'invoiceTypeOptions' => $typeOptions,
            'invoiceCurrency' => $currency,
            'invoiceCurrencyOptions' => $currencyOptions,
        ];
    }

    /** Client-linked cashflow rows, exposed as the payments tab. */
    private function clientPaymentData(Request $request, Client $client, PartyStatement $statements): array
    {
        $available = Schema::hasTable('cashflow_entries');
        $search = $request->query('payment_search');
        $search = is_string($search) ? mb_substr(trim($search), 0, 150) : '';
        $dateFrom = DateRanges::normalise($request->query('payment_date_from'));
        $dateTo = DateRanges::normalise($request->query('payment_date_to'));
        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $currencyOptions = $this->clientCurrencyOptions($client, $statements);
        $currency = $request->query('payment_currency', 'all');
        $currency = is_string($currency) && in_array(strtoupper($currency), $currencyOptions, true)
            ? strtoupper($currency)
            : 'all';
        $directionOptions = CashflowEntry::transactionTypeOptions();
        $direction = $request->query('payment_direction', 'all');
        $direction = is_string($direction) && array_key_exists($direction, $directionOptions) ? $direction : 'all';
        $statusOptions = CashflowEntry::accountingStatusOptions();
        $status = $request->query('payment_status', 'all');
        $status = is_string($status) && array_key_exists($status, $statusOptions) ? $status : 'all';
        $paymentModeOptions = CashflowEntry::paymentModeOptions();
        $paymentMode = $request->query('payment_mode', 'all');
        $paymentMode = is_string($paymentMode) && array_key_exists($paymentMode, $paymentModeOptions) ? $paymentMode : 'all';

        $query = null;
        $paymentEntries = collect();
        $paymentTotalsByCurrency = collect();
        if ($available) {
            $query = CashflowEntry::query()
                ->where(function ($party) use ($client) {
                    $party->where('client_id', $client->id);
                    if (Schema::hasColumn('cashflow_entries', 'related_party_type')
                        && Schema::hasColumn('cashflow_entries', 'related_party_name')) {
                        $party->orWhere(fn ($related) => $related
                            ->where('related_party_type', 'client')
                            ->where('related_party_name', $client->company_name));
                    }
                })
                ->when($search !== '', function ($builder) use ($search) {
                    $builder->where(fn ($nested) => $nested
                        ->where('particular', 'like', '%'.$search.'%')
                        ->orWhere('invoice_bill_number', 'like', '%'.$search.'%')
                        ->orWhere('bank_reference_number', 'like', '%'.$search.'%'));
                })
                ->when($dateFrom, fn ($builder) => $builder->whereDate('entry_date', '>=', $dateFrom))
                ->when($dateTo, fn ($builder) => $builder->whereDate('entry_date', '<=', $dateTo))
                ->when($currency !== 'all', fn ($builder) => $builder->where('currency', $currency))
                ->when($direction !== 'all', fn ($builder) => $builder->where('transaction_type', $direction))
                ->when($status !== 'all', fn ($builder) => $builder->where('accounting_status', $status))
                ->when($paymentMode !== 'all', fn ($builder) => $builder->where('payment_mode', $paymentMode));

            $paymentTotalsByCurrency = (clone $query)
                ->reorder()
                ->select('currency')
                ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(credit_amount), 0) as credits, COALESCE(SUM(debit_amount), 0) as debits')
                ->groupBy('currency')
                ->orderBy('currency')
                ->get();
            $paymentEntries = (clone $query)
                ->with(['account', 'category'])
                ->latest('entry_date')->latest('id')
                ->paginate(25, ['*'], 'payment_page')
                ->withQueryString();
        }

        return [
            'paymentEntriesAvailable' => $available,
            'paymentEntries' => $paymentEntries,
            'paymentTotalsByCurrency' => $paymentTotalsByCurrency,
            'paymentSearch' => $search,
            'paymentDateFrom' => $dateFrom,
            'paymentDateTo' => $dateTo,
            'paymentCurrency' => $currency,
            'paymentCurrencyOptions' => $currencyOptions,
            'paymentDirection' => $direction,
            'paymentDirectionOptions' => $directionOptions,
            'paymentStatus' => $status,
            'paymentStatusOptions' => $statusOptions,
            'paymentMode' => $paymentMode,
            'paymentModeOptions' => $paymentModeOptions,
        ];
    }

    /** Keep legacy name-linked ledger rows available in currency filters too. */
    private function clientCurrencyOptions(Client $client, PartyStatement $statements): array
    {
        $preferred = $statements->defaultCurrency('client', (int) $client->id);
        $currencies = $statements->currencies('client', (int) $client->id);

        if (Schema::hasTable('cashflow_entries') && Schema::hasColumn('cashflow_entries', 'currency')) {
            $hasClientId = Schema::hasColumn('cashflow_entries', 'client_id');
            $hasRelatedParty = Schema::hasColumn('cashflow_entries', 'related_party_type')
                && Schema::hasColumn('cashflow_entries', 'related_party_name');

            if ($hasClientId || $hasRelatedParty) {
                $legacyCurrencies = CashflowEntry::query()
                    ->where(function ($party) use ($client, $hasClientId, $hasRelatedParty) {
                        if ($hasClientId) {
                            $party->where('client_id', $client->id);
                        }
                        if ($hasRelatedParty) {
                            $relatedParty = fn ($related) => $related
                                ->where('related_party_type', 'client')
                                ->where('related_party_name', $client->company_name);

                            $hasClientId ? $party->orWhere($relatedParty) : $party->where($relatedParty);
                        }
                    })
                    ->whereNotNull('currency')
                    ->select('currency')
                    ->distinct()
                    ->pluck('currency')
                    ->all();

                $currencies = array_merge($currencies, $legacyCurrencies);
            }
        }

        $currencies = array_values(array_unique(array_filter(array_map(
            fn ($currency) => strtoupper(trim((string) $currency)),
            $currencies
        ))));
        $otherCurrencies = array_values(array_diff($currencies, [$preferred]));
        sort($otherCurrencies);

        return array_merge([$preferred], $otherCurrencies);
    }

    /** Build the same live, currency-aware ledger statement used elsewhere. */
    private function clientStatementData(Request $request, Client $client, PartyStatement $statements): array
    {
        $presets = DateRanges::presets();
        $period = $request->query('period');
        $period = is_string($period) ? $period : null;
        $dateFrom = DateRanges::normalise($request->query('date_from'));
        $dateTo = DateRanges::normalise($request->query('date_to'));

        if ($period === 'all') {
            $dateFrom = $dateTo = null;
        } elseif ($period !== null && isset($presets[$period])) {
            $dateFrom = $presets[$period]['from'];
            $dateTo = $presets[$period]['to'];
        } elseif (! $dateFrom && ! $dateTo) {
            $dateFrom = $presets['this_month']['from'];
            $dateTo = $presets['this_month']['to'];
        }

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $currencyOptions = $this->clientCurrencyOptions($client, $statements);
        $defaultCurrency = $statements->defaultCurrency('client', (int) $client->id);
        $requestedCurrency = $request->query('currency');
        $currency = strtoupper(is_string($requestedCurrency) ? trim($requestedCurrency) : $defaultCurrency);
        if ($currency === '' || ! in_array($currency, $currencyOptions, true)) {
            $currency = $defaultCurrency;
        }

        $statement = $statements->build('client', (int) $client->id, $dateFrom, $dateTo, [
            'currency' => $currency,
            'ageing' => false,
        ]);
        abort_if($statement === null, 404);

        $activeRange = ($dateFrom || $dateTo) ? DateRanges::keyOf($dateFrom, $dateTo) : 'all';
        $periodKey = in_array($period, array_merge(array_keys($presets), ['all', 'custom']), true)
            ? $period
            : ($activeRange ?: 'custom');

        return [
            'statement' => $statement,
            'currencyOptions' => $currencyOptions,
            'currency' => $currency,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dateRangeLabels' => DateRanges::LABELS,
            'periodOptions' => DateRanges::LABELS + ['all' => 'All time', 'custom' => 'Custom dates'],
            'periodKey' => $periodKey,
        ];
    }

    private function portalShareMessage(Client $client, ?ClientPortalUser $portalUser, ?string $plainPassword, ?string $loginUrl): string
    {
        if (! $portalUser || ! $loginUrl) {
            return '';
        }

        $message = "Hello ".$client->company_name.",\n\nYour MissPack Client Portal is ready.\nLogin URL: ".$loginUrl."\nUsername: ".$portalUser->username;
        $message .= $plainPassword
            ? "\nOne-time Password: ".$plainPassword
            : "\nPassword: The latest one-time password shared by MissPack.";
        $message .= "\n\nPlease login and change your password.\nMissPack - Packed Perfect";

        return $message;
    }

    public function edit(Client $client): View
    {
        return view('clients.form', $this->formData($client));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validatedData($request, $client);
        $data['shipping_same_as_billing'] = $request->boolean('shipping_same_as_billing');
        $this->copyBillingToShippingIfNeeded($data);

        $client->update($data);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    public function sendKyc(Client $client): RedirectResponse
    {
        $client->update(['kyc_sent_at' => now()]);

        return back()->with('success', 'KYC link marked as sent. Copy and share the public link with the client.');
    }

    public function updateStatus(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,under_review,approved,rejected,revision'],
            'revision_note' => ['required_if:status,revision', 'nullable', 'string', 'max:5000'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:5000'],
        ]);

        $update = [
            'status' => $data['status'],
            'kyc_reviewed_at' => now(),
            'kyc_reviewed_by' => Auth::id(),
        ];

        if ($data['status'] === Client::STATUS_APPROVED) {
            $update['revision_note'] = null;
            $update['rejection_reason'] = null;
        }

        if ($data['status'] === Client::STATUS_REVISION) {
            $update['revision_note'] = $data['revision_note'] ?? null;
            $update['rejection_reason'] = null;
        }

        if ($data['status'] === Client::STATUS_REJECTED) {
            $update['rejection_reason'] = $data['rejection_reason'] ?? null;
            $update['revision_note'] = null;
        }

        $client->update($update);

        return back()->with('success', 'Client KYC status updated successfully.');
    }

    public function publicKyc(string $token): View
    {
        $client = Client::where('public_token', $token)->firstOrFail();

        return view('clients.kyc', [
            'client' => $client,
            'statusOptions' => Client::statusOptions(),
            'typeOptions' => Client::typeOptions(),
            'currencyOptions' => Client::currencyOptions(),
            'readonly' => ! $client->isPublicKycEditable(),
        ]);
    }

    public function submitKyc(Request $request, string $token): RedirectResponse
    {
        $client = Client::where('public_token', $token)->firstOrFail();

        if (! $client->isPublicKycEditable()) {
            return back()->with('error', 'This KYC form is not editable at the moment.');
        }

        $data = $this->validatedData($request, $client, true);
        $data['status'] = Client::STATUS_UNDER_REVIEW;
        $data['kyc_submitted_at'] = now();
        $data['revision_note'] = null;
        $data['rejection_reason'] = null;
        $data['shipping_same_as_billing'] = $request->boolean('shipping_same_as_billing');
        $this->copyBillingToShippingIfNeeded($data);

        $client->update($data);

        return redirect()
            ->route('clients.publicKyc', $client->public_token)
            ->with('success', 'KYC form submitted successfully. It is now under review.');
    }

    private function validatedData(Request $request, ?Client $client = null, bool $public = false): array
    {
        $clientId = $client?->id ?? 'NULL';

        return $request->validate([
            'client_number' => ['nullable', 'string', 'max:255', 'unique:clients,client_number,'.$clientId],
            'company_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', 'in:customer,vendor,both'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'status' => [$public ? 'nullable' : 'required', 'in:draft,under_review,approved,rejected,revision'],

            'ceo_name' => [$public ? 'required' : 'nullable', 'string', 'max:255'],
            'ceo_email' => [$public ? 'required' : 'nullable', 'email', 'max:255'],
            'ceo_contact' => [$public ? 'required' : 'nullable', 'string', 'max:40'],

            'account_person_name' => ['nullable', 'string', 'max:255'],
            'account_person_email' => ['nullable', 'email', 'max:255'],
            'account_person_contact' => ['nullable', 'string', 'max:40'],

            'marketing_person_name' => ['nullable', 'string', 'max:255'],
            'marketing_person_email' => ['nullable', 'email', 'max:255'],
            'marketing_person_contact' => ['nullable', 'string', 'max:40'],

            'dispatch_person_name' => ['nullable', 'string', 'max:255'],
            'dispatch_person_email' => ['nullable', 'email', 'max:255'],
            'dispatch_person_contact' => ['nullable', 'string', 'max:40'],

            'billing_address' => [$public ? 'required' : 'nullable', 'string'],
            'billing_city' => [$public ? 'required' : 'nullable', 'string', 'max:255'],
            'billing_state' => [$public ? 'required' : 'nullable', 'string', 'max:255'],
            'billing_country' => [$public ? 'required' : 'nullable', 'string', 'max:255'],
            'billing_pincode' => [$public ? 'required' : 'nullable', 'string', 'max:30'],

            'shipping_address' => ['nullable', 'string'],
            'shipping_city' => ['nullable', 'string', 'max:255'],
            'shipping_state' => ['nullable', 'string', 'max:255'],
            'shipping_country' => ['nullable', 'string', 'max:255'],
            'shipping_pincode' => ['nullable', 'string', 'max:30'],
            'shipping_same_as_billing' => ['nullable', 'boolean'],

            'gstin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'tan' => ['nullable', 'string', 'max:20'],
            'cin' => ['nullable', 'string', 'max:255'],
            'msme_number' => ['nullable', 'string', 'max:255'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'swift_code' => ['nullable', 'string', 'max:255'],

            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'preferred_currency' => ['nullable', 'in:INR,USD,RMB'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function copyBillingToShippingIfNeeded(array &$data): void
    {
        if (! ($data['shipping_same_as_billing'] ?? false)) {
            return;
        }

        $data['shipping_address'] = $data['billing_address'] ?? null;
        $data['shipping_city'] = $data['billing_city'] ?? null;
        $data['shipping_state'] = $data['billing_state'] ?? null;
        $data['shipping_country'] = $data['billing_country'] ?? null;
        $data['shipping_pincode'] = $data['billing_pincode'] ?? null;
    }

    private function makeClientNumber(): string
    {
        $prefix = 'CL-';
        $next = str_pad((string) (Client::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);
        $number = $prefix.$next;

        while (Client::where('client_number', $number)->exists()) {
            $next = str_pad((string) ((int) $next + 1), 4, '0', STR_PAD_LEFT);
            $number = $prefix.$next;
        }

        return $number;
    }

    private function formData(Client $client): array
    {
        return [
            'client' => $client,
            'statusOptions' => Client::statusOptions(),
            'typeOptions' => Client::typeOptions(),
            'currencyOptions' => Client::currencyOptions(),
            'contactGroups' => [
                ['title' => 'CEO / Director', 'name' => 'ceo_name', 'email' => 'ceo_email', 'phone' => 'ceo_contact'],
                ['title' => 'Accounts', 'name' => 'account_person_name', 'email' => 'account_person_email', 'phone' => 'account_person_contact'],
                ['title' => 'Marketing / Purchase', 'name' => 'marketing_person_name', 'email' => 'marketing_person_email', 'phone' => 'marketing_person_contact'],
                ['title' => 'Inward Dispatch', 'name' => 'dispatch_person_name', 'email' => 'dispatch_person_email', 'phone' => 'dispatch_person_contact'],
            ],
        ];
    }
}
