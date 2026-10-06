{{-- Payment / advance against a PO or bill: the same fields as the vendor Money tab. --}}
    <div class="master-modal" id="paymentModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="purchasePaymentTitle">
            <form method="POST" data-payment-form enctype="multipart/form-data"
                data-action-template="{{ route('purchase-invoices.payments.store', ['purchaseInvoice' => '__INVOICE__']) }}">
                @csrf
                <input type="hidden" name="_dialog" value="paymentModal">
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">¥</span>
                        <div>
                            <h3 class="master-modal-title" id="purchasePaymentTitle">Record a payment</h3>
                            <p class="master-modal-subtitle" data-payment-subtitle>Filed against this document in the vendor ledger. An advance on a PO moves to the bill when you convert.</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="paymentModal" aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentDate">Paid on <span class="master-required">*</span></label>
                            <input class="master-input" id="purchasePaymentDate" type="date" name="transaction_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentParticular">Particular <span class="master-required">*</span></label>
                            <input class="master-input" id="purchasePaymentParticular" type="text" name="particular" required
                                placeholder="Advance / payment against this document">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentAmount">Vendor-currency value <span class="master-required">*</span></label>
                            <input class="master-input" id="purchasePaymentAmount" type="number" step="0.0001" min="0.01" name="foreign_amount" required inputmode="decimal">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentCurrency">Currency <span class="master-required">*</span></label>
                            <select class="master-select" id="purchasePaymentCurrency" name="foreign_currency" required>
                                @foreach ($ledgerCurrencyOptions ?? ($currencyOptions ?? []) as $key => $label)
                                    <option value="{{ $key }}">{{ is_string($label) ? $label : $key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentRate">Exchange rate</label>
                            <input class="master-input" id="purchasePaymentRate" type="number" step="0.000001" min="0" name="exchange_rate"
                                placeholder="₹ per 1 unit" inputmode="decimal">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentInr">Amount in INR</label>
                            <input class="master-input" id="purchasePaymentInr" type="number" step="0.01" min="0" name="amount_in_inr"
                                placeholder="Calculated from the rate when left blank" inputmode="decimal">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentStatus">Status</label>
                            <select class="master-select" id="purchasePaymentStatus" name="status">
                                @foreach ($ledgerStatusOptions ?? ['booked' => 'Booked'] as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'booked')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentProject">Project</label>
                            <select class="master-select" id="purchasePaymentProject" name="project_id">
                                <option value="">No project mapping</option>
                                @foreach ($projects ?? [] as $project)
                                    <option value="{{ $project->id }}">{{ $project->project_number ?? '#'.$project->id }} - {{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentAccount">From account</label>
                            <select class="master-select" id="purchasePaymentAccount" name="paid_account_id">
                                <option value="">Select paid account</option>
                                @foreach($accounts ?? [] as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentMode">Payment mode</label>
                            <select class="master-select" id="purchasePaymentMode" name="payment_mode">
                                <option value="">Select mode</option>
                                @foreach($paymentModeOptions ?? [] as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentRef">Bank reference</label>
                            <input class="master-input" id="purchasePaymentRef" name="bank_reference_number" placeholder="UTR / TT / reference no.">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="purchasePaymentRemarks">Remarks</label>
                            <input class="master-input" id="purchasePaymentRemarks" name="remarks" placeholder="Additional details">
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="purchasePaymentFiles">Bill / proof attachments</label>
                            <input class="master-input" id="purchasePaymentFiles" type="file" name="attachments[]" multiple
                                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                        </div>
                        <div class="master-field full">
                            <label class="master-check">
                                <input type="hidden" name="record_cashflow" value="0">
                                <input type="checkbox" name="record_cashflow" value="1" checked>
                                Record this payment in the INR cashflow
                            </label>
                            <small class="master-help">Needs an exchange rate (or INR amount) so the cashflow entry can be created. An advance on a PO moves to the bill when you convert.</small>
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
