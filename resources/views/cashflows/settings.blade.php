@extends('layouts.app')

@section('page-title', 'Cashflow Settings')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/cashflows.css') }}">
@endpush
<div class="cf-setting">
    <div class="cf-card cf-header">
        <div><h1>Cashflow Settings</h1><p style="margin:5px 0 0;color:#687386;font-weight:500;">Manage master data used by cashflow entries and reports.</p></div>
        <div class="cf-actions"><a href="{{ route('cashflows.index') }}" class="master-btn master-btn-light">Back to Cashflow</a><a href="{{ route('cashflows.reports') }}" class="master-btn master-btn-soft">Reports</a></div>
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
                            <div><label class="master-label">Account Name</label><input class="master-input" name="account_name" required placeholder="Hardik HDFC"></div>
                            <div><label class="master-label">Account Type</label><select class="master-select" name="account_type">@foreach($accountTypeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Bank Name</label><input class="master-input" name="bank_name" placeholder="HDFC / IDFC"></div>
                            <div><label class="master-label">Currency</label><select class="master-select" name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Opening Balance</label><input class="master-input" type="number" step="0.01" name="opening_balance" value="0"></div>
                            <div><label class="master-label">Account Number</label><input class="master-input" name="account_number"></div>
                            <div><label class="master-label">IFSC Code</label><input class="master-input" name="ifsc_code"></div>
                            <div><label class="master-label">Branch</label><input class="master-input" name="branch"><textarea class="master-textarea" name="notes" hidden></textarea></div>
                        </div>
                        <button class="master-btn master-btn-primary" type="submit">Create Account</button>
                    </form>
                </div>

                <div class="cf-list">
                    @forelse($accounts as $account)
                        <form method="POST" action="{{ route('cashflows.settings.accounts.update', $account) }}" class="cf-row">
                            @csrf
                            <div><label class="master-label">Account Name</label><input class="master-input" name="account_name" value="{{ $account->account_name }}" required></div>
                            <div><label class="master-label">Type</label><select class="master-select" name="account_type">@foreach($accountTypeOptions as $key => $label)<option value="{{ $key }}" @selected($account->account_type === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Bank</label><input class="master-input" name="bank_name" value="{{ $account->bank_name }}"></div>
                            <div><label class="master-label">Currency</label><select class="master-select" name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}" @selected($account->currency === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Opening Balance</label><input class="master-input" type="number" step="0.01" name="opening_balance" value="{{ $account->opening_balance }}"></div>
                            <div><label class="master-label">Current Balance</label><div class="current-balance">{{ $account->currency }} {{ number_format((float) $account->current_balance, 2) }}</div></div>
                            <div class="cf-row-actions">
                                <input name="is_active" value="1" @checked($account->is_active) hidden>
                                <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                <button class="master-btn master-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('cashflows.settings.accounts.destroy', $account) }}" onclick="return confirm('Delete this account?')">Delete</button>
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
                            <div><label class="master-label">Category Name</label><input class="master-input" name="name" required placeholder="Sales Receipt / Office Expense"></div>
                            <div><label class="master-label">Category Type</label><select class="master-select" name="type">@foreach($categoryTypeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="#4f83f1"></div>
                            <div style="display:flex;align-items:end;"><label class="master-check"><input name="is_active" value="1" checked> Active</label></div>
                        </div>
                        <button class="master-btn master-btn-primary" type="submit">Create Category</button>
                    </form>
                </div>

                <div class="cf-list">
                    @forelse($categories as $category)
                        <form method="POST" action="{{ route('cashflows.settings.categories.update', $category) }}" class="cf-row category">
                            @csrf
                            <div><label class="master-label">Category Name</label><input class="master-input" name="name" value="{{ $category->name }}" required></div>
                            <div><label class="master-label">Type</label><select class="master-select" name="type">@foreach($categoryTypeOptions as $key => $label)<option value="{{ $key }}" @selected($category->type === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="{{ $category->color ?: '#4f83f1' }}"></div>
                            <div><label class="master-label">Entries</label><div class="current-balance">{{ $category->entries_count }}</div></div>
                            <div><label class="master-check"><input name="is_active" value="1" @checked($category->is_active)> Active</label></div>
                            <div class="cf-row-actions">
                                <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                <button class="master-btn master-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('cashflows.settings.categories.destroy', $category) }}" onclick="return confirm('Delete this category? Existing entries will become uncategorized.')">Delete</button>
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
                            <div><label class="master-label">Key</label><input class="master-input" name="key" required placeholder="{{ $masterGroup === 'currency' ? 'INR' : 'new_option_key' }}"></div>
                            <div><label class="master-label">Label</label><input class="master-input" name="label" required placeholder="Display label"></div>
                            <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="#4f83f1"></div>
                            <div><label class="master-label">Sort Order</label><input class="master-input" type="number" min="0" name="sort_order" value="10"><input name="is_active" value="1" checked hidden></div>
                        </div>
                        <button class="master-btn master-btn-primary" type="submit">Create Option</button>
                    </form>
                </div>

                <div class="cf-list">
                    @forelse($masters as $master)
                        <form method="POST" action="{{ route('cashflows.settings.masters.update', $master) }}" class="cf-row master">
                            @csrf
                            <input type="hidden" name="group" value="{{ $master->group }}">
                            <label class="current-balance" hidden>{{ $master->groupLabel() }}</label>
                            <div><label class="master-label">Key</label><input class="master-input" name="key" value="{{ $master->key }}" required></div>
                            <div><label class="master-label">Label</label><input class="master-input" name="label" value="{{ $master->label }}" required></div>
                            <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="{{ $master->color ?: '#4f83f1' }}"></div>
                            <div><label class="master-label">Sort</label><input class="master-input" type="number" min="0" name="sort_order" value="{{ $master->sort_order }}"></div>
                            <div><label class="master-check"><input name="is_active" value="1" @checked($master->is_active)> Active</label></div>
                            <div class="cf-row-actions">
                                <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                <button class="master-btn master-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('cashflows.settings.masters.destroy', $master) }}" onclick="return confirm('Delete this master option?')">Delete</button>
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
