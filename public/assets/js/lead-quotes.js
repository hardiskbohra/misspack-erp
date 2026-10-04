/* ==========================================================================
   LEAD-QUOTES.JS — Lead Quotes module
   --------------------------------------------------------------------------
   Quote form behaviour: dynamic item rows (add from the <template> row,
   remove with a one-row minimum). Only the form page loads this script;
   the list and detail pages are static.

   The starting row index comes from the table's data-item-count attribute
   (Blade cannot render inside an external script).
   removeQuoteItemRow is exposed on window because the row buttons use
   inline onclick handlers in the view markup.
   ========================================================================== */
(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var table = document.getElementById('quoteItemsTable');
        var addButton = document.getElementById('addQuoteItemRow');
        var template = document.getElementById('quoteItemRowTemplate');
        if (!table || !addButton || !template) return;

        var tbody = table.querySelector('tbody');
        var quoteItemIndex = parseInt(table.getAttribute('data-item-count') || '0', 10);

        window.removeQuoteItemRow = function (btn) {
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
            }
        };

        addButton.addEventListener('click', function () {
            tbody.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', quoteItemIndex++));
        });
    });
})();
