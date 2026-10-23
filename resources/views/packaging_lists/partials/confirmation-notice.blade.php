@if($list->status === 'draft')
<section class="master-card master-card--flat master-section pl-confirmation-notice" role="note" aria-labelledby="plConfirmationTitle">
    <i class="fa-solid fa-triangle-exclamation pl-confirmation-icon" aria-hidden="true"></i>
    <div>
        <h2 class="master-section-title" id="plConfirmationTitle">Packaging list not confirmed</h2>
        <p class="master-sub">This list is still a draft. Review the products, quantities and carton details, then mark it as Confirmed for shipment readiness checks. Confirmation does not dispatch the shipment.</p>
        @if($verificationInspection['failed'] > 0)
            <p class="master-sub"><strong>{{ $verificationInspection['failed'] }} automatic {{ \Illuminate\Support\Str::plural('check', $verificationInspection['failed']) }} {{ $verificationInspection['failed'] === 1 ? 'needs' : 'need' }} attention before confirmation.</strong> Review the highlighted issues below and correct them in Edit.</p>
        @elseif($verificationInspection['warnings'] > 0)
            <p class="master-sub">{{ $verificationInspection['warnings'] }} review {{ \Illuminate\Support\Str::plural('warning', $verificationInspection['warnings']) }} found. Review the highlighted differences; warnings alone do not prevent confirmation.</p>
        @endif
        @unless(auth()->user()?->hasPermission('operations.manage'))
            <p class="master-sub">Ask an Operations manager to confirm this list after review.</p>
        @endunless
        <div class="pl-confirmation-actions">
            @if(($verificationInspection['project_comparison']['unresolved'] ?? 0) || ($verificationInspection['project_comparison']['review'] ?? 0))
                <a class="master-btn master-btn-soft" href="#projectProductComparison">Review product mapping</a>
            @else
                <a class="master-btn master-btn-soft" href="#plWorkflowChecks">Review checks</a>
            @endif
            @if(auth()->user()?->hasPermission('operations.manage'))
                <form method="POST" action="{{ route('packaging-lists.status', $list) }}"
                      data-confirm="Mark this packaging list as Confirmed? Automatic checks must pass. This does not dispatch the shipment."
                      data-confirm-title="Confirm packaging list" data-confirm-text="Yes" data-confirm-danger="false">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="confirmed">
                    <button type="submit" class="master-btn master-btn-primary">Mark as Confirmed</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endif
