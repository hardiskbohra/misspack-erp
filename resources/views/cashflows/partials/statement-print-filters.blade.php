<div class="pdf-toolbar no-print">
    <a class="pdf-action pdf-action--primary" id="statementDownload"
        href="{{ route('cashflows.statements.pdf', $documentQuery) }}" @unless($pdfAvailable) data-browser-pdf @endunless>Download</a>
    <button class="pdf-action pdf-action--secondary" type="button" data-drawer-open="statementPrintFilters"
        aria-haspopup="dialog" aria-controls="statementPrintFilters" aria-expanded="false">Print options</button>
</div>
@unless($pdfAvailable)<p class="pdf-notice no-print">Download opens the print dialog. Choose Save as PDF, or select your printer.</p>@endunless
<form method="GET" action="{{ route('cashflows.statements.print', ['partyType'=>$statement['party_type'], 'party'=>$statement['party_id']]) }}" class="no-print" id="statementPrintFilterForm">
    <x-drawer id="statementPrintFilters" title="Print options" eyebrow="Statement of account"
        subtitle="Choose the period and fields for this preview and download.">
        <input type="hidden" name="fields_configured" value="1">
        @if($statement['party_type'] !== 'vendor' && $printFields['project_names'])
            <input type="hidden" name="print_fields[]" value="project_names">
        @endif
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
            <h3 class="core-drawer-section-title" id="printColumnsTitle">Transaction fields</h3>
            <p class="stmt-print-help">Date and reference also apply to ageing. Totals use every transaction, even when fields are hidden.</p>
            <div class="stmt-print-fields" role="group" aria-labelledby="printColumnsTitle">
                @foreach($printFieldLabels['columns'] as $key => $label)
                    <label class="master-check stmt-print-field">
                        <input type="checkbox" name="print_fields[]" value="{{ $key }}" @checked($printFields[$key])>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title" id="printSectionsTitle">Statement sections</h3>
            <p class="stmt-print-help">Summary and ageing can still show figures independently of the transaction fields.</p>
            <div class="stmt-print-fields" role="group" aria-labelledby="printSectionsTitle">
                @foreach($printFieldLabels['sections'] as $key => $label)
                    <label class="master-check stmt-print-field">
                        <input type="checkbox" name="print_fields[]" value="{{ $key }}" @checked($printFields[$key])>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="master-toggle-group stmt-print-ageing">
                <label class="master-label" for="printAgeing">Include ageing</label>
                <input type="hidden" name="ageing" value="0">
                <label class="master-switch">
                    <input type="checkbox" id="printAgeing" name="ageing" value="1" role="switch" @checked($ageing)>
                    <span class="master-slider" aria-hidden="true"></span>
                </label>
            </div>
        </section>
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title" id="printHeaderTitle">Header details</h3>
            <p class="stmt-print-help">Issuer and client/vendor names and the reporting period always remain visible.</p>
            <div class="stmt-print-fields" role="group" aria-labelledby="printHeaderTitle">
                @foreach($printFieldLabels['header'] as $key => $label)
                    <label class="master-check stmt-print-field">
                        <input type="checkbox" name="print_fields[]" value="{{ $key }}" @checked($printFields[$key])>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="stmt-print-help">Display choices are not redaction: the same information may appear in descriptions or other sections.</p>
            <button class="master-btn master-btn-soft" type="button" id="statementPrintShowAll">Show all fields</button>
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
