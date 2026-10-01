/* ==========================================================================
   SALES-INVOICES.JS — Sales invoices module
   --------------------------------------------------------------------------
   Behaviour for the invoice form: line-item builder (add/remove rows,
   product autofill), live GST/discount/total preview and client address
   prefill. The product options and existing items are passed through
   data attributes on #invoiceItemsBody (Blade cannot render inside an
   external script).
   The list and detail pages are static (no module JS needed).
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

        function money(v) {
            return '₹ ' + (Number(v || 0)).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function productSelect(name, selected) {
            var html = '<select class="master-select master-product-select" name="' + name + '"><option value="">Manual</option>';
            productOptions.forEach(function (p) {
                html += '<option value="' + p.id + '"' + (String(selected || '') === String(p.id) ? ' selected' : '') + '>' + p.no + ' : ' + p.name + '</option>';
            });
            return html + '</select>';
        }

        function calculateTotals() {
            var subtotal = 0;
            var taxable = 0;
            var tax = 0;

            body.querySelectorAll('tr').forEach(function (row) {
                var qtyInput = row.querySelector('input[name$="[quantity]"]');
                var rateInput = row.querySelector('input[name$="[unit_price]"]');
                var gstInput = row.querySelector('input[name$="[gst_percent]"]');

                var qty = parseFloat(qtyInput && qtyInput.value) || 0;
                var rate = parseFloat(rateInput && rateInput.value) || 0;
                var gst = parseFloat(gstInput && gstInput.value) || 0;

                var gross = qty * rate;
                var taxableLine = gross;

                var gstTypeEl = document.getElementById('gstType');
                var taxLine = (gstTypeEl && gstTypeEl.value === 'export') ?
                    0 :
                    taxableLine * gst / 100;

                var lineTotal = taxableLine + taxLine;

                subtotal += gross;
                taxable += taxableLine;
                tax += taxLine;

                var lineTotalElement = row.querySelector('.line-total');
                if (lineTotalElement) {
                    lineTotalElement.textContent = money(lineTotal);
                }
            });

            var discountValueEl = document.getElementById('discountValue');
            var discountTypeEl = document.getElementById('discountType');
            var freightEl = document.getElementById('freightAmount');
            var packingEl = document.getElementById('packingAmount');
            var otherEl = document.getElementById('otherCharges');
            var roundOffEl = document.getElementById('roundOff');
            var paidEl = document.getElementById('amountPaid');

            var discountValue = parseFloat(discountValueEl && discountValueEl.value) || 0;
            var discountType = (discountTypeEl && discountTypeEl.value) || 'amount';
            var invoiceDiscount = discountType === 'percent' ?
                taxable * discountValue / 100 :
                discountValue;
            var freight = parseFloat(freightEl && freightEl.value) || 0;
            var packing = parseFloat(packingEl && packingEl.value) || 0;
            var other = parseFloat(otherEl && otherEl.value) || 0;
            var roundOff = parseFloat(roundOffEl && roundOffEl.value) || 0;
            var paid = parseFloat(paidEl && paidEl.value) || 0;

            var total = taxable - invoiceDiscount + tax + freight + packing + other + roundOff;
            var balance = total - paid;

            var previewSubtotal = document.getElementById('previewSubtotal');
            var previewTax = document.getElementById('previewTax');
            var previewTotal = document.getElementById('previewTotal');
            var previewBalance = document.getElementById('previewBalance');
            if (previewSubtotal) previewSubtotal.textContent = money(taxable);
            if (previewTax) previewTax.textContent = money(tax);
            if (previewTotal) previewTotal.textContent = money(total);
            if (previewBalance) previewBalance.textContent = money(balance);
        }

        function addRow(item) {
            item = item || {};
            var i = rowIndex++;
            body.insertAdjacentHTML('beforeend', '<tr>\n' +
                '            <td>\n' +
                '                <div>\n' +
                '                ' + productSelect('items[' + i + '][product_id]', item.product_id || '') + '\n' +
                '                <input name="items[' + i + '][project_product_id]" type="hidden" value="' + (item.project_product_id || '') + '"></div><br>\n' +
                '                <div><input class="master-input" name="items[' + i + '][product_name]" value="' + (item.product_name || '') + '" placeholder="Product name"></div><br>\n' +
                '                <textarea class="master-textarea" rows="3" name="items[' + i + '][description]" placeholder="Description">' + (item.description || '') + '</textarea>\n' +
                '            </td>\n' +
                '            <td><input class="master-input" name="items[' + i + '][hsn_sac]" value="' + (item.hsn_sac || '') + '" placeholder="HSN"></td>\n' +
                '            <td><input class="master-input calc" type="number" step="1" min="0" name="items[' + i + '][quantity]" value="' + (item.quantity || 1) + '"></td>\n' +
                '            <td><input class="master-input" name="items[' + i + '][unit]" value="' + (item.unit || 'pcs') + '"></td>\n' +
                '            <td><input class="master-input calc" type="number" step="0.05" min="0" name="items[' + i + '][unit_price]" value="' + (item.unit_price || 0) + '"></td>\n' +
                '            <td><input class="master-input calc" type="number" step="1" min="0" name="items[' + i + '][gst_percent]" value="' + (item.gst_percent || 18) + '"></td>\n' +
                '            <td><strong class="line-total">₹ 0.00</strong><input name="items[' + i + '][remarks]" placeholder="Remarks" style="margin-top:5px;" hidden></td>\n' +
                '            <td><button type="button" class="master-remove">×</button></td>\n' +
                '        </tr>');
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

        body.addEventListener('change', function (e) {
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
            if (e.target.classList.contains('master-remove')) {
                e.target.closest('tr').remove();
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

        /* Client select → billing/shipping address prefill */
        var clientSelect = document.getElementById('clientSelect');
        if (clientSelect) {
            clientSelect.addEventListener('change', function () {
                var o = this.options[this.selectedIndex];
                if (!o) return;
                function set(id, value) {
                    var el = document.getElementById(id);
                    if (el) el.value = value || '';
                }
                set('client_company_name', o.dataset.company);
                set('client_contact_name', o.dataset.contact);
                set('client_email', o.dataset.email);
                set('client_mobile', o.dataset.mobile);
                set('client_gstin', o.dataset.gstin);
                set('client_pan', o.dataset.pan);
                set('billing_address', [o.dataset.billingAddress, o.dataset.billingCity, o.dataset.billingState, o.dataset.billingCountry, o.dataset.billingPincode]
                    .filter(Boolean).join(', '));
                set('shipping_address', [o.dataset.shippingAddress, o.dataset.shippingCity, o.dataset.shippingState, o.dataset.shippingCountry, o.dataset.shippingPincode]
                    .filter(Boolean).join(', '));
            });
        }

        calculateTotals();
    });
})();
