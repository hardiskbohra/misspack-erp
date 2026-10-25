/* Date edits opt into a custom window; untouched All time survives search/Apply. */
(function () {
    'use strict';
    function init() {
        const form = document.getElementById('statementListFiltersForm');
        if (!form) return;
        const period = form.querySelector('[name="period"]');
        for (const field of form.querySelectorAll('input[type="date"]')) {
            field.addEventListener('input', () => { period.value = 'custom'; });
            field.addEventListener('change', () => { period.value = 'custom'; });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
