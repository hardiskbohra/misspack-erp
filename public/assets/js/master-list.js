/* Shared table behavior: row navigation, the pinned-header shadow, and the
   saved-view form toggle. The parts of a list that are behaviour live here;
   the parts that are geometry are the sheet's.

   There is one row rhythm in the ERP — the comfortable one — and it is
   declared once, in the shared table contract (`components/tables.css`), the
   same way for every list and every detail table. Nothing here reads or
   stores a density, and no screen offers one to pick: a table that could be
   three different heights was three different tables to keep in step.

   Columns are the module's to declare, too. The chooser that used to live
   here let a reader hide a column and remembered it on the device, which
   meant a screen could be missing the figure the reader came for and the
   office could not reproduce it from the same page. What a screen draws is
   what its controller sends. */
'use strict';

window.MasterList = (function () {
    /* --------------------------------------------------- row navigation */

    function rowNavigation(options) {
        document.querySelectorAll(options.root + ' tbody tr[data-href]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, label, form, .master-dropdown')) return;
                if (window.getSelection && String(window.getSelection()).length > 0) return;
                window.location.href = row.dataset.href;
            });
        });
    }

    /* ------------------------------------------------------ grid shadow */

    function gridShadow(options) {
        var wrap = document.querySelector(options.root + ' .master-table-wrap');
        if (!wrap) return;
        var sync = function () { wrap.classList.toggle('is-scrolled', wrap.scrollTop > 0); };
        wrap.addEventListener('scroll', sync, { passive: true });
        sync();
    }

    /* ------------------------------------------------------ saved views */

    function saveViewToggle(toggleId, formId) {
        var toggle = document.getElementById(toggleId || 'toggleSaveView');
        var form = document.getElementById(formId || 'saveViewForm');
        if (!toggle || !form) return;
        toggle.addEventListener('click', function () {
            form.hidden = !form.hidden;
            if (!form.hidden) {
                var name = form.querySelector('input[name="name"]');
                if (name) name.focus();
            }
        });
    }

    return {
        rowNavigation: rowNavigation,
        gridShadow: gridShadow,
        saveViewToggle: saveViewToggle,
    };
})();
