/* ==========================================================================
   PRICE-CALCULATOR.JS — Price Calculator module (index view)
   --------------------------------------------------------------------------
   Self-contained RMB → INR pricing wizard:
     - 3-step navigation (create / calculate / export)
     - live cost engine (freight, bank split, BCD/SWS, GST, margin)
     - quantity "ladder" table with per-row recalculation
     - state persistence in localStorage (misspack_price_calculator_wizard_v1)
     - CSV export + quote print
   No server data is needed, so no data bridge is used.
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
    const storageKey = 'misspack_price_calculator_wizard_v1';
    const defaultRows = [
        { qty: 3000, finish: 'Matte', printing: 'One Color', basePrice: 1.35, totalFreight: 42000, localTransport: 2, remarks: 'Small quantity quote' },
        { qty: 5000, finish: 'Matte', printing: 'One Color', basePrice: 1.20, totalFreight: 50000, localTransport: 2, remarks: 'Target quantity' },
        { qty: 10000, finish: 'Glossy', printing: 'One Color', basePrice: 1.05, totalFreight: 65000, localTransport: 1.6, remarks: 'Bulk quote' }
    ];

    let state = loadState();
    let activeStep = Number(state.activeStep || 1);

    function defaultState() {
        return {
            activeStep: 1,
            clientName: 'MissPack Client',
            productName: '50ml Matte Bottle',
            quoteNo: 'QT-001',
            preparedBy: 'Hardik Bohra',
            quoteDate: new Date().toISOString().slice(0, 10),
            currency: 'RMB',
            quoteNotes: 'Matte finish and one color printing. Share photo/video and quote for multiple quantities.',
            bankPaymentPercent: 40,
            gstPercent: 18,
            conversionRate: 12,
            basePrice: 1.20,
            quantity: 5000,
            totalFreight: 50000,
            bcdPercent: 10,
            swsPercent: 10,
            localTransport: 2,
            marginPercent: 25,
            ladderRows: defaultRows
        };
    }

    function loadState() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            return Object.assign(defaultState(), saved, {
                ladderRows: Array.isArray(saved.ladderRows) && saved.ladderRows.length ? saved.ladderRows : defaultRows
            });
        } catch (error) {
            return defaultState();
        }
    }

    function saveState() {
        state.activeStep = activeStep;
        localStorage.setItem(storageKey, JSON.stringify(state));
        const el = document.getElementById('saveStatus');
        el.textContent = '✓ Saved in browser';
        el.style.background = '#e8fff7';
        el.style.color = '#047857';
    }

    function markDirty() {
        const el = document.getElementById('saveStatus');
        el.textContent = 'Saving...';
        el.style.background = '#fff4e5';
        el.style.color = '#92400e';
        clearTimeout(window.pcSaveTimer);
        window.pcSaveTimer = setTimeout(saveState, 250);
    }

    function number(value) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function money(value, decimals = 2) {
        return number(value).toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    function calculate(input) {
        const a = number(input.basePrice);
        const b = number(input.conversionRate);
        const qty = Math.max(number(input.quantity), 0);
        const c = qty > 0 ? number(input.totalFreight) / qty : number(input.totalFreight);
        const bankPaymentPercent = Math.min(Math.max(number(input.bankPaymentPercent ?? state.bankPaymentPercent), 0), 100);
        const cashPaymentPercent = 100 - bankPaymentPercent;
        const bankPct = bankPaymentPercent / 100;
        const cashPct = cashPaymentPercent / 100;
        const d = number(input.bcdPercent ?? state.bcdPercent) / 100;
        const e = number(input.swsPercent ?? state.swsPercent) / 100;
        const gstPct = number(input.gstPercent ?? state.gstPercent) / 100;
        const f = number(input.localTransport);
        const g = number(input.marginPercent ?? state.marginPercent) / 100;
        const baseInr = a * b;
        const h = baseInr + c;
        const bankPortion = h * bankPct;
        const cashPortion = h * cashPct;
        const bcdAmount = bankPortion * d;
        const swsAmount = bcdAmount * e;
        const customsGstAmount = (bankPortion + bcdAmount + swsAmount) * gstPct;
        const k = bankPortion + cashPortion + bcdAmount + swsAmount + f;
        const marginAmount = k * g;
        const l = k + marginAmount;
        const totalFreightAmount = number(input.totalFreight);
        const totalLocalTransportAmount = f * qty;
        const totalMarginAmount = marginAmount * qty;
        const customerGstAmount = (l * bankPct) * gstPct;
        const customerTotalPerUnit = l + customerGstAmount;
        const vendorTotal = baseInr * qty;

        const customsTotal = (bcdAmount + swsAmount) * qty;
        const customerTotal = l * qty;

        const totalIncome = customerTotal;
        const totalExpense =
            vendorTotal +
            totalFreightAmount +
            customsTotal +
            totalLocalTransportAmount;

        const totalProfit = totalIncome - totalExpense;

        return {
            baseInr, c, h, bankPaymentPercent, cashPaymentPercent, bankPortion, cashPortion,
            bcdAmount, swsAmount, gstAmount: customsGstAmount, k, marginAmount, l,
            totalFreightAmount, totalLocalTransportAmount, totalMarginAmount,
            customerGstAmount, customerTotalPerUnit, 
            vendorTotal: baseInr * qty,
            vendorBankTotal: baseInr * qty * bankPct,
            vendorCashTotal: baseInr * qty * cashPct,
            customsExGstTotal: (bcdAmount + swsAmount) * qty,
            customsGstTotal: customsGstAmount * qty,
            customsTotal: (bcdAmount + swsAmount + customsGstAmount) * qty,
            customerExGstTotal: l * qty,
            customerGstTotal: customerGstAmount * qty,
            customerTotal: customerTotalPerUnit * qty,
            total: customerTotalPerUnit * qty,
            totalIncome, totalExpense, totalProfit
        };
    }

    function applyStateToFields() {
        document.querySelectorAll('.master-save, .master-calc-input').forEach(field => {
            const key = field.dataset.key;
            if (!key || state[key] === undefined) return;
            field.value = state[key];
        });
    }

    function bindFields() {
        document.querySelectorAll('.master-save, .master-calc-input').forEach(field => {
            field.addEventListener('input', function () {
                state[this.dataset.key] = this.value;
                updateAll();
                markDirty();
            });
            field.addEventListener('change', function () {
                state[this.dataset.key] = this.value;
                updateAll();
                markDirty();
            });
        });
    }

    function text(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function value(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val;
    }

    function updateAll() {
        const calc = calculate(state);
        value('cashPaymentPercent', money(calc.cashPaymentPercent));
        value('freightPerUnit', money(calc.c));
        text('miniBaseInr', '₹ ' + money(calc.baseInr));
        text('miniBankPortion', '₹ ' + money(calc.bankPortion));
        text('miniCashPortion', '₹ ' + money(calc.cashPortion));
        text('miniCashPercent', money(calc.cashPaymentPercent) + '%');
        text('miniBcd', '₹ ' + money(calc.bcdAmount));
        text('miniSws', '₹ ' + money(calc.swsAmount));
        text('miniCustomsGst', '₹ ' + money(calc.gstAmount));
        text('miniMargin', '₹ ' + money(calc.marginAmount));
        text('summaryBaseInr', '₹ ' + money(calc.baseInr));
        text('summaryFreight', '₹ ' + money(calc.c));
        text('summaryBank', '₹ ' + money(calc.bankPortion));
        text('summaryCash', '₹ ' + money(calc.cashPortion));
        text('summaryBcd', '₹ ' + money(calc.bcdAmount));
        text('summarySws', '₹ ' + money(calc.swsAmount));
        text('summaryGst', '₹ ' + money(calc.gstAmount));
        text('summaryLocalTransport', '₹ ' + money(number(state.localTransport)));
        text('summaryMargin', '₹ ' + money(calc.marginAmount));
        text('summaryLanding', '₹ ' + money(calc.k));
        text('summarySelling', '₹ ' + money(calc.l));
        text('summaryTotal', '₹ ' + money(calc.customerTotal));
        text('payVendorTotal', '₹ ' + money(calc.vendorTotal));
        text('payVendorBank', '₹ ' + money(calc.vendorBankTotal));
        text('payVendorCash', '₹ ' + money(calc.vendorCashTotal));
        text('payCustomsExGst', '₹ ' + money(calc.customsExGstTotal));
        text('payCustomsGst', '₹ ' + money(calc.customsGstTotal));
        text('payCustomsTotal', '₹ ' + money(calc.customsTotal));
        text('receiveCustomerExGst', '₹ ' + money(calc.customerExGstTotal));
        text('receiveCustomerGst', '₹ ' + money(calc.customerGstTotal));
        text('receiveCustomerTotal', '₹ ' + money(calc.customerTotal));
        text('payFreightTotal', '₹ ' + money(calc.totalFreightAmount));
        text('payLocalTransportTotal', '₹ ' + money(calc.totalLocalTransportAmount));
        text('payMarginTotal', '₹ ' + money(calc.totalMarginAmount));
        text('summaryIncome', '₹ ' + money(calc.totalIncome));
        text('summaryExpense', '₹ ' + money(calc.totalExpense));
        text('summaryProfit', '₹ ' + money(calc.totalProfit));
        renderLadder(false);
        updateSteps();
    }

    function goStep(step) {
        activeStep = Math.min(Math.max(Number(step), 1), 3);
        document.querySelectorAll('.master-step-panel').forEach(panel => panel.classList.toggle('active', Number(panel.dataset.step) === activeStep));
        updateSteps();
        saveState();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateSteps() {
        document.querySelectorAll('.master-step-tab').forEach(tab => {
            const step = Number(tab.dataset.goStep);
            tab.classList.toggle('active', step === activeStep);
            tab.classList.toggle('done', step < activeStep);
        });
    }

    function renderLadder(rebuild = true) {
        const tbody = document.getElementById('ladderBody');
        if (!tbody) return;
        if (rebuild) tbody.innerHTML = '';
        if (!rebuild && tbody.children.length === state.ladderRows.length) {
            [...tbody.children].forEach((tr, index) => updateLadderOutput(tr, index));
            return;
        }
        tbody.innerHTML = '';
        state.ladderRows.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input class="master-input master-yellow" type="number" step="1" data-ladder="qty" value="${row.qty ?? ''}"></td>
                <td><input class="master-input master-yellow" type="number" step="0.0001" data-ladder="basePrice" value="${row.basePrice ?? ''}"></td>
                <td><input class="master-input master-yellow" type="number" step="0.01" data-ladder="totalFreight" value="${row.totalFreight ?? ''}"></td>
                <td><input class="master-input master-yellow" type="number" step="0.01" data-ladder="localTransport" value="${row.localTransport ?? ''}"></td>
                <td><input class="master-input master-output" data-output="landing" readonly></td>
                <td><input class="master-input master-output" data-output="selling" readonly></td>
                <td><div class="master-actions-cell"><button type="button" class="master-icon-btn danger" data-remove-row="${index}">×</button></div></td>
            `;
            tbody.appendChild(tr);
            tr.querySelectorAll('[data-ladder]').forEach(input => {
                input.addEventListener('input', function () {
                    state.ladderRows[index][this.dataset.ladder] = this.value;
                    updateLadderOutput(tr, index);
                    markDirty();
                });
            });
            tr.querySelector('[data-remove-row]').addEventListener('click', function () {
                state.ladderRows.splice(index, 1);
                renderLadder(true);
                markDirty();
            });
            updateLadderOutput(tr, index);
        });
    }

    function updateLadderOutput(tr, index) {
        const row = state.ladderRows[index];
        if (!row) return;
        const calc = calculate({
            basePrice: row.basePrice,
            conversionRate: state.conversionRate,
            quantity: row.qty,
            totalFreight: row.totalFreight,
            bankPaymentPercent: state.bankPaymentPercent,
            bcdPercent: state.bcdPercent,
            swsPercent: state.swsPercent,
            gstPercent: state.gstPercent,
            localTransport: row.localTransport,
            marginPercent: state.marginPercent
        });
        tr.querySelector('[data-output="landing"]').value = money(calc.k);
        tr.querySelector('[data-output="selling"]').value = money(calc.l);
    }

    function addLadderRow() {
        state.ladderRows.push({ qty: state.quantity, finish: 'Matte', printing: 'One Color', basePrice: state.basePrice, totalFreight: state.totalFreight, localTransport: state.localTransport, remarks: '' });
        renderLadder(true);
        markDirty();
    }

    function exportCsv() {
        const rows = [['Qty','Finish','Printing','Base Price','Total Freight','Local Transport','Landing Cost INR','Selling Price INR','Remarks']];
        state.ladderRows.forEach(row => {
            const calc = calculate({ basePrice: row.basePrice, conversionRate: state.conversionRate, quantity: row.qty, totalFreight: row.totalFreight, bankPaymentPercent: state.bankPaymentPercent, bcdPercent: state.bcdPercent, swsPercent: state.swsPercent, gstPercent: state.gstPercent, localTransport: row.localTransport, marginPercent: state.marginPercent });
            rows.push([row.qty, row.finish, row.printing, row.basePrice, row.totalFreight, row.localTransport, calc.k.toFixed(2), calc.l.toFixed(2), row.remarks]);
        });
        const csv = rows.map(row => row.map(value => `"${String(value ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'price-calculator.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function printQuote() {
        document.getElementById('printDate').textContent = 'Generated on ' + new Date().toLocaleString();
        window.print();
    }

    document.querySelectorAll('[data-go-step]').forEach(btn => btn.addEventListener('click', () => goStep(btn.dataset.goStep)));
    document.querySelectorAll('[data-next-step]').forEach(btn => btn.addEventListener('click', () => goStep(btn.dataset.nextStep)));
    document.querySelectorAll('[data-prev-step]').forEach(btn => btn.addEventListener('click', () => goStep(btn.dataset.prevStep)));
    document.getElementById('addLadderRow')?.addEventListener('click', addLadderRow);
    document.getElementById('exportCsv')?.addEventListener('click', exportCsv);
    document.getElementById('exportCsvBottom')?.addEventListener('click', exportCsv);
    document.getElementById('printQuote')?.addEventListener('click', printQuote);
    document.getElementById('printQuoteBottom')?.addEventListener('click', printQuote);

    document.getElementById('resetCalculator').addEventListener('click', function () {
        if (!confirm('Reset browser cached calculator data?')) return;
        localStorage.removeItem(storageKey);
        state = defaultState();
        activeStep = 1;
        applyStateToFields();
        renderLadder(true);
        updateAll();
        saveState();
        goStep(1);
    });

    applyStateToFields();
    bindFields();
    renderLadder(true);
    updateAll();
    goStep(activeStep);
    saveState();
    });
})();
