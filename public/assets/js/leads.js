/* ==========================================================================
   LEADS.JS — Leads module (admin views)
   --------------------------------------------------------------------------
   The lead list page's Quick Lead dialog is a standard .master-modal: the
   shared master-* modal layer (app-layout.js) owns close/Escape/backdrop
   and [data-close-modal] buttons, so this file only carries the open
   trigger. The other admin views (form, image, settings, detail) are
   static — no module JS needed.
   Public lead pages (public-create / public-show) use leads-public.js.
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
        var openBtn = document.getElementById('openQuickLeadModal');
        var modal = document.getElementById('quickLeadModal');
        if (openBtn && modal && typeof window.MasterModal !== 'undefined') {
            openBtn.addEventListener('click', function () {
                window.MasterModal.open(modal);
            });
        }
    });
})();
