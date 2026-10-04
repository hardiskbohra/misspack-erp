{{-- The payment dialog, shared by the bill listing and the bill record page. The
     button that opens it carries `data-open-payment` with the bill it is for; the
     form's action is filled in from `data-action-template` and the module's
     script opens it through the shared `MasterModal`. One definition, because a
     second copy is a second set of field names to keep in step with
     `PurchaseInvoiceController::recordPayment()`.

     The payment is a **debit in the vendor ledger**, where the bank line is
     reconciled — the same entry the vendor module's own Money tab would write —
     so the bill's balance, the payables ageing and the vendor statement are all
     reading one number. --}}
<div class="master-modal" id="paymentModal" aria-hidden="true">
    <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
        <form method="POST" data-payment-form
            data-action-template="{{ route('purchase-bills.payments.store', ['purchaseInvoice' => '__INVOICE__']) }}">
            @csrf
            <input type="hidden" name="_dialog" value="paymentModal">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon">₹</span>
                    <div>
                        <h3 class="master-modal-title" id="paymentModalTitle">Record a payment</h3>
                        <p class="master-modal-subtitle" data-payment-subtitle>Filed against the vendor ledger</p>
                    </div>
                </div>
            </div>
            <div class="master-modal-body">
                <div class="master-form-grid">
                    <div class="master-field full">
                        <label class="master-label" for="paymentAmount">
                            Amount paid <span class="master-required" aria-hidden="true">*</span>
                        </label>
                        <input class="master-input" id="paymentAmount" type="number" step="0.01" min="0.01"
                            name="amount" value="{{ old('amount') }}" required>
                        <p class="master-help" data-payment-currency>In rupees — the bill is in INR.</p>
                        <p class="master-help" data-payment-conversion hidden></p>
                        @error('amount')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="paymentDate">Paid on <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="paymentDate" type="date" name="transaction_date"
                            value="{{ old('transaction_date', now()->toDateString()) }}" required>
                        @error('transaction_date')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="paymentAccount">Paid from</label>
                        <select class="master-select" id="paymentAccount" name="paid_account_id">
                            <option value="">Not decided</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('paid_account_id') === (string) $account->id)>{{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="paymentMode">Mode</label>
                        <select class="master-select" id="paymentMode" name="payment_mode">
                            <option value="">Not decided</option>
                            @foreach(\App\Models\VendorPaymentEntry::paymentModeOptions() as $key => $label)
                                <option value="{{ $key }}" @selected(old('payment_mode') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="paymentReference">Bank reference</label>
                        <input class="master-input" id="paymentReference" name="bank_reference_number"
                            value="{{ old('bank_reference_number') }}" placeholder="UTR / cheque number">
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="paymentRemarks">Remarks</label>
                        <input class="master-input" id="paymentRemarks" name="remarks"
                            value="{{ old('remarks') }}" placeholder="Leave empty to use the bill number">
                    </div>
                    <div class="master-field full">
                        <label class="master-check">
                            <input type="checkbox" name="record_cashflow" value="1"
                                @checked(old('record_cashflow', true))>
                            Also record it in the INR cashflow
                        </label>
                        <p class="master-help">
                            Leave this on and one bank line appears in the cashflow ledger beside the vendor entry —
                            the same mirror the vendor module writes for a payment typed there.
                        </p>
                    </div>
                </div>
                <p class="master-sub">
                    The payment is a debit in the vendor ledger, linked to this bill — so the ledger, the payables
                    ageing and the vendor's own statement are all reading the same money.
                </p>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="paymentModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Record payment</button>
            </div>
        </form>
    </div>
</div>

{{-- A failed save comes back with the input kept and the dialog reopened. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
