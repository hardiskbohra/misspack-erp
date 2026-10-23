<section class="cf-document-payment" data-document-payment-summary hidden aria-live="polite" aria-atomic="true" aria-label="Selected document payment summary">
    <p class="cf-document-payment-title" data-payment-heading></p>
    <dl class="cf-document-payment-values">
        <div><dt data-payment-total-label>Document total</dt><dd data-payment-total></dd></div>
        <div><dt>Paid so far</dt><dd data-payment-paid></dd></div>
        <div><dt data-payment-balance-label>Balance due</dt><dd data-payment-balance></dd></div>
    </dl>
    <p class="master-field-hint" data-payment-note hidden></p>
    <p class="master-field-hint">Saved at page load, including saved allocations. Unsaved changes are not included.</p>
</section>
@once
    @push('scripts')
        <script src="{{ $assetVer('assets/js/cashflow-document-balance.js') }}"></script>
    @endpush
@endonce
