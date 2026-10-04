{{-- The reminder dialog, shared by the listing and the record page.
     `data-open-reminder` on the button carries the invoice and the words the model
     writes (`SalesInvoice::reminderMessage()`); the script fills the action from
     `data-action-template` and opens it through the shared `MasterModal`. --}}
<div class="master-modal" id="reminderModal" aria-hidden="true">
    <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="reminderModalTitle">
        <form method="POST" data-reminder-form
            data-action-template="{{ route('sales-invoices.reminders.store', ['salesInvoice' => '__INVOICE__']) }}">
            @csrf
            <input type="hidden" name="_dialog" value="reminderModal">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon">☎</span>
                    <div>
                        <h3 class="master-modal-title" id="reminderModalTitle">Log a reminder</h3>
                        <p class="master-modal-subtitle" data-reminder-subtitle>What was asked, and when</p>
                    </div>
                </div>
            </div>
            <div class="master-modal-body">
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label" for="reminderChannel">How</label>
                        <select class="master-select" id="reminderChannel" name="channel">
                            @foreach($reminderChannels as $channelKey => $channelLabel)
                                <option value="{{ $channelKey }}" @selected(old('channel', 'whatsapp') === $channelKey)>{{ $channelLabel }}</option>
                            @endforeach
                        </select>
                        @error('channel')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="reminderDate">Asked on</label>
                        <input class="master-input" id="reminderDate" type="date" name="reminded_at"
                            value="{{ old('reminded_at', now()->toDateString()) }}">
                        @error('reminded_at')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="reminderMessage">What was sent</label>
                        <textarea class="master-textarea" id="reminderMessage" name="message" rows="6"
                            data-reminder-message>{{ old('message') }}</textarea>
                        <p class="master-sub">
                            The model writes this — the amount still owed, how late it is, and the client's link
                            when the invoice is on the portal.
                            <button type="button" class="master-link" data-copy-target="reminderMessage"
                                data-copy-label="Copy">Copy</button>
                        </p>
                        @error('message')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="reminderNote">What came back</label>
                        <textarea class="master-textarea" id="reminderNote" name="note" rows="2"
                            placeholder="Promised Friday · cheque couriered · no answer">{{ old('note') }}</textarea>
                        @error('note')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="reminderModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">Log it</button>
            </div>
        </form>
    </div>
</div>

{{-- A failed save comes back with the input kept and the dialog reopened. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>
