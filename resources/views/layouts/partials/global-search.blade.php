<div class="gs" id="globalSearch" hidden data-gs data-gs-url="{{ route('search') }}">
    <div class="gs-backdrop" data-gs-close></div>
    <div class="gs-panel" role="dialog" aria-modal="true" aria-labelledby="globalSearchTitle">
        <div class="gs-bar">
            <i class="fas fa-search gs-icon" aria-hidden="true"></i>
            <input type="text" id="globalSearchInput" class="gs-input" autocomplete="off" spellcheck="false"
                placeholder="Search the office…" aria-labelledby="globalSearchTitle">
            <button type="button" class="gs-esc" data-gs-close>Esc</button>
        </div>
        <div class="gs-body" data-gs-results>
            <p class="gs-empty" id="globalSearchTitle">Clients, vendors, products, projects, leads, invoices, cashflow, shipments, tasks, people and settings.</p>
        </div>
        <div class="gs-foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> move</span>
            <span><kbd>↵</kbd> open</span>
            <span><kbd>esc</kbd> close</span>
        </div>
    </div>
</div>
