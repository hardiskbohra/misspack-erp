<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
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
        ]);
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

    public function show(Client $client): View
    {
        $client->load('reviewer');
        $portalInstalled = Schema::hasTable('client_portal_users');
        $portalUser = $portalInstalled ? $client->portalUser()->first() : null;

        return view('clients.show', [
            ...$this->formData($client),
            'portalInstalled' => $portalInstalled,
            'portalUser' => $portalUser,
        ]);
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
