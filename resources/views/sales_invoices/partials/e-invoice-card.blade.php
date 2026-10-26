{{--
    The e-invoice (IRN/QR) card on a tax invoice's record page.

    Four states, one card:
      - no record        → pre-flight reasons (if any) or the generate button
      - pending          → the payload to copy out, plus the form that records
                           the portal's IRN/Ack/QR back (the manual provider)
      - registered       → IRN, acknowledgement and the QR; cancel inside the
                           portal's 24-hour window
      - failed/cancelled → what happened and the only honest next step

    `$eInvoiceIssues` is computed by the controller; `$invoice->eInvoice` is
    the registration row or null. Renders only for tax invoices — the caller
    guards that, so nothing here re-asks "is this taxable".
--}}
@php
    $eInv = $invoice->eInvoice ?? null;
    $cancelReasons = \App\Models\SalesInvoiceEInvoice::CANCEL_REASONS;
@endphp

<section class="master-card master-section" id="eInvoiceCard" aria-labelledby="eInvoiceTitle">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title" id="eInvoiceTitle"><i class="fas fa-qrcode" aria-hidden="true"></i> E-invoice (IRN/QR)</h2>
            <p class="master-sub">GST-network registration: the Invoice Reference Number and signed QR the law
                expects on a tax invoice when turnover crosses the e-invoice threshold.</p>
        </div>
        @if ($eInv)
            <span class="master-chip si-status-{{ $eInv->status === 'registered' ? 'positive' : ($eInv->status === 'cancelled' ? 'neutral' : 'warning') }}">
                {{ ucfirst($eInv->status) }}@if ($eInv->provider) · via {{ $eInv->provider }} @endif
            </span>
        @endif
    </div>

    @unless ($eInv)
        @if ($eInvoiceIssues)
            <div class="master-alert master-alert-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span><strong>Not registerable yet:</strong>
                    <ul style="margin:6px 0 0 18px;">@foreach ($eInvoiceIssues as $issue)<li>{{ $issue }}</li>@endforeach</ul>
                </span>
            </div>
        @else
            <p class="master-help">The invoice passes every pre-check (seller/buyer GSTIN, HSN on every line, place
                of supply). Generating builds the NIC-schema JSON and, on the manual path, holds it here for you to
                register on the e-invoice portal.</p>
            <form method="POST" action="{{ route('sales-invoices.einvoice.generate', $invoice) }}">
                @csrf
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-bolt" aria-hidden="true"></i> Generate e-invoice payload</button>
            </form>
        @endif
    @else
        @if ($eInv->status === \App\Models\SalesInvoiceEInvoice::STATUS_FAILED)
            <div class="master-alert master-alert-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span><strong>Registration failed:</strong> {{ $eInv->error }}</span></div>
            <form method="POST" action="{{ route('sales-invoices.einvoice.generate', $invoice) }}">
                @csrf
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-rotate" aria-hidden="true"></i> Try again</button>
            </form>
        @elseif ($eInv->isRegistered())
            <div class="master-grid-2">
                <div class="master-field"><span class="master-label">IRN</span>
                    <code class="set-hsn-code" style="word-break: break-all;">{{ $eInv->irn }}</code></div>
                <div class="master-field"><span class="master-label">Acknowledgement</span>
                    <p>{{ $eInv->ack_no ?: '—' }}@if ($eInv->ack_date) · {{ $eInv->ack_date->format('d M Y H:i') }} @endif</p></div>
            </div>
            @if ($eInv->signed_qr_code)
                <div class="si-einvoice-qr" data-einvoice-qr>{{ $eInv->signed_qr_code }}</div>
                <p class="master-help">This QR verifies the invoice offline with the IRN: verify apps. It also prints
                    on the invoice document.</p>
            @endif

            @if ($eInv->isCancellable())
                <details class="si-details">
                    <summary class="master-btn master-btn-light master-btn-sm"><i class="fas fa-ban" aria-hidden="true"></i> Cancel this IRN</summary>
                    <form method="POST" action="{{ route('sales-invoices.einvoice.cancel', $invoice) }}" class="master-form"
                        onsubmit="return confirm('Cancel the IRN on the GST network? This cannot be undone — a new invoice would need a new IRN.');">
                        @csrf
                        <div class="master-form-grid is-two">
                            <div class="master-field">
                                <label class="master-label" for="cancelReason">Reason (portal codes)</label>
                                <select class="master-select" id="cancelReason" name="cancel_reason">
                                    @foreach ($cancelReasons as $code => $label)
                                        <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field">
                                <label class="master-label" for="cancelRemark">Remark (optional, ≤100 chars)</label>
                                <input class="master-input" id="cancelRemark" name="cancel_remark" maxlength="100">
                            </div>
                        </div>
                        <button type="submit" class="master-btn master-btn-danger">Cancel IRN</button>
                    </form>
                </details>
            @elseif ($eInv->cancelled_at === null && $eInv->ack_date)
                <p class="master-help">The portal's 24-hour cancellation window closed
                    {{ $eInv->ack_date->copy()->addHours(24)->format('d M Y H:i') }} — corrections now move through a credit note.</p>
            @endif
        @elseif ($eInv->status === \App\Models\SalesInvoiceEInvoice::STATUS_CANCELLED)
            <div class="master-alert"><i class="fas fa-circle-info" aria-hidden="true"></i>
                <span>IRN cancelled {{ $eInv->cancelled_at?->format('d M Y H:i') }}
                    ({{ $cancelReasons[$eInv->cancel_reason] ?? 'unknown' }}@if ($eInv->cancel_remark): {{ $eInv->cancel_remark }}@endif).</span></div>
        @else
            {{-- pending: payload out, acknowledgement in --}}
            <details class="si-details">
                <summary class="master-btn master-btn-light"><i class="fas fa-code" aria-hidden="true"></i> The payload to register (NIC schema JSON)</summary>
                <p class="master-help">Paste this into your GSP's generate-IRN call, or mirror the fields on
                    einvoice.gst.gov.in's "Generate IRN" form. It is also what a future API integration would POST.</p>
                <pre class="si-einvoice-payload" id="eInvoicePayload">{{ json_encode($eInv->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                <button type="button" class="master-btn master-btn-light master-btn-sm" data-copy="#eInvoicePayload">
                    <i class="fas fa-copy" aria-hidden="true"></i> Copy JSON</button>
            </details>

            <form method="POST" action="{{ route('sales-invoices.einvoice.record', $invoice) }}" class="master-form">
                @csrf
                <h3 class="master-section-title">Record the portal's acknowledgement</h3>
                <div class="master-form-grid is-two">
                    <div class="master-field">
                        <label class="master-label" for="eInvIrn">IRN (64 hex characters)</label>
                        <input class="master-input" id="eInvIrn" name="irn" minlength="64" maxlength="64" required
                            placeholder="a5c12d…" style="font-family: var(--ui-font-mono, monospace);">
                        <p class="master-field-error">@error('irn'){{ $message }}@enderror</p>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="eInvAckNo">Ack number</label>
                        <input class="master-input" id="eInvAckNo" name="ack_no" maxlength="30" placeholder="162510000123456">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="eInvAckDate">Ack date &amp; time</label>
                        <input class="master-input" id="eInvAckDate" name="ack_date" type="datetime-local">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="eInvQr">Signed QR string (from the portal response)</label>
                        <textarea class="master-input" id="eInvQr" name="signed_qr_code" rows="3"
                            placeholder="eyJhbGciOiJSUzI1NiIs…"></textarea>
                    </div>
                </div>
                <button type="submit" class="master-btn master-btn-primary"><i class="fas fa-check" aria-hidden="true"></i> Record registration</button>
            </form>
        @endif
    @endunless
</section>

@if ($eInv && $eInv->isRegistered() && $eInv->signed_qr_code)
    @pushOnce('scripts')
        <script src="{{ $assetVer('assets/js/qr.js') }}"></script>
        <script>
            /* Render the portal's signed QR locally (offline, no external call). */
            document.querySelectorAll('[data-einvoice-qr]').forEach(function (node) {
                var payload = node.textContent.trim();
                try { node.innerHTML = window.ShipQR.svg(payload, { quiet: 2 }); }
                catch (e) { node.innerHTML = '<p class="master-help">QR payload is too long to render here; the print keeps it as text.</p>'; }
            });
        </script>
    @endPushOnce
@endif
