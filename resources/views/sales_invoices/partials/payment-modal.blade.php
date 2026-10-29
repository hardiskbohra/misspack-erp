{{-- One detailed receipt dialog is shared by the invoice list and record page.
     The opening button supplies invoice context; the server owns all ledger
     links and recalculates the selected account after saving. --}}
<div class="master-modal" id="paymentModal" aria-hidden="true">
    <div class="master-modal-card is-wide si-payment-modal" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
        <form method="POST" data-payment-form
            data-action-template="{{ route('sales-invoices.payments.store', ['salesInvoice' => '__INVOICE__']) }}">
            @csrf
            <input type="hidden" name="_dialog" value="paymentModal">
            <input type="hidden" name="_invoice_id" value="{{ old('_invoice_id') }}" data-payment-context-id>
            <input type="hidden" name="_invoice_number" value="{{ old('_invoice_number') }}" data-payment-context-number>
            <input type="hidden" name="_invoice_client" value="{{ old('_invoice_client') }}" data-payment-context-client>
            <input type="hidden" name="_invoice_balance" value="{{ old('_invoice_balance') }}" data-payment-context-balance>
            <input type="hidden" name="_invoice_currency" value="{{ old('_invoice_currency', 'INR') }}" data-payment-context-currency>

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="paymentModalTitle">Record invoice receipt</h3>
                        <p class="master-modal-subtitle" data-payment-subtitle>Post received money to the cashflow ledger</p>
                    </div>
                </div>
            </div>

            <div class="master-modal-body">
                <section class="si-payment-context" aria-label="Invoice being paid">
                    <div><span>Invoice</span><strong data-payment-invoice>—</strong></div>
                    <div><span>Client</span><strong data-payment-client>—</strong></div>
                    <div><span>Outstanding</span><strong data-payment-outstanding>—</strong></div>
                    <div><span>Currency</span><strong data-payment-currency>INR</strong></div>
                </section>

                <section class="si-payment-section">
                    <div class="si-payment-section-head">
                        <div><p class="master-eyebrow">Receipt details</p><h4>Money received</h4></div>
                        <span>Credit entry</span>
                    </div>
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="paymentAmount">Amount received <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="paymentAmount" type="number" step="0.001" min="0.001"
                                name="amount" value="{{ old('amount') }}" required>
                            @error('amount')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentDate">Received on <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="paymentDate" type="date" name="entry_date"
                                value="{{ old('entry_date', now()->toDateString()) }}" required>
                            @error('entry_date')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentAccount">Received into <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select" id="paymentAccount" name="account_id" required>
                                <option value="">Select account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}"
                                        data-balance="{{ number_format((float) $account->current_balance, 3, '.', '') }}"
                                        data-currency="{{ $account->currency ?: 'INR' }}"
                                        @selected((string) old('account_id') === (string) $account->id)>
                                        {{ $account->account_name }} · {{ $account->typeLabel() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('account_id')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentMode">Payment mode</label>
                            <select class="master-select" id="paymentMode" name="payment_mode">
                                <option value="">Select mode</option>
                                @foreach(\App\Models\CashflowEntry::paymentModeOptions() as $key => $label)
                                    <option value="{{ $key }}" @selected(old('payment_mode') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('payment_mode')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="si-account-balance" data-account-balance hidden>
                        <div><span>Current account balance</span><strong data-account-current>—</strong></div>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        <div><span>After this receipt</span><strong data-account-projected>—</strong></div>
                    </div>
                </section>

                <section class="si-payment-section">
                    <div class="si-payment-section-head"><div><p class="master-eyebrow">Ledger classification</p><h4>Accounting information</h4></div></div>
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="paymentStatus">Accounting status <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select" id="paymentStatus" name="accounting_status" required>
                                @foreach(\App\Models\CashflowEntry::accountingStatusOptions() as $key => $label)
                                    <option value="{{ $key }}" @selected(old('accounting_status', 'booked') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('accounting_status')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentCategory">Category</label>
                            <select class="master-select" id="paymentCategory" name="category_id">
                                <option value="">Uncategorized receipt</option>
                                @foreach(($receiptCategories ?? collect()) as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }} · {{ $category->typeLabel() }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentReference">Bank reference</label>
                            <input class="master-input" id="paymentReference" name="bank_reference_number"
                                value="{{ old('bank_reference_number') }}" placeholder="UTR, cheque or transaction number">
                            @error('bank_reference_number')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentParticular">Ledger particular</label>
                            <input class="master-input" id="paymentParticular" name="particular"
                                value="{{ old('particular') }}" placeholder="Defaults to receipt against invoice">
                            @error('particular')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="paymentNotes">Internal notes</label>
                            <textarea class="master-textarea" id="paymentNotes" name="notes" rows="3" placeholder="Optional reconciliation or follow-up note">{{ old('notes') }}</textarea>
                            @error('notes')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <div class="master-help si-payment-help">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>This creates a credit entry linked to the invoice, client and project. The selected account’s running balance is recalculated immediately.</span>
                </div>
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="paymentModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Record receipt</button>
            </div>
        </form>
    </div>
</div>

{{-- Validation returns to the same dialog with all submitted values retained. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
