/* ==========================================================================
   CLIENT-PORTAL-KYC.JS — Client Portal module
   --------------------------------------------------------------------------
   KYC status page: copy the verification link to the clipboard
   (MasterAlert fallback). Exposed on window because the button uses an
   inline onclick in the view markup.
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
        window.copyKycLink = function () {
            var link = document.getElementById('kycLink').value;
            var message = document.getElementById('copySuccess');
            navigator.clipboard.writeText(link).then(function () {
                message.hidden = false;
                setTimeout(function () {
                    message.hidden = true;
                }, 2000);
            }).catch(function () {
                MasterAlert.alert(link, { title: 'Copy KYC link — select and copy manually', type: 'info' });
            });
        };
    });
})();
