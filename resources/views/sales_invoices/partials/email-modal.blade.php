{{-- The compose dialog, shared by the listing's row menu and the record page,
     exactly like the reminder dialog beside it: the trigger carries the invoice
     and the defaults the model writes (`SalesInvoice::emailDefaults()`); the
     script fills the action from `data-action-template`. What goes out — To,
     subject, the whole message — is what the send log stores, so edit freely. --}}
<div class="master-modal" id="emailInvoiceModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="emailInvoiceModalTitle">
        <form method="POST" data-email-form
            data-action-template="{{ route('sales-invoices.email', ['salesInvoice' => '__INVOICE__']) }}">
            @csrf
            <input type="hidden" name="_dialog" value="emailInvoiceModal">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon">✉</span>
                    <div>
                        <h3 class="master-modal-title" id="emailInvoiceModalTitle">Email invoice to the client</h3>
                        <p class="master-modal-subtitle" data-email-subtitle>The invoice as the client reads it</p>
                    </div>
                </div>
            </div>
            <div class="master-modal-body">
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label" for="emailInvoiceTo">To</label>
                        <input class="master-input" id="emailInvoiceTo" type="text" name="to"
                            value="{{ old('to') }}" data-email-to
                            placeholder="accounts@client.example — comma or space for several">
                        @error('to')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="emailInvoiceCc">Cc</label>
                        <input class="master-input" id="emailInvoiceCc" type="text" name="cc"
                            value="{{ old('cc') }}" data-email-cc placeholder="optional">
                        @error('cc')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="emailInvoiceSubject">Subject</label>
                        <input class="master-input" id="emailInvoiceSubject" type="text" name="subject"
                            value="{{ old('subject') }}" data-email-subject maxlength="190">
                        @error('subject')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="emailInvoiceMessage">Message</label>
                        <textarea class="master-textarea" id="emailInvoiceMessage" name="message" rows="8"
                            data-email-message>{{ old('message') }}</textarea>
                        <p class="master-sub">
                            The invoice number, date, due date and totals travel in the mail on their own —
                            write to the person, don't repeat the sheet. The client copy link joins on its own
                            when the invoice is visible on the portal.
                        </p>
                        @error('message')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-check">
                            <input type="checkbox" name="mark_sent" value="1"
                                @checked(old('mark_sent', '1') === '1')>
                            <span>Mark as sent &amp; show in the client portal</span>
                        </label>
                        <p class="master-sub">
                            Same move as “Mark sent”: a draft becomes sent, <em>sent at</em> is stamped, and the
                            public copy opens. Untick when re-sending an already-issued invoice to someone new.
                        </p>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="emailInvoiceModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Send it
                </button>
            </div>
        </form>
    </div>
</div>

{{-- A failed save comes back with the input kept and the dialog reopened. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
