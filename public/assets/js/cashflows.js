/* ==========================================================================
   CASHFLOWS.JS — Cashflow module (index view)
   --------------------------------------------------------------------------
   The index page's dialogs (quick cashflow entry, account management) are
   standard .master-modal dialogs: the shared master-* modal layer
   (app-layout.js) owns open/close state, backdrop, Escape and
   [data-close-modal] buttons, so this file only carries the open triggers.
   (The old delete-confirm dialog had no trigger in the markup and was
   removed; row delete confirms via <form data-confirm> handled by
   master-alert.js.)
   The ledger and the document archive share the list toolkit; the other
   cashflow views (form, pdf, reports, settings, show) are static.
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

    /* The list behaves like every other list screen: the shared toolkit owns
       row navigation, the pinned-header shadow and the saved-view form; this
       file names the cashflow lists. The ledger, the archive and the
       statements run are the same surface, so the shadow is shared between
       them. */
    function initList() {
        /* The ledger, the archive, the statements run and the report builder
           share one pinned-header shadow: they are the same surface seen from
           four angles. */
        ['.cashflow-index', '.cashflow-documents', '.cashflow-statements', '.cashflow-reports'].forEach(function (root) {
            window.MasterList.gridShadow({ root: root });
        });

        /* only the ledger's rows carry data-href */
        window.MasterList.rowNavigation({ root: '.cashflow-index' });
        window.MasterList.saveViewToggle();
    }

    /* ------------------------------------------------------- the party picker
       "Related To" says which list the entry is being filed against, so the field
       under it is that list — a client, a vendor, or the head a cash expense was
       spent under. The modes are hidden *and cleared*: a hidden select still
       submits, and a client left over from a moment ago is exactly how an entry
       ends up on the wrong party's statement. The wiring is driven by the
       `data-party-for` attribute, so another mode is markup and one word.

       The employee field is deliberately not one of them: who the money went to
       is a fact about the entry in every mode, so its list stays on screen and
       keeps whatever was picked when the mode changes. */
    function partyPicker() {
        var source = document.querySelector('[data-party-source]');
        if (!source) return;

        var fields = [].slice.call(document.querySelectorAll('.party-picker[data-party-for]'));

        function clear(field) {
            var control = field.querySelector('select, input');

            if (!control) return;

            if (control.tagName === 'SELECT' && window.jQuery && window.jQuery(control).data('select2')) {
                window.jQuery(control).val('').trigger('change.select2');
            } else {
                control.value = '';
            }
        }

        function sync() {
            var wanted = source.value;

            fields.forEach(function (field) {
                var mine = field.getAttribute('data-party-for') === wanted;

                field.hidden = ! mine;

                if (! mine) clear(field);
            });
        }

        source.addEventListener('change', sync);
        sync();
    }

    onReady(function () {
        partyPicker();
    });

    onReady(function () {
        if (typeof window.MasterList !== 'undefined') {
            initList();
        }

        if (typeof window.MasterModal === 'undefined') return;

        /* A failed save redirects back with the input kept and the errors on the
           bag; the marker names the dialog it came from, so it reopens with the
           office's typing still in it — the modal is server-rendered with
           `old()`, so opening is all that is left to do. */
        var marker = document.querySelector('[data-open-dialog]');
        var reopen = marker ? marker.getAttribute('data-open-dialog') : '';

        if (reopen) {
            var dialog = document.getElementById(reopen);

            if (dialog) window.MasterModal.open(dialog);
        }

        /* the header action and the empty state carry the same modal */
        ['openQuickCashflowModal', 'emptyQuickCashflow'].forEach(function (id) {
            var trigger = document.getElementById(id);
            var quickModal = document.getElementById('quickCashflowModal');
            if (trigger && quickModal) {
                trigger.addEventListener('click', function () {
                    window.MasterModal.open(quickModal);
                });
            }
        });

        /* the archive's own dialog: filing a document that has no entry behind
           it. The header action and the empty state carry the same one. */
        ['openFileDocumentModal', 'emptyFileDocument'].forEach(function (id) {
            var trigger = document.getElementById(id);
            var fileModal = document.getElementById('fileDocumentModal');
            if (trigger && fileModal) {
                trigger.addEventListener('click', function () {
                    window.MasterModal.open(fileModal);
                });
            }
        });

        var openAccount = document.getElementById('openAccountModal');
        var accountModal = document.getElementById('accountModal');
        if (openAccount && accountModal) {
            openAccount.addEventListener('click', function () {
                window.MasterModal.open(accountModal);
            });
        }

        /* the statement's share dialog: one modal, two triggers on the page */
        ['openShareStatement', 'emptyShareStatement'].forEach(function (id) {
            var trigger = document.getElementById(id);
            var shareModal = document.getElementById('shareStatementModal');
            if (trigger && shareModal) {
                trigger.addEventListener('click', function () {
                    window.MasterModal.open(shareModal);
                });
            }
        });
    });

    /* Printing the statement and not the toolbar: what is on screen behind the
       document (filters, links, actions) carries .no-print, so the browser's
       print dialog needs no help beyond being opened. */
    onReady(function () {
        var printButton = document.getElementById('printStatement');
        if (printButton) {
            printButton.addEventListener('click', function () {
                window.print();
            });
        }

        /* Copy a link without selecting the text by hand. Falls back to
           selecting the field when the clipboard API is unavailable (a page
           served over plain HTTP has no navigator.clipboard). */
        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.getElementById(button.getAttribute('data-copy-target'));
                if (!field) return;

                var label = button.getAttribute('data-copy-label') || 'Copy';
                var done = function () {
                    button.textContent = 'Copied';
                    window.setTimeout(function () {
                        button.textContent = label;
                    }, 1800);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(field.value).then(done, function () {
                        field.select();
                    });
                    return;
                }

                field.select();
                document.execCommand('copy');
                done();
            });
        });
    });

    onReady(function () {
        var form = document.getElementById('cashflowBulkForm');
        var bar = document.querySelector('[data-bulk-bar]');
        var picks = document.querySelectorAll('[data-bulk-pick]');

        if (!form || !bar || !picks.length) return;

        var count = form.querySelector('[data-bulk-count]');
        var all = document.querySelector('[data-bulk-all]');
        var clear = form.querySelector('[data-bulk-clear]');
        var action = form.querySelector('[name="action"]');

        var selected = function () {
            return Array.prototype.filter.call(picks, function (pick) { return pick.checked; });
        };

        var sync = function () {
            var ids = selected();

            bar.hidden = ids.length === 0;

            if (count) {
                count.textContent = ids.length === 1 ? '1 selected' : ids.length + ' selected';
            }

            picks.forEach(function (pick) {
                pick.closest('tr')?.classList.toggle('is-picked', pick.checked);
            });

            if (all) {
                all.checked = ids.length > 0 && ids.length === picks.length;
                all.indeterminate = ids.length > 0 && ids.length < picks.length;
            }
        };

        picks.forEach(function (pick) {
            pick.addEventListener('change', sync);
        });

        if (all) {
            all.addEventListener('change', function () {
                picks.forEach(function (pick) { pick.checked = all.checked; });
                sync();
            });
        }

        if (clear) {
            clear.addEventListener('click', function () {
                picks.forEach(function (pick) { pick.checked = false; });
                if (all) all.checked = false;
                sync();
            });
        }

        form.addEventListener('submit', function (event) {
            if (!selected().length) {
                event.preventDefault();
                return;
            }

            if (action && action.value === 'delete') {
                var ok = window.confirm('Delete the selected cashflow entries? Linked vendor payments and shipment costs will be unlinked.');
                if (!ok) event.preventDefault();
            }
        });

        sync();
    });
})();
