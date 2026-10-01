/* ==========================================================================
   MASTER SELECTS — Select2 bootstrap for every form select (.master-select)
   Expects jQuery + Select2 loaded beforehand. Public pages and print views
   that do not load the vendor files are simply skipped.
   ========================================================================== */
(function () {
    'use strict';

    function init($) {
        if (!$ || !$.fn || !$.fn.select2) return;

        function decorate($sel) {
            if ($sel.data('select2')) return; // already initialised
            var optionsCount = $sel.find('option').length;
            $sel.select2({
                width: '100%',
                // keep dropdowns inside modals (fixed-positioned ancestors)
                dropdownParent: $sel.closest('.master-modal-box, .master-modal-card').length
                    ? $sel.closest('.master-modal-box, .master-modal-card')
                    : $('body'),
                // small option lists don't need a search box
                minimumResultsForSearch: optionsCount <= 8 ? 0 : Infinity,
                templateResult: function (data) {
                    if (data.id === '' && data.element && data.element.disabled) {
                        return $('<option>').text(data.text || '');
                    }
                    return data.text;
                },
                templateSelection: function (data) {
                    return data.text;
                }
            });
        }

        function initAll() {
            $('select.master-select').not('[data-no-select2]').not(':disabled').each(function () {
                decorate($(this));
            });
        }

        $(function () {
            initAll();

            // Re-decorate selects injected dynamically (AJAX modal rebuilds etc.).
            if (window.MutationObserver) {
                new MutationObserver(function (mutations) {
                    var found = false;
                    for (var i = 0; i < mutations.length; i++) {
                        var added = mutations[i].addedNodes;
                        for (var j = 0; j < added.length; j++) {
                            var node = added[j];
                            if (node.nodeType !== 1) continue;
                            if (node.matches && node.matches('select.master-select')) found = true;
                            if (node.querySelector && node.querySelector('select.master-select')) found = true;
                        }
                    }
                    if (found) initAll();
                }).observe(document.body, { childList: true, subtree: true });
            }
        });
    }

    window.MasterSelects = { init: init };

    if (window.jQuery) {
        init(window.jQuery);
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery) init(window.jQuery);
        });
    }
})();
