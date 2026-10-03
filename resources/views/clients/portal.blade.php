@extends('layouts.app')

@section('page-title', 'Client Portal Management')

@section('content')
<div class="client-portal-admin">
    <div class="cpa-hero">
        <div>
            <p class="cpa-eyebrow">Client Portal</p>
            <h1>{{ $client->company_name }}</h1>
            <p>Enable portal access, create login credentials, share one-time password, publish invoices and notify client.</p>
        </div>
        <div class="cpa-actions">
            <a href="{{ route('clients.show', $client) }}" class="master-btn master-btn-light">Back to Client</a>
            <a href="{{ $loginUrl }}" target="_blank" class="master-btn master-btn-primary">Open Portal Login</a>
        </div>
    </div>

    @if(session('success'))<div class="cpa-alert cpa-alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="cpa-alert cpa-alert-error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="cpa-alert cpa-alert-error"><strong>Please fix:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="cpa-grid">
        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Credentials</p><h2>Portal User</h2></div><span class="cpa-badge {{ $portalUser && $portalUser->is_active ? 'active' : '' }}">{{ $portalUser && $portalUser->is_active ? 'Active' : 'Not Active' }}</span></div>
            <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid">
                @csrf
                <div class="master-field"><label class="master-label">Name</label><input class="master-input" name="name" value="{{ old('name', $portalUser->name ?? ($client->account_person_name ?: $client->company_name)) }}"></div>
                <div class="master-field"><label class="master-label">Username *</label><input class="master-input" name="username" required value="{{ old('username', $portalUser->username ?? \Illuminate\Support\Str::slug($client->brand_name ?: $client->company_name, '')) }}"></div>
                <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="email" value="{{ old('email', $portalUser->email ?? ($client->account_person_email ?: $client->ceo_email)) }}"></div>
                <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="mobile" value="{{ old('mobile', $portalUser->mobile ?? ($client->account_person_contact ?: $client->ceo_contact)) }}"></div>
                <div class="master-field"><label class="master-label">Manual One-time Password</label><input class="master-input" name="password" placeholder="Leave blank to keep existing / auto generate"></div>
                <div class="cpa-checks">
                    <label class="master-check"><input type="checkbox" name="generate_password" value="1" {{ ! $portalUser ? 'checked' : '' }}> Generate Password</label>
                    <label class="master-check"><input type="checkbox" name="portal_enabled" value="1" {{ old('portal_enabled', $portalUser->portal_enabled ?? true) ? 'checked' : '' }}> Portal Enabled</label>
                    <label class="master-check"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $portalUser->is_active ?? true) ? 'checked' : '' }}> Active User</label>
                    <label class="master-check"><input type="checkbox" name="must_change_password" value="1" {{ old('must_change_password', $portalUser->must_change_password ?? true) ? 'checked' : '' }}> Must Change Password</label>
                </div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Save Portal Credentials</button></div>
            </form>

            @if(session('portal_plain_password'))
                <div class="cpa-password-box">
                    <span>Generated One-time Password</span>
                    <strong id="plainPassword">{{ session('portal_plain_password') }}</strong>
                    <button class="master-btn master-btn-soft master-btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('plainPassword').innerText)">Copy Password</button>
                </div>
            @endif

            @if($portalUser)
                <form method="POST" action="{{ route('clients.portal.resetPassword', $client) }}" style="margin-top:12px;">@csrf<button class="master-btn master-btn-soft" type="submit">Reset One-time Password</button></form>
            @endif
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Share</p><h2>Share Login Details</h2></div></div>
            @if($portalUser)
                <div class="master-field"><label class="master-label">Login URL</label><input class="master-input" readonly id="portalLoginUrl" value="{{ $loginUrl }}"></div>
                <div class="master-field"><label class="master-label">Share Message</label><textarea class="master-textarea" readonly id="shareMessage" rows="8">{{ $shareMessage }}</textarea></div>
                <div class="cpa-actions-wrap">
                    <button type="button" class="master-btn master-btn-soft" onclick="navigator.clipboard.writeText(document.getElementById('shareMessage').value)">Copy Message</button>
                    <a class="master-btn master-btn-green" target="_blank" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', (string) ($portalUser->mobile ?: $client->account_person_contact ?: $client->ceo_contact)) }}?text={{ urlencode($shareMessage) }}">Share WhatsApp</a>
                    <a class="master-btn master-btn-light-dark" href="mailto:{{ $portalUser->email }}?subject={{ urlencode('MissPack Client Portal Login') }}&body={{ urlencode($shareMessage) }}">Share Email</a>
                    <form method="POST" action="{{ route('clients.portal.markShared', $client) }}">@csrf<button class="master-btn master-btn-primary">Mark Shared</button></form>
                </div>
                <p class="cpa-muted">Last shared: {{ $client->portal_last_shared_at ? $client->portal_last_shared_at : 'Not marked' }}</p>
            @else
                <div class="cpa-empty">Create credentials first to share login details.</div>
            @endif
        </div>
    </div>

    <div class="cpa-grid" style="margin-top:18px;">
        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Invoices</p><h2>Publish Invoice</h2></div></div>
            <form method="POST" action="{{ route('clients.portal.invoices.store', $client) }}" enctype="multipart/form-data" class="cpa-form-grid">
                @csrf
                <div class="master-field"><label class="master-label">Invoice No.</label><input class="master-input" name="invoice_number" placeholder="Auto if blank"></div>
                <div class="master-field"><label class="master-label">Title</label><input class="master-input" name="title"></div>
                <div class="master-field"><label class="master-label">Invoice Date</label><input class="master-input" type="date" name="invoice_date" value="{{ now()->toDateString() }}"></div>
                <div class="master-field"><label class="master-label">Due Date</label><input class="master-input" type="date" name="due_date"></div>
                <div class="master-field"><label class="master-label">Currency</label><select class="master-select" name="currency"><option value="INR">INR</option><option value="USD">USD</option><option value="RMB">RMB</option></select></div>
                <div class="master-field"><label class="master-label">Total Amount *</label><input class="master-input" type="number" step="0.01" name="total_amount" required></div>
                <div class="master-field"><label class="master-label">Paid Amount</label><input class="master-input" type="number" step="0.01" name="paid_amount"></div>
                <div class="master-field"><label class="master-label">Status</label><select class="master-select" name="status">@foreach(\App\Models\ClientPortalInvoice::statusOptions() as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label">Invoice File</label><input class="master-input" type="file" name="file"></div>
                <label class="master-check"><input type="checkbox" name="is_public_to_client" value="1" checked> Show in Client Portal</label>
                <div class="master-field full"><label class="master-label">Notes</label><textarea class="master-textarea" name="notes"></textarea></div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Publish Invoice</button></div>
            </form>
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Notify</p><h2>Send Notification</h2></div></div>
            <form method="POST" action="{{ route('clients.portal.notifications.store', $client) }}" class="cpa-form-grid">
                @csrf
                <div class="master-field full"><label class="master-label">Title *</label><input class="master-input" name="title" required></div>
                <div class="master-field"><label class="master-label">Type</label><select class="master-select" name="type"><option value="info">Info</option><option value="project">Project</option><option value="shipment">Shipment</option><option value="invoice">Invoice</option><option value="payment">Payment</option></select></div>
                <div class="master-field"><label class="master-label">Action URL</label><input class="master-input" name="action_url" placeholder="Optional"></div>
                <div class="master-field full"><label class="master-label">Message</label><textarea class="master-textarea" name="message"></textarea></div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Send Notification</button></div>
            </form>
        </div>
    </div>

    <div class="cpa-card" style="margin-top:18px;">
        <div class="cpa-section-head"><div><p class="cpa-eyebrow">Records</p><h2>Portal Invoices</h2></div></div>
        <div class="cpa-table-wrap"><table class="cpa-table"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th><th>Public</th><th>Action</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number }}</strong><span>{{ $invoice->title }}</span></td><td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</td><td>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</td><td>{{ $invoice->statusLabel() }}</td><td>{{ $invoice->is_public_to_client ? 'Yes' : 'No' }}</td><td><form method="POST" action="{{ route('clients.portal.invoices.destroy', $invoice) }}" data-confirm="Delete invoice?">@csrf @method('DELETE')<button class="master-btn master-btn-soft master-btn-sm">Delete</button></form></td></tr>@empty<tr><td colspan="6"><div class="cpa-empty">No invoices added.</div></td></tr>@endforelse</tbody></table></div>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
@endpush
@endsection
