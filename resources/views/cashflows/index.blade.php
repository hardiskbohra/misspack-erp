@extends('layouts.app')

@section('page-title', 'Cashflow Management')

{{-- The page's primary action lives in the header, so it stays reachable
     however far the list scrolls. --}}
@section('page-actions')
    {{-- The ledger's primary action stays in the header, with the module's other
         destinations beside it: the detailed form, the account an entry lands
         in, and the two module pages that have no other way in. --}}
    <button type="button" class="master-btn master-btn-primary" id="openQuickCashflowModal">
        + Quick Entry
    </button>
    <a class="master-btn master-btn-soft" href="{{ route('cashflows.create') }}">Detailed Form</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.reports') }}">Reports</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.settings.index') }}">Settings</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    {{-- the shared list chrome (chips, applied strip, density, pinned grid,
         mobile card, totals row, empty state) — after the module sheet, so the
         chrome keeps its own properties --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush

@php
    /* "Reset" and "clear filters" only make sense when something is filtered. */
    $filtersActive = trim((string) $search) !== ''
        || ($accountId && $accountId !== 'all')
        || ($accountType && $accountType !== 'all')
        || ($transactionType && $transactionType !== 'all')
        || ($accountingStatus && $accountingStatus !== 'all')
        || ($relatedPartyType && $relatedPartyType !== 'all')
        || filled($dateFrom)
        || filled($dateTo);

    /* The quick-view chips are filters too: clicking one replaces the chip
       dimension it owns and keeps everything else. */
    $chipBase = collect(request()->except([
        'transaction_type', 'accounting_status', 'date_from', 'date_to', 'page', 'saved_view',
    ]))->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    $monthFrom = \Illuminate\Support\Carbon::now()->startOfMonth()->toDateString();
    $monthTo = \Illuminate\Support\Carbon::now()->endOfMonth()->toDateString();
    $chipActive = [
        'credit' => $transactionType === 'credit',
        'debit' => $transactionType === 'debit',
        'pending' => $accountingStatus === 'pending',
        'this_month' => $dateFrom === $monthFrom && $dateTo === $monthTo,
    ];
    $chipActive['all'] = ! array_filter($chipActive);

    /* One URL per removable filter: everything else stays, 'page' restarts (a
       filter change is a new list) and 'saved_view' is dropped because the
       result is no longer that view. */
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page', 'saved_view']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('cashflows.index', $keep->all());
    };

    /* The period is one filter, so its chip removes both ends in one click. */
    $dateUrl = route('cashflows.index', collect(request()->except(['date_from', 'date_to', 'page', 'saved_view']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all')
        ->all());

    /* The list reads as a statement, so it is grouped by when it happened:
       today, yesterday, the rest of this month, and everything older. The
       rows are already in date order, so a divider only has to mark the
       change. */
    $dateBucket = function ($date) use ($monthFrom) {
        if (! $date) {
            return 'Undated';
        }

        $day = $date->toDateString();
        $today = \Illuminate\Support\Carbon::now()->toDateString();

        if ($day === $today) {
            return 'Today';
        }

        if ($day === \Illuminate\Support\Carbon::now()->subDay()->toDateString()) {
            return 'Yesterday';
        }

        return $day >= $monthFrom ? 'Earlier this month' : 'Older entries';
    };

    /* How many rows each divider covers, counted once — a paginator's
       filter() would rewrite the page itself. */
    $bucketCounts = [];
    foreach ($entries as $row) {
        $key = $dateBucket($row->entry_date);
        $bucketCounts[$key] = ($bucketCounts[$key] ?? 0) + 1;
    }
@endphp

<div class="cf cashflow-index master-list">

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat green tooltip-container">
            <span class="icon">↘</span>
            <div>
                <p class="master-stat-title">Credit (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['credit']) }}</p>
                <span class="tooltip-text">Every credit entry matching the filters above, wherever it landed.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat orange tooltip-container">
            <span class="icon">↗</span>
            <div>
                <p class="master-stat-title">Debit (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['debit']) }}</p>
                <span class="tooltip-text">Every debit entry matching the filters above, including expenses paid on a client's behalf.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $stats['net'] < 0 ? 'red' : 'blue' }} tooltip-container">
            <span class="icon">=</span>
            <div>
                <p class="master-stat-title">Net (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['net']) }}</p>
                <span class="tooltip-text">Credit minus debit for the entries matching the filters — a period result, not an account balance.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal tooltip-container">
            <span class="icon">🏦</span>
            <div>
                <p class="master-stat-title">Bank &amp; Cash</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency((float) $stats['current_balance'] + (float) $stats['saving_balance'] + (float) $stats['cash_balance']) }}</p>
                <span class="tooltip-text">Current + saving + cash balances across all accounts. This is the live figure, never filtered.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon">!</span>
            <div>
                <p class="master-stat-title">Pending settlement</p>
                <p class="master-stat-value">{{ $stats['pending'] }}</p>
                <p class="master-sub">{{ \Illuminate\Support\Str::plural('entry', $stats['pending']) }} awaiting reconciliation</p>
                <span class="tooltip-text">Entries still marked pending, across the whole ledger.</span>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        @php($baseFilters = request()->except(['page', 'saved_view']))
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ $chipActive['all'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.index', $chipBase->all()) }}">All entries</a>
                <a class="master-list-chip {{ $chipActive['credit'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.index', $chipBase->all() + ['transaction_type' => 'credit']) }}">
                    Credit <span class="master-list-chip-count">{{ $chipCounts['credit'] }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['debit'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.index', $chipBase->all() + ['transaction_type' => 'debit']) }}">
                    Debit <span class="master-list-chip-count">{{ $chipCounts['debit'] }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['pending'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.index', $chipBase->all() + ['accounting_status' => 'pending']) }}">
                    Pending <span class="master-list-chip-count">{{ $chipCounts['pending'] }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['this_month'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.index', $chipBase->all() + ['date_from' => $monthFrom, 'date_to' => $monthTo]) }}">
                    This month <span class="master-list-chip-count">{{ $chipCounts['this_month'] }}</span>
                </a>
            </div>

            <div class="master-list-saved">
                @foreach ($savedViews as $view)
                    <span class="master-list-saved-chip">
                        <a href="{{ route('cashflows.index', ['saved_view' => $view->id]) }}"
                            title="{{ $view->is_shared ? 'Shared view' : 'Your view' }}">{{ $view->name }}</a>
                        @if ((int) $view->user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('cashflows.saved-views.destroy', $view) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove saved view">&times;</button>
                            </form>
                        @endif
                    </span>
                @endforeach
                <button type="button" class="master-btn master-btn-soft master-btn-sm" id="toggleSaveView">☆ Save this view</button>
                <form method="POST" action="{{ route('cashflows.saved-views.store', $baseFilters) }}" class="master-list-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input class="master-input" name="name" placeholder="View name" maxlength="60" aria-label="Saved view name" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm">Save</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('cashflows.index') }}">
            <div class="master-filter-row">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Search particular, invoice, bank reference, party..." aria-label="Search cashflow entries">
                </div>
                <div class="cf-account-filter">
                    <select class="master-select" name="account_id" aria-label="Filter by account">
                        <option value="all">All Accounts</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) $accountId === (string) $account->id)>
                                {{ $account->account_name }}</option>
                        @endforeach
                    </select>
                    {{-- the account the entries are filtered by is the account you
                         sometimes have to add first, so the two sit together --}}
                    <button type="button" class="master-btn master-btn-ghost" id="openAccountModal"
                        title="Add an account" aria-label="Add an account">+ New</button>
                </div>
                <select class="master-select" name="transaction_type" aria-label="Filter by credit or debit">
                    <option value="all">Credit + Debit</option>
                    @foreach($transactionTypeOptions as $key => $label)
                        <option value="{{ $key }}" @selected($transactionType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="master-select" name="accounting_status" aria-label="Filter by accounting status">
                    <option value="all">All Accounting Status</option>
                    @foreach($accountingStatusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($accountingStatus === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="master-input desktop-only" type="date" name="date_from" value="{{ $dateFrom }}"
                    aria-label="Entries from" title="Entries from">
                <input class="master-input desktop-only" type="date" name="date_to" value="{{ $dateTo }}"
                    aria-label="Entries to" title="Entries to">

                <div class="master-list-filter-group">
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('cashflows.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </div>

            {{-- What is actually filtering, one removable chip each — including
                 filters that arrived from a saved view or a URL and therefore
                 have no visible control above. --}}
            @if ($filtersActive)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @if (trim((string) $search) !== '')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $search }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('search') }}"
                                aria-label="Remove the search filter" title="Remove the search filter">&times;</a>
                        </span>
                    @endif

                    @if ($accountId && $accountId !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Account</span>
                            <span class="master-list-applied-value">{{ $accounts->firstWhere('id', (int) $accountId)?->account_name ?? $accountId }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('account_id') }}"
                                aria-label="Remove the account filter" title="Remove the account filter">&times;</a>
                        </span>
                    @endif

                    @if ($accountType && $accountType !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Account type</span>
                            <span class="master-list-applied-value">{{ $accountTypeOptions[$accountType] ?? $accountType }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('account_type') }}"
                                aria-label="Remove the account-type filter" title="Remove the account-type filter">&times;</a>
                        </span>
                    @endif

                    @if ($transactionType && $transactionType !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Type</span>
                            <span class="master-list-applied-value">{{ $transactionTypeOptions[$transactionType] ?? $transactionType }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('transaction_type') }}"
                                aria-label="Remove the type filter" title="Remove the type filter">&times;</a>
                        </span>
                    @endif

                    @if ($accountingStatus && $accountingStatus !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Status</span>
                            <span class="master-list-applied-value">{{ $accountingStatusOptions[$accountingStatus] ?? $accountingStatus }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('accounting_status') }}"
                                aria-label="Remove the status filter" title="Remove the status filter">&times;</a>
                        </span>
                    @endif

                    @if ($relatedPartyType && $relatedPartyType !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Related to</span>
                            <span class="master-list-applied-value">{{ $relatedPartyOptions[$relatedPartyType] ?? $relatedPartyType }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('related_party_type') }}"
                                aria-label="Remove the related-party filter" title="Remove the related-party filter">&times;</a>
                        </span>
                    @endif

                    @if (filled($dateFrom) || filled($dateTo))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Dates</span>
                            <span class="master-list-applied-value">
                                {{ $dateFrom ? \Illuminate\Support\Carbon::parse($dateFrom)->format('d M Y') : 'start' }}
                                →
                                {{ $dateTo ? \Illuminate\Support\Carbon::parse($dateTo)->format('d M Y') : 'today' }}
                            </span>
                            <a class="master-list-applied-x" href="{{ $dateUrl }}"
                                aria-label="Remove the period filter" title="Remove the period filter">&times;</a>
                        </span>
                    @endif

                    <a class="master-list-applied-clear" href="{{ route('cashflows.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint"
                title="Newest entry date on top; entries on the same day keep the order they were entered in. The list is grouped by when it happened.">
                Newest first &middot; grouped by day
            </p>

            <div class="master-list-density desktop-only" role="group" aria-label="Row density">
                <button type="button" class="master-list-density-btn" data-density="comfortable"
                    aria-pressed="true">Comfortable</button>
                <button type="button" class="master-list-density-btn" data-density="compact"
                    aria-pressed="false">Compact</button>
            </div>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Particular</th>
                        <th scope="col">Account</th>
                        <th scope="col" class="is-num">Credit</th>
                        <th scope="col" class="is-num">Debit</th>
                        <th scope="col" class="is-num">Balance</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php($lastBucket = null)
                    @forelse($entries as $entry)
                        @php($bucket = $dateBucket($entry->entry_date))
                        @if ($bucket !== $lastBucket)
                            @php($lastBucket = $bucket)
                            <tr class="master-list-group">
                                {{-- the cell stays a table cell: display:flex on a <td>
                                     takes it out of the table layout and colspan stops
                                     spanning, so the strip lives in a wrapper inside it --}}
                                <td colspan="8">
                                    <div class="master-list-group-inner">
                                        <span>{{ $bucket }}</span>
                                        <span class="master-list-group-count">{{ $bucketCounts[$bucket] ?? 0 }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                        {{-- The whole row opens the record (assets/js/master-list.js);
                             anything interactive inside it keeps its own click. --}}
                        <tr class="cf-row is-clickable" data-href="{{ route('cashflows.show', $entry) }}">
                            <td data-label="Date">
                                <span class="cf-date">{{ $entry->entry_date?->format('d M') }}</span>
                                <span class="master-sub">{{ $entry->entry_date?->format('Y') }}</span>
                            </td>
                            <td class="cf-particular" data-label="Particular">
                                <strong>{{ $entry->particular }}</strong>
                                @if (! empty($mirroredPayments[$entry->id] ?? null))
                                    <a class="cf-sync-chip" href="{{ route('vendors.show', $entry->vendor_id) }}#payments"
                                        title="Auto-synced from a vendor payment — open the vendor ledger">↔ Vendor payment</a>
                                @endif
                                @if (! empty($mirroredShipmentCosts[$entry->id] ?? null))
                                    <a class="cf-sync-chip" href="{{ route('shipments.show', $mirroredShipmentCosts[$entry->id]) }}"
                                        title="Auto-synced from a shipment cost head — open the shipment">↔ Shipment cost</a>
                                @endif
                                <span class="master-sub">
                                    {{ $relatedPartyOptions[$entry->related_party_type] ?? 'Other' }}:
                                    {{ $entry->client?->company_name ?? $entry->vendor?->vendor_name ?? $entry->related_party_name ?? $entry->expense_head ?? '-' }}
                                    @if ($entry->invoice_bill_number || $entry->bank_reference_number)
                                        · {{ $entry->invoice_bill_number ?: $entry->bank_reference_number }}
                                    @endif
                                    @if ($entry->payment_mode)
                                        <span class="cf-tag">{{ $paymentModeOptions[$entry->payment_mode] ?? strtoupper($entry->payment_mode) }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="cf-account" data-label="Account">
                                {{ $entry->account?->account_name ?: '—' }}
                                <span class="master-sub">{{ $entry->account?->typeLabel() ?: 'No account' }}</span>
                            </td>
                            <td class="cf-money is-num cf-credit" data-label="Credit">
                                {{ $entry->credit_amount > 0 ? \App\Helpers\CommonHelper::amount($entry->credit_amount, $entry->currency) : '—' }}
                            </td>
                            <td class="cf-money is-num cf-debit" data-label="Debit">
                                {{ $entry->debit_amount > 0 ? \App\Helpers\CommonHelper::amount($entry->debit_amount, $entry->currency) : '—' }}
                            </td>
                            <td class="cf-money is-num" data-label="Balance">
                                {{ $entry->balance !== null ? \App\Helpers\CommonHelper::amount($entry->balance, $entry->currency) : '—' }}
                            </td>
                            <td data-label="Status">
                                <span class="cf-status status-{{ str_replace('_', '-', $entry->accounting_status) }}">
                                    {{ $entry->statusLabel() }}
                                </span>
                            </td>
                            <td data-label="Actions">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $entry->particular }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('cashflows.show', $entry) }}">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                                View Entry
                                            </a>
                                            <a href="{{ route('cashflows.edit', $entry) }}">
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                                Edit Entry
                                            </a>
                                            <form method="POST" action="{{ route('cashflows.destroy', $entry) }}"
                                                data-confirm="Delete this cashflow entry?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                    Delete Entry
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">₹</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtersActive ? 'No entries match these filters' : 'No cashflow entries yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Adjust the search or the filters above — the counts on each chip show what is available.'
                                            : 'Record the first bank statement entry to start the ledger.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a class="master-btn master-btn-soft" href="{{ route('cashflows.index') }}">Clear filters</a>
                                        @endif
                                        <button type="button" class="master-btn master-btn-primary" id="emptyQuickCashflow">+ Quick Entry</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="master-list-total">
                        <td colspan="3">
                            <strong>Total — {{ $entries->count() }} {{ \Illuminate\Support\Str::plural('entry', $entries->count()) }} shown</strong>
                            <span class="master-sub">Filtered totals cover every page</span>
                        </td>
                        <td class="is-num">
                            <strong class="cf-credit">{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['credit']) }}</strong>
                            <span class="master-sub">All pages: {{ \App\Helpers\CommonHelper::indianCurrency($stats['credit']) }}</span>
                        </td>
                        <td class="is-num">
                            <strong class="cf-debit">{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['debit']) }}</strong>
                            <span class="master-sub">All pages: {{ \App\Helpers\CommonHelper::indianCurrency($stats['debit']) }}</span>
                        </td>
                        <td class="is-num">
                            <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['net']) }}</strong>
                            <span class="master-sub">Net on this page</span>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <x-pagination :items="$entries" />
    </div>

    <div class="master-modal" id="quickCashflowModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickCashflowTitle">
            <form method="POST" action="{{ route('cashflows.quickStore') }}">@csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">₹</span>
                        <div>
                            <h3 class="master-modal-title" id="quickCashflowTitle">Quick Cashflow Entry</h3>
                            <p class="master-modal-subtitle">One bank line, recorded in a few seconds</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="quickCashflowModal"
                        aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="quickEntryDate">Date <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quickEntryDate" type="date" name="entry_date"
                                value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label">Transaction Type <span class="master-required" aria-hidden="true">*</span></label>
                            <div class="master-chip-group">
                                @foreach($transactionTypeOptions as $key => $label)
                                    <label class="master-chip {{ $key === 'credit' ? 'credit-chip' : 'debit-chip' }}">
                                        <input type="radio" name="transaction_type" value="{{ $key }}"
                                            {{ old('transaction_type', 'debit') === $key ? 'checked' : '' }} required>
                                        <span>
                                            <i class="fa-solid {{ $key === 'credit' ? 'fa-arrow-down' : 'fa-arrow-up' }}" aria-hidden="true"></i>
                                            {{ $label }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="quickParticular">Particular <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quickParticular" name="particular" required
                                placeholder="Bank statement particular">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickAmount">Amount <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quickAmount" type="number" step="0.01" min="0" name="amount" required>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickAccount">Account <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select" id="quickAccount" name="account_id" required>
                                <option value="">Select account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickRelatedParty">Related To</label>
                            <select class="master-select" id="quickRelatedParty" name="related_party_type">
                                @foreach($relatedPartyOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickProject">Project / Deal</label>
                            <select id="quickProject" name="project_id" class="master-select">
                                <option value="">No project mapping</option>
                                @foreach(\App\Models\Project::query()->latest('id')->get() as $project)
                                    <option value="{{ $project->id }}">{{ $project->project_number }} - {{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickCategory">Category</label>
                            <select class="master-select" id="quickCategory" name="category_id">
                                <option value="">Uncategorized</option>
                                @forelse($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }} - {{ $category->typeLabel() }}</option>
                                @empty
                                    <option value="" disabled>No categories found - add from Cashflow Settings</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickPartyName">Party / Expense Name</label>
                            <input class="master-input" id="quickPartyName" name="related_party_name">
                        </div>
                    </div>
                    <p class="master-sub">
                        Quick Entry covers the bank line. Need payment mode, category or notes?
                        <a href="{{ route('cashflows.create') }}">Open the detailed form</a>.
                    </p>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="quickCashflowModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Create Entry</button>
                </div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="accountModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
            <form method="POST" action="{{ route('cashflows.accounts.store') }}">@csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">🏦</span>
                        <div>
                            <h3 class="master-modal-title" id="accountModalTitle">Add Cashflow Account</h3>
                            <p class="master-modal-subtitle">Current, saving or cash account</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="accountModal"
                        aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="accountName">Account Name</label>
                            <input class="master-input" id="accountName" name="account_name" required placeholder="Hardik HDFC">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountType">Account Type</label>
                            <select class="master-select" id="accountType" name="account_type">
                                @foreach($accountTypeOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountBank">Bank Name</label>
                            <input class="master-input" id="accountBank" name="bank_name">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountOpening">Opening Balance</label>
                            <input class="master-input" id="accountOpening" type="number" step="0.01" name="opening_balance" value="0">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountCurrency">Currency</label>
                            <select class="master-select" id="accountCurrency" name="currency">
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountNumber">Account Number</label>
                            <input class="master-input" id="accountNumber" name="account_number">
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="accountModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflows.js') }}"></script>
@endpush
@endsection
