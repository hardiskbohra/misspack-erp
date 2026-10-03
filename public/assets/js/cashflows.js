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
       row navigation, the density switch, the pinned-header shadow and the
       saved-view form; this file names the cashflow lists. The ledger and the
       document archive are the same surface, so the density choice is shared
       between them. */
    function initList() {
        /* The ledger, the archive and the statements run share one density
           choice: they are the same surface seen from three angles. */
        ['.cashflow-index', '.cashflow-documents', '.cashflow-statements'].forEach(function (root) {
            window.MasterList.gridShadow({ root: root });
            window.MasterList.density({ root: root, key: 'misspack.cashflows.density' });
        });

        /* only the ledger's rows carry data-href */
        window.MasterList.rowNavigation({ root: '.cashflow-index' });
        window.MasterList.saveViewToggle();
    }

    onReady(function () {
        if (typeof window.MasterList !== 'undefined') {
            initList();
        }

        if (typeof window.MasterModal === 'undefined') return;

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
})();
