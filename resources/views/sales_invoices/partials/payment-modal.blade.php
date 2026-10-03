{{-- The receipt dialog, shared by the listing and the record page.
     The button that opens it carries `data-open-payment` with the invoice it is
     for; the form's action is filled in from `data-action-template` and the
     module's script opens it through the shared `MasterModal`. One definition,
     because a second copy is a second set of field names to keep in step with
     `SalesInvoiceController::recordPayment()`. --}}
    <div class="master-modal" id="paymentModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
            <form method="POST" data-payment-form
                data-action-template="{{ route('sales-invoices.payments.store', ['salesInvoice' => '__INVOICE__']) }}">
                @csrf
                <input type="hidden" name="_dialog" value="paymentModal">
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">₹</span>
                        <div>
                            <h3 class="master-modal-title" id="paymentModalTitle">Record a receipt</h3>
                            <p class="master-modal-subtitle" data-payment-subtitle>Filed against the invoice in the cashflow ledger</p>
                        </div>
                    </div>
                </div>
                <div class="master-modal-body">
                    <div class="master-form-grid">
                        <div class="master-field full">
                            <label class="master-label" for="paymentAmount">Amount received <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="paymentAmount" type="number" step="0.01" min="0.01"
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
                            <label class="master-label" for="paymentAccount">Into account</label>
                            <select class="master-select" id="paymentAccount" name="account_id">
                                <option value="">Not decided</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="paymentMode">Mode</label>
                            <select class="master-select" id="paymentMode" name="payment_mode">
                                <option value="">Not decided</option>
                                @foreach(\App\Models\CashflowEntry::paymentModeOptions() as $key => $label)
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
                            <label class="master-label" for="paymentParticular">Particular</label>
                            <input class="master-input" id="paymentParticular" name="particular"
                                value="{{ old('particular') }}" placeholder="Leave empty to use the invoice number">
                        </div>
                    </div>
                    <p class="master-sub">
                        The receipt is a credit entry in the cashflow ledger, linked to this invoice — so the
                        client's statement, the ledger and this balance are the same money.
                    </p>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="paymentModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Record receipt</button>
                </div>
            </form>
        </div>
    </div>

{{-- A failed save comes back with the input kept and the dialog reopened: the
     controller re-sends the interaction with `_dialog` set to the dialog's id. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
