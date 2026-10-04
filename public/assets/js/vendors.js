/* ==========================================================================
   VENDORS.JS — the vendor module
   --------------------------------------------------------------------------
   Loaded by:

     - resources/views/vendors/index.blade.php   the list: row navigation,
                                                 quick add, delete
     - resources/views/vendors/show.blade.php    the record: ledger dialogs,
                                                 INR auto-calc, edit prefill
     - resources/views/vendors/form.blade.php    the form: image preview
     - resources/views/vendor_quotes/*           quick quote + price rows

   The dialogs are the shared ones: `.master-modal` from master-index.css and
   the lifecycle in app-layout.js (`window.MasterModal`) — which moves focus
   into the card, returns it to the opener, and closes on Escape or a backdrop
   click. The fallback below exists so a page still works when the shared
   script has not run; it is deliberately the same three lines, not a second
   modal system.

   The record's tabs are links: every panel has its own URL, so the script
   only marks the clicked tab immediately and lets the browser navigate.
   ========================================================================== */
(function () {
    'use strict';

    function byId(id) {
        return document.getElementById(id);
    }

    function openModal(modal) {
        if (!modal) return;

        if (window.MasterModal) {
            window.MasterModal.open(modal);
            return;
        }

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('master-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;

        if (window.MasterModal) {
            window.MasterModal.close(modal);
            return;
        }

        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.master-modal.open')) {
            document.body.classList.remove('master-modal-open');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {

        /* ------------------------------------------------ the list ---- */

        if (window.MasterList) {
            window.MasterList.rowNavigation({ root: '.vendor-index' });
            window.MasterList.gridShadow({ root: '.vendor-index' });
            window.MasterList.density({ root: '.vendor-index', key: 'misspack.vendors.density' });
            window.MasterList.saveViewToggle();
        }

        var quickModal = byId('quickVendorModal');
        var deleteModal = byId('deleteVendorModal');
        var deleteForm = byId('deleteVendorForm');
        var deleteDescription = byId('deleteVendorDesc');

        /* The header's primary action and the empty state's CTA both open the
           quick dialog; both are bound so neither depends on the other. */
        document.querySelectorAll('[data-open-quick-vendor]').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(quickModal);
                byId('quick_vendor_name')?.focus();
            });
        });

        document.querySelectorAll('.master-delete-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (deleteDescription) {
                    deleteDescription.textContent = 'Delete “' + (button.dataset.name || 'this vendor') +
                        '”? Their uploaded documents go with the record.';
                }
                if (deleteForm) {
                    deleteForm.action = button.dataset.deleteUrl || '';
                }
                openModal(deleteModal);
            });
        });

        /* A failed save re-opens the dialog it came from, so the reader does
           not have to find their way back to it. */
        var reopenMarker = document.querySelector('[data-open-dialog]');
        var reopen = reopenMarker ? reopenMarker.getAttribute('data-open-dialog') : '';

        if (reopen === 'quick-vendor') {
            openModal(quickModal);
        }

        /* ---------------------------------------------- the record ---- */

        [['openAddPaymentModal', 'addPaymentModal'],
         ['openAddPaymentModalEmpty', 'addPaymentModal'],
         ['openAddAttachmentModal', 'addAttachmentModal'],
         ['openAddAttachmentModalEmpty', 'addAttachmentModal'],
         ['openAddCommentModal', 'addCommentModal'],
         ['openAddCommentModalEmpty', 'addCommentModal']
        ].forEach(function (pair) {
            byId(pair[0])?.addEventListener('click', function () {
                openModal(byId(pair[1]));
            });
        });

        /* Mark the clicked tab at once; the navigation is the browser's. */
        document.querySelectorAll('[data-vendor-tab-link]').forEach(function (link) {
            link.addEventListener('click', function () {
                var strip = link.closest('.master-tabs');
                if (!strip) return;

                strip.querySelectorAll('.master-tab').forEach(function (tab) {
                    tab.classList.remove('is-active');
                    tab.setAttribute('aria-selected', 'false');
                });
                link.classList.add('is-active');
                link.setAttribute('aria-selected', 'true');
            });
        });

        /* Both ledger dialogs carry the same field names, so the auto-calc is
           wired per form — the add and the edit modal each get their own. */
        function calculateInrAmount(form) {
            var amount = parseFloat(form.elements.foreign_amount?.value || '0');
            var rate = parseFloat(form.elements.exchange_rate?.value || '0');
            var amountInInr = form.elements.amount_in_inr;
            var currency = form.elements.foreign_currency?.value;

            if (!amountInInr) return;

            if (currency === 'INR' && amount > 0) {
                amountInInr.value = amount.toFixed(2);
                return;
            }

            if (amount > 0 && rate > 0) {
                amountInInr.value = (amount * rate).toFixed(2);
            }
        }

        document.querySelectorAll('.vendor-payment-form').forEach(function (form) {
            var amountField = form.elements.foreign_amount;
            var rateField = form.elements.exchange_rate;
            var currencyField = form.elements.foreign_currency;

            [amountField, rateField].forEach(function (field) {
                field?.addEventListener('input', function () { calculateInrAmount(form); });
            });
            currencyField?.addEventListener('change', function () { calculateInrAmount(form); });

            /* A payment (debit) is mirrored into the cashflow module, so the
               checkbox is on by default and only matters for payments: a bill
               creates a payable, not a cash movement. The box is only
               auto-flipped when the type actually changes, so a deliberate
               untick survives while the other fields are edited. */
            var typeSelect = form.elements.transaction_type;
            var syncCheckbox = form.querySelector('input[type="checkbox"][name="also_create_cashflow"]');
            var accountSelect = form.elements.paid_account_id;
            var hint = form.querySelector('.vendor-sync-hint');
            var defaultHint = hint ? hint.textContent : '';

            if (typeSelect && syncCheckbox) {
                var applySyncState = function (keepChoice) {
                    var isPayment = typeSelect.value === 'debit';

                    if (!keepChoice) {
                        syncCheckbox.checked = isPayment;
                    }

                    if (accountSelect) {
                        accountSelect.required = isPayment && syncCheckbox.checked;
                    }

                    if (hint) {
                        if (!isPayment) {
                            hint.textContent = 'A bill creates a payable, not a cash movement — tick the box only if money also left the account.';
                        } else if (!syncCheckbox.checked) {
                            hint.textContent = 'Cashflow entry will be skipped for this payment.';
                        } else {
                            hint.textContent = defaultHint;
                        }
                    }
                };

                applySyncState(true);
                typeSelect.addEventListener('change', function () { applySyncState(false); });
                syncCheckbox.addEventListener('change', function () { applySyncState(true); });
            }
        });

        /* Edit-entry prefill from the row's data-payment payload. */
        var editPaymentModal = byId('editPaymentModal');
        var editPaymentForm = byId('editPaymentForm');
        var editPaymentSubtitle = byId('editPaymentSubtitle');

        var setField = function (form, name, value) {
            var field = form.elements[name];
            if (!field) return;

            if (field.type === 'checkbox') {
                field.checked = Boolean(value);
            } else {
                field.value = value === null || value === undefined ? '' : value;
            }
        };

        document.querySelectorAll('.editPaymentBtn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!editPaymentForm) return;

                var payment = {};
                try {
                    payment = JSON.parse(button.dataset.payment || '{}');
                } catch (error) {
                    return;
                }

                /* The update route carries both ids: a vendor payment entry is
                   only ever edited under the vendor that owns it. The template
                   comes from the server, so a sub-path install still matches. */
                var paymentUrlTemplate = editPaymentForm.dataset.paymentUrl || '';
                editPaymentForm.action = paymentUrlTemplate
                    ? paymentUrlTemplate.replace('__ENTRY__', payment.id)
                    : '/vendors/' + payment.vendor_id + '/payments/' + payment.id;

                var paymentDate = payment.transaction_date ? String(payment.transaction_date).substring(0, 10) : '';

                setField(editPaymentForm, 'transaction_date', paymentDate);
                setField(editPaymentForm, 'invoice_number', payment.invoice_number);
                setField(editPaymentForm, 'particular', payment.particular);
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
                setField(editPaymentForm, 'remarks', payment.remarks);

                /* The mirror is what matters, not a form flag: keep the box in
                   step with whether a cashflow entry is actually linked. */
                var syncBox = editPaymentForm.querySelector('input[type="checkbox"][name="also_create_cashflow"]');
                if (syncBox) {
                    syncBox.checked = Boolean(payment.cashflow_entry_id);
                    syncBox.dispatchEvent(new Event('change'));
                }

                if (editPaymentSubtitle) {
                    editPaymentSubtitle.textContent = payment.invoice_number
                        ? 'Entry for invoice ' + payment.invoice_number
                        : 'Update this vendor ledger row';
                }

                openModal(editPaymentModal);
            });
        });

        /* ------------------------------------------------ the form ---- */

        var imageInput = document.querySelector('[data-vendor-image-input]');
        var imagePreview = document.querySelector('[data-vendor-image-preview]');

        imageInput?.addEventListener('change', function () {
            var file = imageInput.files && imageInput.files[0];
            if (!file || !imagePreview) return;

            var reader = new FileReader();
            reader.onload = function (event) {
                if (imagePreview.tagName === 'IMG') {
                    imagePreview.src = event.target.result;
                    return;
                }

                var image = document.createElement('img');
                image.className = imagePreview.className;
                image.setAttribute('data-vendor-image-preview', '');
                image.setAttribute('alt', '');
                image.src = event.target.result;
                imagePreview.replaceWith(image);
                imagePreview = image;
            };
            reader.readAsDataURL(file);
        });

    });

})();
