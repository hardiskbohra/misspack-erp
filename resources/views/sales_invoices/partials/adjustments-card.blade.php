{{-- Adjustments & returns — the record of every correction posted against a
     tax invoice. An issued tax invoice never changes its money by edit: the
     trail here is what changed instead, why, and who signed it. --}}
@php
    /** @var \App\Models\SalesInvoice $invoice — like the send log beside it,
        the card appears only once the record asked for its corrections. */
    $notesSupported = $invoice->relationLoaded('adjustments');
    $notes = $notesSupported ? $invoice->adjustments : collect();
    $adjustmentsUnlocked = $invoice->invoice_type === 'tax'
        && $invoice->status !== 'draft'
        && $invoice->status !== 'cancelled'
        && ! $invoice->isSuperseded();
    $credited = $invoice->creditedTotal();
    $debited = $invoice->debitedTotal();
    $payable = $invoice->payableTotal();
    $paper = $invoice->receivableTotal();
@endphp

@if ($invoice->invoice_type === 'tax' && $notesSupported)
    <div class="master-card master-section">
        <div class="master-section-head">
            <div>
                <h2 class="master-section-title">Adjustments &amp; returns</h2>
                <p class="master-sub">
                    @if ($notes->isEmpty())
                        Corrections go here — the invoice itself stays untouched
                    @else
                        {{ $credited > 0 ? 'Credited '.\App\Helpers\CommonHelper::amount($credited, $invoice->currency) : '' }}
                        {{ $debited > 0 ? ' · Debited '.\App\Helpers\CommonHelper::amount($debited, $invoice->currency) : '' }}
                        {{-- What the paper says vs what the client owes --}}
                        · owed now {{ \App\Helpers\CommonHelper::amount($payable, $invoice->currency) }}
                        (paper {{ \App\Helpers\CommonHelper::amount($paper, $invoice->currency) }})
                    @endif
                </p>
            </div>
            @if ($adjustmentsUnlocked)
                <div class="master-section-meta">
                    <button type="button" class="master-btn master-btn-soft" data-open-adjust data-kind="credit">
                        <i class="fas fa-arrow-trend-down" aria-hidden="true"></i> Credit note
                    </button>
                    <button type="button" class="master-btn master-btn-soft" data-open-adjust data-kind="debit">
                        <i class="fas fa-arrow-trend-up" aria-hidden="true"></i> Debit note
                    </button>
                    <button type="button" class="master-btn master-btn-soft" data-open-return>
                        <i class="fas fa-rotate-left" aria-hidden="true"></i> Record return
                    </button>
                </div>
            @endif
        </div>

        @if ($notes->isEmpty())
            <div class="master-empty-state">
                <i class="fas fa-file-circle-check"></i>
                <p>
                    @if ($invoice->status === 'draft')
                        Drafts still edit freely — corrections come into play once the invoice is issued.
                    @elseif ($invoice->status === 'cancelled')
                        A cancelled invoice takes no corrections; its trail ends at the cancellation.
                    @else
                        Nothing corrected yet. When money must move after issue — a price dispute, damaged
                        goods, a return — issue a credit or debit note here; the invoice stays as it was
                        issued, and the note carries the change with its reason.
                    @endif
                </p>
            </div>
        @else
            <div class="si-reminder-list">
                @foreach ($notes as $note)
                    <div class="si-reminder">
                        <div class="si-reminder-when">
                            <strong>
                                <a class="si-payment-ref" href="{{ route('sales-invoice-notes.print', $note) }}">
                                    {{ $note->number }}
                                </a>
                                <span class="master-status-chip {{ $note->isLive() ? ($note->kind === 'credit' ? 'info' : 'warning') : 'danger' }}">
                                    {{ $note->isLive() ? ($note->is_return ? 'Return' : ($note->kind === 'credit' ? 'Credit' : 'Debit')) : 'Cancelled' }}
                                </span>
                            </strong>
                            <span class="master-sub">{{ $note->note_date->format('d M Y') }}</span>
                        </div>
                        <div class="si-reminder-what">
                            {{-- The amount carries its own sign: a credit is
                                 negative money on the client's side, a debit
                                 positive — formatted, not hand-assembled. --}}
                            <strong class="{{ $note->kind === 'credit' ? 'si-clear' : 'si-due' }}">
                                {{ \App\Helpers\CommonHelper::amount($note->kind === 'credit' ? -(float) $note->total : (float) $note->total, $invoice->currency) }}
                                @if ($note->restock) · restocked @endif
                            </strong>
                            <span class="master-sub">
                                {{ \Illuminate\Support\Str::limit($note->reason, 90) }}
                                @if ($note->creator) · by {{ $note->creator->name }} @endif
                                @if (! $note->isLive())
                                    · cancelled because {{ \Illuminate\Support\Str::limit($note->cancel_reason, 60) }}
                                @endif
                            </span>
                        </div>
                        @if ($note->isLive() && $adjustmentsUnlocked)
                            <div>
                                <button type="button" class="master-btn master-btn-light" data-open-cancel-note
                                    data-note-id="{{ $note->id }}" data-note-label="{{ $note->titleLabel() }}">
                                    Cancel
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
