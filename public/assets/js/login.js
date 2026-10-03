/* ==========================================================================
   LOGIN.JS — Auth module (standalone sign-in page)
   --------------------------------------------------------------------------
   Password visibility toggle for the admin sign-in page.
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
        var toggle = document.getElementById('togglePassword');
        if (!toggle) return;
        toggle.addEventListener('click', function () {
            var field = document.getElementById('passwordField');
            var isPass = field.type === 'password';
            field.type = isPass ? 'text' : 'password';
            toggle.className = isPass ? 'fas fa-eye-slash input-icon' : 'fas fa-eye input-icon';
        });
    });
})();
