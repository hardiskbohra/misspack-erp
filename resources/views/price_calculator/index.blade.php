@extends('layouts.app')

@section('page-title', 'Price Calculator')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/price-calculator.css') }}">
@endpush
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


@push('scripts')
    <script src="{{ asset('assets/js/price-calculator.js') }}"></script>
@endpush
@endsection
