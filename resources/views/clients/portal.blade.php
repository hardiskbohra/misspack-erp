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
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Credentials</p><h2>Primary portal user</h2></div><span class="cpa-badge {{ $portalUser && $portalUser->is_active ? 'active' : '' }}">{{ $portalUser && $portalUser->is_active ? 'Active' : 'Not Active' }}</span></div>
            <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid">
                @csrf
                @if($portalUser)<input type="hidden" name="portal_user_id" value="{{ $portalUser->id }}">@endif
                <div class="master-field"><label class="master-label">Name</label><input class="master-input" name="name" value="{{ old('name', $portalUser->name ?? ($client->account_person_name ?: $client->company_name)) }}"></div>
                <div class="master-field"><label class="master-label">Username *</label><input class="master-input" name="username" required value="{{ old('username', $portalUser->username ?? \Illuminate\Support\Str::slug($client->brand_name ?: $client->company_name, '')) }}"></div>
                <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="email" value="{{ old('email', $portalUser->email ?? ($client->account_person_email ?: $client->ceo_email)) }}"></div>
                <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="mobile" value="{{ old('mobile', $portalUser->mobile ?? ($client->account_person_contact ?: $client->ceo_contact)) }}"></div>
                <div class="master-field"><label class="master-label">Manual One-time Password</label><input class="master-input" name="password" placeholder="Leave blank to keep existing / auto generate"></div>
                <div class="cpa-checks">
                    <label class="master-check"><input type="checkbox" name="generate_password" value="1" {{ ! $portalUser ? 'checked' : '' }}> Generate Password</label>
                    <input type="hidden" name="portal_enabled" value="0"><label class="master-check"><input type="checkbox" name="portal_enabled" value="1" {{ old('portal_enabled', $portalUser->portal_enabled ?? true) ? 'checked' : '' }}> Portal Enabled</label>
                    <input type="hidden" name="is_active" value="0"><label class="master-check"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $portalUser->is_active ?? true) ? 'checked' : '' }}> Active User</label>
                    <input type="hidden" name="must_change_password" value="0"><label class="master-check"><input type="checkbox" name="must_change_password" value="1" {{ old('must_change_password', $portalUser->must_change_password ?? true) ? 'checked' : '' }}> Must Change Password</label>
                </div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Save Portal Credentials</button></div>
            </form>

            @if(session('portal_plain_password') && (int) session('portal_plain_password_user_id') === (int) ($credentialUser?->id))
                <div class="cpa-password-box">
                    <span>One-time password for {{ $credentialUser->displayName() }}</span>
                    <strong id="plainPassword">{{ session('portal_plain_password') }}</strong>
                    <button class="master-btn master-btn-soft master-btn-sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('plainPassword').innerText)">Copy Password</button>
                </div>
            @endif

            @if($portalUser)
                <form method="POST" action="{{ route('clients.portal.users.resetPassword', [$client, $portalUser]) }}" style="margin-top:12px;">@csrf<button class="master-btn master-btn-soft" type="submit">Reset Primary Password</button></form>
            @endif
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Share</p><h2>Share Login Details</h2></div></div>
            @if($portalUsers->isNotEmpty())
                <form method="GET" action="{{ route('clients.portal.show', $client) }}" class="master-field"><label class="master-label" for="share-portal-user">Choose portal user</label><select class="master-select" id="share-portal-user" name="portal_user_id" onchange="this.form.submit()">@foreach($portalUsers as $user)<option value="{{ $user->id }}" @selected((int) $credentialUser?->id === (int) $user->id)>{{ $user->displayName() }} · {{ $user->username }}</option>@endforeach</select></form>
            @endif
            @if($credentialUser)
                <div class="master-field"><label class="master-label">Sharing credentials for</label><input class="master-input" readonly value="{{ $credentialUser->displayName() }} · {{ $credentialUser->username }}"></div>
                <div class="master-field"><label class="master-label">Login URL</label><input class="master-input" readonly id="portalLoginUrl" value="{{ $loginUrl }}"></div>
                <div class="master-field"><label class="master-label">Share Message</label><textarea class="master-textarea" readonly id="shareMessage" rows="8">{{ $shareMessage }}</textarea></div>
                <div class="cpa-actions-wrap">
                    <button type="button" class="master-btn master-btn-soft" onclick="navigator.clipboard.writeText(document.getElementById('shareMessage').value)">Copy Message</button>
                    <a class="master-btn master-btn-green" target="_blank" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', (string) ($credentialUser->mobile ?: $client->account_person_contact ?: $client->ceo_contact)) }}?text={{ urlencode($shareMessage) }}">Share WhatsApp</a>
                    <a class="master-btn master-btn-light-dark" href="mailto:{{ $credentialUser->email }}?subject={{ urlencode('MissPack Client Portal Login') }}&body={{ urlencode($shareMessage) }}">Share Email</a>
                    <form method="POST" action="{{ route('clients.portal.users.markShared', [$client, $credentialUser]) }}">@csrf<button class="master-btn master-btn-primary">Mark Shared</button></form>
                </div>
                <p class="cpa-muted">Last shared with {{ $credentialUser->displayName() }}: {{ $credentialUser->invitation_sent_at?->format('d M Y, h:i A') ?: 'Not marked' }}</p>
            @else
                <div class="cpa-empty">Create credentials first to share login details.</div>
            @endif
        </div>
    </div>

    <div class="cpa-grid cpa-team-grid" style="margin-top:18px;">
        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Team access</p><h2>Additional portal users</h2></div><span class="cpa-badge">{{ max($portalUsers->count() - 1, 0) }}</span></div>
            @forelse($portalUsers->skip(1) as $teamUser)
                <div class="cpa-secondary-user">
                    <div class="cpa-secondary-user-head">
                        <div><strong>{{ $teamUser->displayName() }}</strong><span>{{ $teamUser->username }} · {{ $teamUser->email ?: 'No email' }}</span></div>
                        <span class="cpa-badge {{ $teamUser->is_active && $teamUser->portal_enabled ? 'active' : '' }}">{{ $teamUser->is_active && $teamUser->portal_enabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid cpa-secondary-form">
                        @csrf
                        <input type="hidden" name="portal_user_id" value="{{ $teamUser->id }}">
                        <div class="master-field"><label class="master-label">Name</label><input class="master-input" name="name" value="{{ $teamUser->name }}" required></div>
                        <div class="master-field"><label class="master-label">Username</label><input class="master-input" name="username" value="{{ $teamUser->username }}" required></div>
                        <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="email" value="{{ $teamUser->email }}"></div>
                        <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="mobile" value="{{ $teamUser->mobile }}"></div>
                        <div class="master-field"><label class="master-label">Set temporary password</label><input class="master-input" type="password" name="password" minlength="12" placeholder="Leave blank to keep current"></div>
                        <div class="cpa-checks">
                            <input type="hidden" name="portal_enabled" value="0"><label class="master-check"><input type="checkbox" name="portal_enabled" value="1" {{ $teamUser->portal_enabled ? 'checked' : '' }}> Portal enabled</label>
                            <input type="hidden" name="is_active" value="0"><label class="master-check"><input type="checkbox" name="is_active" value="1" {{ $teamUser->is_active ? 'checked' : '' }}> Active user</label>
                            <input type="hidden" name="must_change_password" value="0"><label class="master-check"><input type="checkbox" name="must_change_password" value="1" {{ $teamUser->must_change_password ? 'checked' : '' }}> Require password change</label>
                        </div>
                        <div class="cpa-submit"><button class="master-btn master-btn-soft">Save user</button></div>
                    </form>
                    <div class="cpa-secondary-actions">
                        <form method="POST" action="{{ route('clients.portal.users.resetPassword', [$client, $teamUser]) }}">@csrf<button class="master-btn master-btn-light-dark master-btn-sm">Reset password</button></form>
                        <a class="master-btn master-btn-light-dark master-btn-sm" href="{{ route('clients.portal.show', ['client' => $client->id, 'portal_user_id' => $teamUser->id]) }}">Prepare share message</a>
                    </div>
                </div>
            @empty
                <div class="cpa-empty">Only the primary portal account is configured. Add additional users for finance, operations or management.</div>
            @endforelse
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Invite a colleague</p><h2>Add portal user</h2></div></div>
            <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="cpa-form-grid">
                @csrf
                <input type="hidden" name="create_user" value="1">
                <div class="master-field"><label class="master-label">Name *</label><input class="master-input" name="name" required></div>
                <div class="master-field"><label class="master-label">Username *</label><input class="master-input" name="username" required autocomplete="off"></div>
                <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email" name="email"></div>
                <div class="master-field"><label class="master-label">Mobile</label><input class="master-input" name="mobile"></div>
                <div class="master-field"><label class="master-label">Temporary password <span class="cpa-muted">(leave blank to generate)</span></label><input class="master-input" type="password" name="password" minlength="12" autocomplete="new-password"></div>
                <div class="cpa-checks">
                    <label class="master-check"><input type="checkbox" name="generate_password" value="1" checked> Generate temporary password</label>
                    <input type="hidden" name="portal_enabled" value="0"><label class="master-check"><input type="checkbox" name="portal_enabled" value="1" checked> Enable portal access</label>
                    <input type="hidden" name="is_active" value="0"><label class="master-check"><input type="checkbox" name="is_active" value="1" checked> Active user</label>
                    <input type="hidden" name="must_change_password" value="0"><label class="master-check"><input type="checkbox" name="must_change_password" value="1" checked> Require password change</label>
                </div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Create portal user</button></div>
            </form>
            <div class="cpa-support-shortcut"><strong>{{ $supportCount }} support {{ \Illuminate\Support\Str::plural('request', $supportCount) }}</strong><span>Review and respond to client questions from the portal inbox.</span><a class="master-btn master-btn-soft" href="{{ route('clients.portal.support.index', $client) }}">Open support inbox</a></div>
        </div>
    </div>

    <div class="cpa-card cpa-billing-primary" style="margin-top:18px;">
        <div class="cpa-section-head">
            <div><p class="cpa-eyebrow">Source of truth · ERP billing</p><h2>Sales invoices published to the portal</h2><p class="cpa-muted">New client billing should be created and shared from the sales invoice workflow.</p></div>
            <a class="master-btn master-btn-primary" href="{{ route('sales-invoices.index', ['client_id' => $client->id]) }}"><i class="fa-solid fa-file-invoice-dollar"></i> Manage sales invoices</a>
        </div>
        <div class="cpa-table-wrap">
            <table class="cpa-table"><thead><tr><th>Invoice</th><th>Date</th><th>Due date</th><th>Total</th><th>State</th><th>Portal</th><th></th></tr></thead><tbody>
                @forelse($salesInvoices as $salesInvoice)
                    <tr><td><strong>{{ $salesInvoice->invoice_number }}</strong><span>{{ $salesInvoice->typeLabel() }}</span></td><td>{{ optional($salesInvoice->invoice_date)->format('d M Y') ?: '—' }}</td><td>{{ optional($salesInvoice->due_date)->format('d M Y') ?: '—' }}</td><td>{{ \App\Helpers\CommonHelper::amount($salesInvoice->total_amount, $salesInvoice->currency) }}</td><td>{{ $salesInvoice->stateLabel() }}</td><td>{{ $salesInvoice->show_client_portal ? 'Visible' : 'Hidden' }}</td><td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('sales-invoices.show', $salesInvoice) }}">Manage</a></td></tr>
                @empty
                    <tr><td colspan="7"><div class="cpa-empty">No sales invoices are currently associated with this client.</div></td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>

    <div class="cpa-grid" style="margin-top:18px;">
        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Previous records</p><h2>Add legacy portal invoice</h2><p class="cpa-muted">Use only for existing/manual historical records. ERP sales invoices are listed above.</p></div></div>
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
                <div class="master-field full"><label class="master-label">Client-visible invoice note</label><textarea class="master-textarea" name="notes" maxlength="4000" placeholder="Only add text intended for the client."></textarea></div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Publish Invoice</button></div>
            </form>
        </div>

        <div class="cpa-card">
            <div class="cpa-section-head"><div><p class="cpa-eyebrow">Notify</p><h2>Send Notification</h2></div></div>
            <form method="POST" action="{{ route('clients.portal.notifications.store', $client) }}" class="cpa-form-grid">
                @csrf
                <div class="master-field full"><label class="master-label">Title *</label><input class="master-input" name="title" required></div>
                <div class="master-field"><label class="master-label">Type</label><select class="master-select" name="type"><option value="info">Info</option><option value="project">Project</option><option value="shipment">Shipment</option><option value="invoice">Invoice</option><option value="payment">Payment</option></select></div>
                <div class="master-field"><label class="master-label">Action URL</label><input class="master-input" name="action_url" placeholder="/client-portal/projects"></div>
                <div class="master-field full"><label class="master-label">Message</label><textarea class="master-textarea" name="message"></textarea></div>
                <div class="cpa-submit"><button class="master-btn master-btn-primary">Send Notification</button></div>
            </form>
        </div>
    </div>

    <div class="cpa-card" style="margin-top:18px;">
        <div class="cpa-section-head"><div><p class="cpa-eyebrow">Legacy records</p><h2>Portal invoices</h2></div></div>
        <div class="cpa-table-wrap"><table class="cpa-table"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Status</th><th>Public</th><th>Action</th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td><strong>{{ $invoice->invoice_number }}</strong><span>{{ $invoice->title }}</span></td><td>{{ optional($invoice->invoice_date)->format('d M Y') ?: '-' }}</td><td>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</td><td>{{ $invoice->statusLabel() }}</td><td>{{ $invoice->is_public_to_client ? 'Yes' : 'No' }}</td><td><form method="POST" action="{{ route('clients.portal.invoices.destroy', $invoice) }}" data-confirm="Delete invoice?">@csrf @method('DELETE')<button class="master-btn master-btn-soft master-btn-sm">Delete</button></form></td></tr>@empty<tr><td colspan="6"><div class="cpa-empty">No invoices added.</div></td></tr>@endforelse</tbody></table></div>
    </div>
</div>

    <div class="cpa-card" style="margin-top:18px;">
        <div class="cpa-section-head"><div><p class="cpa-eyebrow">Secure file room</p><h2>Client portal documents</h2><p class="cpa-muted">Files are served through authorization-checked downloads. Older public files remain compatible until migrated.</p></div><a class="master-btn master-btn-soft" href="{{ route('clients.portal.support.index', $client) }}">Support inbox · {{ $supportCount }}</a></div>
        <div class="cpa-table-wrap"><table class="cpa-table"><thead><tr><th>File</th><th>Category</th><th>Uploaded by</th><th>Client visible</th><th>Uploaded</th><th></th></tr></thead><tbody>
            @forelse($documents as $document)
                <tr><td><strong>{{ $document->title ?: $document->original_name }}</strong><span>{{ strtoupper($document->extension ?: 'FILE') }}</span></td><td>{{ $document->categoryLabel() }}</td><td>{{ $document->portalUser?->displayName() ?: 'MissPack team' }}</td><td>{{ $document->is_public_to_client ? 'Yes' : 'No' }}</td><td>{{ $document->created_at->format('d M Y') }}</td><td><a class="master-btn master-btn-soft master-btn-sm" href="{{ route('clients.portal.documents.file', $document) }}">Download</a></td></tr>
            @empty
                <tr><td colspan="6"><div class="cpa-empty">No portal documents are stored for this client yet.</div></td></tr>
            @endforelse
        </tbody></table></div>
    </div>

@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/clients.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/client-portal-workspace.css') }}">
@endpush
@endsection
