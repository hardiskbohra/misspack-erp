{{-- Payment against a purchase bill: a debit in the vendor ledger. --}}
    <div class="master-modal" id="paymentModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="purchasePaymentTitle">
            <form method="POST" data-payment-form
                data-action-template="{{ route('purchase-invoices.payments.store', ['purchaseInvoice' => '__INVOICE__']) }}">
                @csrf
                <input type="hidden" name="_dialog" value="paymentModal">
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">₹</span>
                        <div>
                            <h3 class="master-modal-title" id="purchasePaymentTitle">Record a payment</h3>
                            <p class="master-modal-subtitle" data-payment-subtitle>Filed against the bill in the vendor ledger</p>
                        </div>
                    </div>
                </div>
                <div class="master-modal-body">
                    <div class="master-form-grid">
                        <div class="master-field full">
                            <label class="master-label" for="purchasePaymentAmount">Amount paid <span class="master-required">*</span></label>
                            <input class="master-input" id="purchasePaymentAmount" type="number" step="0.01" min="0.01" name="amount" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentDate">Paid on</label>
                            <input class="master-input" id="purchasePaymentDate" type="date" name="transaction_date" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentAccount">From account</label>
                            <select class="master-select" id="purchasePaymentAccount" name="paid_account_id">
                                <option value="">Not decided</option>
                                @foreach($accounts ?? [] as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentMode">Mode</label>
                            <select class="master-select" id="purchasePaymentMode" name="payment_mode">
                                <option value="">Not decided</option>
                                @foreach($paymentModeOptions ?? [] as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentRef">Bank reference</label>
                            <input class="master-input" id="purchasePaymentRef" name="bank_reference_number" placeholder="UTR / cheque number">
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="purchasePaymentRemarks">Remarks</label>
                            <input class="master-input" id="purchasePaymentRemarks" name="remarks">
                        </div>
                        <div class="master-field full">
                            <label class="master-check">
                                <input type="checkbox" name="record_cashflow" value="1" checked>
                                Mirror this payment in the cashflow ledger
                            </label>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="paymentModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Record payment</button>
                </div>
            </form>
        </div>
    </div>
    <span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
