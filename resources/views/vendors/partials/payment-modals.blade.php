@php
    $editingPayment = old('_vendor_payment_form') === 'edit'
        ? $vendorPaymentEntries->firstWhere('id', (int) old('_vendor_payment_entry_id'))
        : null;
@endphp

<div class="master-modal" id="addPaymentModal" aria-hidden="true"
    @if ($errors->any() && old('_vendor_payment_form') === 'add') data-auto-open="true" @endif>
    <div class="master-modal-card vendor-payment-modal-card" role="dialog" aria-modal="true"
        aria-labelledby="addPaymentTitle" aria-describedby="addPaymentDescription" tabindex="-1">
        <form method="POST" action="{{ route('vendors.payments.store', $vendor) }}" enctype="multipart/form-data" class="vendor-payment-form">
            @csrf
            <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">
            <input type="hidden" name="_vendor_payment_form" value="add">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="addPaymentTitle">Add ledger entry</h2>
                        <p class="master-modal-subtitle" id="addPaymentDescription">Record a vendor bill, payment, expense, adjustment or refund.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeAddPaymentModal" data-close-modal aria-label="Close add ledger entry dialog">&times;</button>
            </div>
            <div class="master-modal-body">
                @if ($errors->any() && old('_vendor_payment_form') === 'add')
                    <div class="vendor-form-errors" role="alert"><strong>Review the highlighted ledger details.</strong></div>
                @endif
                @include('vendors.partials.payment-fields', ['prefix' => 'add', 'isEdit' => false])
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddPaymentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Save entry</button>
            </div>
        </form>
    </div>
</div>

<div class="master-modal" id="editPaymentModal" aria-hidden="true"
    @if ($errors->any() && old('_vendor_payment_form') === 'edit') data-auto-open="true" @endif>
    <div class="master-modal-card vendor-payment-modal-card" role="dialog" aria-modal="true"
        aria-labelledby="editPaymentTitle" aria-describedby="editPaymentDescription" tabindex="-1">
        <form id="editPaymentForm" method="POST" action="{{ $editingPayment ? route('vendors.payments.update', [$vendor, $editingPayment]) : '' }}"
            enctype="multipart/form-data" class="vendor-payment-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="_vendor_payment_form" value="edit">
            <input type="hidden" name="_vendor_payment_entry_id" value="{{ old('_vendor_payment_entry_id', $editingPayment?->id) }}">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fa-solid fa-pen" aria-hidden="true"></i></span>
                    <div>
                        <h2 class="master-modal-title" id="editPaymentTitle">Edit ledger entry</h2>
                        <p class="master-modal-subtitle" id="editPaymentDescription">Update the vendor ledger and its linked INR cashflow entry.</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeEditPaymentModal" data-close-modal aria-label="Close edit ledger entry dialog">&times;</button>
            </div>
            <div class="master-modal-body">
                @if ($errors->any() && old('_vendor_payment_form') === 'edit')
                    <div class="vendor-form-errors" role="alert"><strong>Review the highlighted ledger details.</strong></div>
                @endif
                @include('vendors.partials.payment-fields', ['prefix' => 'edit', 'isEdit' => true])
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelEditPaymentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-check" aria-hidden="true"></i> Save changes</button>
            </div>
        </form>
    </div>
</div>
