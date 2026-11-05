<div class="vendor-tab-stack">
    @if(session('vendor_portal_credentials'))
        @php
            $credentials = session('vendor_portal_credentials');
        @endphp
        <section class="master-card master-detail-section vendor-portal-secret" role="status">
            <div class="master-section-header"><div><p class="master-eyebrow">Copy now</p><h2>One-time vendor credentials</h2></div></div>
            <div class="master-facts master-facts--3">
                <div class="master-fact"><span>Login URL</span><strong>{{ $credentials['url'] }}</strong></div>
                <div class="master-fact"><span>Username</span><strong>{{ $credentials['username'] }}</strong></div>
                <div class="master-fact"><span>One-time password</span><strong>{{ $credentials['password'] }}</strong></div>
            </div>
            <p class="master-hint">The password is shown only in this response. The vendor must replace it on first sign-in.</p>
        </section>
    @endif

    @if(session('vendor_share_url'))
        <section class="master-card master-detail-section vendor-share-created" role="status">
            <div class="master-section-header"><div><p class="master-eyebrow">Secure link ready</p><h2>Copy this expiring URL</h2></div></div>
            <div class="vendor-share-copy"><code>{{ session('vendor_share_url') }}</code></div>
        </section>
    @endif

    <section class="master-card master-detail-section">
        <div class="master-section-header">
            <div><p class="master-eyebrow">Authenticated workspace</p><h2>Vendor portal access</h2></div>
            <form method="POST" action="{{ route('vendors.portal.enable', $vendor) }}">@csrf @method('PATCH')
                <button class="master-btn {{ $vendor->portal_enabled ? 'master-btn-light' : 'master-btn-primary' }}" type="submit"><i class="fa-solid fa-power-off"></i> {{ $vendor->portal_enabled ? 'Disable portal' : 'Enable portal' }}</button>
            </form>
        </div>
        <div class="master-facts master-facts--3">
            <div class="master-fact"><span>Portal status</span><strong>{{ $vendor->portal_enabled ? 'Enabled' : 'Disabled' }}</strong></div>
            <div class="master-fact"><span>Portal users</span><strong>{{ $portalUsers->count() }}</strong></div>
            <div class="master-fact"><span>Login address</span><strong>{{ route('public-links.vendor-login') }}</strong></div>
        </div>
    </section>

    <div class="master-detail-grid">
        <section class="master-card master-detail-section">
            <div class="master-section-header"><div><p class="master-eyebrow">People</p><h2>Portal users</h2></div></div>
            <form method="POST" action="{{ route('vendors.portal.users.store', $vendor) }}" class="master-form-grid two-col">@csrf
                <div class="master-field"><label class="master-label" for="portalUserName">Name</label><input class="master-input" id="portalUserName" name="name" required></div>
                <div class="master-field"><label class="master-label" for="portalUsername">Username</label><input class="master-input" id="portalUsername" name="username" required></div>
                <div class="master-field"><label class="master-label" for="portalUserEmail">Email</label><input class="master-input" id="portalUserEmail" name="email" type="email"></div>
                <div class="master-field"><label class="master-label" for="portalUserMobile">Mobile</label><input class="master-input" id="portalUserMobile" name="mobile"></div>
                <div class="master-form-actions"><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-user-plus"></i> Create portal user</button></div>
            </form>
            <div class="vendor-portal-rows">
                @forelse($portalUsers as $portalUser)
                    <div class="vendor-portal-row"><span><strong>{{ $portalUser->displayName() }}</strong><small>{{ $portalUser->username }} · {{ $portalUser->last_login_at ? 'Last signed in '.$portalUser->last_login_at->diffForHumans() : 'Never signed in' }}</small></span><span class="master-badge">{{ $portalUser->is_active ? 'Active' : 'Inactive' }}</span><form method="POST" action="{{ route('vendors.portal.users.reset', [$vendor, $portalUser]) }}">@csrf<button class="master-btn master-btn-light" type="submit">Reset password</button></form><form method="POST" action="{{ route('vendors.portal.users.toggle', [$vendor, $portalUser]) }}">@csrf @method('PATCH')<button class="master-btn master-btn-light" type="submit">{{ $portalUser->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>
                @empty
                    <div class="master-empty-state"><i class="fa-solid fa-user-lock" aria-hidden="true"></i><p>No portal users. Create a named login for the vendor’s authorised contact.</p></div>
                @endforelse
            </div>
        </section>

        <section class="master-card master-detail-section">
            <div class="master-section-header"><div><p class="master-eyebrow">Accountless access</p><h2>Create secure share link</h2></div></div>
            <form method="POST" action="{{ route('vendors.shares.store', $vendor) }}" class="master-form-grid">@csrf
                <div class="master-field"><label class="master-label" for="shareLabel">Recipient / purpose</label><input class="master-input" id="shareLabel" name="label" required placeholder="Production team PO review"></div>
                <div class="master-field"><label class="master-label" for="shareResource">Share</label><select class="master-select" id="shareResource" name="resource_key" required><option value="workspace">Vendor workspace summary</option><optgroup label="Purchase documents">@foreach($portalPurchaseDocuments as $document)<option value="purchase_invoice:{{ $document->id }}">{{ $document->invoice_number }} · {{ $document->typeLabel() }}</option>@endforeach</optgroup><optgroup label="Shipments">@foreach($portalShipments as $shipment)<option value="shipment:{{ $shipment->id }}">{{ $shipment->shipment_number }}</option>@endforeach</optgroup><optgroup label="Packaging lists">@foreach($portalPackagingLists as $packing)<option value="packaging_list:{{ $packing->id }}">{{ $packing->packing_number }}</option>@endforeach</optgroup></select></div>
                <div class="master-field"><label class="master-label" for="shareExpiry">Expires after</label><select class="master-select" id="shareExpiry" name="expiry_days">@foreach([1,3,7,14,30,60,90] as $days)<option value="{{ $days }}" @selected($days === 14)>{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}</option>@endforeach</select></div>
                <label class="master-choice"><input type="checkbox" name="allow_updates" value="1"><span>Allow status updates when a purchase document is selected</span></label>
                <div class="master-form-actions"><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-link"></i> Create secure link</button></div>
            </form>
        </section>
    </div>

    <section class="master-card master-detail-section">
        <div class="master-section-header"><div><p class="master-eyebrow">Collaboration inbox</p><h2>Vendor updates</h2></div><span class="master-badge">{{ $portalUpdates->count() }} recent</span></div>
        <form method="POST" action="{{ route('vendors.portal.updates.store', $vendor) }}" class="vendor-update-compose">@csrf
            <div class="master-field"><label class="master-label" for="officeUpdateDocument">Purchase document</label><select class="master-select" id="officeUpdateDocument" name="purchase_invoice_id"><option value="">General vendor update</option>@foreach($portalPurchaseDocuments as $document)<option value="{{ $document->id }}">{{ $document->invoice_number }} · {{ $document->typeLabel() }}</option>@endforeach</select></div>
            <div class="master-field"><label class="master-label" for="officeUpdateStatus">Update type</label><select class="master-select" id="officeUpdateStatus" name="status">@foreach(\App\Models\VendorCollaborationUpdate::STATUSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="master-field"><label class="master-label" for="officeUpdateDate">Expected date</label><input class="master-input" id="officeUpdateDate" name="expected_date" type="date"></div>
            <div class="master-field full"><label class="master-label" for="officeUpdateMessage">Message visible to vendor</label><textarea class="master-textarea" id="officeUpdateMessage" name="message" rows="3" required></textarea></div>
            <div class="master-form-actions"><button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Share update</button></div>
        </form>
        <div class="vendor-update-register">
            @forelse($portalUpdates as $update)
                <div class="vendor-update-row"><span class="vendor-update-marker"><i class="fa-solid fa-message" aria-hidden="true"></i></span><span><strong>{{ $update->statusLabel() }}</strong><small>{{ $update->authorLabel() }} · {{ $update->created_at->diffForHumans() }}@if($update->purchaseInvoice) · {{ $update->purchaseInvoice->invoice_number }}@endif</small><p>{{ $update->message }}</p></span>@if($update->expected_date)<span class="master-badge">Expected {{ $update->expected_date->format('d M Y') }}</span>@endif</div>
            @empty
                <div class="master-empty-state"><i class="fa-regular fa-comments" aria-hidden="true"></i><p>No vendor updates have been submitted yet.</p></div>
            @endforelse
        </div>
    </section>

    <section class="master-card master-detail-section">
        <div class="master-section-header"><div><p class="master-eyebrow">Access register</p><h2>Secure share links</h2></div><span class="master-badge">{{ $shareLinks->count() }} links</span></div>
        <div class="vendor-share-register">
            @forelse($shareLinks as $share)
                <div class="vendor-share-row"><span><strong>{{ $share->label }}</strong><small>{{ \App\Models\VendorShareLink::RESOURCE_TYPES[$share->resource_type] ?? $share->resource_type }} · expires {{ $share->expires_at?->format('d M Y, H:i') ?: 'never' }}</small></span><span class="master-badge">{{ $share->stateLabel() }}</span><span><strong>{{ $share->views }}</strong><small>views</small></span><a class="master-btn master-btn-light" href="{{ $share->publicUrl() }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Open</a>@if(!$share->revoked_at)<form method="POST" action="{{ route('vendors.shares.revoke', [$vendor, $share]) }}">@csrf @method('PATCH')<button class="master-btn master-btn-danger" type="submit">Revoke</button></form>@endif</div>
            @empty
                <div class="master-empty-state"><i class="fa-solid fa-link-slash" aria-hidden="true"></i><p>No secure links. Create an expiring, revocable link for a vendor who does not need a portal login.</p></div>
            @endforelse
        </div>
    </section>
</div>
