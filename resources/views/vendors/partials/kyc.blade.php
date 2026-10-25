@php
    $manage=app(\App\Services\PermissionMatrix::class)->allows(auth()->user(),'purchase.manage');
    $canReview=\App\Services\VendorKyc::canReview($vendor,auth()->user());
    $kycLink=$vendor->kyc_public_token?route('vendors.publicKyc',$vendor->kyc_public_token):null;
    $submitted=$vendor->kyc_submitted_data ?? [];
@endphp
<div class="vendor-kyc">
    <section class="master-card master-card--flat vendor-kyc-card" aria-labelledby="vendor-kyc-status-heading">
        <h2 id="vendor-kyc-status-heading">KYC status</h2>
        <div class="master-facts">
            <div class="master-info"><span>Current status</span><strong>{{ $vendor->kycStatusLabel() }}</strong></div>
            <div class="master-info"><span>Submitted</span><strong>{{ $vendor->kyc_submitted_at?->format('d M Y, h:i A') ?: 'Not submitted' }}</strong></div>
            <div class="master-info"><span>Last reviewed</span><strong>{{ $vendor->kyc_reviewed_at?->format('d M Y, h:i A') ?: 'Not reviewed' }}</strong></div>
            <div class="master-info"><span>Reviewer</span><strong>{{ $vendor->kycReviewer?->name ?: 'Not reviewed' }}</strong></div>
        </div>
        <p class="master-sub">KYC approval is separate from operational {{ $vendor->statusLabel() }} status. Existing procurement and payments are unchanged.</p>
    </section>
    <section class="master-card master-card--flat vendor-kyc-card" aria-labelledby="vendor-kyc-link-heading">
        <h2 id="vendor-kyc-link-heading">KYC form link</h2>
        <p class="master-sub">Copy and share this secure link. No email is sent automatically. Rotating or revoking it disables the old link and its document access.</p>
        @if ($kycLink)
            <div class="vendor-kyc-link">
                <input class="master-input" id="vendorKycLink" type="text" readonly aria-label="Public vendor KYC link" value="{{ $kycLink }}">
                <button type="button" class="master-btn master-btn-soft" onclick="maCopy('vendorKycLink', 'KYC link')">Copy</button>
            </div>
            <a class="master-btn master-btn-light master-btn-sm" href="{{ $kycLink }}" target="_blank" rel="noopener noreferrer">Preview public KYC form</a>
            <p class="master-sub">{{ $vendor->kyc_sent_at?'Last marked sent '.$vendor->kyc_sent_at->format('d M Y, h:i A'):'Link has not been marked as sent' }}</p>
        @else
            <p class="master-sub">No active link. Create a link to invite this vendor.</p>
        @endif
        @if ($manage)
            <div class="vendor-kyc-actions">
                @foreach ($kycLink?['mark_sent'=>'Mark as sent','rotate'=>'Rotate link','revoke'=>'Revoke link']:['issue'=>'Create KYC link'] as $action=>$label)
                    <form method="POST" action="{{ route('vendors.kyc.link',$vendor) }}" @if(in_array($action,['rotate','revoke'],true)) data-confirm="{{ $action==='revoke'?'Revoke this link? Existing vendor access will stop.':'Replace this link? Share the new link with the vendor afterwards.' }}" data-confirm-title="{{ $label }}?" data-confirm-text="{{ $label }}" @endif>
                        @csrf<input type="hidden" name="action" value="{{ $action }}">
                        <button type="submit" class="master-btn {{ $action==='revoke'?'master-btn-danger':'master-btn-soft' }} master-btn-sm">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>
    <section class="master-card master-card--flat vendor-kyc-card vendor-kyc-wide" aria-labelledby="vendor-review-heading">
        <h2 id="vendor-review-heading">Review decision</h2>
        @if ($canReview)
            <p class="master-sub">Check the submitted details and documents below. Approval updates the live vendor identity and bank details. Add a reason for changes or rejection.</p>
            <form method="POST" action="{{ route('vendors.kyc.review',$vendor) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="kyc_version" value="{{ $vendor->kyc_version }}">
                <div class="master-field">
                    <label class="master-label" for="vendor_kyc_reason">Revision / rejection reason</label>
                    <textarea class="master-textarea" id="vendor_kyc_reason" name="reason" maxlength="5000" rows="3" placeholder="Tell the vendor which details need attention">{{ old('reason') }}</textarea>
                </div>
                <div class="vendor-kyc-actions">
                    <button class="master-btn master-btn-green" type="submit" name="decision" value="approved">Approve</button>
                    <button class="master-btn master-btn-orange" type="submit" name="decision" value="revision">Request changes</button>
                    <button class="master-btn master-btn-danger" type="submit" name="decision" value="rejected">Reject</button>
                </div>
            </form>
        @elseif ($manage && in_array($vendor->kyc_status,['approved','rejected'],true))
            <p class="master-sub">Need updated details? Reopen this KYC with a reason. The existing vendor record remains unchanged until a new submission is approved.</p>
            <form method="POST" action="{{ route('vendors.kyc.reopen',$vendor) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="kyc_version" value="{{ $vendor->kyc_version }}">
                <div class="master-field"><label class="master-label" for="vendor_kyc_reopen_reason">Reason for updated KYC</label>
                    <textarea class="master-textarea" id="vendor_kyc_reopen_reason" name="reason" required maxlength="5000" rows="3">{{ old('reason') }}</textarea></div>
                <div class="vendor-kyc-actions"><button class="master-btn master-btn-orange" type="submit">Request updated KYC</button></div>
            </form>
        @elseif ($vendor->kyc_status==='under_review' && $manage)
            <p class="master-sub">A different purchase manager must review this submission. The maker / record creator cannot approve their own vendor KYC.</p>
        @else
            <p class="master-sub">{{ $manage?'Review becomes available when the vendor submits their KYC.':'Read-only access. A different purchase manager can review submitted KYC.' }}</p>
        @endif
        @if ($vendor->kyc_revision_note)<p class="master-sub"><strong>Revision requested:</strong> {{ $vendor->kyc_revision_note }}</p>@endif
        @if ($vendor->kyc_rejection_reason)<p class="master-sub"><strong>Rejection reason:</strong> {{ $vendor->kyc_rejection_reason }}</p>@endif
    </section>
    <section class="master-card master-card--flat vendor-kyc-card vendor-kyc-wide" aria-labelledby="vendor-kyc-details-heading">
        <h2 id="vendor-kyc-details-heading">Submitted details</h2>
        <p class="master-sub">{{ $vendor->kyc_status==='approved'?'These details were approved and applied to the vendor record.':'Submitted values are pending verification. They do not change the live vendor record until approved.' }}</p>
        @if ($submitted)
            <div class="master-table-wrap"><table class="master-table">
                <thead><tr><th scope="col">Field</th><th scope="col">Current vendor record</th><th scope="col">Submitted for review</th></tr></thead>
                <tbody>@foreach (\App\Services\VendorKyc::FIELDS as $field)
                    <tr><th scope="row">{{ \Illuminate\Support\Str::headline($field) }}</th><td>{{ $field==='vendor_type'?$vendor->typeLabel():($vendor->{$field} ?: '—') }}</td><td>{{ $field==='vendor_type'?(\App\Models\Vendor::typeOptions()[$submitted[$field]??'']??'—'):($submitted[$field]??'—') }}</td></tr>
                @endforeach</tbody>
            </table></div>
        @else <p class="master-sub">No KYC submitted yet. Draft details are staged and do not update the live vendor record.</p>@endif
        <h3>Supporting documents</h3>
        @if ($kycDocuments->isEmpty())<p class="master-sub">No supporting documents uploaded. Documents are optional.</p>@else
            <ul class="vendor-kyc-documents">@foreach ($kycDocuments as $doc)<li><a href="{{ route('vendors.kyc.documents',[$vendor,$doc->id]) }}">{{ $doc->original_name }}</a> <span class="master-sub">{{ number_format($doc->file_size/1024,1) }} KB · {{ $doc->created_at->format('d M Y') }}</span></li>@endforeach</ul>
        @endif
    </section>
    <section class="master-card master-card--flat vendor-kyc-card vendor-kyc-wide" aria-labelledby="vendor-kyc-history-heading">
        <h2 id="vendor-kyc-history-heading">Review history</h2>
        @if ($kycEvents->isEmpty())<p class="master-sub">No KYC activity yet.</p>@else
            <ol class="vendor-kyc-history">@foreach ($kycEvents as $event)
                <li><strong>{{ \Illuminate\Support\Str::headline($event->action) }}</strong> <span class="master-sub">{{ $event->created_at->format('d M Y, h:i A') }} · {{ $event->actor?->name ?: 'Vendor (secure link)' }} · Version {{ $event->kyc_version }}</span>
                    @if ($event->reason)<p>{{ $event->reason }}</p>@endif
                </li>
            @endforeach</ol>
        @endif
    </section>
</div>
