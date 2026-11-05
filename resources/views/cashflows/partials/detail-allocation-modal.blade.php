@php
    /* The mirror of a vendor payment is the bank side of that payment; its bill
       is settled in the vendor ledger. Locking the purchase select here keeps
       the operator out of the "counted twice" validation error entirely. */
    $mirrorPayment = $linkedVendorPayment ?? null;
    $purchaseLocked = ! empty($mirrorPayment);
@endphp
<div class="master-modal" id="detailAllocationModal" aria-hidden="true" data-entry-amount="{{ $entry->amountMoved() }}" data-allocated-amount="{{ $entry->allocatedAmount() }}" data-currency="{{ $entry->currency ?: 'INR' }}">
    <div class="master-modal-card is-wide cf-allocation-modal" role="dialog" aria-modal="true" aria-labelledby="detailAllocationModalTitle">
        <form method="POST" action="{{ route('cashflows.allocations.store', $entry) }}" data-detail-allocation-form>
            @csrf
            <input type="hidden" name="allocation_form" value="1">
            <input type="hidden" name="allocation_update_url" value="{{ old('allocation_update_url') }}" data-allocation-update-url>
            <input type="hidden" name="allocation_original_amount" value="{{ old('allocation_original_amount', 0) }}" data-allocation-original-amount>
            <span data-allocation-method></span>
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fa-solid fa-code-branch" aria-hidden="true"></i></span>
                    <div><h3 class="master-modal-title" id="detailAllocationModalTitle" data-detail-allocation-title>Add allocation</h3></div>
                </div>
            </div>
            <div class="master-modal-body">
                <div class="master-stats cf-allocation-modal-stats" aria-live="polite">
                    <div class="master-stat master-stat--flat blue"><div><p class="master-stat-title">Entry amount</p><p class="master-stat-value" data-detail-entry-total></p></div></div>
                    <div class="master-stat master-stat--flat green"><div><p class="master-stat-title">Other allocations</p><p class="master-stat-value" data-detail-other-total></p></div></div>
                    <div class="master-stat master-stat--flat purple"><div><p class="master-stat-title">Available for this line</p><p class="master-stat-value" data-detail-available-total></p></div></div>
                </div>
                @if(old('allocation_form') && $errors->any())
                    <div class="master-error cf-allocation-detail-errors">
                        @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif
                @if($purchaseLocked)
                    <div class="cf-allocation-mirror" role="note">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <div>
                            <strong>Bank mirror of vendor payment #{{ $mirrorPayment->id }}@if($mirrorPayment->vendor) · {{ $mirrorPayment->vendor->vendor_name }}@endif</strong>
                            <p>The bill is already settled by that payment in the vendor ledger, so this row allocates to a project only.
                                @if (\Illuminate\Support\Facades\Route::has('vendors.show'))
                                    <a href="{{ route('vendors.show', $mirrorPayment->vendor_id) }}#payments">Open the vendor payment</a> to change which bill it settles.
                                @else
                                    Open the vendor payment to change which bill it settles.
                                @endif
                            </p>
                        </div>
                    </div>
                @endif
                <div class="cf-allocation-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="detailAllocationProject">Project</label>
                        <select class="master-select" id="detailAllocationProject" name="project_id">
                            <option value="">No project</option>
                            @foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->project_number }} · {{ $project->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="detailAllocationSales">Sales invoice / proforma</label>
                        <select class="master-select" id="detailAllocationSales" name="sales_invoice_id" data-detail-sales>
                            <option value="">No sales document</option>
                            @foreach($salesInvoices as $invoice)<option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}" @include('cashflows.partials.document-payment-option-data') @selected((string) old('sales_invoice_id') === (string) $invoice->id)>{{ $invoice->invoice_number }} · {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="detailAllocationPurchase">Purchase order / bill</label>
                        <select class="master-select" id="detailAllocationPurchase" name="purchase_invoice_id" data-detail-purchase @disabled($purchaseLocked)>
                            <option value="">{{ $purchaseLocked ? 'Settled by the vendor payment' : 'No purchase document' }}</option>
                            @unless($purchaseLocked)
                                @foreach($purchaseInvoices as $invoice)<option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}" @include('cashflows.partials.document-payment-option-data') @selected((string) old('purchase_invoice_id') === (string) $invoice->id)>{{ $invoice->invoice_number }} · {{ $invoice->typeLabel() }} · {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</option>@endforeach
                            @endunless
                        </select>
                        @if($purchaseLocked)
                            <p class="master-field-hint">Bills are paid in Vendors → Payments against vendor payment #{{ $mirrorPayment->id }}.</p>
                        @endif
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="detailAllocationAmount">Cash amount <span class="master-required">*</span></label>
                        <input class="master-input" id="detailAllocationAmount" name="amount" type="number" min="0.001" step="0.001" value="{{ old('amount') }}" required>
                    </div>
                    @include('cashflows.partials.document-payment-summary')
                    <div class="master-field">
                        <label class="master-label" for="detailAllocationDocumentAmount">Document amount</label>
                        <input class="master-input" id="detailAllocationDocumentAmount" name="document_amount" type="number" min="0.001" step="0.001" value="{{ old('document_amount') }}">
                        <p class="master-field-hint" data-detail-currency-hint>Leave blank when it is the same as the cash amount.</p>
                        @if (! empty($entry->vendor_id))
                            <p class="master-field-hint">The vendor ledger shows the payment at this amount, in the bill's currency.</p>
                        @endif
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="detailAllocationNote">Allocation note</label>
                        <input class="master-input" id="detailAllocationNote" name="note" maxlength="500" value="{{ old('note') }}">
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="detailAllocationModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Save allocation</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('detailAllocationModal');
    if (!modal) return;
    var form = modal.querySelector('[data-detail-allocation-form]');
    var storeUrl = form.action;
    var title = modal.querySelector('[data-detail-allocation-title]');
    var methodSlot = modal.querySelector('[data-allocation-method]');
    var updateUrl = modal.querySelector('[data-allocation-update-url]');
    var originalAmount = modal.querySelector('[data-allocation-original-amount]');
    var project = modal.querySelector('[name="project_id"]');
    var sales = modal.querySelector('[name="sales_invoice_id"]');
    var purchase = modal.querySelector('[name="purchase_invoice_id"]');
    var amount = modal.querySelector('[name="amount"]');
    var documentAmount = modal.querySelector('[name="document_amount"]');
    var note = modal.querySelector('[name="note"]');
    var entryTotal = Number(modal.dataset.entryAmount) || 0;
    var allocatedTotal = Number(modal.dataset.allocatedAmount) || 0;
    var currency = modal.dataset.currency || 'INR';
    var activeAmount = 0;

    function formatAllocationMoney(value) {
        return currency + ' ' + (Number(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
    }
    function notify(select) { if (window.jQuery) window.jQuery(select).trigger('change'); }
    function setValue(control, value) { control.value = value || ''; notify(control); }
    function stats() {
        var other = Math.max(allocatedTotal - activeAmount, 0);
        modal.querySelector('[data-detail-entry-total]').textContent = formatAllocationMoney(entryTotal);
        modal.querySelector('[data-detail-other-total]').textContent = formatAllocationMoney(other);
        modal.querySelector('[data-detail-available-total]').textContent = formatAllocationMoney(Math.max(entryTotal - other, 0));
    }
    function currencyHint() {
        window.CashflowDocumentBalance.refresh(modal, sales, purchase);
        var selected = sales.value ? sales.options[sales.selectedIndex] : (purchase.value ? purchase.options[purchase.selectedIndex] : null);
        var documentCurrency = selected ? selected.dataset.currency : '';
        modal.querySelector('[data-detail-currency-hint]').textContent = documentCurrency && documentCurrency !== currency
            ? 'Required: enter the amount applied in ' + documentCurrency + '.'
            : 'Leave blank when it is the same as the cash amount.';
    }
    function openModal() {
        /* Use the shared dialog lifecycle. The modal stylesheet displays
           `.master-modal.open`; `is-open` belongs to no shared dialog rule and
           left this detail-page modal hidden behind the backdrop. */
        if (window.MasterModal) {
            window.MasterModal.open(modal);
        } else {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('master-modal-open');
        }
        stats(); currencyHint();
        window.setTimeout(function () { amount.focus(); }, 40);
    }
    function addMode() {
        activeAmount = 0;
        title.textContent = 'Add allocation';
        form.action = storeUrl;
        methodSlot.innerHTML = '';
        updateUrl.value = '';
        originalAmount.value = '0';
        [project, sales, purchase, amount, documentAmount, note].forEach(function (control) { setValue(control, ''); });
        openModal();
    }
    function editMode(button) {
        activeAmount = Number(button.dataset.amount) || 0;
        title.textContent = 'Edit allocation';
        form.action = button.dataset.updateUrl;
        methodSlot.innerHTML = '<input type="hidden" name="_method" value="PUT">';
        updateUrl.value = button.dataset.updateUrl;
        originalAmount.value = button.dataset.amount;
        setValue(project, button.dataset.projectId);
        setValue(sales, button.dataset.salesId);
        setValue(purchase, button.dataset.purchaseId);
        setValue(amount, button.dataset.amount);
        setValue(documentAmount, button.dataset.documentAmount);
        setValue(note, button.dataset.note);
        openModal();
    }

    document.querySelectorAll('[data-detail-allocation-add]').forEach(function (button) { button.addEventListener('click', addMode); });
    document.querySelectorAll('[data-detail-allocation-edit]').forEach(function (button) { button.addEventListener('click', function () { editMode(button); }); });
    function documentChanged(event) {
        var control = event.target;
        var opposite = control === sales ? purchase : sales;
        if (control.value && opposite.value) setValue(opposite, '');
        var option = control.options[control.selectedIndex];
        if (option && option.getAttribute('data-project') && !project.value) setValue(project, option.getAttribute('data-project'));
        currencyHint();
    }
    // Select2 emits jQuery change events; native-only listeners do not see them.
    if (window.jQuery) window.jQuery(sales).add(purchase).on('change.cfDetailAllocation', documentChanged);
    else [sales, purchase].forEach(function (control) { control.addEventListener('change', documentChanged); });
    form.addEventListener('submit', function (event) {
        var available = entryTotal - Math.max(allocatedTotal - activeAmount, 0);
        if (!project.value && !sales.value && !purchase.value) { event.preventDefault(); window.alert('Select a project or document.'); return; }
        if ((Number(amount.value) || 0) > available + 0.0005) { event.preventDefault(); window.alert('This allocation exceeds the available ' + formatAllocationMoney(available) + '.'); }
    });

    @if(!old('allocation_form'))
        if (new URLSearchParams(window.location.search).get('allocation') === 'add') addMode();
    @endif
    @if(old('allocation_form'))
        activeAmount = Number(@json(old('allocation_original_amount', 0))) || 0;
        if (@json((bool) old('allocation_update_url'))) {
            title.textContent = 'Edit allocation';
            form.action = @json(old('allocation_update_url'));
            methodSlot.innerHTML = '<input type="hidden" name="_method" value="PUT">';
        }
        openModal();
    @endif
});
</script>
@endpush
