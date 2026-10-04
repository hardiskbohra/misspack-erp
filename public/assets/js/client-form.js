/* Client form helpers: shipping-address copy and KYC-review reason validation. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-client-form]').forEach((form) => {
        const sameAddress = form.querySelector('[name="shipping_same_as_billing"]');
        if (!sameAddress || sameAddress.disabled) return;

        const addressPairs = [
            ['billing_address', 'shipping_address'],
            ['billing_city', 'shipping_city'],
            ['billing_state', 'shipping_state'],
            ['billing_country', 'shipping_country'],
            ['billing_pincode', 'shipping_pincode'],
        ];

        const shippingFields = addressPairs
            .map(([, shippingName]) => form.querySelector(`[name="${shippingName}"]`))
            .filter(Boolean);

        function copyBillingAddress() {
            addressPairs.forEach(([billingName, shippingName]) => {
                const billing = form.querySelector(`[name="${billingName}"]`);
                const shipping = form.querySelector(`[name="${shippingName}"]`);
                if (billing && shipping) shipping.value = billing.value;
            });
        }

        function updateShippingAddress() {
            if (sameAddress.checked) copyBillingAddress();
            shippingFields.forEach((field) => {
                field.readOnly = sameAddress.checked;
                field.setAttribute('aria-readonly', String(sameAddress.checked));
            });
        }

        sameAddress.addEventListener('change', updateShippingAddress);
        addressPairs.forEach(([billingName]) => {
            form.querySelector(`[name="${billingName}"]`)?.addEventListener('input', () => {
                if (sameAddress.checked) copyBillingAddress();
            });
        });
        updateShippingAddress();
    });

    const reviewForm = document.querySelector('[data-client-review-form]');
    if (reviewForm) {
        const revisionNote = reviewForm.querySelector('[name="revision_note"]');
        const rejectionReason = reviewForm.querySelector('[name="rejection_reason"]');

        reviewForm.addEventListener('submit', (event) => {
            const status = event.submitter?.value || '';
            const needsRevisionNote = status === 'revision';
            const needsRejectionReason = status === 'rejected';

            if (revisionNote) revisionNote.required = needsRevisionNote;
            if (rejectionReason) rejectionReason.required = needsRejectionReason;

            const missingReason = (needsRevisionNote && !revisionNote?.value.trim())
                ? revisionNote
                : (needsRejectionReason && !rejectionReason?.value.trim() ? rejectionReason : null);

            if (missingReason) {
                event.preventDefault();
                missingReason.setCustomValidity('Add a reason so the client knows what to update.');
                missingReason.reportValidity();
                missingReason.addEventListener('input', () => missingReason.setCustomValidity(''), { once: true });
            }
        });
    }
});
