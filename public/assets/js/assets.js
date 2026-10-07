/* ==========================================================================
   FIXED ASSETS — the register's own behaviour, and only that.

   One idea, applied everywhere: **a door is a marker, not an address.** Every
   control that does something to an asset carries

       data-open-asset-modal="allocate"

   and each dialog's form carries the same word:

       data-asset-form="allocate"

   so this file never holds a list of dialog ids, never asks which page it is on,
   and never has to be told twice when a dialog is renamed or moved between the
   register and the record. On top of that one mechanism it does five things:

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
     3. points the shared form at the row the door meant — the URL the server
        already generated (`route('assets.allocate', $asset)`) into the form's
        `action`, and the verb into its `_method` when the door names one. A
        dialog that both creates and changes (the asset classes in Settings) is
        one dialog with two doors, not two dialogs that drift apart;
     4. **fills the form from the row** when the door carries one (`data-payload`,
        a JSON object the row rendered). The add door carries no payload, so the
        defaults the server rendered stand — which is exactly what "add" means;
     5. re-opens the dialog a failed save came back from. The server names it in
        the `_dialog` field — **the same word the door and the form use**, because
        a marker that speaks one vocabulary on the way out and another on the way
        back is a dialog nobody ever finds again — echoes it into the marker
        below, and the typing is already in `old()`; the reopen path is
        deliberately separate from the click path because it must **not** reset
        the form. A shared dialog also remembers *which row* it was for
        (`_record`), so the office comes back to the row they were working in and
        not to a nameless form.

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
       line (which asset this is about), the note under it (the state it is in
       today) and the footer's own word for the save. A dialog opened from the
       record has all of them already correct from the server and its door carries
       no data attributes, so nothing is overwritten. */
    function setText(modal, selector, text) {
        if (!modal || !text) return;

        var node = modal.querySelector(selector);
        if (node) node.textContent = text;
    }

    /* The row a door carries, as the server rendered it. A door with no payload
       is a door about nothing at all — the add door — and returns nothing, which
       is why the form's own defaults stand for it. */
    function payloadOf(door) {
        try {
            return JSON.parse(door.getAttribute('data-payload') || '{}');
        } catch (e) {
            return {};
        }
    }

    /* Write the row's values into the fields that carry those names. Hidden
       fields are skipped on purpose: a hidden field is the server's own — the
       verb, the row's identity — and a payload never writes one. */
    function fill(form, data) {
        Object.keys(data).forEach(function (name) {
            form.querySelectorAll('[name="' + name + '"]').forEach(function (field) {
                var value = data[name];

                if (field.type === 'hidden') return;

                if (field.type === 'checkbox') {
                    field.checked = !!value && value !== '0';
                } else if (field.type === 'radio') {
                    field.checked = String(value) === field.value;
                } else {
                    field.value = (value === null || value === undefined) ? '' : value;
                }
            });
        });
    }

    /* The verb the door means. `attribute()` returns null when the door says
       nothing, and a door that says nothing leaves the form's own method alone —
       the register's dialogs post as their server-rendered forms were built. */
    function setMethod(form, method) {
        if (method === null) return;

        var field = form.querySelector('[name="_method"]');

        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = '_method';
            form.appendChild(field);
        }

        field.value = method;
    }

    /* Point the shared form at the row this door means — and fill it from that
       row, unless the server has already rendered the office's typing (the
       reopen path, where resetting would throw away what they are fixing). */
    function applyDoor(door, form, keep) {
        if (!keep) form.reset();

        var action = door.getAttribute('data-action');
        if (action) form.setAttribute('action', action);

        setMethod(form, door.getAttribute('data-method'));

        var record = form.querySelector('[name="_record"]');
        if (record) record.value = door.getAttribute('data-record') || '';

        if (!keep) fill(form, payloadOf(door));
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

                /* Which row this door is for — the address, the verb and the
                   values, all of them the server's own rendering of the row. */
                if (form) applyDoor(trigger, form, false);

                setText(modal, '[data-asset-subject]', trigger.getAttribute('data-subject'));
                setText(modal, '[data-asset-current]', trigger.getAttribute('data-current'));
                setText(modal, '[data-asset-submit]', trigger.getAttribute('data-submit'));

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
                   reader is fixing. What a shared dialog *does* need is the row
                   it was for: the form remembers it in `_record`, and that row's
                   own door restores the address and the words — from the same
                   payload the office's click would have used. */
                var record = form.querySelector('[name="_record"]');
                var door = record && record.value
                    ? document.querySelector('[data-open-asset-modal="' + reopen + '"][data-record="' + record.value + '"]')
                    : null;

                if (door) {
                    applyDoor(door, form, true);
                    setText(modal, '[data-asset-subject]', door.getAttribute('data-subject'));
                    setText(modal, '[data-asset-current]', door.getAttribute('data-current'));
                    setText(modal, '[data-asset-submit]', door.getAttribute('data-submit'));
                }

                openModal(modal);

                var first = modal.querySelector('input:not([type="hidden"]), select, textarea');

                if (first) first.focus();
            }
        }
    });
})();
