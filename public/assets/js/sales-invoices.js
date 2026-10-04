/* ==========================================================================
   SALES-INVOICES.JS — Sales invoices module
   --------------------------------------------------------------------------
   Two screens' behaviour, one file:

     the form   the line-item builder (add/remove rows, product autofill), the
                live GST/discount/total preview and the client snapshot prefill
                (the client's own record, in one list the form carries).
                The product options and existing items arrive through data
                attributes on #invoiceItemsBody (Blade cannot render inside an
                external script);
     the list   the shared list chrome every module's listing wears — clickable
                rows, the density switch, the saved-view toggle — plus the two
                actions that only exist here: the receipt dialog (which posts to
                the cashflow ledger, so its form's action is filled in from the
                row that opened it) and the client link, copied to the clipboard.

   The list half is keyed off `.si-index`, so a page without the list boots
   nothing, and the modal is opened through the shared `MasterModal`.
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
        var portalPromptForm = document.querySelector('[data-invoice-portal-confirm]');
        if (portalPromptForm) {
            portalPromptForm.addEventListener('submit', function () {
                var portalChoice = portalPromptForm.querySelector('[data-invoice-portal-choice]');
                if (!portalChoice) return;

                portalChoice.value = window.confirm(
                    'Show this invoice in the client portal?\n\nPress OK to make it visible to the client, or Cancel to create it privately.'
                ) ? '1' : '0';
            });
        }

        var body = document.getElementById('invoiceItemsBody');
        if (!body) return;

        var productOptions = [];
        var initialItems = [];
        try {
            productOptions = JSON.parse(body.getAttribute('data-product-options') || '[]');
        } catch (e) { /* ignore malformed payload */ }
        try {
            initialItems = JSON.parse(body.getAttribute('data-initial-items') || '[]');
        } catch (e) { /* ignore malformed payload */ }

        var rowIndex = 0;
        var ledgerReceived = parseFloat(body.getAttribute('data-ledger-received')) || 0;

        function money(v) {
            return '₹ ' + (Number(v || 0)).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        /* A figure that moves the total down is written as a subtraction, not as
           a negative amount: "− ₹ 1,000.00", never "₹ -1,000.00". */
        function minus(v) {
            var amount = round2(Math.abs(Number(v) || 0));
            return amount === 0 ? money(0) : '− ' + money(amount);
        }

        /* The same rounding the server does, so a preview and a save agree to
           the paisa. */
        function round2(v) {
            return Math.round((Number(v || 0) + Number.EPSILON) * 100) / 100;
        }

        /* Everything the office types into a field ends up inside an attribute
           or a text node: a product description with a quote in it must not be
           able to break the row it is typed into. */
        function esc(value) {
            return String(value === undefined || value === null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        /* An empty field is not a zero: `val(item.quantity, 1)` keeps the row's
           own default, where `item.quantity || 1` would turn a typed 0 into 1. */
        function val(value, fallback) {
            return (value === undefined || value === null || value === '') ? fallback : value;
        }

        function fieldValue(id) {
            var el = document.getElementById(id);
            return el ? (parseFloat(el.value) || 0) : 0;
        }

        function fieldText(id) {
            var el = document.getElementById(id);
            return el ? (el.value || '') : '';
        }

        function setText(id, text) {
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        /* One line of the money ledger: the value, and whether the line applies
           at all. A ledger of eleven rows of ₹ 0.00 hides the two that matter. */
        function setRow(rowId, valueId, text, show) {
            var row = document.getElementById(rowId);
            if (row) row.hidden = !show;
            setText(valueId, text);
        }

        /* A pick made in a select2 is announced with `$el.trigger('change')` —
           a **jQuery** trigger, which runs jQuery handlers and the inline
           `onchange` and dispatches no DOM event at all. A plain
           `addEventListener('change', …)` never hears it, so a handler bound
           that way fills nothing when the office chooses from the list; every
           `.master-select` in this app is a select2. Bind the way the pick is
           announced: jQuery when it is here (the shell loads it, and select2
           needs it anyway), the native listener as the fallback. */
        function onChange(el, handler) {
            if (!el) return;

            if (window.jQuery && window.jQuery.fn) {
                window.jQuery(el).on('change', handler);
            } else {
                el.addEventListener('change', handler);
            }
        }

        function productSelect(name, selected) {
            var html = '<select class="master-select master-product-select" name="' + name + '"><option value="">Manual</option>';
            productOptions.forEach(function (p) {
                html += '<option value="' + esc(p.id) + '"' + (String(selected || '') === String(p.id) ? ' selected' : '') + '>' + esc(p.no) + ' : ' + esc(p.name) + '</option>';
            });
            return html + '</select>';
        }

        /* ------------------------------------------------------------- the money
           The office sees the invoice before it is saved, and the figures they
           see have to be the figures that get stored. This mirrors
           `SalesInvoiceController::syncItemsAndTotals()` step for step: gross,
           the line's own discount, taxable, the invoice discount, the tax
           scaled by the discount's ratio, the charges, the round off, the total
           — and then the model's balance rule, with the receipts already in the
           ledger (handed over in `data-ledger-received`) on the same side of the
           subtraction. A preview that adds up a different invoice is how the
           office learns not to trust either figure. */
        function calculateTotals() {
            var gstType = fieldText('gstType');
            var exportSale = gstType === 'export';

            var gross = 0;
            var taxable = 0;
            var cgst = 0;
            var sgst = 0;
            var igst = 0;

            body.querySelectorAll('tr').forEach(function (row) {
                var qty = parseFloat((row.querySelector('input[name$="[quantity]"]') || {}).value) || 0;
                var rate = parseFloat((row.querySelector('input[name$="[unit_price]"]') || {}).value) || 0;
                var discount = parseFloat((row.querySelector('input[name$="[discount_percent]"]') || {}).value) || 0;
                var gst = parseFloat((row.querySelector('input[name$="[gst_percent]"]') || {}).value) || 0;

                var lineGross = round2(qty * rate);
                var lineTaxable = Math.max(round2(lineGross - round2(lineGross * discount / 100)), 0);
                var lineTax = 0;

                if (!exportSale) {
                    if (gstType === 'inter_state') {
                        lineTax = round2(lineTaxable * gst / 100);
                    } else {
                        var half = round2(lineTaxable * (gst / 200));
                        lineTax = round2(half * 2);
                    }
                }

                gross += lineGross;
                taxable += lineTaxable;

                if (!exportSale) {
                    if (gstType === 'inter_state') {
                        igst += lineTax;
                    } else {
                        cgst += round2(lineTax / 2);
                        sgst += round2(lineTax - round2(lineTax / 2));
                    }
                }

                var total = row.querySelector('.line-total');
                if (total) total.textContent = money(round2(lineTaxable + lineTax));
            });

            var discountValue = fieldValue('discountValue');
            var discountType = fieldText('discountType') || 'amount';
            var invoiceDiscount = discountType === 'percent'
                ? round2(taxable * discountValue / 100)
                : Math.min(discountValue, taxable);

            var taxableAfter = Math.max(round2(taxable - invoiceDiscount), 0);
            var ratio = taxable > 0 ? (taxableAfter / taxable) : 1;

            cgst = round2(cgst * ratio);
            sgst = round2(sgst * ratio);
            igst = round2(igst * ratio);

            var freight = fieldValue('freightAmount');
            var packing = fieldValue('packingAmount');
            var other = fieldValue('otherCharges');
            var roundOff = fieldValue('roundOff');
            var charges = round2(freight + packing + other);
            var tax = round2(cgst + sgst + igst);

            var total = round2(taxableAfter + tax + charges + roundOff);
            var received = round2(fieldValue('amountPaid') + ledgerReceived);
            var balance = Math.max(round2(total - received), 0);

            setRow('rowSubtotal', 'previewSubtotal', money(gross), true);
            setRow('rowLineDiscount', 'previewLineDiscount', minus(taxable - gross), round2(taxable - gross) !== 0);
            setRow('rowInvoiceDiscount', 'previewInvoiceDiscount', minus(invoiceDiscount), invoiceDiscount > 0);
            setRow('rowTaxable', 'previewTaxable', money(taxableAfter), true);
            setRow('rowCgst', 'previewCgst', money(cgst), cgst !== 0);
            setRow('rowSgst', 'previewSgst', money(sgst), sgst !== 0);
            setRow('rowIgst', 'previewIgst', money(igst), igst !== 0);
            setRow('rowFreight', 'previewFreight', money(freight), freight !== 0);
            setRow('rowPacking', 'previewPacking', money(packing), packing !== 0);
            setRow('rowOther', 'previewOther', money(other), other !== 0);
            setRow('rowRoundOff', 'previewRoundOff', money(roundOff), roundOff !== 0);
            setRow('rowTotal', 'previewTotal', money(total), true);
            setRow('rowReceived', 'previewReceived', minus(received), received !== 0);
            setRow('rowBalance', 'previewBalance', money(balance), true);

            /* Red while money is owed, green when nothing is: the same two tones
               the listing's balance column reads. */
            var balanceText = document.getElementById('previewBalance');
            if (balanceText) balanceText.className = balance > 0 ? 'si-due' : 'si-clear';
        }

        /* One line of the invoice. Every name here is one
           `SalesInvoiceController::syncItemsAndTotals()` reads — including
           `discount_percent`, which the row did not post at all: the office
           could type a line discount into the database and the next save of the
           invoice wrote it back as zero. */
        function addRow(item) {
            item = item || {};
            var i = rowIndex++;

            var html = [
                '<tr>',
                '    <td>',
                '        ' + productSelect('items[' + i + '][product_id]', val(item.product_id, '')),
                '        <input type="hidden" name="items[' + i + '][project_product_id]" value="' + esc(val(item.project_product_id, '')) + '">',
                '        <input class="master-input" name="items[' + i + '][product_name]" value="' + esc(val(item.product_name, '')) + '" placeholder="Product name">',
                '        <textarea class="master-textarea" rows="3" name="items[' + i + '][description]" placeholder="Description">' + esc(val(item.description, '')) + '</textarea>',
                '    </td>',
                '    <td><input class="master-input" name="items[' + i + '][hsn_sac]" value="' + esc(val(item.hsn_sac, '')) + '" placeholder="HSN"></td>',
                '    <td class="is-num"><input class="master-input calc" type="number" step="0.001" min="0" name="items[' + i + '][quantity]" value="' + esc(val(item.quantity, 1)) + '"></td>',
                '    <td><input class="master-input" name="items[' + i + '][unit]" value="' + esc(val(item.unit, 'pcs')) + '"></td>',
                '    <td class="is-num"><input class="master-input calc" type="number" step="0.01" min="0" name="items[' + i + '][unit_price]" value="' + esc(val(item.unit_price, 0)) + '"></td>',
                '    <td class="is-num"><input class="master-input calc" type="number" step="0.01" min="0" name="items[' + i + '][discount_percent]" value="' + esc(val(item.discount_percent, 0)) + '"></td>',
                '    <td class="is-num"><input class="master-input calc" type="number" step="0.05" min="0" name="items[' + i + '][gst_percent]" value="' + esc(val(item.gst_percent, 18)) + '"></td>',
                '    <td class="is-num"><strong class="line-total">₹ 0.00</strong>',
                '        <input type="hidden" name="items[' + i + '][remarks]" value="' + esc(val(item.remarks, '')) + '"></td>',
                '    <td class="is-num"><button type="button" class="master-btn master-btn-ghost master-remove" aria-label="Remove this line"><i class="fas fa-xmark" aria-hidden="true"></i></button></td>',
                '</tr>'
            ].join('\n');

            body.insertAdjacentHTML('beforeend', html);
            calculateTotals();
        }

        initialItems.forEach(function (item) { addRow(item); });

        var addItemRow = document.getElementById('addItemRow');
        if (addItemRow) {
            addItemRow.addEventListener('click', function () { addRow({}); });
        }

        body.addEventListener('input', function (e) {
            if (e.target.classList.contains('calc')) calculateTotals();
        });

        /* The same trap as the client select: the row's product list is a
           select2 too (the shell decorates every `.master-select`, including the
           rows this file injects). */
        onChange(body, function (e) {
            if (e.target.classList.contains('master-product-select')) {
                var product = productOptions.find(function (p) { return String(p.id) === String(e.target.value); });
                var row = e.target.closest('tr');
                if (product && row) {
                    var nameInput = row.querySelector('input[name$="[product_name]"]');
                    var descInput = row.querySelector('textarea[name$="[description]"]');
                    if (nameInput && !nameInput.value) nameInput.value = product.name || '';
                    if (descInput && !descInput.value) descInput.value = product.description || '';
                }
            }
        });

        body.addEventListener('click', function (e) {
            /* the button carries an icon: the click may land on the <i> inside
               it, so the handler walks up to the control itself */
            var remove = e.target.closest ? e.target.closest('.master-remove') : null;

            if (remove) {
                remove.closest('tr').remove();
                calculateTotals();
            }
        });

        ['discountValue', 'discountType', 'freightAmount', 'packingAmount', 'otherCharges', 'roundOff',
            'amountPaid', 'gstType'
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', calculateTotals);
        });
        ['discountType', 'gstType'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', calculateTotals);
        });

        /* ------------------------------------------ the client's own record
           The invoice keeps a copy of the client, and the form has a field for
           every part of that copy. Both sides read ONE list: the controller
           writes it onto the option as JSON, and this applies it by field name,
           so a column added there arrives here with no edit to this file — and
           there is no second list to fall out of step with the first — and the
           handler is bound through `onChange` below, because a select2 announces
           a pick with a jQuery trigger that a DOM listener never hears. */
        var clientSelect = document.getElementById('clientSelect');
        if (clientSelect) {
            var clientForm = clientSelect.closest('.master-form') || document;

            function readJson(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return null;
                }
            }

            var snapshotFields = readJson(clientSelect.dataset.snapshotFields) || [];

            /* `onlyEmpty` is the difference between showing and choosing. An
               invoice already holds the office's copy of the client — sometimes
               a copy older than the client record — and a screen that rewrote it
               on sight would change a sent invoice's address by being opened.
               Picking a client is the office saying whose details they want now:
               that pass fills every field, and clears what the last one had. */
            function applySnapshot(option, onlyEmpty) {
                var snapshot = option ? readJson(option.dataset.snapshot) : null;
                if (!snapshot) return;

                snapshotFields.forEach(function (name) {
                    var el = clientForm.querySelector('[name="' + name + '"]');
                    if (!el) return;
                    if (onlyEmpty && el.value) return;
                    el.value = snapshot[name] || '';
                });
            }

            function clientChosen() {
                /* The placeholder is not a client: picking it leaves what the
                   office has typed where it is. */
                if (!clientSelect.value) return;
                applySnapshot(clientSelect.options[clientSelect.selectedIndex], false);
            }

            /* Through `onChange`, so a pick made in the select2 list is heard:
               a DOM `change` listener is the one binding select2 never fires. */
            onChange(clientSelect, clientChosen);

            applySnapshot(clientSelect.options[clientSelect.selectedIndex], true);

            /* Shipping is usually the billing address: one click, five fields. */
            var copyBilling = clientForm.querySelector('[data-copy-billing]');
            if (copyBilling) {
                copyBilling.addEventListener('click', function () {
                    ['address', 'city', 'state', 'country', 'pincode'].forEach(function (part) {
                        var from = clientForm.querySelector('[name="billing_' + part + '"]');
                        var to = clientForm.querySelector('[name="shipping_' + part + '"]');
                        if (from && to) to.value = from.value;
                    });
                });
            }
        }

        calculateTotals();
    });

    /* ============================================================ the list
       The invoice list wears the same chrome as every other listing in the
       office: it is the module's job to boot it and the shell's job to draw it.
       Every piece is guarded, because this same file loads on the form, which
       has none of it. */
    onReady(function () {
        var list = document.querySelector('.si-index');
        if (!list || typeof window.MasterList === 'undefined') return;

        window.MasterList.rowNavigation({ root: '.si-index' });
        window.MasterList.gridShadow({ root: '.si-index' });
        window.MasterList.saveViewToggle();
        window.MasterList.density({ root: '.si-index', key: 'invoiceDensity' });
    });

    /* --------------------------------------------------- the receipt dialog
       One dialog serves the whole page; the row that opens it says which
       invoice it is for, and the form's action comes from the template the
       controller's route rendered (so the route lives in Blade, not here). */
    onReady(function () {
        var modal = document.getElementById('paymentModal');
        var form = modal ? modal.querySelector('[data-payment-form]') : null;
        var triggers = document.querySelectorAll('[data-open-payment]');

        if (!modal || !form || !triggers.length) return;

        var template = form.getAttribute('data-action-template') || '';
        var subtitle = modal.querySelector('[data-payment-subtitle]');
        var amount = modal.querySelector('input[name="amount"]');

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var id = trigger.getAttribute('data-invoice-id');
                var number = trigger.getAttribute('data-invoice-number') || 'this invoice';
                var balance = trigger.getAttribute('data-invoice-balance') || '';

                form.setAttribute('action', template.replace('__INVOICE__', id));

                if (subtitle) {
                    subtitle.textContent = 'Against ' + number + (balance ? ' · ' + balance + ' still owed' : '');
                }

                /* What is owed is opened ready to be confirmed, not retyped. */
                if (amount) {
                    amount.value = trigger.getAttribute('data-invoice-amount') || balance.replace(/[^0-9.]/g, '');
                }

                if (window.MasterModal) {
                    window.MasterModal.open(modal);
                }
            });
        });
    });

    /* ----------------------------------------------------------- clipboard
       The office sends an invoice by WhatsApp far more often than by email, so
       the link, and the words that ask for the money, go to the clipboard. Two
       sources, one helper: the button may carry the text (`data-copy-text`), or
       name the field whose *current* value to copy (`data-copy-target`) — the
       reminder dialog's message, which the office may have edited a moment ago.
       Neither works on plain HTTP, so both fall back to selecting the text. */
    function copyText(text, button) {
        var label = button.innerHTML;
        var done = function () {
            button.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Copied';

            window.setTimeout(function () {
                button.innerHTML = label;
            }, 1600);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () {
                window.prompt('Copy', text);
            });

            return;
        }

        window.prompt('Copy', text);
    }

    onReady(function () {
        document.querySelectorAll('[data-copy-link], [data-copy-text]').forEach(function (button) {
            button.addEventListener('click', function () {
                copyText(button.getAttribute('data-copy-link') || button.getAttribute('data-copy-text') || '', button);
            });
        });

        document.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.getElementById(button.getAttribute('data-copy-target'));
                if (!field) return;

                /* a field the office is editing is copied as it stands */
                copyText(field.value || field.textContent || '', button);
            });
        });
    });

    /* --------------------------------------------------- the reminder dialog
       One dialog for the page, exactly like the receipt dialog beside it: the
       row says which invoice, the words come from the model, and the office may
       edit them before sending — the copy button then copies what they wrote. */
    onReady(function () {
        var modal = document.getElementById('reminderModal');
        var form = modal ? modal.querySelector('[data-reminder-form]') : null;
        var triggers = document.querySelectorAll('[data-open-reminder]');

        if (!modal || !form || !triggers.length) return;

        var template = form.getAttribute('data-action-template') || '';
        var subtitle = modal.querySelector('[data-reminder-subtitle]');
        var message = modal.querySelector('[data-reminder-message]');

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var id = trigger.getAttribute('data-invoice-id');
                var number = trigger.getAttribute('data-invoice-number') || 'this invoice';

                form.setAttribute('action', template.replace('__INVOICE__', id));

                if (subtitle) {
                    subtitle.textContent = 'Against ' + number + ' — what was asked, and when';
                }

                if (message) {
                    message.value = trigger.getAttribute('data-invoice-message') || '';
                }

                if (window.MasterModal) {
                    window.MasterModal.open(modal);
                }
            });
        });
    });

    /* ---------------------------------------------------------- the sweep
       Ticking rows is the month-end move: mark a batch sent, push it to the
       portal, log one reminder for a dozen clients, or take the selection to the
       CSV and the GST summary. The checkboxes are attached to the bulk form by
       id, so the bar can live in the toolbar while the boxes live in the table. */
    onReady(function () {
        var form = document.getElementById('bulkForm');
        var bar = document.querySelector('[data-bulk-bar]');
        var picks = document.querySelectorAll('[data-bulk-pick]');

        if (!form || !bar || !picks.length) return;

        var count = form.querySelector('[data-bulk-count]');
        var all = document.querySelector('[data-bulk-all]');
        var exportLink = form.querySelector('[data-bulk-export]');
        var gstLink = form.querySelector('[data-bulk-gst]');
        var clear = form.querySelector('[data-bulk-clear]');

        var selected = function () {
            return Array.prototype.filter.call(picks, function (pick) { return pick.checked; });
        };

        var withIds = function (link, ids) {
            if (!link) return;

            var url = link.getAttribute('href').split('?')[0];
            var query = ids.map(function (id) { return 'ids[]=' + encodeURIComponent(id); });

            link.setAttribute('href', query.length ? url + '?' + query.join('&') : url);
        };

        var sync = function () {
            var ids = selected().map(function (pick) { return pick.value; });

            bar.hidden = ids.length === 0;

            if (count) {
                count.textContent = ids.length === 1 ? '1 selected' : ids.length + ' selected';
            }

            /* "export the selected rows" is the screen's own exporter, narrowed */
            withIds(exportLink, ids);
            withIds(gstLink, ids);

            if (all) {
                all.checked = ids.length > 0 && ids.length === picks.length;
                all.indeterminate = ids.length > 0 && ids.length < picks.length;
            }
        };

        picks.forEach(function (pick) {
            pick.addEventListener('change', sync);
        });

        if (all) {
            all.addEventListener('change', function () {
                picks.forEach(function (pick) { pick.checked = all.checked; });
                sync();
            });
        }

        if (clear) {
            clear.addEventListener('click', function () {
                picks.forEach(function (pick) { pick.checked = false; });
                if (all) all.checked = false;
                sync();
            });
        }

        sync();
    });

    /* A failed save comes back with the input kept and the errors on the bag:
       the marker names the dialog it came from, so it reopens with the office's
       typing still in it. */
    onReady(function () {
        var marker = document.querySelector('[data-open-dialog]');
        var reopen = marker ? marker.getAttribute('data-open-dialog') : '';
        var dialog = reopen ? document.getElementById(reopen) : null;

        if (dialog && window.MasterModal) {
            window.MasterModal.open(dialog);
        }
    });
})();
