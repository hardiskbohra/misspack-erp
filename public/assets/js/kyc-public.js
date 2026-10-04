/* ==========================================================================
   KYC-PUBLIC.JS — public client KYC page (clients/kyc standalone view)
   --------------------------------------------------------------------------
   Consumes window.kycToast (set inline by the view): an array of
   {type: 'success'|'error'|'validation', message, color} entries.
   Also wires the submit confirmation dialog. Requires master-alert.js,
   which the page loads before this file.
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {
    (window.kycToast || []).forEach(function (t) {
        if (t.type === 'success') {
            MasterAlert.toast(t.message, 'success', { title: 'Success' });
        } else if (t.type === 'validation') {
            MasterAlert.alert(t.message, { title: 'Validation Error', type: 'error', danger: true });
        } else {
            MasterAlert.alert(t.message, { title: 'Error', type: 'error', danger: true });
        }
    });

    const kycSubmitForm = document.getElementById('kycSubmitForm');

    if (!kycSubmitForm) {
        return;
    }

    kycSubmitForm.addEventListener('submit', function (event) {
        event.preventDefault();

        MasterAlert.confirm(
            'Please confirm that all details are correct. After submission, the form will go under review.',
            { title: 'Submit KYC for Review?', confirmText: 'Yes, Submit', cancelText: 'Cancel', danger: false }
        ).then(function (ok) {
            if (ok) kycSubmitForm.submit();
        });
    });
});
