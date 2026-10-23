/* Shared payment position for the cashflow Add/Edit allocation dialogs.
 * Figures are already in document currency, including saved allocations.
 * Never subtract the editing line again or add an unsaved cash/FX amount. */
(function () {
    'use strict';

    function refresh(root, sales, purchase) {
        var summary = root.querySelector('[data-document-payment-summary]');
        if (!summary) return;
        var select = sales.value ? sales : purchase;
        var option = select.options[select.selectedIndex];
        if (!select.value || !option) {
            summary.hidden = true;
            summary.querySelectorAll('dd, [data-payment-heading], [data-payment-note]').forEach(function (item) { item.textContent = ''; });
            return;
        }
        var currency = option.getAttribute('data-currency') || 'INR';
        ['total', 'paid', 'balance'].forEach(function (key) {
            var raw = option.getAttribute('data-payment-' + key);
            var amount = Number(raw);
            summary.querySelector('[data-payment-' + key + ']').textContent = raw !== null && Number.isFinite(amount)
                ? currency + ' ' + amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : 'Not available';
        });
        summary.querySelector('[data-payment-heading]').textContent = 'Payment position · ' + option.getAttribute('data-payment-reference');
        summary.querySelector('[data-payment-total-label]').textContent = option.getAttribute('data-payment-total-label') || 'Document total';
        summary.querySelector('[data-payment-balance-label]').textContent = option.getAttribute('data-payment-balance-label') || 'Balance due';
        var note = summary.querySelector('[data-payment-note]');
        note.textContent = option.getAttribute('data-payment-note') || '';
        note.hidden = !note.textContent;
        summary.hidden = false;
    }

    window.CashflowDocumentBalance = { refresh: refresh };
})();
