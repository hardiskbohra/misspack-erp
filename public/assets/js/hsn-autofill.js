/* ==========================================================================
   HSN-AUTOFILL.JS — one HSN/SAC master, offered on every invoice line grid
   --------------------------------------------------------------------------
   Every module's line grid (sales invoices, purchase invoices) builds its
   rows from JS strings, so the codes travel the same way the product options
   do: a `data-hsn-codes` JSON attribute on `#invoiceItemsBody`, offered as a
   native `<datalist>` on the HSN input of every row — present and future.

   Picking a mastered code fills the row's GST rate *advisorily*: the rate is
   written into the field, the totals recompute (a real `input` event is
   dispatched, never a fake by-pass), and the user can still override it —
   obligations, not straightjackets.
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
        var body = document.getElementById('invoiceItemsBody');
        if (!body || !body.dataset.hsnCodes) return;

        var codes;
        try { codes = JSON.parse(body.dataset.hsnCodes); } catch (e) { codes = []; }
        if (!codes.length) return;

        var byCode = {};
        codes.forEach(function (entry) { byCode[entry.code] = entry; });

        /* One datalist for the whole grid; rows point at it as they appear. */
        var listEl = document.createElement('datalist');
        listEl.id = 'hsnSacMasterList';
        codes.forEach(function (entry) {
            var opt = document.createElement('option');
            opt.value = entry.code;
            opt.label = entry.kind === 'service' ? 'SAC' : 'HSN';
            opt.label += ' ' + entry.gst_rate + '% — ' + entry.description;
            listEl.appendChild(opt);
        });
        document.body.appendChild(listEl);

        function arm(input) {
            if (input.dataset.hsnArmed) return;
            input.dataset.hsnArmed = '1';
            input.setAttribute('list', 'hsnSacMasterList');
        }

        body.querySelectorAll('input[name$="[hsn_sac]"]').forEach(arm);

        /* Rows are added by string-insertion — watch instead of re-listening. */
        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    (node.matches('input[name$="[hsn_sac]"]') ? [node]
                        : Array.prototype.slice.call(node.querySelectorAll('input[name$="[hsn_sac]"]'))).forEach(arm);
                });
            });
        }).observe(body, { childList: true, subtree: true });

        body.addEventListener('change', function (event) {
            if (!event.target.matches('input[name$="[hsn_sac]"]')) return;

            var entry = byCode[event.target.value.trim()];
            if (!entry) return;

            var row = event.target.closest('tr');
            var rateInput = row && row.querySelector('input[name$="[gst_percent]"]');
            if (!rateInput) return;

            rateInput.value = entry.gst_rate;
            /* The grids recompute on input events, so fire one for real. */
            rateInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });
})();
