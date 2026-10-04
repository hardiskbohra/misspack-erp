/* Vendor directory/detail interactions and the separate vendor-quote helpers. */
(function () {
    'use strict';

    var activeModal = null;
    var returnFocusTo = null;

    function focusableIn(modal) {
        if (!modal) return [];
        return Array.from(modal.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (element) {
            return !element.hidden && element.getAttribute('aria-hidden') !== 'true';
        });
    }

    function focusAfterOpen(modal, preferred) {
        var target = preferred || focusableIn(modal)[0] || modal.querySelector('[role="dialog"]');
        if (target && typeof target.focus === 'function') target.focus();
    }

    function openModal(modal, trigger, preferredFocus) {
        if (!modal) return;
        if (activeModal && activeModal !== modal) closeModal(activeModal, false);
        if (modal.classList.contains('open')) return;

        returnFocusTo = trigger || document.activeElement;
        activeModal = modal;
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');

        var schedule = window.requestAnimationFrame || function (callback) { window.setTimeout(callback, 0); };
        schedule(function () { focusAfterOpen(modal, preferredFocus); });
    }

    function closeModal(modal, restoreFocus) {
        if (!modal) return;
        if (restoreFocus === undefined) restoreFocus = true;
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');

        if (activeModal === modal) activeModal = null;
        document.body.classList.toggle('master-modal-open', Boolean(document.querySelector('.master-modal.open')));

        if (restoreFocus && returnFocusTo && returnFocusTo.isConnected) returnFocusTo.focus();
        if (restoreFocus) returnFocusTo = null;
    }

    function bindModalClose(modal) {
        if (!modal) return;
        modal.querySelectorAll('[data-close-modal]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeModal(modal);
            });
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal(modal);
        });
    }

    function setField(form, name, value) {
        var field = form && form.elements ? form.elements[name] : null;
        if (!field) return;
        if (field.type === 'checkbox') {
            field.checked = Boolean(value);
        } else {
            field.value = value == null ? '' : value;
        }
    }

    function bindPaymentForm(form) {
        if (!form) return;
        var foreignAmount = form.querySelector('input[name="foreign_amount"]');
        var foreignCurrency = form.querySelector('select[name="foreign_currency"]');
        var exchangeRate = form.querySelector('input[name="exchange_rate"]');
        var amountInInr = form.querySelector('input[name="amount_in_inr"]');
        var typeSelect = form.querySelector('select[name="transaction_type"]');
        var syncCheckbox = form.querySelector('input[type="checkbox"][name="also_create_cashflow"]');
        var accountSelect = form.querySelector('select[name="paid_account_id"]');
        var hint = form.querySelector('.vendor-sync-hint');
        if (!foreignAmount || !foreignCurrency || !exchangeRate || !amountInInr) return;

        function calculateInrAmount() {
            var amount = parseFloat(foreignAmount.value || '0');
            var rate = parseFloat(exchangeRate.value || '0');
            if (foreignCurrency.value === 'INR' && amount > 0 && !amountInInr.value) {
                amountInInr.value = amount.toFixed(2);
                return;
            }
            if (amount > 0 && rate > 0) amountInInr.value = (amount * rate).toFixed(2);
        }

        foreignAmount.addEventListener('input', calculateInrAmount);
        exchangeRate.addEventListener('input', calculateInrAmount);
        foreignCurrency.addEventListener('change', calculateInrAmount);

        if (!typeSelect || !syncCheckbox) return;
        var defaultHint = hint ? hint.textContent.trim() : '';

        function applySyncState(autoFlip) {
            var isPayment = typeSelect.value === 'debit';
            if (autoFlip) syncCheckbox.checked = isPayment;
            if (accountSelect) accountSelect.required = isPayment && syncCheckbox.checked;

            if (!hint) return;
            if (!isPayment) {
                hint.textContent = 'A bill creates a payable, not a cash movement — tick the box only if money also left the account.';
            } else if (!syncCheckbox.checked) {
                hint.textContent = 'Cashflow entry will be skipped for this payment.';
            } else {
                hint.textContent = defaultHint;
            }
        }

        applySyncState(false);
        typeSelect.addEventListener('change', function () { applySyncState(true); });
        syncCheckbox.addEventListener('change', function () { applySyncState(false); });
    }

    function bindEditPayments() {
        var modal = document.getElementById('editPaymentModal');
        var form = document.getElementById('editPaymentForm');
        if (!modal || !form) return;

        document.querySelectorAll('.editPaymentBtn').forEach(function (button) {
            button.addEventListener('click', function () {
                var payment;
                try {
                    payment = JSON.parse(button.dataset.payment || '{}');
                } catch (error) {
                    return;
                }
                if (!payment.id || !button.dataset.updateUrl) return;

                form.action = button.dataset.updateUrl;
                setField(form, '_vendor_payment_entry_id', payment.id);
                setField(form, 'invoice_number', payment.invoice_number);
                setField(form, 'transaction_date', payment.transaction_date ? String(payment.transaction_date).substring(0, 10) : '');
                setField(form, 'particular', payment.particular);
                setField(form, 'foreign_amount', payment.foreign_amount);
                setField(form, 'foreign_currency', payment.foreign_currency);
                setField(form, 'exchange_rate', payment.exchange_rate);
                setField(form, 'amount_in_inr', payment.amount_in_inr);
                setField(form, 'transaction_type', payment.transaction_type);
                setField(form, 'entry_category', payment.entry_category);
                setField(form, 'status', payment.status);
                setField(form, 'project_id', payment.project_id);
                setField(form, 'paid_account_id', payment.paid_account_id);
                setField(form, 'payment_mode', payment.payment_mode);
                setField(form, 'bank_reference_number', payment.bank_reference_number);
                setField(form, 'remarks', payment.remarks);

                var syncCheckbox = form.querySelector('input[type="checkbox"][name="also_create_cashflow"]');
                if (syncCheckbox) {
                    /* Existing cashflow link state is authoritative; do not create
                       a duplicate simply because the row is being edited. */
                    syncCheckbox.checked = Boolean(payment.cashflow_entry_id);
                    syncCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
                openModal(modal, button, form.querySelector('[name="transaction_date"]'));
            });
        });
    }

    function bindVendorDirectory() {
        var quickModal = document.getElementById('quickVendorModal');
        var deleteModal = document.getElementById('deleteVendorModal');
        var deleteForm = document.getElementById('deleteVendorForm');
        var deleteDescription = document.getElementById('deleteVendorDesc');
        var openQuickButton = document.getElementById('openQuickVendorModal');

        openQuickButton?.addEventListener('click', function () {
            openModal(quickModal, this, document.getElementById('quick_vendor_name'));
        });

        ['closeQuickVendorModal', 'cancelQuickVendorModal'].forEach(function (id) {
            document.getElementById(id)?.addEventListener('click', function () { closeModal(quickModal); });
        });

        document.querySelectorAll('.master-delete-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (deleteDescription) {
                    deleteDescription.textContent = 'Are you sure you want to delete “' +
                        (button.dataset.name || 'this vendor') + '”? This action cannot be undone.';
                }
                if (deleteForm) deleteForm.action = button.dataset.deleteUrl || '';
                openModal(deleteModal, button, document.getElementById('cancelDeleteVendorModal'));
            });
        });

        ['closeDeleteVendorModal', 'cancelDeleteVendorModal'].forEach(function (id) {
            document.getElementById(id)?.addEventListener('click', function () { closeModal(deleteModal); });
        });
    }

    function bindVendorDetail() {
        document.getElementById('openAddPaymentModal')?.addEventListener('click', function () {
            openModal(document.getElementById('addPaymentModal'), this);
        });
        document.getElementById('openAddAttachmentModal')?.addEventListener('click', function () {
            openModal(document.getElementById('addAttachmentModal'), this);
        });
        document.getElementById('openAddCommentModal')?.addEventListener('click', function () {
            openModal(document.getElementById('addCommentModal'), this);
        });

        document.querySelectorAll('.vendor-payment-form').forEach(bindPaymentForm);
        bindEditPayments();
    }

    function bindVendorForm() {
        var currencySelect = document.getElementById('vendor_preferred_currency');
        var currencyLabels = document.querySelectorAll('[data-vendor-currency-label]');
        if (!currencySelect || !currencyLabels.length) return;

        function updateCurrencyLabels() {
            var currency = currencySelect.value || 'INR';
            currencyLabels.forEach(function (label) { label.textContent = currency; });
        }

        updateCurrencyLabels();
        currencySelect.addEventListener('change', updateCurrencyLabels);
    }

    function bindQuoteScreens() {
        document.getElementById('openQuickQuoteModal')?.addEventListener('click', function () {
            var modal = document.getElementById('quickQuoteModal');
            openModal(modal, this);
        });

        var addPriceRow = document.getElementById('addQuotePriceRow');
        var priceBody = document.querySelector('#quotePricesTable tbody');
        if (addPriceRow && priceBody) {
            var priceIndex = priceBody.children.length;
            addPriceRow.addEventListener('click', function () {
                var template = document.getElementById('quotePriceRowTemplate');
                if (!template) return;
                priceBody.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', priceIndex++));
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.MasterList) {
            window.MasterList.density({ root: '.vendor-index', key: 'misspack.vendors.density' });
            window.MasterList.gridShadow({ root: '.vendor-index' });
        }

        bindVendorDirectory();
        bindVendorDetail();
        bindVendorForm();
        bindQuoteScreens();

        document.querySelectorAll('.master-modal').forEach(bindModalClose);
        document.querySelectorAll('.master-modal[data-auto-open="true"]').forEach(function (modal) {
            var firstInvalid = modal.querySelector('[aria-invalid="true"], .is-invalid');
            openModal(modal, null, firstInvalid || undefined);
        });

        document.addEventListener('keydown', function (event) {
            if (!activeModal || !activeModal.classList.contains('open')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal(activeModal);
                return;
            }
            if (event.key !== 'Tab') return;

            var focusable = focusableIn(activeModal);
            if (!focusable.length) {
                event.preventDefault();
                activeModal.querySelector('[role="dialog"]')?.focus();
                return;
            }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    });

    /* Quote form rows call this from a legacy inline onclick. */
    window.removeQuotePriceRow = function (button) {
        var body = document.querySelector('#quotePricesTable tbody');
        if (body && body.children.length > 1 && button?.closest('tr')) button.closest('tr').remove();
    };
})();
