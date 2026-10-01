/* ==========================================================================
   VENDORS.JS — Vendor module (admin screens)
   --------------------------------------------------------------------------
   Loaded via @push('scripts') on vendor module pages:

     - vendors/index.blade.php        Quick Vendor + delete modals
     - vendors/show.blade.php         Tab navigation, ledger modals,
                                      payment INR auto-calc, edit-entry prefill
     - vendor_quotes/index.blade.php  Quick Quote modal
     - vendor_quotes/form.blade.php   Quantity price-break rows

   Modals use the shared master-* modal system (master-index.css): the
   .open class toggles visibility and .master-modal-open locks page scroll.
   All bindings are guarded, so the file is safe on any vendor page.
   ========================================================================== */
(function () {
    'use strict';

    /* ---------------- Shared master-* modal helpers ---------------- */

    function openModal(modal) {
        if (!modal || modal.classList.contains('open')) return;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.master-modal.open')) {
            document.body.classList.remove('master-modal-open');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {

        /* Generic modal wiring, safe on every vendor page:
             - [data-close-modal] buttons (value = modal id, or nearest modal)
             - backdrop click
             - Escape key                                            */
        document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.dataset.closeModal
                    ? document.getElementById(btn.dataset.closeModal)
                    : btn.closest('.master-modal');
                closeModal(target);
            });
        });

        document.querySelectorAll('.master-modal').forEach(function (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal(modal);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.master-modal.open').forEach(closeModal);
        });

        /* ---------- vendors/index: Quick Vendor + delete modals ---------- */

        var quickVendorModal = document.getElementById('quickVendorModal');
        var deleteVendorModal = document.getElementById('deleteVendorModal');
        var deleteVendorForm = document.getElementById('deleteVendorForm');
        var deleteVendorDesc = document.getElementById('deleteVendorDesc');

        document.getElementById('openQuickVendorModal')?.addEventListener('click', function () {
            openModal(quickVendorModal);
        });
        document.getElementById('closeQuickVendorModal')?.addEventListener('click', function () {
            closeModal(quickVendorModal);
        });
        document.getElementById('cancelQuickVendorModal')?.addEventListener('click', function () {
            closeModal(quickVendorModal);
        });

        if (deleteVendorForm && deleteVendorDesc) {
            document.querySelectorAll('.master-delete-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    deleteVendorDesc.textContent = 'Are you sure you want to delete "' +
                        button.dataset.name + '"? This action cannot be undone.';
                    deleteVendorForm.action = button.dataset.deleteUrl;
                    openModal(deleteVendorModal);
                });
            });
        }

        document.getElementById('closeDeleteVendorModal')?.addEventListener('click', function () {
            closeModal(deleteVendorModal);
        });
        document.getElementById('cancelDeleteVendorModal')?.addEventListener('click', function () {
            closeModal(deleteVendorModal);
        });

        /* ---------- vendors/show: tab navigation (persisted) ---------- */

        var vendorShow = document.querySelector('.vendor-show');
        if (vendorShow) {
            var tabKey = 'vendor_show_tab_' + (vendorShow.dataset.vendorId || '');
            var tabs = vendorShow.querySelectorAll('.vendor-tab');

            tabs.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    vendorShow.querySelectorAll('.vendor-tab').forEach(function (b) {
                        b.classList.remove('active');
                    });
                    vendorShow.querySelectorAll('.vendor-panel').forEach(function (panel) {
                        panel.classList.remove('active');
                    });
                    btn.classList.add('active');
                    var panel = vendorShow.querySelector('[data-panel="' + btn.dataset.tab + '"]');
                    if (panel) panel.classList.add('active');
                    try {
                        localStorage.setItem(tabKey, btn.dataset.tab);
                    } catch (e) {}
                });
            });

            try {
                var savedTab = localStorage.getItem(tabKey);
                if (savedTab) {
                    var savedBtn = vendorShow.querySelector('.vendor-tab[data-tab="' + savedTab + '"]');
                    if (savedBtn) savedBtn.click();
                }
            } catch (e) {}
        }

        /* ---------- vendors/show: payment modals + INR auto-calc ---------- */

        document.getElementById('openAddPaymentModal')?.addEventListener('click', function () {
            openModal(document.getElementById('addPaymentModal'));
        });
        document.getElementById('openAddAttachmentModal')?.addEventListener('click', function () {
            openModal(document.getElementById('addAttachmentModal'));
        });
        document.getElementById('openAddCommentModal')?.addEventListener('click', function () {
            openModal(document.getElementById('addCommentModal'));
        });

        /* Both ledger modals carry the same field names, so the auto-calc is
           wired per form (the add + edit modals each get their own). */
        document.querySelectorAll('.vendor-payment-form').forEach(function (form) {
            var foreignAmount = form.querySelector('input[name="foreign_amount"]');
            var foreignCurrency = form.querySelector('select[name="foreign_currency"]');
            var exchangeRate = form.querySelector('input[name="exchange_rate"]');
            var amountInInr = form.querySelector('input[name="amount_in_inr"]');
            if (!foreignAmount || !foreignCurrency || !exchangeRate || !amountInInr) return;

            function calculateInrAmount() {
                var amount = parseFloat(foreignAmount.value || '0');
                var rate = parseFloat(exchangeRate.value || '0');
                if (foreignCurrency.value === 'INR' && amount > 0 && !amountInInr.value) {
                    amountInInr.value = amount.toFixed(2);
                    return;
                }
                if (amount > 0 && rate > 0) {
                    amountInInr.value = (amount * rate).toFixed(2);
                }
            }

            foreignAmount.addEventListener('input', calculateInrAmount);
            exchangeRate.addEventListener('input', calculateInrAmount);
            foreignCurrency.addEventListener('change', calculateInrAmount);
        });

        /* Edit-entry prefill from the row's data-payment payload. */
        var editPaymentModal = document.getElementById('editPaymentModal');
        var editPaymentForm = document.getElementById('editPaymentForm');

        function setField(form, name, value) {
            var field = form.elements[name];
            if (!field) return;
            if (field.type === 'checkbox') {
                field.checked = !!value;
            } else {
                field.value = value ?? '';
            }
        }

        document.querySelectorAll('.editPaymentBtn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var payment = JSON.parse(this.dataset.payment || '{}');
                editPaymentForm.action = '/vendors/' + payment.vendor_id + '/payments/' + payment.id;

                setField(editPaymentForm, 'invoice_number', payment.invoice_number ?? '');

                var paymentDate = payment.transaction_date;
                if (paymentDate) {
                    paymentDate = String(paymentDate).substring(0, 10);
                }
                setField(editPaymentForm, 'transaction_date', paymentDate);

                setField(editPaymentForm, 'particular', payment.particular ?? '');
                setField(editPaymentForm, 'foreign_amount', payment.foreign_amount);
                setField(editPaymentForm, 'foreign_currency', payment.foreign_currency);
                setField(editPaymentForm, 'exchange_rate', payment.exchange_rate);
                setField(editPaymentForm, 'amount_in_inr', payment.amount_in_inr);
                setField(editPaymentForm, 'transaction_type', payment.transaction_type);
                setField(editPaymentForm, 'entry_category', payment.entry_category);
                setField(editPaymentForm, 'status', payment.status);
                setField(editPaymentForm, 'project_id', payment.project_id);
                setField(editPaymentForm, 'paid_account_id', payment.paid_account_id);
                setField(editPaymentForm, 'payment_mode', payment.payment_mode);
                setField(editPaymentForm, 'bank_reference_number', payment.bank_reference_number);
                setField(editPaymentForm, 'remarks', payment.remarks ?? '');
                setField(editPaymentForm, 'also_create_cashflow', payment.also_create_cashflow);

                openModal(editPaymentModal);
            });
        });

        /* ---------- vendor_quotes/index: Quick Quote modal ---------- */

        document.getElementById('openQuickQuoteModal')?.addEventListener('click', function () {
            openModal(document.getElementById('quickQuoteModal'));
        });
    });

    /* ---------- vendor_quotes/form: quantity price-break rows ---------- */

    /* Kept global: rows call removeQuotePriceRow(this) from inline onclick. */
    window.removeQuotePriceRow = function (btn) {
        var tbody = document.querySelector('#quotePricesTable tbody');
        if (tbody && tbody.children.length > 1) btn.closest('tr').remove();
    };

    document.addEventListener('DOMContentLoaded', function () {
        var addQuotePriceRow = document.getElementById('addQuotePriceRow');
        if (!addQuotePriceRow) return;

        var quoteTbody = document.querySelector('#quotePricesTable tbody');
        var quotePriceIndex = quoteTbody ? quoteTbody.children.length : 0;

        addQuotePriceRow.addEventListener('click', function () {
            var template = document.getElementById('quotePriceRowTemplate');
            if (quoteTbody && template) {
                quoteTbody.insertAdjacentHTML('beforeend',
                    template.innerHTML.replaceAll('__INDEX__', quotePriceIndex++));
            }
        });
    });
})();
