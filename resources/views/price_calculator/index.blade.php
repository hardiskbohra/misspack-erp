@extends('layouts.app')

@section('page-title', 'Price Calculator')

@section('content')
<style>
    :root {
        --pc-primary: #4f83f1;
        --pc-primary-2: #6366f1;
        --pc-info: #159ff7;
        --pc-teal: #12cbb7;
        --pc-purple: #8b5cf6;
        --pc-orange: #f59e0b;
        --pc-red: #ef4770;
        --pc-green: #10b981;
        --pc-dark: #17233b;
        --pc-muted: #687386;
        --pc-border: #dfe7f3;
        --pc-bg: #eef3ff;
        --pc-soft: #edf5ff;
        --pc-white: #ffffff;
        --pc-yellow: #fff4c7;
        --pc-yellow-border: #f6c343;
        --pc-shadow: 0 14px 35px rgba(25, 42, 70, 0.08);
    }
    
    .master-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 18px 22px;
        margin-bottom: 20px;
    }

    .master-toolbar-left, .master-toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .master-status { min-height: 38px; display: inline-flex; align-items: center; gap: 8px; padding: 9px 13px; border-radius: 12px; background: #e8fff7; color: #047857; font-weight: 600; white-space: nowrap; }

    .master-btn-primary { background: linear-gradient(135deg, var(--master-primary), var(--master-primary-2)); color: #fff; }
    .master-btn-soft { background: var(--master-soft); color: var(--master-primary); }
    .master-btn-light { background: #f3f6fb; color: var(--master-dark); }
    .master-btn-danger { background: #fff0f4; color: #e11d48; }
    .master-btn-green { background: #e8fff7; color: #0e9f6e; }
    .master-btn:disabled { opacity: .55; cursor: not-allowed; }

    .master-wizard {
        overflow: hidden;
    }

    .master-step-tabs {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0;
        border-bottom: 1px solid var(--master-border);
        background: #fbfdff;
    }

    .master-step-tab {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 22px;
        border: 0;
        border-right: 1px solid var(--master-border);
        background: transparent;
        color: var(--master-muted);
        text-align: left;
        cursor: pointer;
        transition: .2s ease;
    }

    .master-step-tab:last-child { border-right: 0; }
    .master-step-tab.active { background: #fff; color: var(--master-primary); box-shadow: inset 0 -3px 0 var(--master-primary); }
    .master-step-tab.done .master-step-number { background: #e8fff7; color: #047857; }
    .master-step-number { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; background: var(--master-soft); color: var(--master-primary); font-weight: 600; }
    .master-step-text strong { display: block; font-size: 16px; font-weight: 600; color: inherit; }
    .master-step-text span { display: block; margin-top: 2px; font-size: 16px; font-weight: 500; color: var(--master-muted); }

    .master-step-content { padding: 24px; }
    .master-step-panel { display: none; }
    .master-step-panel.active { display: block; }


    .master-section-title h2 { margin: 0; font-size: 16px; font-weight: 600; }
    .master-section-title p { margin: 5px 0 0; color: var(--master-muted); font-size: 13px; font-weight: 500; margin-bottom: 24px; }

    .master-form-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-top: 10px;
    }

    .master-field.full { grid-column: 1 / -1; }
    .master-field.two { grid-column: span 2; }

    .master-label { display: block; margin-bottom: 7px; color: #536079; font-size: 14px; font-weight: 600; }

    .master-input, .master-select, .master-textarea {
        width: 100%;
        border: 1px solid #d8e2ef;
        border-radius: 12px;
        background: #fff;
        color: var(--master-dark);
        font-size: 14px;
        font-weight: 500;
        outline: none;
        transition: .2s ease;
    }

    .master-input, .master-select { height: 44px; padding: 10px 14px; }
    .master-textarea { min-height: 82px; padding: 10px 12px; resize: vertical; }
    .master-input:focus, .master-select:focus, .master-textarea:focus { border-color: var(--master-primary); box-shadow: 0 0 0 3px rgba(79, 131, 241, .12); }

    .master-yellow { background: var(--master-yellow) !important; border-color: var(--master-yellow-border) !important; }
    .master-white-edit { background: #fff !important; }
    .master-output { background: #f8fafc !important; color: #334155; cursor: not-allowed; }
    .master-hint { font-size: 12px; color: var(--master-muted); font-weight: 500; margin-top: 6px; }

    .master-mini-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-top: 50px;
    }

    .master-mini-card, .master-summary-item {
        padding: 14px;
        border: 1px solid  #333333;
        border-radius: 14px;
        background:  #fff4c7 !important;
    }

    .master-mini-card span, .master-summary-item span {
        display: block;
        color: #7d8aa0;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 5px;
    }

    .master-mini-card strong, .master-summary-item strong {
        display: block;
        color: var(--master-dark);
        font-size: 18px;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .master-summary-item.regular { background: #f6f5f5 !important; border-color: #a7f3d0; }
    .master-summary-item.highlight { background: linear-gradient(135deg, #e8fff7, #f0fdf4); border-color: #a7f3d0; }
    .master-summary-item.highlight strong { color: #047857; font-size: 18px; }
    .master-summary-item.red-highlight strong { color: #df350b; font-size: 18px; }
    .master-summary-item.green-highlight strong { color: #047857; font-size: 18px; }

    .master-step-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid var(--master-border);
    }

    .master-step-actions > div { display: flex; gap: 10px; flex-wrap: wrap; }

    .master-summary-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(360px, .55fr);
        gap: 20px;
    }

    .master-summary-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .master-sheet-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid var(--master-border);
        border-radius: 16px;
    }

    .master-sheet { width: 100%; min-width: 1040px; border-collapse: collapse; }
    .master-sheet th, .master-sheet td { border-bottom: 1px solid var(--master-border); border-right: 1px solid var(--master-border); padding: 9px; vertical-align: middle; }
    .master-sheet th { background: #fbfdff; color: #7d8aa0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; text-align: left; }
    .master-sheet td:last-child, .master-sheet th:last-child { border-right: 0; }
    .master-sheet tr:last-child td { border-bottom: 0; }
    .master-sheet .master-input, .master-sheet .master-select { height: 38px; border-radius: 10px; font-size: 13px; }

    .master-actions-cell { display: flex; gap: 8px; align-items: center; }
    .master-icon-btn { width: 34px; height: 34px; border: 0; border-radius: 10px; background: #eef5ff; color: var(--master-primary); cursor: pointer; font-weight: 600; }
    .master-icon-btn.danger { background: #fff0f4; color: var(--master-red); }

    .master-pill { display: inline-flex; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: #eaf1ff; color: #3f7cf4; }

    .master-info-box {
        padding: 14px;
        border: 1px solid var(--master-border);
        border-radius: 14px;
        background: #fbfdff;
        color: #536079;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.75;
    }

    .master-print-header { display: none; }
</style>

<div class="master">

    <div class="master-card master-toolbar">
        <div class="master-toolbar-left">
            <span class="master-status" id="saveStatus">✓ Saved in browser</span>
            <span class="master-pill">No database storage</span>
        </div>
        <div class="master-toolbar-actions">
            <button type="button" class="master-btn master-btn-light" id="exportCsv">Export CSV</button>
            <button type="button" class="master-btn master-btn-light" id="printQuote">Print / Save PDF</button>
            <button type="button" class="master-btn master-btn-danger" id="resetCalculator">Reset Cache</button>
        </div>
    </div>

    <div class="master-print-header">
        <h2>MissPack Price Calculator</h2>
        <p id="printDate"></p>
    </div>

    <div class="master-card master-wizard">
        <div class="master-step-tabs">
            <button type="button" class="master-step-tab active" data-go-step="1">
                <span class="master-step-number">1</span>
                <span class="master-step-text"><strong>Basic Inputs</strong></span>
            </button>
            <button type="button" class="master-step-tab" data-go-step="2">
                <span class="master-step-number">2</span>
                <span class="master-step-text"><strong>Charges & Margin</strong></span>
            </button>
            <button type="button" class="master-step-tab" data-go-step="3">
                <span class="master-step-number">3</span>
                <span class="master-step-text"><strong>Summary</strong></span>
            </button>
        </div>

        <div class="master-step-content">
            <section class="master-step-panel active" data-step="1">

                <div class="master-form-grid">
                        <input class="master-input master-white-edit master-save" data-key="clientName" value="MissPack Client" hidden>
                        <input class="master-input master-white-edit master-save" data-key="productName" value="50ml Matte Bottle" hidden>
                        <input class="master-input master-white-edit master-save" data-key="quoteNo" value="QT-001" hidden>
                        <input class="master-input master-white-edit master-save" data-key="preparedBy" value="Hardik Bohra" hidden>
                        <input class="master-input master-white-edit master-save" type="date" data-key="quoteDate" hidden>
                        <select class="master-select master-yellow master-save" data-key="currency" hidden>
                            <option value="RMB">RMB</option>
                            <option value="USD">USD</option>
                            <option value="INR">INR</option>
                        </select>
                    <div>
                        <label class="master-label">Bank Payment %</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="10" min="0" max="100" data-key="bankPaymentPercent" value="40">
                        <div class="master-hint">Cash payment auto = 100 - Bank %</div>
                    </div>
                        <input class="master-input master-output" id="cashPaymentPercent" readonly hidden>
                        <input class="master-input master-output" type="number" step="1" data-key="gstPercent" value="18" readonly hidden>
                    <div>
                        <label class="master-label">Conversion Rate to INR</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="0.5" data-key="conversionRate" value="15">
                    </div>
                    <div>
                        <label class="master-label">Unit Price</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="0.1" data-key="basePrice" value="1.20">
                    </div>
                    <div>
                        <label class="master-label">Quantity</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="1" data-key="quantity" value="5000">
                    </div>
                    <textarea class="master-textarea master-white-edit master-save" data-key="quoteNotes" hidden>Matte finish and one color printing. Share photo/video and quote for multiple quantities.</textarea>
                </div>

                <div class="master-mini-summary">
                    <div class="master-mini-card"><span>Base INR / Unit</span><strong id="miniBaseInr">0.00</strong></div>
                    <div class="master-mini-card"><span>Bank Portion / Unit</span><strong id="miniBankPortion">0.00</strong></div>
                    <div class="master-mini-card"><span>Cash Portion / Unit</span><strong id="miniCashPortion">0.00</strong></div>
                    <strong id="miniCashPercent" hidden>0.00%</strong>
                </div>

                <div class="master-step-actions">
                    <span class="master-hint">Step 1 data is saved automatically in browser cache.</span>
                    <div><button type="button" class="master-btn master-btn-primary" data-next-step="2">Next: Charges</button></div>
                </div>
            </section>

            <section class="master-step-panel" data-step="2">

                <div class="master-form-grid">
                    <div>
                        <label class="master-label">Freight Amount</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="0.01" data-key="totalFreight" value="50000">
                    </div>
                    <div>
                        <label class="master-label">Freight / Unit</label>
                        <input class="master-input master-output" id="freightPerUnit" readonly>
                    </div>
                    <div>
                        <label class="master-label">BCD %</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="1" data-key="bcdPercent" value="10">
                        <div class="master-hint">Applies only on bank payment portion.</div>
                    </div>
                    <div>
                        <label class="master-label">SWS %</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="1" data-key="swsPercent" value="10">
                        <div class="master-hint">Calculated on BCD amount.</div>
                    </div>
                    <div>
                        <label class="master-label">Local Transportation Charges / Unit</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="1" data-key="localTransport" value="2">
                    </div>
                    <div>
                        <label class="master-label">Margin %</label>
                        <input class="master-input master-yellow master-calc-input" type="number" step="2" data-key="marginPercent" value="25">
                    </div>
                </div>

                <div class="master-mini-summary">
                    <div class="master-mini-card"><span>BCD / Unit</span><strong id="miniBcd">0.00</strong></div>
                    <div class="master-mini-card"><span>SWS / Unit</span><strong id="miniSws">0.00</strong></div>
                    <div class="master-mini-card"><span>Margin / Unit</span><strong id="miniMargin">0.00</strong></div>
                </div>

                <div class="master-step-actions">
                    <div><button type="button" class="master-btn master-btn-light" data-prev-step="1">Back</button></div>
                    <span class="master-hint">GST, BCD and SWS apply only on the bank payment portion. Cash payment portion is added without these duties/taxes.</span>
                    <div><button type="button" class="master-btn master-btn-primary" data-next-step="3">Next: Summary</button></div>
                </div>
            </section>

            <section class="master-step-panel" data-step="3">

                <div class="master-mini-summary" style="margin-top:0px; margin-bottom: 20px;">
                    <div class="master-mini-card"><span>Income</span><strong id="summaryIncome">0.00</strong></div>
                    <div class="master-mini-card"><span>Expense</span><strong id="summaryExpense">0.00</strong></div>
                    <div class="master-mini-card"><span>Profit</span><strong id="summaryProfit">0.00</strong></div>
                </div>

                <div class="master-summary-grid">
                    <div>
                        <div class="master-card" style="box-shadow:none;margin-bottom:20px;">
                            <div style="padding:18px;">
                                <div class="master-section-title">
                                    <div>
                                        <h3>Unit-wise Calculation</h3>
                                    </div>
                                </div>
                                <div class="master-summary-list">
                                    <div class="master-summary-item regular"><span>Base Price / Unit</span><strong id="summaryBaseInr">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Freight Amount / Unit</span><strong id="summaryFreight">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Bank Portion / Unit</span><strong id="summaryBank">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Cash Portion / Unit</span><strong id="summaryCash">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>BCD Amount / Unit</span><strong id="summaryBcd">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>SWS Amount / Unit</span><strong id="summarySws">0.00</strong></div>
                                    <strong id="summaryGst" hidden>0.00</strong>
                                    <div class="master-summary-item regular"><span>Local Transport / Unit</span><strong id="summaryLocalTransport">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Margin Amount / Unit</span><strong id="summaryMargin">0.00</strong></div>
                                    <div class="master-summary-item red-highlight"><span>Landing Cost / Unit</span><strong id="summaryLanding" style="font-size:22px !important;">0.00</strong></div>
                                    <div class="master-summary-item green-highlight"><span>Selling Price Excl. GST / Unit</span><strong id="summarySelling" style="font-size:22px !important;">0.00</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="master-card" style="box-shadow:none;margin-bottom:20px;">
                            <div style="padding:18px;">
                                <div class="master-section-title">
                                    <div>
                                        <h3>Payment Summary</h3>
                                    </div>
                                </div>
                                <div class="master-summary-list">
                                    <div class="master-summary-item regular red-highlight"><span>Pay to Vendor</span><strong id="payVendorTotal">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Vendor Bank Payment</span><strong id="payVendorBank">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Vendor Cash Payment</span><strong id="payVendorCash">0.00</strong></div>
                                    <div class="master-summary-item regular red-highlight"><span>Customs Excl. GST</span><strong id="payCustomsExGst">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Customs GST Amount</span><strong id="payCustomsGst">0.00</strong></div>
                                    <div class="master-summary-item regular red-highlight"><span>Total Freight</span><strong id="payFreightTotal">0.00</strong></div>
                                    <div class="master-summary-item regular red-highlight"><span>Local Transportation</span><strong id="payLocalTransportTotal">0.00</strong></div>
                                    <div class="master-summary-item regular green-highlight"><span>Customer Excl. GST</span><strong id="receiveCustomerExGst">0.00</strong></div>
                                    <div class="master-summary-item regular"><span>Customer GST Amount</span><strong id="receiveCustomerGst">0.00</strong></div>
                                    <div class="master-summary-item regular green-highlight"><span>Total Profit Amount</span><strong id="payMarginTotal">0.00</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="master-card" style="box-shadow:none;">
                    <div style="padding:18px;">
                        <div class="master-section-title">
                            <div>
                                <h3>Quantity Price Ladder</h3>
                            </div>
                            <button type="button" class="master-btn master-btn-soft" id="addLadderRow">+ Add Qty Row</button>
                        </div>
                        <div class="master-sheet-wrap">
                            <table class="master-sheet" id="ladderTable">
                                <thead>
                                    <tr>
                                        <th>Qty</th>
                                        <th>Base Price</th>
                                        <th>Total Freight</th>
                                        <th>Local Transport</th>
                                        <th>Landing Cost</th>
                                        <th>Selling Price</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="ladderBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="master-step-actions">
                    <div><button type="button" class="master-btn master-btn-light" data-prev-step="2">Back</button></div>
                    <div>
                        <button type="button" class="master-btn master-btn-light" id="exportCsvBottom">Export CSV</button>
                        <button type="button" class="master-btn master-btn-primary" id="printQuoteBottom">Print / Save PDF</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
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
</script>
@endsection
