<div class="pdf-toolbar no-print">
    <a class="pdf-action pdf-action--primary" id="statementDownload"
        href="{{ route('cashflows.statements.pdf', $documentQuery) }}" @unless($pdfAvailable) data-browser-pdf @endunless>Download</a>
    <button class="pdf-action pdf-action--secondary" type="button" data-drawer-open="statementPrintFilters"
        aria-haspopup="dialog" aria-controls="statementPrintFilters" aria-expanded="false">Filters</button>
</div>
@unless($pdfAvailable)<p class="pdf-notice no-print">Download opens the print dialog. Choose Save as PDF, or select your printer.</p>@endunless
<form method="GET" action="{{ route('cashflows.statements.print', ['partyType'=>$statement['party_type'], 'party'=>$statement['party_id']]) }}" class="no-print" id="statementPrintFilterForm">
    <x-drawer id="statementPrintFilters" title="Statement filters" eyebrow="Reporting filters"
        subtitle="Apply the same selection to the preview and download.">
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Reporting period</h3>
            <div class="core-drawer-fields">
                <div class="master-field"><label class="master-label" for="printPeriod">Period</label>
                    <select class="master-select" id="printPeriod" name="period">
                        @foreach($dateRangeLabels + ['all'=>'All time','custom'=>'Custom dates'] as $key=>$label)
                            <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field"><label class="master-label" for="printCurrency">Currency</label>
                    <select class="master-select" id="printCurrency" name="currency">
                        @foreach($currencyOptions as $code)<option value="{{ $code }}" @selected($statement['currency'] === $code)>{{ $code }}</option>@endforeach
                    </select>
                </div>
                <div class="master-field"><label class="master-label" for="printFrom">From</label>
                    <input class="master-input" type="date" id="printFrom" name="date_from" value="{{ $dateFrom }}"></div>
                <div class="master-field"><label class="master-label" for="printTo">To</label>
                    <input class="master-input" type="date" id="printTo" name="date_to" value="{{ $dateTo }}"></div>
            </div>
        </section>
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Statement options</h3>
            <div class="master-toggle-group">
                <label class="master-label" for="printAgeing">Include ageing</label>
                <input type="hidden" name="ageing" value="0">
                <label class="master-switch">
                    <input type="checkbox" id="printAgeing" name="ageing" value="1" role="switch" @checked($ageing)>
                    <span class="master-slider" aria-hidden="true"></span>
                </label>
            </div>
        </section>
        <x-slot:footer>
            <button class="master-btn master-btn-soft pdf-action pdf-action--secondary" type="button" data-drawer-close>Cancel</button>
            <button class="master-btn master-btn-primary pdf-action pdf-action--primary" type="submit">Apply filters</button>
        </x-slot:footer>
    </x-drawer>
</form>
<script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
<script src="{{ $assetVer('assets/js/master-selects.js') }}"></script>
<script src="{{ $assetVer('assets/js/master-drawer.js') }}"></script>
<script src="{{ $assetVer('assets/js/statement-print.js') }}"></script>
