/* ==========================================================================
   CLIENT-PORTAL.JS — Client Portal module (shared portal layout)
   --------------------------------------------------------------------------
   Portal shell behaviour: mobile sidebar open/close (toggle + overlay).
   Loaded by resources/views/client_portal/layouts/app.blade.php on every
   portal page.
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
        var shell = document.getElementById('cpShell');
        var toggle = document.getElementById('cpToggle');
        var overlay = document.getElementById('cpOverlay');
        if (toggle) toggle.addEventListener('click', function () { shell.classList.toggle('sidebar-open'); });
        if (overlay) overlay.addEventListener('click', function () { shell.classList.remove('sidebar-open'); });
    });
})();
