@extends('layouts.app')

@section('title', 'Settings · Cashflow')
@section('page-title', 'Settings · Cashflow')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/cashflows.css') }}">
        <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
    @endpush
    <div class="set master-list">

        @include('settings.partials.nav', ['current' => 'cashflow'])

        <div class="set-area">

            @if ($errors->any())
                <div class="master-info-box is-danger" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="master-card master-card--flat set-head">
                <span class="set-head-mark" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                <div>
                    <h1 class="set-head-title">Cashflow</h1>
                    <p class="master-sub">
                        The accounts money moves through, the categories its rows are filed under, and the option lists
                        every cashflow form is filled from. Reports read the same lists — nothing here changes a figure,
                        only how the next one is entered.
                    </p>
                </div>
                <div class="set-head-actions">
                    <a href="{{ route('cashflows.index') }}" class="master-btn master-btn-light">Back to Cashflow</a>
                    <a href="{{ route('cashflows.reports') }}" class="master-btn master-btn-soft">Reports</a>
                </div>
            </div>

            <div class="master-tabs-card">
                <nav class="master-tabs" role="tablist" aria-label="Cashflow settings">
                    <a class="master-tab {{ $activeTab === 'accounts' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $activeTab === 'accounts' ? 'true' : 'false' }}" href="{{ route('settings.cashflow', ['tab' => 'accounts']) }}">
                        Accounts
                        @if ($accounts->count() > 0)<span class="master-tab-count">{{ $accounts->count() }}</span>@endif
                    </a>
                    <a class="master-tab {{ $activeTab === 'categories' ? 'is-active' : '' }}" role="tab" aria-selected="{{ $activeTab === 'categories' ? 'true' : 'false' }}" href="{{ route('settings.cashflow', ['tab' => 'categories']) }}">
                        Categories
                        @if ($categories->count() > 0)<span class="master-tab-count">{{ $categories->count() }}</span>@endif
                    </a>
                    @foreach($groupOptions as $groupKey => $groupLabel)
                        <a class="master-tab {{ $activeTab === $groupKey ? 'is-active' : '' }}" role="tab" aria-selected="{{ $activeTab === $groupKey ? 'true' : 'false' }}" href="{{ route('settings.cashflow', ['tab' => $groupKey]) }}">{{ $groupLabel }}</a>
                    @endforeach
                </nav>

                <div class="master-tab-panel cf-panel">
                    @if($activeTab === 'accounts')
                        <div class="cf-sub-card">
                            <h3>Add New Account</h3>
                            <form method="POST" action="{{ route('settings.cashflow.accounts.store') }}">
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
                                <form method="POST" action="{{ route('settings.cashflow.accounts.update', $account) }}" class="cf-row">
                                    @csrf
                                    <div><label class="master-label">Account Name</label><input class="master-input" name="account_name" value="{{ $account->account_name }}" required></div>
                                    <div><label class="master-label">Type</label><select class="master-select" name="account_type">@foreach($accountTypeOptions as $key => $label)<option value="{{ $key }}" @selected($account->account_type === $key)>{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="master-label">Bank</label><input class="master-input" name="bank_name" value="{{ $account->bank_name }}"></div>
                                    <div><label class="master-label">Currency</label><select class="master-select" name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}" @selected($account->currency === $key)>{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="master-label">Opening Balance</label><input class="master-input" type="number" step="0.01" name="opening_balance" value="{{ $account->opening_balance }}"></div>
                                    <div><label class="master-label">Current Balance</label><div class="current-balance">{{ \App\Helpers\CommonHelper::amount($account->current_balance, $account->currency) }}</div></div>
                                    <div class="cf-row-actions">
                                        <input type="checkbox" name="is_active" value="1" @checked($account->is_active) hidden>
                                        <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                        <button class="master-btn master-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('settings.cashflow.accounts.destroy', $account) }}" data-confirm="Delete this account?">Delete</button>
                                    </div>
                                </form>
                            @empty
                                <div class="cf-sub-card">No accounts found.</div>
                            @endforelse
                        </div>
                    @elseif($activeTab === 'categories')
                        <div class="cf-sub-card">
                            <h3>Add New Category</h3>
                            <form method="POST" action="{{ route('settings.cashflow.categories.store') }}">
                                @csrf
                                <div class="cf-form-grid">
                                    <div><label class="master-label">Category Name</label><input class="master-input" name="name" required placeholder="Sales Receipt / Office Expense"></div>
                                    <div><label class="master-label">Category Type</label><select class="master-select" name="type">@foreach($categoryTypeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="#4f83f1"></div>
                                    <div style="display:flex;align-items:end;"><label class="master-check"><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
                                </div>
                                <button class="master-btn master-btn-primary" type="submit">Create Category</button>
                            </form>
                        </div>

                        <div class="cf-list">
                            @forelse($categories as $category)
                                <form method="POST" action="{{ route('settings.cashflow.categories.update', $category) }}" class="cf-row category">
                                    @csrf
                                    <div><label class="master-label">Category Name</label><input class="master-input" name="name" value="{{ $category->name }}" required></div>
                                    <div><label class="master-label">Type</label><select class="master-select" name="type">@foreach($categoryTypeOptions as $key => $label)<option value="{{ $key }}" @selected($category->type === $key)>{{ $label }}</option>@endforeach</select></div>
                                    <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="{{ $category->color ?: '#4f83f1' }}"></div>
                                    <div><label class="master-label">Entries</label><div class="current-balance">{{ $category->entries_count }}</div></div>
                                    <div><label class="master-check"><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Active</label></div>
                                    <div class="cf-row-actions">
                                        <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                        <button class="master-btn master-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('settings.cashflow.categories.destroy', $category) }}" data-confirm="Delete this category? Existing entries will become uncategorized.">Delete</button>
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
                            <form method="POST" action="{{ route('settings.cashflow.masters.store') }}">
                                @csrf
                                <input type="hidden" name="group" value="{{ $masterGroup }}">
                                <div class="cf-form-grid">
                                    <div><label class="master-label">Key</label><input class="master-input" name="key" required placeholder="{{ $masterGroup === 'currency' ? 'INR' : 'new_option_key' }}"></div>
                                    <div><label class="master-label">Label</label><input class="master-input" name="label" required placeholder="Display label"></div>
                                    <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="#4f83f1"></div>
                                    <div><label class="master-label">Sort Order</label><input class="master-input" type="number" min="0" name="sort_order" value="10"><input type="checkbox" name="is_active" value="1" checked hidden></div>
                                </div>
                                <button class="master-btn master-btn-primary" type="submit">Create Option</button>
                            </form>
                        </div>

                        <div class="cf-list">
                            @forelse($masters as $master)
                                <form method="POST" action="{{ route('settings.cashflow.masters.update', $master) }}" class="cf-row master">
                                    @csrf
                                    <input type="hidden" name="group" value="{{ $master->group }}">
                                    <label class="current-balance" hidden>{{ $master->groupLabel() }}</label>
                                    <div><label class="master-label">Key</label><input class="master-input" name="key" value="{{ $master->key }}" required></div>
                                    <div><label class="master-label">Label</label><input class="master-input" name="label" value="{{ $master->label }}" required></div>
                                    <div><label class="master-label">Color</label><input class="master-input" type="color" name="color" value="{{ $master->color ?: '#4f83f1' }}"></div>
                                    <div><label class="master-label">Sort</label><input class="master-input" type="number" min="0" name="sort_order" value="{{ $master->sort_order }}"></div>
                                    <div><label class="master-check"><input type="checkbox" name="is_active" value="1" @checked($master->is_active)> Active</label></div>
                                    <div class="cf-row-actions">
                                        <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                        <button class="master-btn master-btn-danger" type="submit" name="_method" value="DELETE" formaction="{{ route('settings.cashflow.masters.destroy', $master) }}" data-confirm="Delete this master option?">Delete</button>
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
    </div>
@endsection
