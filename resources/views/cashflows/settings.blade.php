@extends('layouts.app')

@section('page-title', 'Cashflow Settings')

@section('content')
<style>
    :root {
        --cf-primary: #4f83f1;
        --cf-primary-2: #6366f1;
        --cf-info: #159ff7;
        --cf-teal: #12cbb7;
        --cf-purple: #8b5cf6;
        --cf-orange: #f59e0b;
        --cf-red: #ef4770;
        --cf-green: #10b981;
        --cf-dark: #17233b;
        --cf-muted: #687386;
        --cf-border: #dfe7f3;
        --cf-bg: #eef3ff;
        --cf-soft: #edf5ff;
        --cf-white: #fff;
        --cf-shadow: 0 14px 35px rgba(25, 42, 70, .08);
    }

    .cf-settings,
    .cf-settings * {
        box-sizing: border-box
    }

    .cf-settings {
        background: var(--cf-bg);
        min-height: calc(100vh - 70px);
        padding: 28px;
        color: var(--cf-dark);
        font-size: 14px;
        line-height: 1.45
    }

    .cf-card {
        background: #fff;
        border: 1px solid var(--cf-border);
        border-radius: 18px;
        box-shadow: var(--cf-shadow)
    }

    .cf-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 22px 28px;
        margin-bottom: 24px
    }

    .cf-header h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 900
    }

    .cf-header p {
        margin: 5px 0 0;
        color: var(--cf-muted);
        font-weight: 700
    }

    .cf-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap
    }

    .cf-btn {
        min-height: 40px;
        border: 0;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap
    }

    .cf-btn-primary {
        background: linear-gradient(135deg, var(--cf-primary), var(--cf-primary-2));
        color: #fff
    }

    .cf-btn-soft {
        background: var(--cf-soft);
        color: var(--cf-primary)
    }

    .cf-btn-light {
        background: #f3f6fb;
        color: var(--cf-dark)
    }

    .cf-btn-danger {
        background: #fff0f4;
        color: #e11d48
    }

    .cf-btn-green {
        background: #e8fff7;
        color: #0e9f6e
    }

    .cf-alert {
        padding: 14px 16px;
        margin-bottom: 18px;
        border-radius: 14px;
        font-weight: 800
    }

    .cf-alert-success {
        color: #047857;
        background: #e8fff7;
        border: 1px solid #a7f3d0
    }

    .cf-alert-error {
        color: #be123c;
        background: #fff0f4;
        border: 1px solid #fecdd3
    }

    .cf-tabs {
        display: flex;
        gap: 0;
        border-bottom: 1px solid var(--cf-border);
        overflow-x: auto
    }

    .cf-tab {
        padding: 16px 24px;
        color: var(--cf-dark);
        text-decoration: none;
        font-weight: 500;
        border-bottom: 2px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap
    }

    .cf-tab.active {
        color: var(--cf-primary);
        border-bottom-color: var(--cf-primary);
        background: #fbfdff
    }

    .cf-panel {
        padding: 22px
    }

    .cf-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 0 0 16px
    }

    .cf-section-title h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 900
    }

    .cf-section-title p {
        margin: 4px 0 0;
        color: var(--cf-muted);
        font-weight: 600
    }

    .cf-form-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 18px
    }

    .cf-form-grid.two {
        grid-template-columns: repeat(2, minmax(0, 1fr))
    }

    .cf-field.full {
        grid-column: 1/-1
    }

    .cf-label {
        display: block;
        margin-bottom: 7px;
        color: #536079;
        font-size: 12px;
        font-weight: 900
    }

    .cf-input,
    .cf-select,
    .cf-textarea {
        width: 100%;
        border: 1px solid #d8e2ef;
        border-radius: 12px;
        background: #fff;
        color: var(--cf-dark);
        font-size: 14px;
        font-weight: 500;
        outline: none
    }

    .cf-input,
    .cf-select {
        height: 42px;
        padding: 9px 12px
    }

    .cf-textarea {
        min-height: 74px;
        padding: 10px 12px;
        resize: vertical
    }

    .cf-input:focus,
    .cf-select:focus,
    .cf-textarea:focus {
        border-color: var(--cf-primary);
        box-shadow: 0 0 0 3px rgba(79, 131, 241, .12)
    }

    .cf-help {
        color: var(--cf-muted);
        font-size: 12px;
        font-weight: 700
    }

    .cf-sub-card {
        border: 2px solid var(--cf-border);
        border-radius: 16px;
        padding: 18px;
        background: #fbfdff;
        margin-bottom: 22px
    }

    .cf-sub-card h3 {
        margin: 0 0 14px;
        font-size: 16px;
        font-weight: 900
    }

    .cf-list {
        display: grid;
        gap: 12px
    }

    .cf-row {
        display: grid;
        grid-template-columns: 1.4fr 1fr 1fr .8fr .9fr .8fr auto;
        gap: 10px;
        align-items: end;
        padding: 14px;
        border: 1px solid var(--cf-border);
        border-radius: 14px;
        background: #fff
    }

    .cf-row.category {
        grid-template-columns: 1.5fr 1fr .7fr .7fr .7fr auto
    }

    .cf-row.master {
        grid-template-columns: 1fr 1fr 1fr .6fr .7fr .7fr auto
    }

    .cf-row .current-balance {
        font-size: 15px;
        font-weight: 900;
        color: var(--cf-green);
        padding: 10px 0
    }

    .cf-check {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 900;
        color: #536079;
        min-height: 42px
    }

    .cf-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 10px 10px;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase
    }

    .cf-badge.active {
        background: #e8fff7;
        color: #0e9f6e
    }

    .cf-badge.inactive {
        background: #f3f6fb;
        color: #536079
    }

    .cf-color-dot {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: inline-block;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px var(--cf-border)
    }

    .cf-master-filter {
        display: flex;
        gap: 12px;
        align-items: end;
        flex-wrap: wrap;
        margin-bottom: 18px
    }

    .cf-master-filter>div {
        min-width: 240px;
        flex: 0 1 280px
    }

    @media(max-width:1280px) {
        .cf-settings {
            padding: 22px 18px
        }

        .cf-form-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .cf-row,
        .cf-row.category,
        .cf-row.master {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .cf-row-actions {
            grid-column: 1/-1;
            display: flex;
            justify-content: flex-end;
            gap: 8px
        }
    }

    @media(max-width:700px) {
        .cf-settings {
            padding: 14px
        }

        .cf-header {
            align-items: flex-start;
            flex-direction: column;
            padding: 18px
        }

        .cf-actions,
        .cf-actions .cf-btn {
            width: 100%
        }

        .cf-panel {
            padding: 16px
        }

        .cf-form-grid,
        .cf-form-grid.two,
        .cf-row,
        .cf-row.category,
        .cf-row.master {
            grid-template-columns: 1fr
        }

        .cf-row-actions {
            justify-content: stretch;
            flex-direction: column
        }

        .cf-row-actions .cf-btn {
            width: 100%
        }

        .cf-tabs {
            padding: 0 4px
        }

        .cf-tab {
            padding: 14px 16px
        }
    }
</style>

<div class="cf-setting">
    <div class="cf-card cf-header">
        <div><h1>Cashflow Settings</h1><p style="margin:5px 0 0;color:#687386;font-weight:500;">Manage master data used by cashflow entries and reports.</p></div>
        <div class="cf-actions"><a href="{{ route('cashflows.index') }}" class="cf-btn cf-btn-light">Back to Cashflow</a><a href="{{ route('cashflows.reports') }}" class="cf-btn cf-btn-soft">Reports</a></div>
    </div>

    @if(session('success'))<div class="cf-alert cf-alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="cf-alert cf-alert-error">{{ $errors->first() }}</div>@endif

    <div class="cf-card">
        @php($masterIcons = [
    'currency' => '',
    'accounting_status' => '',
    'payment_mode' => '',
    'related_party_type' => '',
    'account_type' => '',
    'category_type' => '',
])
        <div class="cf-tabs">
            <a class="cf-tab {{ $activeTab === 'accounts' ? 'active' : '' }}" href="{{ route('cashflows.settings.index', ['tab' => 'accounts']) }}">Accounts</a>
            <a class="cf-tab {{ $activeTab === 'categories' ? 'active' : '' }}" href="{{ route('cashflows.settings.index', ['tab' => 'categories']) }}">Categories</a>
            @foreach($groupOptions as $groupKey => $groupLabel)
                <a class="cf-tab {{ $activeTab === $groupKey ? 'active' : '' }}" href="{{ route('cashflows.settings.index', ['tab' => $groupKey]) }}">
                    {{ $masterIcons[$groupKey] ?? '⚙' }} {{ $groupLabel }}
                </a>
            @endforeach
        </div>

        <div class="cf-panel">
            @if($activeTab === 'accounts')
                <div class="cf-sub-card">
                    <h3>Add New Account</h3>
                    <form method="POST" action="{{ route('cashflows.settings.accounts.store') }}">
                        @csrf
                        <div class="cf-form-grid">
                            <div><label class="cf-label">Account Name</label><input class="cf-input" name="account_name" required placeholder="Hardik HDFC"></div>
                            <div><label class="cf-label">Account Type</label><select class="cf-select" name="account_type">@foreach($accountTypeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="cf-label">Bank Name</label><input class="cf-input" name="bank_name" placeholder="HDFC / IDFC"></div>
                            <div><label class="cf-label">Currency</label><select class="cf-select" name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="cf-label">Opening Balance</label><input class="cf-input" type="number" step="0.01" name="opening_balance" value="0"></div>
                            <div><label class="cf-label">Account Number</label><input class="cf-input" name="account_number"></div>
                            <div><label class="cf-label">IFSC Code</label><input class="cf-input" name="ifsc_code"></div>
                            <div><label class="cf-label">Branch</label><input class="cf-input" name="branch"><textarea class="cf-textarea" name="notes" hidden></textarea></div>
                        </div>
                        <button class="cf-btn cf-btn-primary" type="submit">Create Account</button>
                    </form>
                </div>

                <div class="cf-list">
                    @forelse($accounts as $account)
                        <form method="POST" action="{{ route('cashflows.settings.accounts.update', $account) }}" class="cf-row">
                            @csrf
                            <div><label class="cf-label">Account Name</label><input class="cf-input" name="account_name" value="{{ $account->account_name }}" required></div>
                            <div><label class="cf-label">Type</label><select class="cf-select" name="account_type">@foreach($accountTypeOptions as $key => $label)<option value="{{ $key }}" @selected($account->account_type === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="cf-label">Bank</label><input class="cf-input" name="bank_name" value="{{ $account->bank_name }}"></div>
                            <div><label class="cf-label">Currency</label><select class="cf-select" name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}" @selected($account->currency === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="cf-label">Opening Balance</label><input class="cf-input" type="number" step="0.01" name="opening_balance" value="{{ $account->opening_balance }}"></div>
                            <div><label class="cf-label">Current Balance</label><div class="current-balance">{{ $account->currency }} {{ number_format((float) $account->current_balance, 2) }}</div></div>
                            <div class="cf-row-actions">
                                <input type="checkbox" name="is_active" value="1" @checked($account->is_active) hidden>
                                <button class="cf-btn cf-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                <button class="cf-btn cf-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('cashflows.settings.accounts.destroy', $account) }}" onclick="return confirm('Delete this account?')">Delete</button>
                            </div>
                        </form>
                    @empty
                        <div class="cf-sub-card">No accounts found.</div>
                    @endforelse
                </div>
            @elseif($activeTab === 'categories')
                <div class="cf-sub-card">
                    <h3>Add New Category</h3>
                    <form method="POST" action="{{ route('cashflows.settings.categories.store') }}">
                        @csrf
                        <div class="cf-form-grid">
                            <div><label class="cf-label">Category Name</label><input class="cf-input" name="name" required placeholder="Sales Receipt / Office Expense"></div>
                            <div><label class="cf-label">Category Type</label><select class="cf-select" name="type">@foreach($categoryTypeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="cf-label">Color</label><input class="cf-input" type="color" name="color" value="#4f83f1"></div>
                            <div style="display:flex;align-items:end;"><label class="cf-check"><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
                        </div>
                        <button class="cf-btn cf-btn-primary" type="submit">Create Category</button>
                    </form>
                </div>

                <div class="cf-list">
                    @forelse($categories as $category)
                        <form method="POST" action="{{ route('cashflows.settings.categories.update', $category) }}" class="cf-row category">
                            @csrf
                            <div><label class="cf-label">Category Name</label><input class="cf-input" name="name" value="{{ $category->name }}" required></div>
                            <div><label class="cf-label">Type</label><select class="cf-select" name="type">@foreach($categoryTypeOptions as $key => $label)<option value="{{ $key }}" @selected($category->type === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="cf-label">Color</label><input class="cf-input" type="color" name="color" value="{{ $category->color ?: '#4f83f1' }}"></div>
                            <div><label class="cf-label">Entries</label><div class="current-balance">{{ $category->entries_count }}</div></div>
                            <div><label class="cf-check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active</label></div>
                            <div class="cf-row-actions">
                                <button class="cf-btn cf-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                <button class="cf-btn cf-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('cashflows.settings.categories.destroy', $category) }}" onclick="return confirm('Delete this category? Existing entries will become uncategorized.')">Delete</button>
                            </div>
                        </form>
                    @empty
                        <div class="cf-sub-card">No categories found.</div>
                    @endforelse
                </div>
            @else
                @php($currentMasterLabel = $groupOptions[$masterGroup] ?? 'Master Options')

                <div class="cf-sub-card">
                    <h3>Add {{ $currentMasterLabel }} Option</h3>
                    <form method="POST" action="{{ route('cashflows.settings.masters.store') }}">
                        @csrf
                        <input type="hidden" name="group" value="{{ $masterGroup }}">
                        <div class="cf-form-grid">
                            <div><label class="cf-label">Key</label><input class="cf-input" name="key" required placeholder="{{ $masterGroup === 'currency' ? 'INR' : 'new_option_key' }}"></div>
                            <div><label class="cf-label">Label</label><input class="cf-input" name="label" required placeholder="Display label"></div>
                            <div><label class="cf-label">Color</label><input class="cf-input" type="color" name="color" value="#4f83f1"></div>
                            <div><label class="cf-label">Sort Order</label><input class="cf-input" type="number" min="0" name="sort_order" value="10"><input type="checkbox" name="is_active" value="1" checked hidden></div>
                        </div>
                        <button class="cf-btn cf-btn-primary" type="submit">Create Option</button>
                    </form>
                </div>

                <div class="cf-list">
                    @forelse($masters as $master)
                        <form method="POST" action="{{ route('cashflows.settings.masters.update', $master) }}" class="cf-row master">
                            @csrf
                            <input type="hidden" name="group" value="{{ $master->group }}">
                            <label class="current-balance" hidden>{{ $master->groupLabel() }}</label>
                            <div><label class="cf-label">Key</label><input class="cf-input" name="key" value="{{ $master->key }}" required></div>
                            <div><label class="cf-label">Label</label><input class="cf-input" name="label" value="{{ $master->label }}" required></div>
                            <div><label class="cf-label">Color</label><input class="cf-input" type="color" name="color" value="{{ $master->color ?: '#4f83f1' }}"></div>
                            <div><label class="cf-label">Sort</label><input class="cf-input" type="number" min="0" name="sort_order" value="{{ $master->sort_order }}"></div>
                            <div><label class="cf-check"><input type="checkbox" name="is_active" value="1" @checked($master->is_active)> Active</label></div>
                            <div class="cf-row-actions">
                                <button class="cf-btn cf-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                <button class="cf-btn cf-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('cashflows.settings.masters.destroy', $master) }}" onclick="return confirm('Delete this master option?')">Delete</button>
                            </div>
                        </form>
                    @empty
                        <div class="cf-sub-card">No master options found for {{ $currentMasterLabel }}.</div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
