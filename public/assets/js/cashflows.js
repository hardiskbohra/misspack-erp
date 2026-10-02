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
        ['.cashflow-index', '.cashflow-documents'].forEach(function (root) {
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
    });
})();
