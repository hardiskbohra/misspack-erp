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
   The other cashflow views (form, pdf, reports, settings, show) are static.
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

    onReady(function () {
        if (typeof window.MasterModal === 'undefined') return;

        var openQuick = document.getElementById('openQuickCashflowModal');
        var quickModal = document.getElementById('quickCashflowModal');
        if (openQuick && quickModal) {
            openQuick.addEventListener('click', function () {
                window.MasterModal.open(quickModal);
            });
        }

        var openAccount = document.getElementById('openAccountModal');
        var accountModal = document.getElementById('accountModal');
        if (openAccount && accountModal) {
            openAccount.addEventListener('click', function () {
                window.MasterModal.open(accountModal);
            });
        }
    });
})();
