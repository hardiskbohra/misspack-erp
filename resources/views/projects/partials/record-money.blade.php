<section class="master-tab-panel" id="project-panel-payments" role="tabpanel" aria-labelledby="project-tab-payments">
    <div class="project-blocks">
        <section class="master-card master-card--flat">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Payment entries</h2>
                    <p class="master-sub">{{ $project->payments->count() }}
                    {{ \Illuminate\Support\Str::plural('entry', $project->payments->count()) }} recorded on this project</p>
                </div>
                <div class="master-section-meta">
                    <button type="button" class="master-btn master-btn-primary" id="openAddPaymentModal">
                        <i class="fas fa-plus" aria-hidden="true"></i> Add payment</button>
                </div>
            </div>
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Type</th>
                            <th scope="col">Reference</th>
                            <th scope="col" class="is-num">Amount</th>
                            <th scope="col">Mode</th>
                            <th scope="col">Visibility</th>
                            <th scope="col" class="project-col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->payments as $payment)
                            <tr>
                                <td data-label="Date">
                                    {{ optional($payment->payment_date)->format('d M Y') ?: '—' }}
                                    <span class="project-fact-note">{{ $payment->transaction_type === 'inward' ? 'Inward' : 'Expense' }}</span>
                                </td>
                                <td data-label="Type">{{ $payment->category ?: '—' }}</td>
                                <td data-label="Reference">{{ $payment->reference_number ?: '—' }}</td>
                                <td data-label="Amount" class="is-num">
                                    <strong class="{{ $payment->transaction_type === 'inward' ? 'project-money-in' : 'project-money-out' }}">
                                    {{ \App\Helpers\CommonHelper::amount($payment->amount, $payment->currency) }}</strong>
                                </td>
                                <td data-label="Mode">{{ $payment->payment_mode ? \Illuminate\Support\Str::title($payment->payment_mode) : '—' }}</td>
                                <td data-label="Visibility">
                                    <span class="master-badge {{ $payment->is_public ? 'status-completed' : 'status-draft' }}">
                                    {{ $payment->is_public ? 'Public' : 'Internal' }}</span>
                                </td>
                                <td data-label="Action" class="project-col-actions">
                                    <div class="master-row-actions">
                                        <button type="button" class="master-icon-btn editPaymentBtn"
                                            aria-label="Edit payment {{ $payment->reference_number }}"
                                            data-payment='@json($payment)'><i class="fas fa-pen" aria-hidden="true"></i></button>
                                        <form method="POST" action="{{ route('projects.payments.destroy', $payment) }}"
                                            data-confirm="Delete this payment entry?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="master-icon-btn danger" aria-label="Delete payment {{ $payment->reference_number }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                                        <p>No payment entry on this project yet. Record the advance here and the client portal
                                        shows it with the rest of the money.</p>
                                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                                            data-modal-open="addPaymentModal"><i class="fas fa-plus" aria-hidden="true"></i> Add payment</button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="master-card master-card--flat">
            <div class="master-section-head">
                <div>
                    <h2 class="master-section-title">Ledger entries</h2>
                    <p class="master-sub">Rows in the cashflow ledger linked to this project</p>
                </div>
                <div class="master-section-meta">
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('cashflows.index') }}">
                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> Open ledger
                    </a>
                </div>
            </div>
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Particular</th>
                            <th scope="col">Bank reference</th>
                            <th scope="col" class="is-num">Amount</th>
                            <th scope="col">Mode</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($project->cashflowEntries as $entry)
                            <tr>
                                <td data-label="Date">
                                    {{ optional($entry->entry_date)->format('d M Y') ?: '—' }}
                                    <span class="project-fact-note">{{ $entry->transaction_type === 'credit' ? 'Inward' : 'Expense' }}</span>
                                </td>
                                <td data-label="Particular">{{ $entry->particular ?: '—' }}</td>
                                <td data-label="Bank reference">{{ $entry->bank_reference_number ?: '—' }}</td>
                                <td data-label="Amount" class="is-num">
                                    <strong class="{{ $entry->transaction_type === 'credit' ? 'project-money-in' : 'project-money-out' }}">
                                    {{ \App\Helpers\CommonHelper::amount($entry->transaction_type === 'credit' ? $entry->credit_amount : $entry->debit_amount, $entry->currency ?? $project->currency) }}</strong>
                                </td>
                                <td data-label="Mode">{{ $entry->payment_mode ? \Illuminate\Support\Str::title($entry->payment_mode) : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="master-empty-state">
                                        <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                                        <p>No ledger row is linked to this project yet. Ledger entries are written in the cashflow
                                        module with this project selected.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>


<!--Add Payment-->
<div class="master-modal" id="addPaymentModal" aria-hidden="true">
    <div class="master-modal-card">
        <form method="POST" action="{{ route('projects.payments.store', $project) }}">
            @csrf
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <div>
                        <h3 class="master-modal-title">Add Payment</h3>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeAddPaymentModal" data-close-modal>×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label">Type</label>
                        <select class="master-select" name="transaction_type">
                            @foreach ($paymentTypeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Date</label>
                        <input class="master-input" type="date" name="payment_date"
                            value="{{ now()->toDateString() }}" required></div>
                    <div class="master-field">
                        <label class="master-label">Amount</label>
                        <input class="master-input" type="number" step="0.01"
                            min="0.01" name="amount" required></div>
                    <div class="master-field">
                        <label class="master-label">Currency</label>
                        <select class="master-select" name="currency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ $project->currency === $key ? 'selected' : '' }}>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Mode</label>
                        <select class="master-select" name="payment_mode">
                            <option value="">Select mode</option>
                            @foreach ($paymentModeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Reference No.</label>
                        <input class="master-input" type="text"
                            name="reference_number"></div>
                    <div class="master-field">
                        <label class="master-label">Category</label>
                        <input class="master-input" type="text" name="category"
                            placeholder="Advance / Vendor / Freight"></div>
                    <div class="master-field">
                        <div class="master-toggle-group" >
                            <label class="master-label">Public for Client</label><br>

                            <label class="master-switch">
                                <input type="checkbox" name="is_public" value="1">
                                <span class="master-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="master-field full">
                        <label class="master-label">Notes</label>
                        <textarea class="master-textarea" name="notes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelAddPaymentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Add Payment</button>
            </div>
        </form>
    </div>
</div>

<!--Update Payment-->
<div class="master-modal" id="editPaymentModal">
    <div class="master-modal-card">
        <form id="editPaymentForm" method="POST"
            data-update-url="{{ route('projects.payments.update', ['projectPayment' => '__ID__']) }}">
            @csrf
            @method('PUT')
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <div>
                        <h3 class="master-modal-title">Update Payment</h3>
                    </div>
                </div>
                <button type="button" class="master-modal-close" id="closeEditPaymentModal" data-close-modal>×</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label">Type</label>
                        <select class="master-select" name="transaction_type">
                            @foreach ($paymentTypeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Date</label>
                        <input class="master-input" type="date" name="payment_date" required></div>
                    <div class="master-field">
                        <label class="master-label">Amount</label>
                        <input class="master-input" type="number" step="0.01"
                            min="0.01" name="amount" required></div>
                    <div class="master-field">
                        <label class="master-label">Currency</label>
                        <select class="master-select" name="currency">
                            @foreach ($currencyOptions as $key => $label)
                                <option value="{{ $key }}"
                                    {{ $project->currency === $key ? 'selected' : '' }}>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Mode</label>
                        <select class="master-select" name="payment_mode">
                            <option value="">Select mode</option>
                            @foreach ($paymentModeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Reference No.</label>
                        <input class="master-input" type="text"
                            name="reference_number"></div>
                    <div class="master-field">
                        <label class="master-label">Category</label>
                        <input class="master-input" type="text" name="category"
                            placeholder="Advance / Vendor / Freight"></div>
                    <div class="master-field">
                        <div class="master-toggle-group" >
                            <label class="master-label">Public for Client</label><br>

                            <label class="master-switch">
                                <input type="checkbox" name="is_public" value="1">
                                <span class="master-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="master-field full">
                        <label class="master-label">Notes</label>
                        <textarea class="master-textarea" name="notes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" id="cancelEditPaymentModal" data-close-modal>Cancel</button>
                <button class="master-btn master-btn-primary" type="submit"> Save Payment</button>
            </div>
        </form>
    </div>
</div>
