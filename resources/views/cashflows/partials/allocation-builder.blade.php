@php
    $projectOptions = $projects->keyBy('id');
    $salesOptions = $salesInvoices->keyBy('id');
    $purchaseOptions = $purchaseInvoices->keyBy('id');

    /* A cashflow row that mirrors a vendor payment is only the bank side of
       that payment: the bill it settles is recorded in the vendor ledger, so a
       purchase document chosen here would pay the same bill twice. Offer the
       project slice and point at the payment instead of letting the operator
       discover the rule after submitting. */
    $mirrorPayment = $linkedVendorPayment ?? null;
    $purchaseLocked = ! empty($mirrorPayment);
@endphp

<section class="master-section cf-allocation-section" data-allocation-builder>
    <div class="master-section-head">
        <div>
            <h3 class="master-section-title">Allocation breakdown</h3>
        </div>
        <button type="button" class="master-btn master-btn-soft master-btn-sm" data-add-allocation>
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Add allocation
        </button>
    </div>

    <div class="master-stats cf-allocation-stats" aria-live="polite">
        <div class="master-stat master-stat--flat blue">
            <span class="icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Entry amount</p><p class="master-stat-value" data-allocation-entry>0.00</p></div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon"><i class="fa-solid fa-code-branch" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Allocated</p><p class="master-stat-value" data-allocation-total>0.00</p></div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
            <div><p class="master-stat-title">Unallocated</p><p class="master-stat-value" data-allocation-remaining>0.00</p></div>
        </div>
    </div>
    @error('allocations')<div class="master-error">{{ $message }}</div>@enderror

    @if($purchaseLocked)
        <div class="cf-allocation-mirror" role="note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <div>
                <strong>Bank mirror of vendor payment #{{ $mirrorPayment->id }}@if($mirrorPayment->vendor) · {{ $mirrorPayment->vendor->vendor_name }}@endif</strong>
                <p>The vendor ledger already counts this payment against its bill, so a purchase document is not selected here —
                    @if (\Illuminate\Support\Facades\Route::has('vendors.show'))
                        change the bill on the <a href="{{ route('vendors.show', $mirrorPayment->vendor_id) }}#payments">vendor payment</a>.
                    @else
                        change the bill on the vendor payment.
                    @endif
                    This breakdown carries the project share only.</p>
                @if($allocationRows && collect($allocationRows)->contains(fn ($row) => filled($row['purchase_invoice_id'] ?? null)))
                    <p class="cf-allocation-mirror-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        A purchase document is still attached to a line below. Saving this form removes it, because the same payment cannot be counted twice.</p>
                @endif
            </div>
        </div>
    @endif

    <div class="master-table-wrap cf-allocation-table-wrap">
        <table class="master-table cf-allocation-table">
            <thead>
                <tr>
                    <th scope="col">Project</th>
                    <th scope="col">Document</th>
                    <th scope="col">Allocation note</th>
                    <th scope="col" class="is-num">Cash amount</th>
                    <th scope="col" class="is-num">Document amount</th>
                    <th scope="col" class="is-action">Action</th>
                </tr>
            </thead>
            <tbody data-allocation-list>
                @foreach($allocationRows as $index => $allocation)
                    @php
                        $project = $projectOptions->get((int) ($allocation['project_id'] ?? 0));
                        $salesDocument = $salesOptions->get((int) ($allocation['sales_invoice_id'] ?? 0));
                        $purchaseDocument = $purchaseOptions->get((int) ($allocation['purchase_invoice_id'] ?? 0));
                        $document = $salesDocument ?: $purchaseDocument;
                        $documentType = $salesDocument ? 'sales' : ($purchaseDocument ? 'purchase' : '');
                        $rowError = collect(['project_id', 'sales_invoice_id', 'purchase_invoice_id', 'amount', 'document_amount', 'note'])
                            ->map(fn ($field) => $errors->first('allocations.'.$index.'.'.$field))
                            ->first();
                        $rowHasError = filled($rowError);
                    @endphp
                    <tr data-allocation-row class="{{ $rowHasError ? 'has-error' : '' }}">
                        <td data-label="Project"><strong data-project-label>{{ $project ? $project->project_number.' · '.$project->name : 'No project' }}</strong></td>
                        <td data-label="Document">
                            <strong data-document-label>{{ $document ? $document->invoice_number : 'No document' }}</strong>
                            <span class="master-sub" data-document-meta>{{ $document ? (($documentType === 'sales' ? 'Sales' : $document->typeLabel()).' · '.$document->currency) : 'Project-only allocation' }}</span>
                        </td>
                        <td data-label="Allocation note">
                            <span data-note-label>{{ $allocation['note'] ?? '—' }}</span>
                            @if($rowError)<span class="master-error">{{ $rowError }}</span>@endif
                        </td>
                        <td data-label="Cash amount" class="is-num"><strong data-cash-label>{{ \App\Helpers\CommonHelper::amount((float) ($allocation['amount'] ?? 0), $entry->currency ?: 'INR') }}</strong></td>
                        <td data-label="Document amount" class="is-num"><span data-document-amount-label>{{ $document ? \App\Helpers\CommonHelper::amount((float) ($allocation['document_amount'] ?? $allocation['amount'] ?? 0), $document->currency) : '—' }}</span></td>
                        <td data-label="Action" class="is-action">
                            <div class="master-row-actions cf-allocation-actions">
                                <button type="button" class="master-icon-btn" data-edit-allocation aria-label="Edit allocation"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>
                                <button type="button" class="master-icon-btn is-danger" data-remove-allocation aria-label="Remove allocation"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                            </div>
                            <div data-allocation-inputs>
                                <input type="hidden" name="allocations[{{ $index }}][project_id]" value="{{ $allocation['project_id'] ?? '' }}" data-value="project_id">
                                <input type="hidden" name="allocations[{{ $index }}][sales_invoice_id]" value="{{ $allocation['sales_invoice_id'] ?? '' }}" data-value="sales_invoice_id">
                                <input type="hidden" name="allocations[{{ $index }}][purchase_invoice_id]" value="{{ $allocation['purchase_invoice_id'] ?? '' }}" data-value="purchase_invoice_id">
                                <input type="hidden" name="allocations[{{ $index }}][amount]" value="{{ $allocation['amount'] ?? '' }}" data-value="amount" data-allocation-amount>
                                <input type="hidden" name="allocations[{{ $index }}][document_amount]" value="{{ $allocation['document_amount'] ?? '' }}" data-value="document_amount">
                                <input type="hidden" name="allocations[{{ $index }}][note]" value="{{ $allocation['note'] ?? '' }}" data-value="note">
                            </div>
                        </td>
                    </tr>
                @endforeach
                <tr data-allocation-empty {{ $allocationRows ? 'hidden' : '' }}>
                    <td colspan="6">
                        <div class="master-empty-state cf-allocation-empty">
                            <i class="fa-solid fa-code-branch" aria-hidden="true"></i>
                            <p>No allocation lines yet. The full amount will remain unapplied until you add one.</p>
                            <button type="button" class="master-btn master-btn-soft master-btn-sm" data-add-allocation>Add first allocation</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<div class="master-modal" id="allocationModal" aria-hidden="true">
    <div class="master-modal-card is-wide cf-allocation-modal" role="dialog" aria-modal="true" aria-labelledby="allocationModalTitle">
        <div class="master-modal-header">
            <div class="master-modal-heading">
                <span class="master-modal-icon"><i class="fa-solid fa-code-branch" aria-hidden="true"></i></span>
                <div>
                    <h3 class="master-modal-title" id="allocationModalTitle" data-allocation-modal-title>Add allocation</h3>
                </div>
            </div>
        </div>
        <div class="master-modal-body">
            <div class="master-stats cf-allocation-modal-stats" aria-live="polite">
                <div class="master-stat master-stat--flat blue"><div><p class="master-stat-title">Entry amount</p><p class="master-stat-value" data-modal-entry-total>0.00</p></div></div>
                <div class="master-stat master-stat--flat green"><div><p class="master-stat-title">Other allocations</p><p class="master-stat-value" data-modal-allocated-total>0.00</p></div></div>
                <div class="master-stat master-stat--flat purple"><div><p class="master-stat-title">Available for this line</p><p class="master-stat-value" data-modal-available-total>0.00</p></div></div>
            </div>
            <div class="cf-allocation-modal-grid">
                <div class="master-field">
                    <label class="master-label" for="allocationProject">Project</label>
                    <select class="master-select" id="allocationProject" data-modal-value="project_id">
                        <option value="">No project</option>
                        @foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->project_number }} · {{ $project->name }}</option>@endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="allocationSales">Sales invoice / proforma</label>
                    <select class="master-select" id="allocationSales" data-modal-value="sales_invoice_id" data-allocation-sales>
                        <option value="">No sales document</option>
                        @foreach($salesInvoices as $invoice)<option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}">{{ $invoice->invoice_number }} · {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</option>@endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label" for="allocationPurchase">Purchase order / bill</label>
                    <select class="master-select" id="allocationPurchase" data-modal-value="purchase_invoice_id" data-allocation-purchase @disabled($purchaseLocked)>
                        <option value="">{{ $purchaseLocked ? 'Settled by the vendor payment' : 'No purchase document' }}</option>
                        @unless($purchaseLocked)
                            @foreach($purchaseInvoices as $invoice)<option value="{{ $invoice->id }}" data-currency="{{ $invoice->currency }}" data-project="{{ $invoice->project_id }}">{{ $invoice->invoice_number }} · {{ $invoice->typeLabel() }} · {{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</option>@endforeach
                        @endunless
                    </select>
                    @if($purchaseLocked)
                        <p class="master-field-hint">The bill is settled in Vendors → Payments against vendor payment #{{ $mirrorPayment->id }}.</p>
                    @endif
                </div>
                <div class="master-field">
                    <label class="master-label" for="allocationCashAmount">Cash amount <span class="master-required">*</span></label>
                    <input class="master-input" id="allocationCashAmount" type="number" min="0.01" step="0.01" data-modal-value="amount" placeholder="Amount from this bank entry">
                </div>
                <div class="master-field">
                    <label class="master-label" for="allocationDocumentAmount">Document amount</label>
                    <input class="master-input" id="allocationDocumentAmount" type="number" min="0.01" step="0.01" data-modal-value="document_amount" placeholder="Required when currencies differ">
                    <p class="master-field-hint" data-document-currency-hint>Leave blank when it is the same as the cash amount.</p>
                    @if (! empty($entry->vendor_id))
                        {{-- The vendor's ledger is kept in their currency, and this
                             is the figure it records for the payment. --}}
                        <p class="master-field-hint">The vendor ledger shows the payment at this amount, in the bill's currency.</p>
                    @endif
                </div>
                <div class="master-field">
                    <label class="master-label" for="allocationNote">Allocation note</label>
                    <input class="master-input" id="allocationNote" data-modal-value="note" maxlength="500" placeholder="Duty for Project A, advance against PI-003…">
                </div>
            </div>
            <div class="master-error" data-allocation-modal-error hidden></div>
        </div>
        <div class="master-modal-footer">
            <button type="button" class="master-btn master-btn-light" data-close-modal="allocationModal">Cancel</button>
            <button type="button" class="master-btn master-btn-primary" data-save-allocation><i class="fa-solid fa-check" aria-hidden="true"></i> Save allocation</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var builder = document.querySelector('[data-allocation-builder]');
    var modal = document.getElementById('allocationModal');
    if (!builder || !modal) return;

    var list = builder.querySelector('[data-allocation-list]');
    var empty = builder.querySelector('[data-allocation-empty]');
    var amountInput = document.querySelector('input[name="amount"]');
    var currencyInput = document.querySelector('select[name="currency"]');
    var activeRow = null;

    function field(key) { return modal.querySelector('[data-modal-value="' + key + '"]'); }
    function notifySelect(select) { if (window.jQuery) window.jQuery(select).trigger('change'); }
    function optionText(select) { return select.value ? select.options[select.selectedIndex].text.trim() : ''; }
    function formatAllocationMoney(value, currency) {
        return (currency || 'INR') + ' ' + (Number(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function rows() { return Array.from(list.querySelectorAll('[data-allocation-row]')); }
    function value(row, key) { var input = row.querySelector('[data-value="' + key + '"]'); return input ? input.value : ''; }

    function reindex() {
        rows().forEach(function (row, index) {
            row.querySelectorAll('[data-value]').forEach(function (input) {
                input.name = 'allocations[' + index + '][' + input.getAttribute('data-value') + ']';
            });
        });
        empty.hidden = rows().length > 0;
    }

    function totals() {
        var entry = parseFloat(amountInput ? amountInput.value : 0) || 0;
        var allocated = rows().reduce(function (sum, row) { return sum + (parseFloat(value(row, 'amount')) || 0); }, 0);
        var currency = currencyInput ? currencyInput.value : 'INR';
        builder.querySelector('[data-allocation-entry]').textContent = formatAllocationMoney(entry, currency);
        builder.querySelector('[data-allocation-total]').textContent = formatAllocationMoney(allocated, currency);
        var remaining = entry - allocated;
        builder.querySelector('[data-allocation-remaining]').textContent = formatAllocationMoney(remaining, currency);
        builder.classList.toggle('has-allocation-error', remaining < -0.009);
        modalTotals();
    }

    function modalTotals() {
        var entry = parseFloat(amountInput ? amountInput.value : 0) || 0;
        var otherAllocated = rows().reduce(function (sum, row) {
            return row === activeRow ? sum : sum + (parseFloat(value(row, 'amount')) || 0);
        }, 0);
        var available = entry - otherAllocated;
        var currency = currencyInput ? currencyInput.value : 'INR';
        modal.querySelector('[data-modal-entry-total]').textContent = formatAllocationMoney(entry, currency);
        modal.querySelector('[data-modal-allocated-total]').textContent = formatAllocationMoney(otherAllocated, currency);
        modal.querySelector('[data-modal-available-total]').textContent = formatAllocationMoney(available, currency);
        return available;
    }

    function clearModal() {
        modal.querySelectorAll('[data-modal-value]').forEach(function (input) { input.value = ''; if (input.tagName === 'SELECT') notifySelect(input); });
        modal.querySelector('[data-allocation-modal-error]').hidden = true;
    }

    function openModal(row) {
        activeRow = row || null;
        clearModal();
        modal.querySelector('[data-allocation-modal-title]').textContent = row ? 'Edit allocation' : 'Add allocation';
        if (row) {
            ['project_id', 'sales_invoice_id', 'purchase_invoice_id', 'amount', 'document_amount', 'note'].forEach(function (key) {
                field(key).value = value(row, key);
                if (field(key).tagName === 'SELECT') notifySelect(field(key));
            });
        }
        updateCurrencyHint();
        modalTotals();
        if (window.MasterModal) window.MasterModal.open(modal);
    }

    function hidden(key, val) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.setAttribute('data-value', key);
        if (key === 'amount') input.setAttribute('data-allocation-amount', '');
        input.value = val || '';
        return input;
    }

    function makeRow(values) {
        var row = document.createElement('tr');
        row.setAttribute('data-allocation-row', '');
        row.innerHTML = '<td data-label="Project"><strong data-project-label></strong></td>' +
            '<td data-label="Document"><strong data-document-label></strong><span class="master-sub" data-document-meta></span></td>' +
            '<td data-label="Allocation note"><span data-note-label></span></td>' +
            '<td data-label="Cash amount" class="is-num"><strong data-cash-label></strong></td>' +
            '<td data-label="Document amount" class="is-num"><span data-document-amount-label></span></td>' +
            '<td data-label="Action" class="is-action"><div class="master-row-actions cf-allocation-actions">' +
            '<button type="button" class="master-icon-btn" data-edit-allocation aria-label="Edit allocation"><i class="fa-solid fa-pen" aria-hidden="true"></i></button>' +
            '<button type="button" class="master-icon-btn is-danger" data-remove-allocation aria-label="Remove allocation"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div><div data-allocation-inputs></div></td>';
        var inputs = row.querySelector('[data-allocation-inputs]');
        Object.keys(values).forEach(function (key) { inputs.appendChild(hidden(key, values[key])); });
        return row;
    }

    function paintRow(row, values) {
        var project = field('project_id');
        var sales = field('sales_invoice_id');
        var purchase = field('purchase_invoice_id');
        var documentSelect = values.sales_invoice_id ? sales : purchase;
        var documentType = values.sales_invoice_id ? 'Sales' : (values.purchase_invoice_id ? 'Purchase' : '');
        var documentOption = documentSelect.options[documentSelect.selectedIndex];
        var documentCurrency = documentOption ? documentOption.getAttribute('data-currency') : '';
        var entryCurrency = currencyInput ? currencyInput.value : 'INR';

        row.querySelector('[data-project-label]').textContent = optionText(project) || 'No project';
        row.querySelector('[data-document-label]').textContent = optionText(documentSelect) || 'No document';
        row.querySelector('[data-document-meta]').textContent = documentType ? documentType + ' · ' + documentCurrency : 'Project-only allocation';
        row.querySelector('[data-note-label]').textContent = values.note || '—';
        row.querySelector('[data-cash-label]').textContent = formatAllocationMoney(values.amount, entryCurrency);
        row.querySelector('[data-document-amount-label]').textContent = documentType ? formatAllocationMoney(values.document_amount || values.amount, documentCurrency) : '—';
    }

    function updateCurrencyHint() {
        var select = field('sales_invoice_id').value ? field('sales_invoice_id') : field('purchase_invoice_id');
        var option = select.options[select.selectedIndex];
        var documentCurrency = option ? option.getAttribute('data-currency') : '';
        var entryCurrency = currencyInput ? currencyInput.value : 'INR';
        modal.querySelector('[data-document-currency-hint]').textContent = documentCurrency && documentCurrency !== entryCurrency
            ? 'Required: enter the amount applied in ' + documentCurrency + '. The bank entry is in ' + entryCurrency + '.'
            : 'Leave blank to use the same value as the cash amount.';
    }

    builder.querySelectorAll('[data-add-allocation]').forEach(function (button) { button.addEventListener('click', function () { openModal(null); }); });
    if (amountInput) amountInput.addEventListener('input', totals);
    if (currencyInput) currencyInput.addEventListener('change', totals);

    list.addEventListener('click', function (event) {
        var row = event.target.closest('[data-allocation-row]');
        if (!row) return;
        if (event.target.closest('[data-edit-allocation]')) openModal(row);
        if (event.target.closest('[data-remove-allocation]')) { row.remove(); reindex(); totals(); }
    });

    modal.addEventListener('change', function (event) {
        if (!event.target.matches('[data-allocation-sales], [data-allocation-purchase]')) return;
        var opposite = event.target.matches('[data-allocation-sales]') ? field('purchase_invoice_id') : field('sales_invoice_id');
        if (event.target.value && opposite.value) { opposite.value = ''; notifySelect(opposite); }
        var option = event.target.options[event.target.selectedIndex];
        var projectId = option ? option.getAttribute('data-project') : '';
        if (projectId && !field('project_id').value) { field('project_id').value = projectId; notifySelect(field('project_id')); }
        updateCurrencyHint();
    });

    modal.querySelector('[data-save-allocation]').addEventListener('click', function () {
        var values = {};
        ['project_id', 'sales_invoice_id', 'purchase_invoice_id', 'amount', 'document_amount', 'note'].forEach(function (key) { values[key] = field(key).value.trim(); });
        /* On a vendor-payment mirror the bill is settled in the vendor ledger;
           the disabled select cannot contribute a purchase document. */
        if (field('purchase_invoice_id').disabled) values.purchase_invoice_id = '';
        var error = modal.querySelector('[data-allocation-modal-error]');
        var documentSelect = values.sales_invoice_id ? field('sales_invoice_id') : field('purchase_invoice_id');
        var documentOption = documentSelect.options[documentSelect.selectedIndex];
        var documentCurrency = documentOption ? documentOption.getAttribute('data-currency') : '';
        var entryCurrency = currencyInput ? currencyInput.value : 'INR';
        var message = '';
        if (!values.project_id && !values.sales_invoice_id && !values.purchase_invoice_id) message = 'Choose a project or a document.';
        else if (!(parseFloat(values.amount) > 0)) message = 'Enter a cash allocation amount greater than zero.';
        else if (parseFloat(values.amount) > modalTotals() + 0.009) message = 'This allocation exceeds the unallocated cash amount.';
        else if (values.sales_invoice_id && values.purchase_invoice_id) message = 'Choose either a sales document or a purchase document, not both.';
        else if (documentCurrency && documentCurrency !== entryCurrency && !(parseFloat(values.document_amount) > 0)) message = 'Enter the amount applied in ' + documentCurrency + '.';
        if (message) { error.textContent = message; error.hidden = false; return; }

        var row = activeRow || makeRow(values);
        if (!activeRow) list.insertBefore(row, empty);
        row.querySelectorAll('[data-value]').forEach(function (input) { input.value = values[input.getAttribute('data-value')] || ''; });
        paintRow(row, values);
        reindex();
        totals();
        if (window.MasterModal) window.MasterModal.close(modal);
    });

    reindex();
    totals();
});
</script>
@endpush
