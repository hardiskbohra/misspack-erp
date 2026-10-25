/* The document route owns its reporting state; only explicit date edits select custom. */
(function () {
    'use strict';
    const form = document.getElementById('statementPrintFilterForm');
    const period = document.getElementById('printPeriod');
    if (form && period) {
        form.querySelectorAll('input[type="date"]').forEach(input => {
            for (const event of ['input', 'change']) input.addEventListener(event, () => {
                period.value = 'custom';
                if (window.jQuery) window.jQuery(period).trigger('change.select2');
            });
        });
        const applyPreset = () => {
            if (period.value !== 'custom') form.querySelectorAll('input[type="date"]').forEach(input => { input.value = ''; });
        };
        // Select2 emits a jQuery change; native-only listeners miss preset picks.
        if (window.jQuery) window.jQuery(period).on('change.statementPrint', applyPreset);
        else period.addEventListener('change', applyPreset);
    }
    document.querySelector('[data-browser-pdf]')?.addEventListener('click', event => {
        event.preventDefault();
        window.print();
    });
})();
