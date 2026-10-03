/* ==========================================================================
   MASTER LIST — the behaviour every listing page shares
   --------------------------------------------------------------------------
   Loaded after the markup, before (or after) the module script — the module
   calls in with its own root and its own storage key:

       MasterList.density({ root: '.ship-index', key: 'misspack.shipments.density' });
       MasterList.rowNavigation({ root: '.ship-index' });
       MasterList.gridShadow({ root: '.ship-index' });
       MasterList.saveViewToggle();

   One implementation, so two lists cannot behave differently:

     • density       — comfortable/compact, remembered on the device and
                       applied before the table paints
     • rowNavigation — the whole row opens the record, unless the click lands
                       on something interactive or on a text selection
     • gridShadow    — the pinned header lifts once the list scrolls under it
     • saveViewToggle— the saved-view form appears on request and takes focus
   ========================================================================== */
'use strict';

window.MasterList = (function () {
    /* ---------------------------------------------------------- density */

    function applyDensity(root, value, buttonSelector) {
        var next = value === 'compact' ? 'compact' : 'comfortable';

        root.setAttribute('data-density', next);

        root.querySelectorAll(buttonSelector).forEach(function (button) {
            var active = button.getAttribute('data-density') === next;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function density(options) {
        var root = document.querySelector(options.root);
        var buttonSelector = options.buttons || '.master-list-density-btn';

        if (!root || !root.querySelector(buttonSelector)) return;

        var stored = null;
        try {
            stored = window.localStorage.getItem(options.key);
        } catch (e) {
            /* private mode: the buttons still work, the choice is just not kept */
        }

        applyDensity(root, stored, buttonSelector);

        root.querySelectorAll(buttonSelector).forEach(function (button) {
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-density');
                applyDensity(root, value, buttonSelector);

                try {
                    window.localStorage.setItem(options.key, value);
                } catch (e) { /* nothing to remember it with */ }
            });
        });
    }

    /* --------------------------------------------------- row navigation */

    function rowNavigation(options) {
        document.querySelectorAll(options.root + ' tbody tr[data-href]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                /* a click on an open action panel belongs to the panel, and a
                   text selection is a copy, not a navigation */
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

        var sync = function () {
            wrap.classList.toggle('is-scrolled', wrap.scrollTop > 0);
        };

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
                form.querySelector('input[name="name"]').focus();
            }
        });
    }

    return {
        density: density,
        rowNavigation: rowNavigation,
        gridShadow: gridShadow,
        saveViewToggle: saveViewToggle,
    };
})();
