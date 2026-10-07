/* ==========================================================================
   FIXED ASSETS — the register's own behaviour, and only that.

   One idea, applied everywhere: **a door is a marker, not an address.** Every
   control that does something to an asset carries

       data-open-asset-modal="allocate"

   and each dialog's form carries the same word:

       data-asset-form="allocate"

   so this file never holds a list of dialog ids, never asks which page it is on,
   and never has to be told twice when a dialog is renamed or moved between the
   register and the record. On top of that one mechanism it does four things:

     1. opens the dialog behind the marker (Escape, the backdrop and the close
        button are the shared `MasterModal` lifecycle in `app-layout.js` — a
        second implementation here is how two dialogs start behaving
        differently);
     2. **restores the form before filling it.** The dialogs are shared by every
        row, so a location typed for the asset in Anand must not still be sitting
        in the box when the reader opens the same door for one in Rajkot.
        `form.reset()` puts every field back to what the server rendered, and only
        then does the row's own data go in — which is also why the row's menu
        carries the sentence ("Currently Ravi Patel at Unit 2"), not the dialog;
     3. points the shared form at the asset the row meant (`data-action`), by
        writing the URL the server already generated — `route('assets.allocate',
        $asset)` — into the form's `action`;
     4. re-opens the dialog a failed save came back from. The server names it in
        the `_dialog` field, echoes it into the marker below, and the typing is
        already in `old()`; the reopen path is deliberately separate from the
        click path because it must **not** reset the form.

   The list's two shared behaviours — the whole row opening its asset, and the
   pinned header's shadow — are the shell's, called here rather than
   reimplemented.
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

    /* The dialog that owns a form — the one structural fact this file relies on,
       and the reason a door only has to name its form. */
    function dialogFor(marker) {
        var form = document.querySelector('form[data-asset-form="' + marker + '"]');

        return form ? form.closest('.master-modal') : null;
    }

    /* The dialog's own sentence-writing elements, if it has them: the subject
       line (which asset this is about) and the note under it (the state it is
       in today). A dialog opened from the record has both already correct from
       the server and carries no data attributes, so nothing is overwritten. */
    function setText(modal, selector, text) {
        if (!modal || !text) return;

        var node = modal.querySelector(selector);
        if (node) node.textContent = text;
    }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function () {
        /* ------------------------------------------------ the register list */

        var index = document.querySelector('.ast-index');

        if (index && window.MasterList) {
            window.MasterList.rowNavigation({ root: '.ast-index' });
            window.MasterList.gridShadow({ root: '.ast-index' });
        }

        /* ------------------------------------------------------- the doors */

        document.querySelectorAll('[data-open-asset-modal]').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                var marker = trigger.getAttribute('data-open-asset-modal');
                var modal = dialogFor(marker);

                if (!modal) return;

                event.preventDefault();

                var form = modal.querySelector('form[data-asset-form="' + marker + '"]');

                /* Which asset this door is for. Absent on the record's own
                   buttons: there the form already posts to the asset it is
                   about, and a reset is all that is needed. */
                var action = trigger.getAttribute('data-action');

                if (form && action) {
                    form.reset();
                    form.setAttribute('action', action);
                } else if (form) {
                    form.reset();
                }

                setText(modal, '[data-asset-subject]', trigger.getAttribute('data-subject'));
                setText(modal, '[data-asset-current]', trigger.getAttribute('data-current'));

                openModal(modal);

                /* The first field is where the work starts — the code on a new
                   asset, the holder when handing one over. */
                var first = modal.querySelector('input:not([type="hidden"]), select, textarea');

                if (first) first.focus();
            });
        });

        /* --------------------------------- a save that came back with an error */

        var marker = document.querySelector('[data-open-dialog]');
        var reopen = marker ? (marker.getAttribute('data-open-dialog') || '') : '';

        if (reopen) {
            var form = document.querySelector('form[data-asset-form="' + reopen + '"]');
            var modal = form ? form.closest('.master-modal') : null;

            if (modal) {
                /* No reset here: the server has already rendered `old()` into the
                   fields, and clearing them would throw away exactly what the
                   reader is fixing. */
                openModal(modal);

                var first = modal.querySelector('input:not([type="hidden"]), select, textarea');

                if (first) first.focus();
            }
        }
    });
})();
