/* ==========================================================================
   VENDORS.JS — the vendor module
   --------------------------------------------------------------------------
   Loaded by:

     - resources/views/vendors/index.blade.php   the list: row navigation,
                                                 quick add, delete
     - resources/views/vendors/show.blade.php    the record: ledger dialogs,
                                                 INR auto-calc, edit prefill
     - resources/views/vendors/form.blade.php    the form: image preview

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

        /* Bulk selection. The checkboxes carry form="vendorBulkForm", so the
           bar can sit above the table and the table stays a table; the bar
           itself is only shown once something is picked. One status change
           across every ticked row is the difference between fixing a supplier
           file and fixing twenty of them. */
        var bulkForm = byId('vendorBulkForm');

        if (bulkForm) {
            var picks = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-pick]'));
            var pickAll = document.querySelector('[data-bulk-all]');
            var countLabel = bulkForm.querySelector('[data-bulk-count]');
            var clearButton = bulkForm.querySelector('[data-bulk-clear]');

            var syncBulk = function () {
                var chosen = picks.filter(function (pick) { return pick.checked; });

                bulkForm.hidden = chosen.length === 0;
                if (countLabel) {
                    countLabel.textContent = chosen.length === 1 ? '1 selected' : chosen.length + ' selected';
                }
                picks.forEach(function (pick) {
                    pick.closest('tr')?.classList.toggle('is-picked', pick.checked);
                });
                if (pickAll) {
                    pickAll.checked = chosen.length > 0 && chosen.length === picks.length;
                    pickAll.indeterminate = chosen.length > 0 && chosen.length < picks.length;
                }
            };

            picks.forEach(function (pick) { pick.addEventListener('change', syncBulk); });

            pickAll?.addEventListener('change', function () {
                picks.forEach(function (pick) { pick.checked = pickAll.checked; });
                syncBulk();
            });

            clearButton?.addEventListener('click', function () {
                picks.forEach(function (pick) { pick.checked = false; });
                syncBulk();
            });

            bulkForm.addEventListener('submit', function (event) {
                if (!picks.some(function (pick) { return pick.checked; })) {
                    event.preventDefault();
                    return;
                }
            });

            syncBulk();
        }


        if (window.MasterList) {
            window.MasterList.rowNavigation({ root: '.vendor-index' });
            window.MasterList.gridShadow({ root: '.vendor-index' });
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
           not have to find their way back to it. The server states which one
           with the `_dialog` field the form posted, echoed into the marker. */
        var reopenMarker = document.querySelector('[data-open-dialog]');
        var reopen = reopenMarker ? (reopenMarker.getAttribute('data-open-dialog') || '') : '';
        var reopenTargets = {
            'quick-vendor': 'quickVendorModal',
            'payment': 'addPaymentModal',
            'attachment': 'addAttachmentModal',
            'comment': 'addCommentModal',
            'contact': 'contactModal',
        };

        if (reopen && reopenTargets[reopen]) {
            openModal(byId(reopenTargets[reopen]));
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
                var modal = byId(pair[1]);
                if (pair[1] === 'addPaymentModal') {
                    var form = modal ? modal.querySelector('form') : null;
                    applyOperatingCurrency(form, form && form.getAttribute('data-vendor-currency'));
                }
                openModal(modal);
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

        var applyOperatingCurrency = function (form, code) {
            var select = form && form.elements.foreign_currency;
            if (!select) return;

            var want = String(code || form.getAttribute('data-vendor-currency') || 'RMB').toUpperCase();
            var match = Array.prototype.find.call(select.options, function (option) {
                return String(option.value).toUpperCase() === want;
            });

            if (match) {
                select.value = match.value;
            } else {
                var option = document.createElement('option');
                option.value = want;
                option.textContent = want;
                select.appendChild(option);
                select.value = want;
            }

            select.dispatchEvent(new Event('change'));
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

        /* A bill's "Record payment" button opens the ledger dialog already
           pointed at that bill: a payment (debit) in the bill's own currency
           for what is still open, so the common case is two keystrokes. */
        var addPaymentModal = byId('addPaymentModal');
        var addPaymentForm = addPaymentModal ? addPaymentModal.querySelector('form') : null;

        document.querySelectorAll('.vendor-payment-form').forEach(function (form) {
            var documentField = form.elements.purchase_invoice_id;
            documentField?.addEventListener('change', function () {
                var option = documentField.selectedOptions && documentField.selectedOptions[0];
                var currency = option && option.getAttribute('data-currency');
                var rate = option && option.getAttribute('data-rate');

                if (currency) {
                    applyOperatingCurrency(form, currency);
                } else if (!documentField.value) {
                    applyOperatingCurrency(form, form.getAttribute('data-vendor-currency'));
                }

                if (rate && Number(rate) > 0 && form.elements.exchange_rate) {
                    form.elements.exchange_rate.value = rate;
                    form.elements.exchange_rate.dispatchEvent(new Event('input'));
                }
            });
        });

        document.querySelectorAll('.payBillBtn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!addPaymentForm) return;

                var bill = {};
                try {
                    bill = JSON.parse(button.dataset.bill || '{}');
                } catch (error) {
                    return;
                }

                var today = new Date().toISOString().slice(0, 10);

                setField(addPaymentForm, 'transaction_date', today);
                setField(addPaymentForm, 'invoice_number', bill.invoice || '');
                setField(addPaymentForm, 'particular', bill.particular ? 'Payment against '.concat(bill.particular) : 'Payment against an open bill');
                setField(addPaymentForm, 'foreign_amount', bill.amount ?? '');
                setField(addPaymentForm, 'transaction_type', 'debit');
                setField(addPaymentForm, 'entry_category', 'payment');
                setField(addPaymentForm, 'amount_in_inr', '');
                setField(addPaymentForm, 'due_date', '');
                setField(addPaymentForm, 'purchase_invoice_id', bill.purchase_invoice_id || '');

                applyOperatingCurrency(addPaymentForm, bill.currency || addPaymentForm.getAttribute('data-vendor-currency'));
                addPaymentForm.elements.transaction_type?.dispatchEvent(new Event('change'));

                openModal(addPaymentModal);
                addPaymentForm.elements.foreign_amount?.focus();
            });
        });

        /* One dialog for a new contact and for editing one: the row's edit
           button hands over the contact and the update URL. */
        var contactModal = byId('contactModal');
        var contactForm = byId('contactForm');

        var setContactMode = function (contact) {
            if (!contactForm) return;

            setField(contactForm, 'name', contact.name || '');
            setField(contactForm, 'designation', contact.designation || '');
            setField(contactForm, 'email', contact.email || '');
            setField(contactForm, 'mobile', contact.mobile || '');
            setField(contactForm, 'whatsapp', contact.whatsapp || '');
            setField(contactForm, 'notes', contact.notes || '');

            var title = byId('contactModalTitle');
            var subtitle = byId('contactModalSubtitle');
            var submit = byId('contactSubmit');
            var method = contactForm.querySelector('input[name="_method"]');

            if (contact.id) {
                contactForm.action = contact.url || contactForm.action;
                if (method) method.value = 'PUT';
                if (title) title.textContent = 'Edit contact';
                if (subtitle) subtitle.textContent = contact.name || 'Update this contact';
                if (submit) submit.textContent = 'Save contact';
            } else {
                contactForm.action = contactForm.dataset.storeUrl || contactForm.action;
                if (method) method.value = 'POST';
                if (title) title.textContent = 'Add contact';
                if (subtitle) subtitle.textContent = contactForm.dataset.storeLabel || 'Somebody else at this vendor';
                if (submit) submit.textContent = 'Add contact';
            }
        };

        byId('openAddContactModal')?.addEventListener('click', function () {
            setContactMode({});
            openModal(contactModal);
            byId('contact_name')?.focus();
        });

        document.querySelectorAll('.editContactBtn').forEach(function (button) {
            button.addEventListener('click', function () {
                var contact = {};
                try {
                    contact = JSON.parse(button.dataset.contact || '{}');
                } catch (error) {
                    return;
                }
                setContactMode(contact);
                openModal(contactModal);
                byId('contact_name')?.focus();
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
