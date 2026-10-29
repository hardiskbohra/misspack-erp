{{-- The three correction dialogs of the invoice record: credit note, debit
     note (one dialog, the trigger says which), and the sales return — plus the
     dialog that cancels a mistaken note with its own reason. All four post to
     routes whose service re-validates everything; these dialogs only collect. --}}

{{-- Credit / debit note --------------------------------------------------- --}}
<div class="master-modal" id="adjustNoteModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="adjustNoteTitle">
        <form method="POST" action="{{ route('sales-invoices.notes.store', $invoice) }}">
            @csrf
            <input type="hidden" name="_dialog" value="adjustNoteModal">
            <input type="hidden" name="kind" value="{{ old('kind', 'credit') }}" data-adjust-kind>
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" data-adjust-icon>₹</span>
                    <div>
                        <h3 class="master-modal-title" id="adjustNoteTitle" data-adjust-title>Credit note</h3>
                        <p class="master-modal-subtitle" data-adjust-subtitle>
                            Lowers what the client owes on {{ $invoice->invoice_number }}
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="adjustNoteModal" aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label" for="adjustNoteDate">Dated</label>
                        <input class="master-input" id="adjustNoteDate" type="date" name="note_date"
                            value="{{ old('note_date', now()->toDateString()) }}">
                        @error('note_date')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="adjustNoteAmount">Amount before tax</label>
                        <input class="master-input" id="adjustNoteAmount" type="number" step="0.01" min="0.01"
                            name="subtotal" value="{{ old('subtotal') }}" placeholder="0.00">
                        <p class="master-sub">Tax follows the invoice's split, pro-rated to this amount.</p>
                        @error('subtotal')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="adjustNoteReason">Why</label>
                        <textarea class="master-textarea" id="adjustNoteReason" name="reason" rows="3"
                            placeholder="Price revision, short supply, rate dispute — the reason this document exists">{{ old('reason') }}</textarea>
                        <p class="master-sub">A correction that cannot say why will not be issued.</p>
                        @error('reason')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="adjustNoteModal">Keep it as it is</button>
                <button type="submit" class="master-btn master-btn-primary" data-adjust-submit>Issue credit note</button>
            </div>
        </form>
    </div>
</div>

{{-- Return ---------------------------------------------------------------- --}}
@php
    /* On a record that never asked for its corrections there is nothing
       already returned — the dialog opens with every line returnable. */
    $notesModel = $invoice->relationLoaded('adjustments') ? $invoice->adjustments : collect();
    $returned = $notesModel->where('is_return', true)
        ->where('status', \App\Models\SalesInvoiceNote::STATUS_ISSUED)
        ->flatMap(fn ($note) => collect($note->lines ?? []))
        ->groupBy('item_id')
        ->map(fn ($rows) => (float) $rows->sum('quantity'));
    $returnable = $invoice->items->filter(fn ($item) =>
        round((float) $item->quantity - (float) ($returned[$item->id] ?? 0), 4) > 0);
@endphp
<div class="master-modal" id="returnNoteModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="returnNoteTitle">
        <form method="POST" action="{{ route('sales-invoices.returns.store', $invoice) }}">
            @csrf
            <input type="hidden" name="_dialog" value="returnNoteModal">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon">↩</span>
                    <div>
                        <h3 class="master-modal-title" id="returnNoteTitle">Record a sales return</h3>
                        <p class="master-modal-subtitle">
                            Goods back against {{ $invoice->invoice_number }} — one move: the return, the
                            restock, and the credit note together
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="returnNoteModal" aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                <div class="master-form-grid">
                    <div class="master-field">
                        <label class="master-label" for="returnNoteDate">Received back on</label>
                        <input class="master-input" id="returnNoteDate" type="date" name="note_date"
                            value="{{ old('note_date', now()->toDateString()) }}">
                    </div>
                    <div class="master-field full">
                        <label class="master-label">What came back, line by line</label>
                        <div class="si-return-lines">
                            @forelse ($returnable as $line)
                                @php
                                    $remaining = round((float) $line->quantity - (float) ($returned[$line->id] ?? 0), 4);
                                    $oldLines = collect(old('lines', []))->firstWhere('item_id', $line->id);
                                @endphp
                                <div class="si-return-line">
                                    <div>
                                        <strong>{{ $line->product_name }}</strong>
                                        <span class="master-sub">
                                            {{ (float) $line->quantity }} {{ $line->unit }} sold
                                            @if ((float) ($returned[$line->id] ?? 0) > 0)
                                                · {{ (float) $returned[$line->id] }} already back
                                            @endif
                                            · {{ \App\Helpers\CommonHelper::amount($line->unit_price, $invoice->currency) }}/{{ $line->unit }}
                                        </span>
                                    </div>
                                    <input type="hidden" name="lines[{{ $line->id }}][item_id]" value="{{ $line->id }}">
                                    <input class="master-input si-return-qty" type="number" step="0.001" min="0"
                                        max="{{ $remaining }}" name="lines[{{ $line->id }}][quantity]"
                                        value="{{ $oldLines['quantity'] ?? '' }}" placeholder="0 / {{ $remaining }}" aria-label="Return quantity for {{ $line->product_name }}">
                                </div>
                            @empty
                                <p class="master-sub">Every line on this invoice has already come back.</p>
                            @endforelse
                        </div>
                        @error('lines')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="returnNoteReason">Why it came back</label>
                        <textarea class="master-textarea" id="returnNoteReason" name="reason" rows="2"
                            placeholder="Damaged in transit, rejected at QC, wrong article —">{{ old('reason') }}</textarea>
                        @error('reason')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="master-field full">
                        <label class="master-check">
                            <input type="checkbox" name="restock" value="1" @checked(old('restock', '1') === '1')>
                            <span>Put the goods back into stock</span>
                        </label>
                        <p class="master-sub">Untick when the goods are scrapped — the credit stands either way.</p>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="returnNoteModal">Keep it as it is</button>
                <button type="submit" class="master-btn master-btn-primary">Record return &amp; issue credit</button>
            </div>
        </form>
    </div>
</div>

{{-- Cancelling a note ----------------------------------------------------- --}}
<div class="master-modal" id="cancelNoteModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="cancelNoteTitle">
        <form method="POST" data-cancel-note-form
            data-action-template="{{ route('sales-invoice-notes.cancel', ['note' => '__NOTE__']) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="_dialog" value="cancelNoteModal">
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon">✕</span>
                    <div>
                        <h3 class="master-modal-title" id="cancelNoteTitle">Cancel this note</h3>
                        <p class="master-modal-subtitle" data-cancel-note-subtitle>The note stays on record; its money stops applying</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="cancelNoteModal" aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                <div class="master-form-grid">
                    <div class="master-field full">
                        <label class="master-label" for="cancelNoteReason">Why is it being cancelled</label>
                        <textarea class="master-textarea" id="cancelNoteReason" name="cancel_reason" rows="2"
                            placeholder="Issued twice, wrong amount — the reason the correction itself was wrong">{{ old('cancel_reason') }}</textarea>
                        @error('cancel_reason')<p class="master-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="cancelNoteModal">Keep the note</button>
                <button type="submit" class="master-btn master-btn-danger">Cancel the note</button>
            </div>
        </form>
    </div>
</div>

{{-- Reopen the dialog a failed save came from; the marker must exist even
     when no error happened, like its siblings. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

@push('scripts')
<script>
    /* The triggers name the correction; the dialogs collect the facts. */
    document.addEventListener('DOMContentLoaded', function () {
        var adjust = document.getElementById('adjustNoteModal');
        if (adjust && window.MasterModal) {
            var COPY = {
                credit: { title: 'Credit note', subtitle: 'Lowers what the client owes on {{ $invoice->invoice_number }}',
                    icon: '↓', submit: 'Issue credit note' },
                debit: { title: 'Debit note', subtitle: 'Raises what the client owes on {{ $invoice->invoice_number }}',
                    icon: '↑', submit: 'Issue debit note' },
            };
            document.querySelectorAll('[data-open-adjust]').forEach(function (trigger) {
                trigger.addEventListener('click', function () {
                    var kind = trigger.getAttribute('data-kind') === 'debit' ? 'debit' : 'credit';
                    var copy = COPY[kind];
                    adjust.querySelector('[data-adjust-kind]').value = kind;
                    adjust.querySelector('[data-adjust-title]').textContent = copy.title;
                    adjust.querySelector('[data-adjust-subtitle]').textContent = copy.subtitle;
                    adjust.querySelector('[data-adjust-icon]').textContent = copy.icon;
                    adjust.querySelector('[data-adjust-submit]').textContent = copy.submit;
                    window.MasterModal.open(adjust);
                });
            });

            var ret = document.getElementById('returnNoteModal');
            var returnTrigger = document.querySelector('[data-open-return]');
            if (ret && returnTrigger) {
                returnTrigger.addEventListener('click', function () { window.MasterModal.open(ret); });
            }

            var cancel = document.getElementById('cancelNoteModal');
            var cancelForm = cancel ? cancel.querySelector('[data-cancel-note-form]') : null;
            if (cancel && cancelForm) {
                var template = cancelForm.getAttribute('data-action-template') || '';
                document.querySelectorAll('[data-open-cancel-note]').forEach(function (trigger) {
                    trigger.addEventListener('click', function () {
                        cancelForm.setAttribute('action', template.replace('__NOTE__', trigger.getAttribute('data-note-id')));
                        var subtitle = cancel.querySelector('[data-cancel-note-subtitle]');
                        if (subtitle) {
                            subtitle.textContent = (trigger.getAttribute('data-note-label') || 'This note')
                                + ' — stays on record; its money stops applying';
                        }
                        window.MasterModal.open(cancel);
                    });
                });
            }
        }
    });
</script>
@endpush
