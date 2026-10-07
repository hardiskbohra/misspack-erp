/* ==========================================================================
   NOTES — the desk's own behaviour, and only that.

   The composer is a dialog, so this file does two things:

     1. opens it from every "New note" control on the page — the topbar action,
        the empty state, and the toolbar above the list all carry
        `[data-open-note-modal]`, so a reader never has to find the page's top;
     2. reopens it when the server says a note came back with errors. A failed
        save redirects to this page with the typing kept and `_dialog` naming the
        dialog it came from; without this the reader lands on the desk with an
        error message and no form.

   Closing — the close button, Escape and the backdrop — is the shared
   `MasterModal` lifecycle in `app-layout.js`; a second implementation here is
   how two dialogs on one page start behaving differently.

   The modal is server-rendered with `old()`, so reopening is all that is left
   to do: the fields already hold what was typed.
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
        var modal = byId('noteCreateModal');
        if (!modal) return;

        /* The desk's own behaviour: the whole row opens its note, and the
           table's pinned header keeps its shadow while it scrolls. The shared
           `master-list.js` skips clicks inside links, buttons, forms and the
           row menu, so the controls in the last column still work. */
        if (window.MasterList) {
            window.MasterList.rowNavigation({ root: '.nt-index' });
            window.MasterList.gridShadow({ root: '.nt-index' });
        }

        document.querySelectorAll('[data-open-note-modal]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                openModal(modal);
            });
        });

        var marker = document.querySelector('[data-open-dialog]');
        var reopen = marker ? marker.getAttribute('data-open-dialog') : '';

        if (reopen === 'noteCreateModal') {
            openModal(modal);

            var first = modal.querySelector('#noteTitle');
            if (first) first.focus();
        }
    });
})();
