/* ==========================================================================
   KYC-PUBLIC.JS — public client KYC page (clients/kyc standalone view)
   --------------------------------------------------------------------------
   Consumes window.kycToast (set inline by the view): an array of
   {type: 'success'|'error'|'validation', message, color} entries — mirrors
   the former inline @if(session(...)) Swal toasts one-to-one.
   Also wires the submit confirmation dialog. Requires SweetAlert2 (CDN),
   which the page loads before this file.
   ========================================================================== */
document.addEventListener('DOMContentLoaded', function () {
    (window.kycToast || []).forEach(function (t) {
        Swal.fire({
            icon: t.type === 'success' ? 'success' : 'error',
            title: t.type === 'success' ? 'Success' : (t.type === 'validation' ? 'Validation Error' : 'Error'),
            text: t.message,
            confirmButtonColor: t.color
        });
    });

    const kycSubmitForm = document.getElementById('kycSubmitForm');

    if (!kycSubmitForm) {
        return;
    }

    kycSubmitForm.addEventListener('submit', function (event) {
        event.preventDefault();

        Swal.fire({
            title: 'Submit KYC for Review?',
            text: 'Please confirm that all details are correct. After submission, the form will go under review.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Submit',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#4f83f1',
            cancelButtonColor: '#ef4770',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                kycSubmitForm.submit();
            }
        });
    });
});
