/* The document route owns its reporting state; only explicit date edits select custom. */
(function () {
    'use strict';
    const form = document.getElementById('statementPrintFilterForm');
    const period = document.getElementById('printPeriod');
    if (form && period) {
        form.querySelectorAll('input[type="date"]').forEach(input => {
            for (const event of ['input', 'change']) input.addEventListener(event, () => { period.value = 'custom'; });
        });
        period.addEventListener('change', () => {
            if (period.value !== 'custom') form.querySelectorAll('input[type="date"]').forEach(input => { input.value = ''; });
        });
    }
    document.querySelector('[data-browser-pdf]')?.addEventListener('click', event => {
        event.preventDefault();
        window.print();
    });
})();
