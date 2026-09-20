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
            <a href="{{ route('clients.show', $client) }}" class="cpa-btn cpa-btn-light">Back to Client</a>
            <a href="{{ $loginUrl }}" target="_blank" class="cpa-btn cpa-btn-primary">Open Portal Login</a>
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
                <div class="cpa-field"><label>Name</label><input name="name" value="{{ old('name', $portalUser->name ?? ($client->account_person_name ?: $client->company_name)) }}"></div>
                <div class="cpa-field"><label>Username *</label><input name="username" required value="{{ old('username', $portalUser->username ?? \Illuminate\Support\Str::slug($client->brand_name ?: $client->company_name, '')) }}"></div>
                <div class="cpa-field"><label>Email</label><input type="email" name="email" value="{{ old('email', $portalUser->email ?? ($client->account_person_email ?: $client->ceo_email)) }}"></div>
                <div class="cpa-field"><label>Mobile</label><input name="mobile" value="{{ old('mobile', $portalUser->mobile ?? ($client->account_person_contact ?: $client->ceo_contact)) }}"></div>
                <div class="cpa-field"><label>Manual One-time Password</label><input name="password" placeholder="Leave blank to keep existing / auto generate"></div>
                <div class="cpa-checks">
                    <label><input type="checkbox" name="generate_password" value="1" {{ ! $portalUser ? 'checked' : '' }}> Generate Password</label>
                    <label><input type="checkbox" name="portal_enabled" value="1" {{ old('portal_enabled', $portalUser->portal_enabled ?? true) ? 'checked' : '' }}> Portal Enabled</label>
                    <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $portalUser->is_active ?? true) ? 'checked' : '' }}> Active User</label>
                    <label><input type="checkbox" name="must_change_password" value="1" {{ old('must_change_password', $portalUser->must_change_password ?? true) ? 'checked' : '' }}> Must Change Password</label>
                </div>
                <div class="cpa-submit"><button class="cpa-btn cpa-btn-primary">Save Portal Credentials</button></div>
            </form>

            @if(session('portal_plain_password'))
                <div class="cpa-password-box">
                    <span>Generated One-time Password</span>
                    <strong id="plainPassword">{{ session('portal_plain_password') }}</strong>
                    <button class="cpa-btn cpa-btn-soft cpa-btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('plainPassword').innerText)">Copy Password</button>
                </div>
            @endif

            @if($portalUser)
                <form method="POST" action="{{ route('clients.portal.resetPassword', $client) }}" style="margin-top:12px;">@csrf<button class="cpa-btn cpa-btn-soft" type="submit">Reset One-time Password</button></form>
            @endif
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Share</p><h2>Share Login Details</h2></div></div>
            @if($portalUser)
                <div class="cpa-field"><label>Login URL</label><input readonly id="portalLoginUrl" value="{{ $loginUrl }}"></div>
                <div class="cpa-field"><label>Share Message</label><textarea readonly id="shareMessage" rows="8">{{ $shareMessage }}</textarea></div>
                <div class="cpa-actions-wrap">
                    <button type="button" class="cpa-btn cpa-btn-soft" onclick="navigator.clipboard.writeText(document.getElementById('shareMessage').value)">Copy Message</button>
                    <a class="cpa-btn cpa-btn-green" target="_blank" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', (string) ($portalUser->mobile ?: $client->account_person_contact ?: $client->ceo_contact)) }}?text={{ urlencode($shareMessage) }}">Share WhatsApp</a>
                    <a class="cpa-btn cpa-btn-light-dark" href="mailto:{{ $portalUser->email }}?subject={{ urlencode('MissPack Client Portal Login') }}&body={{ urlencode($shareMessage) }}">Share Email</a>
                    <form method="POST" action="{{ route('clients.portal.markShared', $client) }}">@csrf<button class="cpa-btn cpa-btn-primary">Mark Shared</button></form>
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
                <div class="cpa-field"><label>Invoice No.</label><input name="invoice_number" placeholder="Auto if blank"></div>
                <div class="cpa-field"><label>Title</label><input name="title"></div>
                <div class="cpa-field"><label>Invoice Date</label><input type="date" name="invoice_date" value="{{ now()->toDateString() }}"></div>
                <div class="cpa-field"><label>Due Date</label><input type="date" name="due_date"></div>
                <div class="cpa-field"><label>Currency</label><select name="currency"><option value="INR">INR</option><option value="USD">USD</option><option value="RMB">RMB</option></select></div>
                <div class="cpa-field"><label>Total Amount *</label><input type="number" step="0.01" name="total_amount" required></div>
                <div class="cpa-field"><label>Paid Amount</label><input type="number" step="0.01" name="paid_amount"></div>
                <div class="cpa-field"><label>Status</label><select name="status">@foreach(\App\Models\ClientPortalInvoice::statusOptions() as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="cpa-field"><label>Invoice File</label><input type="file" name="file"></div>
                <label class="cpa-check-inline"><input type="checkbox" name="is_public_to_client" value="1" checked> Show in Client Portal</label>
                <div class="cpa-field full"><label>Notes</label><textarea name="notes"></textarea></div>
                <div class="cpa-submit"><button class="cpa-btn cpa-btn-primary">Publish Invoice</button></div>
            </form>
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Notify</p><h2>Send Notification</h2></div></div>
            <form method="POST" action="{{ route('clients.portal.notifications.store', $client) }}" class="cpa-form-grid">
                @csrf
                <div class="cpa-field full"><label>Title *</label><input name="title" required></div>
                <div class="cpa-field"><label>Type</label><select name="type"><option value="info">Info</option><option value="project">Project</option><option value="shipment">Shipment</option><option value="invoice">Invoice</option><option value="payment">Payment</option></select></div>
                <div class="cpa-field"><label>Action URL</label><input name="action_url" placeholder="Optional"></div>
                <div class="cpa-field full"><label>Message</label><textarea name="message"></textarea></div>
                <div class="cpa-submit"><button class="cpa-btn cpa-btn-primary">Send Notification</button></div>
            </form>
        </div>
    </div>

    <div class="cpa-card" style="margin-top:18px;">
        <div class="cpa-section-head"><div><p class="cpa-eyebrow">Records</p><h2>Portal Invoices</h2></div></div>
        <div class="cpa-table-wrap"><table class="cpa-table"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th><th>Public</th><th>Action</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number }}</strong><span>{{ $invoice->title }}</span></td><td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</td><td>{{ $invoice->currency }} {{ number_format((float)$invoice->total_amount, 2) }}</td><td>{{ $invoice->statusLabel() }}</td><td>{{ $invoice->is_public_to_client ? 'Yes' : 'No' }}</td><td><form method="POST" action="{{ route('clients.portal.invoices.destroy', $invoice) }}" onsubmit="return confirm('Delete invoice?')">@csrf @method('DELETE')<button class="cpa-btn cpa-btn-soft cpa-btn-sm">Delete</button></form></td></tr>@empty<tr><td colspan="6"><div class="cpa-empty">No invoices added.</div></td></tr>@endforelse</tbody></table></div>
    </div>
</div>

<style>
.client-portal-admin{display:flex;flex-direction:column;gap:18px}.cpa-hero{background:linear-gradient(135deg,#4f83f1,#6366f1);border-radius:24px;padding:24px;color:#fff;display:flex;justify-content:space-between;gap:18px;box-shadow:0 18px 45px rgba(79,131,241,.22)}.cpa-eyebrow{margin:0 0 6px;text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:900;opacity:.78}.cpa-hero h1{margin:0;font-size:30px;font-weight:900}.cpa-hero p{margin:8px 0 0;opacity:.9}.cpa-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.cpa-btn{border:0;border-radius:14px;padding:10px 15px;font-weight:900;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;white-space:nowrap}.cpa-btn-primary{background:#ef4770;color:#fff;box-shadow:0 10px 24px rgba(239,71,112,.24)}.cpa-btn-light{background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.3)}.cpa-btn-light-dark{background:#f3f6fb;color:#17233b}.cpa-btn-soft{background:#eef3ff;color:#4f83f1}.cpa-btn-green{background:#e8fff7;color:#0e9f6e}.cpa-btn-sm{padding:8px 11px;font-size:12px;border-radius:12px}.cpa-alert{border-radius:16px;padding:13px 15px;font-weight:800}.cpa-alert-success{background:#e8fff7;color:#047857;border:1px solid #a7f3d0}.cpa-alert-error{background:#fff0f4;color:#be123c;border:1px solid #fecdd3}.cpa-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.cpa-card{background:#fff;border:1px solid #dfe7f3;border-radius:22px;box-shadow:0 14px 35px rgba(25,42,70,.08);padding:20px}.cpa-section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:14px}.cpa-section-head h2{margin:0}.cpa-badge{background:#f3f6fb;color:#536079;border-radius:999px;padding:7px 10px;font-size:12px;font-weight:900}.cpa-badge.active{background:#e8fff7;color:#047857}.cpa-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.cpa-field{display:flex;flex-direction:column;gap:7px}.cpa-field.full{grid-column:1/-1}.cpa-field label{font-size:12px;color:#536079;font-weight:900}.cpa-field input,.cpa-field select,.cpa-field textarea{width:100%;border:1px solid #d8e2ef;border-radius:13px;padding:11px;background:#fff;outline:none}.cpa-field textarea{min-height:92px}.cpa-checks{grid-column:1/-1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.cpa-checks label,.cpa-check-inline{display:flex;gap:8px;align-items:center;font-weight:800;color:#536079}.cpa-submit{grid-column:1/-1;display:flex;justify-content:flex-end}.cpa-password-box{margin-top:14px;background:#fff8ec;border:1px solid #fedf89;border-radius:16px;padding:14px}.cpa-password-box span{display:block;color:#7a4b08;font-weight:900;font-size:12px;text-transform:uppercase}.cpa-password-box strong{font-size:24px;display:inline-block;margin:7px 10px 7px 0}.cpa-actions-wrap{display:flex;gap:9px;flex-wrap:wrap;margin-top:12px}.cpa-muted{color:#687386;font-weight:700}.cpa-empty{padding:24px;border:1px dashed #d8deea;border-radius:16px;text-align:center;color:#687386;font-weight:800;background:#fbfcff}.cpa-table-wrap{overflow:auto}.cpa-table{width:100%;border-collapse:collapse;min-width:760px}.cpa-table th,.cpa-table td{padding:14px 16px;border-bottom:1px solid #dfe7f3;text-align:left}.cpa-table th{font-size:12px;text-transform:uppercase;color:#7d8aa0}.cpa-table span{display:block;color:#687386;font-size:12px}@media(max-width:991px){.cpa-grid{grid-template-columns:1fr}.cpa-hero{flex-direction:column}}@media(max-width:767px){.cpa-form-grid,.cpa-checks{grid-template-columns:1fr}.cpa-actions,.cpa-actions .cpa-btn,.cpa-actions-wrap .cpa-btn{width:100%}.cpa-submit .cpa-btn{width:100%}.cpa-card{padding:15px}}
</style>
@endsection
