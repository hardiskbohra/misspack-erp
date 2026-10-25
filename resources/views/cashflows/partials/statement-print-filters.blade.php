<div class="pdf-toolbar no-print">
    <a class="pdf-action pdf-action--primary" id="statementDownload"
        href="{{ route('cashflows.statements.pdf', $documentQuery) }}" @unless($pdfAvailable) data-browser-pdf @endunless>Download</a>
    <button class="pdf-action pdf-action--secondary" type="button" data-drawer-open="statementPrintFilters"
        aria-haspopup="dialog" aria-controls="statementPrintFilters" aria-expanded="false">Filters</button>
</div>
@unless($pdfAvailable)<p class="pdf-notice no-print">Download opens the print dialog. Choose Save as PDF, or select your printer.</p>@endunless
<form method="GET" action="{{ route('cashflows.statements.print', ['partyType'=>$statement['party_type'], 'party'=>$statement['party_id']]) }}" class="no-print" id="statementPrintFilterForm">
    <x-drawer id="statementPrintFilters" title="Statement filters" subtitle="Apply the same selection to the preview and download.">
        <div class="core-drawer-fields">
            <div class="master-field"><label class="master-label" for="printPeriod">Period</label>
                <select class="master-select" id="printPeriod" name="period">
                    @foreach($dateRangeLabels + ['all'=>'All time','custom'=>'Custom dates'] as $key=>$label)
                        <option value="{{ $key }}" @selected($periodKey === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="master-field"><label class="master-label" for="printFrom">From</label>
                <input class="master-input" type="date" id="printFrom" name="date_from" value="{{ $dateFrom }}"></div>
            <div class="master-field"><label class="master-label" for="printTo">To</label>
                <input class="master-input" type="date" id="printTo" name="date_to" value="{{ $dateTo }}"></div>
            <div class="master-field"><label class="master-label" for="printCurrency">Currency</label>
                <select class="master-select" id="printCurrency" name="currency">
                    @foreach($currencyOptions as $code)<option value="{{ $code }}" @selected($statement['currency'] === $code)>{{ $code }}</option>@endforeach
                </select></div>
            <div class="master-field"><input type="hidden" name="ageing" value="0">
                <label class="master-check"><input type="checkbox" name="ageing" value="1" @checked($ageing)> Include ageing</label></div>
        </div>
        <x-slot:footer><button class="pdf-action pdf-action--primary" type="submit">Apply filters</button></x-slot:footer>
    </x-drawer>
</form>
<script src="{{ asset('assets/js/master-drawer.js') }}"></script>
<script src="{{ asset('assets/js/statement-print.js') }}"></script>
