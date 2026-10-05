<div class="gs" id="globalSearch" hidden data-gs data-gs-url="{{ route('search') }}">
    <div class="gs-backdrop" data-gs-close></div>
    <div class="gs-panel" role="dialog" aria-modal="true" aria-labelledby="globalSearchTitle">
        <div class="gs-bar">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="globalSearchInput" class="gs-input" autocomplete="off" spellcheck="false"
                placeholder="Search clients, vendors, invoices, cashflow…" aria-labelledby="globalSearchTitle">
            <kbd class="gs-kbd">esc</kbd>
        </div>
        <p class="gs-hint" id="globalSearchTitle">Type at least two characters. Results come from every office module.</p>
        <div class="gs-body" data-gs-results></div>
    </div>
</div>
