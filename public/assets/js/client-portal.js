/* ==========================================================================
   CLIENT-PORTAL.JS — Client Portal module (shared portal layout)
   --------------------------------------------------------------------------
   Portal shell behaviour: tablet/mobile drawer open/close, plus accessible
   titles for the compact desktop navigation rail.
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

        document.querySelectorAll('.cp-nav-link').forEach(function (link) {
            var label = link.querySelector('span:not(.cp-nav-count):not(.cp-nav-pulse)');
            var text = label ? label.textContent.trim() : '';
            if (text && !link.hasAttribute('aria-label')) link.setAttribute('aria-label', text);
            if (text && !link.hasAttribute('title')) link.setAttribute('title', text);
        });

        if (toggle) toggle.addEventListener('click', function () { shell.classList.toggle('sidebar-open'); });
        if (overlay) overlay.addEventListener('click', function () { shell.classList.remove('sidebar-open'); });
    });
})();
