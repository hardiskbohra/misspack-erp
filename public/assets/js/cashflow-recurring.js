/* ==========================================================================
   RECURRING CASHFLOW — the module's own behaviour, and only that.

   Two dialogs, one form. A rule is written from the list (`#recurrenceRuleModal`)
   and edited from its own page (`#recurrenceRuleEditModal`), and both are the
   same partial on the server — so this file does three things and invents
   nothing:

     1. opens the dialog the page offers, from every control that offers it —
        the header action, the empty state, the toolbar and the record's own
        "Edit the draft" all carry their own marker attribute;
     2. reopens the dialog the server came back from. A save that failed
        validation redirects to the page with the typing kept in `old()` and
        `_dialog` naming the dialog it came from; without this the reader lands
        on the list with an error and no form to fix;
     3. hands the list the two shared behaviours every module list has —
        the whole row opening its rule, and the pinned header's shadow.

   Closing (the button, Escape, the backdrop) is the shared `MasterModal`
   lifecycle in `app-layout.js`. A second implementation here is how two dialogs
   on one page start behaving differently.
   ========================================================================== */
(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function openModal(modal) {
        if (!modal) return;

        if (window.MasterModal) {
            window.MasterModal.open(modal);
            return;
        }

        /* The shell's script is deferred as well; if it has not run yet the
           dialog still opens, and the shared close handlers pick its state up
           when they bind. */
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        var dialogs = {
            create: byId('recurrenceRuleModal'),
            edit: byId('recurrenceRuleEditModal'),
        };

        if (dialogs.create && window.MasterList) {
            window.MasterList.rowNavigation({ root: '.cfr-index' });
            window.MasterList.gridShadow({ root: '.cfr-index' });
        }

        var openers = [
            ['[data-open-rule-modal]', dialogs.create],
            ['[data-open-rule-edit-modal]', dialogs.edit],
        ];

        openers.forEach(function (pair) {
            document.querySelectorAll(pair[0]).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    openModal(pair[1]);
                });
            });
        });

        /* One marker per page, naming the dialog the server sent the reader back
           from — and the cursor in the first field, so the fix is one keystroke
           away. */
        var marker = document.querySelector('[data-open-dialog]');
        var reopen = marker ? marker.getAttribute('data-open-dialog') : '';

        if (reopen === 'recurrenceRuleModal') {
            openModal(dialogs.create);
            var title = dialogs.create ? dialogs.create.querySelector('input[name="title"]') : null;
            if (title) title.focus();
        }

        if (reopen === 'recurrenceRuleEditModal') {
            openModal(dialogs.edit);
            var editTitle = dialogs.edit ? dialogs.edit.querySelector('input[name="title"]') : null;
            if (editTitle) editTitle.focus();
        }
    });
})();
