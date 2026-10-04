/* ==========================================================================
   PURCHASE-INVOICES.JS — the purchase module's screens
   --------------------------------------------------------------------------
   The invoice script's sibling, and deliberately the same shape: one line-item
   builder and money preview that mirror `PurchaseInvoiceController::syncItemsAndTotals()`
   step for step, one dialog wiring, one clipboard helper, one listing boot.

   Where the two differ is who is on the other side of the document. An invoice
   fills itself from a **client**; a purchase document fills itself from a
   **vendor** — a name, a GSTIN and an address that come off the vendor record
   the way the client's come off theirs. There is no client portal here, so the
   public link is a print link and nothing else.
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

    /* ======================================================= the form
       The line-item editor, the money preview, and the vendor snapshot. */
    onReady(function () {
        var body = document.getElementById('purchaseItemsBody');
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
        var ledgerPaid = parseFloat(body.getAttribute('data-ledger-paid')) || 0;
        var currency = body.getAttribute('data-currency') || 'INR';

        function money(v) {
            /* A purchase document can be raised in the vendor's currency, and a
               preview that printed ₹ over an RMB bill would be the one figure on
               the page nobody could trust. */
            var symbol = currency === 'INR' ? '₹ ' : currency + ' ';
            return symbol + (Number(v || 0)).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        /* A figure that moves the total down is written as a subtraction. */
        function minus(v) {
            var amount = round2(Math.abs(Number(v) || 0));
            return amount === 0 ? money(0) : '− ' + money(amount);
        }

        /* The same rounding the server does, so a preview and a save agree. */
        function round2(v) {
            return Math.round((Number(v || 0) + Number.EPSILON) * 100) / 100;
        }

        function esc(value) {
            return String(value === undefined || value === null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

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

        function setRow(rowId, valueId, text, show) {
            var row = document.getElementById(rowId);
            if (row) row.hidden = !show;
            setText(valueId, text);
        }

        /* Every `.master-select` in this app is a select2, and a pick made in
           its list is announced with a **jQuery** trigger — which a plain DOM
           listener never hears. Bind the way the pick is announced. */
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
           The office sees the purchase document before it is saved, and the
           figures they see have to be the figures that get stored. This mirrors
           `syncItemsAndTotals()` step for step: gross, the line's own discount,
           taxable, the document discount, the tax scaled by that discount's
           ratio, the charges, the round off, the total — and then the model's
           balance rule, with the payments already in the vendor ledger (handed
           over in `data-ledger-paid`) on the same side of the subtraction.

           An import carries no GST on its lines (`gst_type = export`), exactly
           as the controller stores it. */
        function calculateTotals() {
            var gstType = fieldText('gstType');
            var noGst = gstType === 'export';

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

                if (!noGst) {
                    if (gstType === 'inter_state') {
                        lineTax = round2(lineTaxable * gst / 100);
                    } else {
                        var half = round2(lineTaxable * (gst / 200));
                        lineTax = round2(half * 2);
                    }
                }

                gross += lineGross;
                taxable += lineTaxable;

                if (!noGst) {
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
            var documentDiscount = discountType === 'percent'
                ? round2(taxable * discountValue / 100)
                : Math.min(discountValue, taxable);

            var taxableAfter = Math.max(round2(taxable - documentDiscount), 0);
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
            var paid = round2(fieldValue('amountPaid') + ledgerPaid);
            var balance = Math.max(round2(total - paid), 0);

            setRow('rowSubtotal', 'previewSubtotal', money(gross), true);
            setRow('rowLineDiscount', 'previewLineDiscount', minus(taxable - gross), round2(taxable - gross) !== 0);
            setRow('rowDocDiscount', 'previewDocDiscount', minus(documentDiscount), documentDiscount > 0);
            setRow('rowTaxable', 'previewTaxable', money(taxableAfter), true);
            setRow('rowCgst', 'previewCgst', money(cgst), cgst !== 0);
            setRow('rowSgst', 'previewSgst', money(sgst), sgst !== 0);
            setRow('rowIgst', 'previewIgst', money(igst), igst !== 0);
            setRow('rowFreight', 'previewFreight', money(freight), freight !== 0);
            setRow('rowPacking', 'previewPacking', money(packing), packing !== 0);
            setRow('rowOther', 'previewOther', money(other), other !== 0);
            setRow('rowRoundOff', 'previewRoundOff', money(roundOff), roundOff !== 0);
            setRow('rowTotal', 'previewTotal', money(total), true);
            setRow('rowPaid', 'previewPaid', minus(paid), paid !== 0);
            setRow('rowBalance', 'previewBalance', money(balance), true);

            var balanceText = document.getElementById('previewBalance');
            if (balanceText) balanceText.className = balance > 0 ? 'pi-due' : 'pi-clear';
        }

        /* One line of the document. Every name here is one
           `syncItemsAndTotals()` reads. */
        function addRow(item) {
            item = item || {};
            var i = rowIndex++;

            var html = [
                '<tr>',
                '    <td>',
                '        ' + productSelect('items[' + i + '][product_id]', val(item.product_id, '')),
                '        <input type="hidden" name="items[' + i + '][project_product_id]" value="' + esc(val(item.project_product_id, '')) + '">',
                '        <input class="master-input" name="items[' + i + '][product_name]" value="' + esc(val(item.product_name, '')) + '" placeholder="Item name">',
                '        <textarea class="master-textarea" rows="3" name="items[' + i + '][description]" placeholder="Specification / description">' + esc(val(item.description, '')) + '</textarea>',
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

        /* The currency decides what the preview prints, and the rate decides what
           the ledger will store in rupees: keep the symbol honest as it changes. */
        var currencySelect = document.getElementById('invoiceCurrency');
        if (currencySelect) {
            onChange(currencySelect, function () {
                currency = currencySelect.value || 'INR';
                document.querySelectorAll('[data-preview-currency]').forEach(function (node) {
                    node.textContent = currency;
                });
                calculateTotals();
            });
        }

        /* ------------------------------------------ the vendor's own record
           The purchase document keeps a copy of the vendor, and the form has a
           field for every part of that copy. Both sides read ONE list: the
           controller writes it onto the option as JSON, and this applies it by
           field name, so a column added there arrives here with no edit to this
           file — and there is no second list to fall out of step with the first.
           The handler is bound through `onChange`, because a select2 announces a
           pick with a jQuery trigger that a DOM listener never hears. */
        var vendorSelect = document.getElementById('vendorSelect');
        if (vendorSelect) {
            var vendorForm = vendorSelect.closest('.master-form') || document;

            function readJson(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return null;
                }
            }

            var snapshotFields = readJson(vendorSelect.dataset.snapshotFields) || [];
            var gstStateCodes = {
                jammukashmir: '01', jammuandkashmir: '01', jandk: '01', jk: '01',
                himachalpradesh: '02', hp: '02', punjab: '03', pb: '03', chandigarh: '04', ch: '04',
                uttarakhand: '05', uttaranchal: '05', uk: '05', haryana: '06', hr: '06',
                delhi: '07', nctofdelhi: '07', dl: '07', rajasthan: '08', rj: '08',
                uttarpradesh: '09', up: '09', bihar: '10', br: '10', sikkim: '11', sk: '11',
                arunachalpradesh: '12', ar: '12', nagaland: '13', nl: '13',
                manipur: '14', mn: '14', mizoram: '15', mz: '15', tripura: '16', tr: '16',
                meghalaya: '17', ml: '17', assam: '18', as: '18',
                westbengal: '19', wb: '19', jharkhand: '20', jh: '20',
                odisha: '21', orissa: '21', od: '21', or: '21',
                chhattisgarh: '22', cg: '22', madhyapradesh: '23', mp: '23',
                gujarat: '24', gujrat: '24', gj: '24', guj: '24',
                damananddiu: '25', dd: '25', dadraandnagarhaveli: '26', dn: '26',
                dadraandnagarhavelianddamananddiu: '26', maharashtra: '27', mh: '27',
                andhrapradesh: '37', ap: '37', karnataka: '29', ka: '29', goa: '30', ga: '30',
                lakshadweep: '31', ld: '31', kerala: '32', kl: '32',
                tamilnadu: '33', tn: '33', puducherry: '34', pondicherry: '34', py: '34',
                andamanandnicobarislands: '35', andamanandnicobar: '35', an: '35',
                telangana: '36', ts: '36', ladakh: '38', la: '38'
            };
            var gstStateNames = Object.keys(gstStateCodes).sort(function (a, b) {
                return b.length - a.length;
            });

            function stateCodeFromName(value) {
                var state = String(value || '').trim().toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]/g, '');
                var numeric = state.match(/^(\d{2})/);
                if (numeric) return numeric[1];

                for (var i = 0; i < gstStateNames.length; i++) {
                    if (state === gstStateNames[i] || state.indexOf(gstStateNames[i]) === 0) {
                        return gstStateCodes[gstStateNames[i]];
                    }
                }
                return '';
            }

            function stateCodeFromGstin(value) {
                var match = String(value || '').replace(/\s+/g, '').match(/^(\d{2})/);
                return match ? match[1] : '';
            }

            var gstTypeSelect = vendorForm.querySelector('[name="gst_type"]');
            var gstTypeTouched = false;
            var settingGstType = false;

            /* Whose state decides the split: the vendor's against **ours**. A
               purchase from a Gujarat supplier is a CGST + SGST bill; one from
               Shanghai or Mumbai is IGST (or no GST at all, for an import). */
            function autoSelectGstType() {
                if (!gstTypeSelect || gstTypeTouched || fieldText('gstType') === 'export') return;

                var vendorState = vendorForm.querySelector('[name="vendor_state"]');
                var vendorGstin = vendorForm.querySelector('[name="vendor_gstin"]');
                var buyerState = vendorForm.querySelector('[name="buyer_state"]');
                var buyerGstin = vendorForm.querySelector('[name="buyer_gstin"]');
                var vendorCode = stateCodeFromName(vendorState && vendorState.value)
                    || stateCodeFromGstin(vendorGstin && vendorGstin.value);

                /* A supplier outside India has no GST state code: the bill is an
                   import and carries no GST on its lines. */
                var outsideIndia = (vendorForm.querySelector('[name="vendor_country"]') || {}).value || '';
                if (!vendorCode && outsideIndia && !/^india$/i.test(String(outsideIndia).trim())) {
                    setGstType('export');
                    return;
                }
                if (!vendorCode) return;

                var buyerCode = stateCodeFromName(buyerState && buyerState.value)
                    || stateCodeFromGstin(buyerGstin && buyerGstin.value)
                    || '24';

                setGstType(vendorCode !== buyerCode ? 'inter_state' : 'intra_state');
            }

            function setGstType(nextType) {
                if (!gstTypeSelect || gstTypeSelect.value === nextType) return;

                settingGstType = true;
                if (window.jQuery && window.jQuery.fn) {
                    window.jQuery(gstTypeSelect).val(nextType).trigger('change');
                } else {
                    gstTypeSelect.value = nextType;
                    gstTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                settingGstType = false;
                calculateTotals();
            }

            if (gstTypeSelect) {
                onChange(gstTypeSelect, function () {
                    if (!settingGstType) gstTypeTouched = true;
                    calculateTotals();
                });
            }

            ['vendor_state', 'vendor_gstin', 'vendor_country'].forEach(function (name) {
                var field = vendorForm.querySelector('[name="' + name + '"]');
                if (field) field.addEventListener('input', autoSelectGstType);
            });

            /* `onlyEmpty` is the difference between showing and choosing. A
               document already raised holds the office's copy of the vendor — a
               copy older, sometimes, than the vendor record — and a screen that
               rewrote it on sight would change a sent order's address by being
               opened. Picking a vendor is the office saying whose details they
               want now: that pass fills every field, and clears what the last one
               had. */
            function applySnapshot(option, onlyEmpty) {
                var snapshot = option ? readJson(option.dataset.snapshot) : null;
                if (!snapshot) return;

                snapshotFields.forEach(function (name) {
                    var el = vendorForm.querySelector('[name="' + name + '"]');
                    if (!el) return;
                    if (onlyEmpty && el.value) return;
                    el.value = snapshot[name] || '';
                });
            }

            function vendorChosen() {
                if (!vendorSelect.value) return;
                gstTypeTouched = false;
                applySnapshot(vendorSelect.options[vendorSelect.selectedIndex], false);
                autoSelectGstType();
            }

            onChange(vendorSelect, vendorChosen);
            applySnapshot(vendorSelect.options[vendorSelect.selectedIndex], true);
        }

        calculateTotals();
    });

    /* ============================================================ the listing
       The same chrome as every other listing: row navigation, the grid shadow,
       the density switch and the "save this view" toggle, all from the shared
       `MasterList`. */
    onReady(function () {
        var list = document.querySelector('.pi-index');
        if (!list || typeof window.MasterList === 'undefined') return;

        window.MasterList.rowNavigation({ root: '.pi-index' });
        window.MasterList.gridShadow({ root: '.pi-index' });
        window.MasterList.density({ root: '.pi-index', key: 'purchaseDensity' });
        window.MasterList.saveViewToggle();
    });

    /* ---------------------------------------------------------- the sweep
       Ticking rows is the month-end move: mark a batch sent, approve the lot, or
       take the selection to the CSV and the GST register. The boxes are attached
       to the bulk form by id — a form inside a form is not a form — so the bar
       lives in the toolbar while the boxes live in the table. */
    onReady(function () {
        var form = document.getElementById('purchaseBulkForm');
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

        /* The exporters are the screen's own, narrowed: what was ticked goes in
           the URL, so the file and the screen can never disagree about the rows. */
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

    /* --------------------------------------------------- the payment dialog
       One dialog serves the page; the row that opens it says which bill it is
       for, and the form's action comes from the template the route rendered (so
       the route lives in Blade, not here). A foreign-currency bill shows what
       the payment is worth in rupees at the bill's own rate — the figure the
       vendor ledger will store. */
    onReady(function () {
        var modal = document.getElementById('paymentModal');
        var form = modal ? modal.querySelector('[data-payment-form]') : null;
        var triggers = document.querySelectorAll('[data-open-payment]');

        if (!modal || !form || !triggers.length) return;

        var template = form.getAttribute('data-action-template') || '';
        var subtitle = modal.querySelector('[data-payment-subtitle]');
        var amount = modal.querySelector('input[name="amount"]');
        var currencyNote = modal.querySelector('[data-payment-currency]');
        var conversion = modal.querySelector('[data-payment-conversion]');

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var id = trigger.getAttribute('data-invoice-id');
                var number = trigger.getAttribute('data-invoice-number') || 'this bill';
                var currency = trigger.getAttribute('data-invoice-currency') || 'INR';
                var rate = parseFloat(trigger.getAttribute('data-invoice-rate')) || 1;
                var balance = trigger.getAttribute('data-invoice-balance-figure') || '';
                var balanceLabel = trigger.getAttribute('data-invoice-balance') || '';

                form.setAttribute('action', template.replace('__INVOICE__', id));

                if (subtitle) {
                    subtitle.textContent = 'Against ' + number + (balanceLabel ? ' · ' + balanceLabel + ' still owed' : '');
                }

                /* What the figure is denominated in, said before it is typed: the
                   vendor ledger stores rupees either way, and a payment typed in
                   RMB against a rupee bill is the mistake this line prevents. */
                if (currencyNote) {
                    currencyNote.textContent = currency === 'INR'
                        ? 'In rupees — the bill is in INR.'
                        : 'In ' + currency + ' — the vendor ledger stores the rupee value at the bill\\'s rate.';
                }

                /* What is owed is opened ready to be confirmed, not retyped. */
                if (amount) amount.value = balance;

                if (conversion) {
                    var rupees = (parseFloat(balance) || 0) * rate;
                    conversion.hidden = currency === 'INR';
                    conversion.textContent = 'Equals ₹ ' + rupees.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) + ' at the bill\'s rate of ' + rate + '.';
                }

                if (window.MasterModal) {
                    window.MasterModal.open(modal);
                }
            });
        });
    });

    /* ----------------------------------------------------------- clipboard
       The office sends a purchase order to a supplier by WhatsApp far more often
       than by email, so the print link goes to the clipboard. */
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
