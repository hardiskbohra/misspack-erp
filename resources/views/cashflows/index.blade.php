@extends('layouts.app')

@section('page-title', 'Cashflow Management')

@section('content')

    <div class="cf">

        <div class="master-stats">
            <div class="master-stat blue"><span class="icon">₹</span>
                <div>
                    <p class="master-stat-title">Total Credit</p>
                    <p class="master-stat-value">{{ number_format($stats['credit'], 2) }}</p>
                </div>
            </div>
            <div class="master-stat purple"><span class="icon">↘</span>
                <div>
                    <p class="master-stat-title">Total Debit</p>
                    <p class="master-stat-value">{{ number_format($stats['debit'], 2) }}</p>
                </div>
            </div>
            <div class="master-stat teal"><span class="icon">=</span>
                <div>
                    <p class="master-stat-title">Current A/c</p>
                    <p class="master-stat-value">{{ number_format($stats['current_balance'], 2) }}</p>
                </div>
            </div>
            <div class="master-stat blue"><span class="icon">💵</span>
                <div>
                    <p class="master-stat-title">Cash Balance</p>
                    <p class="master-stat-value">{{ number_format($stats['cash_balance'], 2) }}</p>
                </div>
            </div>
        </div>

        <div class="master-card">
            <form method="GET" action="{{ route('cashflows.index') }}">
                <div class="master-toolbar">
                    <div class="master-search"><span>⌕</span><input class="master-input" type="text" name="search"
                            value="{{ $search }}" placeholder="Search particular, invoice, bank reference, party..."></div>
                    <div class="master-actions-top">
                        <button type="button" class="master-btn master-btn-primary" id="openQuickCashflowModal">+ Quick
                            Entry</button>
                        <a href="{{ route('cashflows.create') }}" class="master-btn master-btn-soft">Detailed Form</a>
                        <button type="button" class="master-btn master-btn-light" id="openAccountModal">+ Account</button>
                        <a href="{{ route('cashflows.settings.index') }}" class="master-btn master-btn-soft">Settings</a>
                        <a href="{{ route('cashflows.reports') }}" class="master-btn master-btn-green">Reports</a>
                    </div>
                </div>
                <div class="master-filter-row">
                    <select class="master-select" name="account_id">
                        <option value="all">All Accounts</option>@foreach($accounts as $account)<option
                            value="{{ $account->id }}" @selected((string) $accountId === (string) $account->id)>
                        {{ $account->account_name }}</option>@endforeach
                    </select>
                    <select class="master-select" name="account_type" hidden>
                        <option value="all">All Account Types</option>@foreach($accountTypeOptions as $key => $label)<option
                        value="{{ $key }}" @selected($accountType === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <select class="master-select" name="transaction_type">
                        <option value="all">Credit + Debit</option>@foreach($transactionTypeOptions as $key => $label)<option
                        value="{{ $key }}" @selected($transactionType === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <select class="master-select" name="accounting_status">
                        <option value="all">All Accounting Status</option>@foreach($accountingStatusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($accountingStatus === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <input class="master-input" type="date" name="date_from" value="{{ $dateFrom }}">
                    <input class="master-input" type="date" name="date_to" value="{{ $dateTo }}">
                    <button class="master-btn master-btn-primary" type="submit">Filter</button><a class="master-btn master-btn-light"
                        href="{{ route('cashflows.index') }}">Reset</a>
                </div>
            </form>
        </div>

        <div class="master-card master-table-card">
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Particular</th>
                            <th>Account</th>
                            <th>Credit</th>
                            <th>Debit</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date?->format('d M') }}</td>
                                <td class="master-particular"><strong>{{ $entry->particular }}</strong><span
                                        class="master-sub">{{ $relatedPartyOptions[$entry->related_party_type] ?? 'Other' }}:
                                        {{ $entry->client?->company_name ?? $entry->vendor?->vendor_name ?? $entry->related_party_name ?? $entry->expense_head ?? '-' }}</span>
                                </td>
                                <td>{{ $entry->account?->account_name }}<span
                                        class="master-sub">{{ $entry->account?->typeLabel() }}</span></td>
                                <td class="master-amount-credit" style="color:green">
                                    {{ $entry->credit_amount > 0 ? ($entry->currency === 'INR' ? '₹' : $entry->currency) . ' ' . number_format((float) $entry->credit_amount, 2) : '-' }}
                                </td>
                                <td class="master-amount-debit" style="color:red">
                                    {{ $entry->debit_amount > 0 ? ($entry->currency === 'INR' ? '₹' : $entry->currency) . ' ' . number_format((float) $entry->debit_amount, 2) : '-' }}
                                </td>
                                <td>{{ $entry->balance !== null ? ($entry->currency === 'INR' ? '₹' : $entry->currency) . ' ' . number_format((float) $entry->balance, 2) : '-' }}
                                </td>
                                <td><span
                                        class="master-badge status-{{ str_replace('_', '-', $entry->accounting_status) }}">{{ $entry->statusLabel() }}</span>
                                </td>
                                <td>
                                    <div class="master-row-actions">

                                        <div class="master-dropdown">
                                            <button type="button" class="master-dropdown-toggle"><i class="fas fa-ellipsis-v"></i></button>
                                    
                                            <div class="master-dropdown-menu">
                                                <a href="{{ route('cashflows.show', $entry) }}"><i class="fas fa-eye"></i>View Entry</a>
                                                <a href="{{ route('cashflows.edit', $entry) }}"><i class="fas fa-pen"></i>Edit Entry</a>
                                                <form method="POST" action="{{ route('cashflows.destroy', $entry) }}" onsubmit="return confirm('Delete this cashflows entry?')">
                                    
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="danger"><i class="fas fa-trash"></i>Delete Entry</button>
                                                </form>
                                            </div>
                                        </div>
                                    
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">
                                    <div class="master-empty">No cashflow entries found. Create your first bank statement entry.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$entries" />
        </div>

        <div class="master-modal" id="quickCashflowModal" aria-hidden="true">
            <div class="master-modal-card" role="dialog">
                <form method="POST" action="{{ route('cashflows.quickStore') }}">@csrf
                    <div class="master-modal-header">
                        <div class="master-modal-heading"><span class="master-modal-icon">₹</span>
                            <div>
                                <h3 class="master-modal-title">Quick Cashflow Entry</h3>
                            </div>
                        </div><button type="button" class="master-modal-close" data-close-modal="quickCashflowModal">×</button>
                    </div>
                    <div class="master-modal-body">
                        <div class="master-modal-grid">
                            <div class="master-field"><label class="master-label">Date <span
                                        class="master-required">*</span></label><input class="master-input" type="date"
                                    name="entry_date" value="{{ now()->toDateString() }}" required></div>
                            <div class="master-field">
                                <label class="master-label">
                                    Transaction Type <span class="master-required">*</span>
                                </label>
                            
                                <div class="master-chip-group">
                                    @foreach($transactionTypeOptions as $key => $label)
                                        <label class="master-chip {{ $key === 'credit' ? 'credit-chip' : 'debit-chip' }}">
                                            <input
                                                type="radio"
                                                name="transaction_type"
                                                value="{{ $key }}"
                                                {{ old('transaction_type', 'debit') === $key ? 'checked' : '' }}
                                                required
                                            >
                                            <span>
                                                @if($key === 'credit')
                                                    <i class="fa-solid fa-arrow-down"></i>
                                                @else
                                                    <i class="fa-solid fa-arrow-up"></i>
                                                @endif
                            
                                                {{ $label }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="master-field full"><label class="master-label">Particular <span
                                        class="master-required">*</span></label><input class="master-input" name="particular"
                                    required placeholder="Bank statement particular"></div>
                            <div class="master-field"><label class="master-label">Amount <span
                                        class="master-required">*</span></label><input class="master-input" type="number"
                                    step="0.01" min="0" name="amount" required>
                            </div>
                            <div class="master-field"><label class="master-label">Account <span
                                        class="master-required">*</span></label><select class="master-select" name="account_id"
                                    required>
                                    <option value="">Select account</option>@foreach($accounts as $account)<option
                                    value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach
                                </select></div>
                            <div class="master-field"><label class="master-label">Related To</label><select class="master-select"
                                    name="related_party_type">@foreach($relatedPartyOptions as $key => $label)<option
                                    value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="master-field">
                                <label class="master-label">Project / Deal</label>
                                <select name="project_id" class="master-select">
                                    <option value="">No project mapping</option>
                                    @foreach(\App\Models\Project::query()->latest('id')->get() as $project)
                                        <option value="{{ $project->id }}">
                                            {{ $project->project_number }} - {{ $project->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="master-field"><label class="master-label">Category</label><select class="master-select"
                                    name="category_id">
                                    <option value="">Uncategorized</option>@forelse($categories as $category)<option
                                    value="{{ $category->id }}">{{ $category->name }} - {{ $category->typeLabel() }}
                                </option>@empty<option value="" disabled>No categories found - add from Cashflow
                                Settings</option>@endforelse
                                </select></div>
                            <div class="master-field"><label class="master-label">Party / Expense Name</label><input
                                    class="master-input" name="related_party_name"></div>
                        </div>
                    </div>
                    <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                            data-close-modal="quickCashflowModal">Cancel</button><button type="submit"
                            class="master-btn master-btn-primary">Create Entry</button></div>
                </form>
            </div>
        </div>

        <div class="master-modal" id="accountModal" aria-hidden="true">
            <div class="master-modal-card" style="max-width:580px;">
                <form method="POST" action="{{ route('cashflows.accounts.store') }}">@csrf
                    <div class="master-modal-header">
                        <div class="master-modal-heading"><span class="master-modal-icon">🏦</span>
                            <div>
                                <h3 class="master-modal-title">Add Cashflow Account</h3>
                                <p class="master-modal-subtitle">Current, saving or cash account</p>
                            </div>
                        </div><button type="button" class="master-modal-close" data-close-modal="accountModal">×</button>
                    </div>
                    <div class="master-modal-body">
                        <div class="master-modal-grid">
                            <div class="master-field"><label class="master-label">Account Name</label><input class="master-input"
                                    name="account_name" required placeholder="Hardik HDFC"></div>
                            <div class="master-field"><label class="master-label">Account Type</label><select class="master-select"
                                    name="account_type">@foreach($accountTypeOptions as $key => $label)<option
                                    value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="master-field"><label class="master-label">Bank Name</label><input class="master-input"
                                    name="bank_name"></div>
                            <div class="master-field"><label class="master-label">Opening Balance</label><input class="master-input"
                                    type="number" step="0.01" name="opening_balance" value="0"></div>
                            <div class="master-field"><label class="master-label">Currency</label><select class="master-select"
                                    name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}">
                                    {{ $label }}</option>@endforeach</select></div>
                            <div class="master-field"><label class="master-label">Account Number</label><input class="master-input"
                                    name="account_number"></div>
                        </div>
                    </div>
                    <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                            data-close-modal="accountModal">Cancel</button><button type="submit"
                            class="master-btn master-btn-primary">Create Account</button></div>
                </form>
            </div>
        </div>

        <div class="master-modal" id="deleteCashflowModal" aria-hidden="true">
            <div class="master-modal-card" style="max-width:440px;">
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon"
                            style="background:#fff0f4;color:var(--master-red);">🗑</span>
                        <div>
                            <h3 class="master-modal-title">Delete Entry</h3>
                            <p class="master-modal-subtitle">This action cannot be undone</p>
                        </div>
                    </div><button type="button" class="master-modal-close" data-close-modal="deleteCashflowModal">×</button>
                </div>
                <div class="master-modal-body">
                    <p id="deleteCashflowDesc" style="margin:0;font-weight:700;color:#536079;">Are you sure?</p>
                </div>
                <form method="POST" id="deleteCashflowForm">@csrf @method('DELETE')
                    <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                            data-close-modal="deleteCashflowModal">Cancel</button><button type="submit"
                            class="master-btn master-btn-danger">Delete Entry</button></div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function openModal(id) { const modal = document.getElementById(id); modal?.classList.add('open'); modal?.setAttribute('aria-hidden', 'false'); document.body.classList.add('master-modal-open'); }
            function closeModal(id) { const modal = document.getElementById(id); modal?.classList.remove('open'); modal?.setAttribute('aria-hidden', 'true'); document.body.classList.remove('master-modal-open'); }
            document.getElementById('openQuickCashflowModal')?.addEventListener('click', () => openModal('quickCashflowModal'));
            document.getElementById('openAccountModal')?.addEventListener('click', () => openModal('accountModal'));
            document.querySelectorAll('[data-close-modal]').forEach(btn => btn.addEventListener('click', () => closeModal(btn.dataset.closeModal)));
            document.querySelectorAll('.master-modal').forEach(modal => modal.addEventListener('click', e => { if (e.target === modal) closeModal(modal.id); }));
            document.querySelectorAll('.master-delete-btn').forEach(btn => btn.addEventListener('click', function () { document.getElementById('deleteCashflowDesc').textContent = 'Are you sure you want to delete "' + this.dataset.name + '"?'; document.getElementById('deleteCashflowForm').action = this.dataset.deleteUrl; openModal('deleteCashflowModal'); }));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.master-modal.open').forEach(m => closeModal(m.id)); });
        });
    </script>
@endsection