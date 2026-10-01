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
                // copy the original select's classes onto the rendered
                // selection element so module CSS hooks still reach it
                selectionCssClass: ':all:',
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
            // Select2 4.1 wraps the control in a generated .select2-container
            // span — that span is the flex/flow item in filter rows, but it
            // does not carry the original select's classes. Mirror them onto
            // the container so module layout rules (e.g.
            // .master-filter-row .master-select flex sizing) keep working.
            // select2-theme.css strips the native-control geometry from
            // span.select2-container.master-select, so the inner
            // .select2-selection remains the only visible (44px) control.
            var $container = $sel.next('.select2-container'); // Select2 4.x inserts it after the select
            if (!$container.length) $container = $sel.prev('.select2-container');
            // Mirror only the app's own classes — never Select2 internals
            // (e.g. select2-hidden-accessible, which would clip the control)
            var cls = ($sel.attr('class') || '').split(/\s+/).filter(function (c) {
                return !!c && c.indexOf('select2') !== 0;
            });
            if (cls.length) $container.addClass(cls.join(' '));
        }

        function initAll() {
            // [hidden] (attribute) is honoured — legacy hidden filter selects
            // stay native and invisible; CSS-hidden selects (e.g. inside
            // .master-modal { display:none }) are still initialised.
            $('select.master-select').not('[data-no-select2]').not(':disabled').not('[hidden]').each(function () {
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
