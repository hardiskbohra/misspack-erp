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
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.documents') }}">Documents</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.reports') }}">Reports</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('cashflows.settings.index') }}">Settings</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
@endpush

@php
    /* The strip below is drawn from the module's filter vocabulary
       (App\Services\CashflowFilters): every dimension that narrowed the query
       arrives here as a chip with the name it filtered by, whether it came
       from a control on this page, a saved view, or a report cell the operator
       clicked to see the rows behind a total. The date pair is one filter and
       keeps its own chip (it names a period, not a value). */
    $appliedChips = collect($appliedFilters)
        ->reject(fn ($chip) => in_array($chip['query'], ['date_from', 'date_to'], true))
        ->map(function ($chip) use ($appliedFilterLabels) {
            $label = trim((string) ($appliedFilterLabels[$chip['key']] ?? ''));
            $chip['value'] = $label !== '' ? $label : $chip['value'];

            return $chip;
        })
        ->values();

    /* "Reset" and "clear filters" only make sense when something is filtered —
       and the same list decides that, so the two can never disagree. */
    $filtersActive = $appliedChips->isNotEmpty() || filled($dateFrom) || filled($dateTo);

    /* The quick-view chips are filters too: clicking one replaces the chip
       dimension it owns and keeps everything else. */
    $chipBase = collect(request()->except([
        'transaction_type', 'accounting_status', 'date_from', 'date_to', 'documents', 'page', 'saved_view',
    ]))->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    /* The period chips are the shared ranges (App\Helpers\DateRanges): the
       label, the range behind the link, the count from the controller and the
       lit state here all read the same keys, so a chip can never claim a month
       its link does not filter by. */
    $chipActive = [
        'credit' => $transactionType === 'credit',
        'debit' => $transactionType === 'debit',
        'pending' => $accountingStatus === 'pending',
        'missing_documents' => $documents === 'missing',
    ];
    foreach ($dateRanges as $rangeKey => $range) {
        $chipActive[$rangeKey] = $activeRange === $rangeKey;
    }
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
    $dateBucket = function ($date) use ($dateRanges) {
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

        return $day >= $dateRanges['this_month']['from'] ? 'Earlier this month' : 'Older entries';
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
                {{-- The periods read left to right, nearest first — the same
                     four ranges every list gets from App\Helpers\DateRanges.
                     Each one is a closed range, so the strip below can name it
                     and clear both ends in one click. --}}
                {{-- The month-end question is "what has no bill yet", so it is
                     a filter like any other: the count comes from the same
                     query as the rows, and the archive is one link away. --}}
                <a class="master-list-chip {{ $chipActive['missing_documents'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.index', $chipBase->all() + ['documents' => 'missing']) }}">
                    Missing documents <span class="master-list-chip-count">{{ $chipCounts['missing_documents'] ?? 0 }}</span>
                </a>
                @foreach ($dateRanges as $rangeKey => $range)
                    <a class="master-list-chip {{ $chipActive[$rangeKey] ? 'is-active' : '' }}"
                        href="{{ route('cashflows.index', $chipBase->all() + ['date_from' => $range['from'], 'date_to' => $range['to']]) }}">
                        {{ $dateRangeLabels[$rangeKey] }}
                        <span class="master-list-chip-count">{{ $chipCounts[$rangeKey] ?? 0 }}</span>
                    </a>
                @endforeach
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
                {{-- The chips own credit/debit and the missing-documents view, so
                     the form carries them: no second control for a dimension the
                     chips already answer with a count, and applying the filters
                     below keeps the chip that is lit instead of dropping it. --}}
                <input type="hidden" name="transaction_type" value="{{ $transactionType ?: 'all' }}">
                <input type="hidden" name="documents" value="{{ $documents ?? 'all' }}">

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

                    @foreach ($appliedChips as $chip)
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                            <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl($chip['query']) }}"
                                aria-label="Remove the {{ strtolower($chip['label']) }} filter"
                                title="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                        </span>
                    @endforeach

                    @if (filled($dateFrom) || filled($dateTo))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $activeRange ? 'Period' : 'Dates' }}</span>
                            {{-- a chip's own range is named, not spelled out: the
                                 operator picked "Last month", so that is what is
                                 filtering the list --}}
                            <span class="master-list-applied-value">
                                @if ($activeRange)
                                    {{ $dateRangeLabels[$activeRange] }}
                                @else
                                    {{ \App\Helpers\DateRanges::display($dateFrom, 'start') }}
                                    →
                                    {{ \App\Helpers\DateRanges::display($dateTo, 'today') }}
                                @endif
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

            {{-- The right-hand end of the toolbar is the shared slot: a
                 module's own destinations first, then the density switch every
                 list has in the same place. --}}
            <div class="master-list-toolbar-actions">
                <a class="master-btn master-btn-ghost master-btn-sm" href="{{ route('cashflows.documents') }}">
                    <i class="fa-regular fa-folder-open" aria-hidden="true"></i> Document archive
                </a>

                <div class="master-list-density desktop-only" role="group" aria-label="Row density">
                    <button type="button" class="master-list-density-btn" data-density="comfortable"
                        aria-pressed="true">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact"
                        aria-pressed="false">Compact</button>
                </div>
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
                                @if (($entry->attachments_count ?? 0) > 0)
                                    <a class="cf-doc-chip" href="{{ route('cashflows.show', $entry) }}#documents"
                                        title="{{ $entry->attachments_count }} document(s) on file — open them">
                                        <i class="fa-solid fa-paperclip" aria-hidden="true"></i>{{ $entry->attachments_count }}
                                    </a>
                                @endif
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
                                    {{-- the party the row is against: the linked record
                                         first, then whatever was typed. One accessor decides
                                         the order, so the list, the row's own page and the
                                         analysis builder never name the same entry differently --}}
                                    {{ $entry->partyLabel() ?: '-' }}
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
                            <td data-label="Action">
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
                                            <a href="{{ route('cashflows.show', $entry) }}#documents">
                                                <i class="fas fa-paperclip" aria-hidden="true"></i>
                                                Documents
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
                        <td class="is-num" data-label="Credit">
                            <strong class="cf-credit">{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['credit']) }}</strong>
                            <span class="master-sub">All pages: {{ \App\Helpers\CommonHelper::indianCurrency($stats['credit']) }}</span>
                        </td>
                        <td class="is-num" data-label="Debit">
                            <strong class="cf-debit">{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['debit']) }}</strong>
                            <span class="master-sub">All pages: {{ \App\Helpers\CommonHelper::indianCurrency($stats['debit']) }}</span>
                        </td>
                        <td class="is-num" data-label="Balance">
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

    @php
        /* Which party field is on screen before any script runs: the selector's
           own value, which is what was chosen last time round if the page is
           coming back from a failed save. The script keeps this in step when the
           selector changes; the server keeps it right when nothing has run yet. */
        $quickPartyType = old('related_party_type', 'client');
    @endphp

    <div class="master-modal" id="quickCashflowModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickCashflowTitle">
            {{-- `_dialog` is how this modal comes back after a failed save:
                 a validation failure redirects here with the input kept, the
                 marker at the foot of the page names the dialog, and the script
                 reopens it. Nobody retypes a bank line. --}}
            <form method="POST" action="{{ route('cashflows.quickStore') }}">@csrf
                <input type="hidden" name="_dialog" value="quickCashflowModal">
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
                                value="{{ old('entry_date', now()->toDateString()) }}" required>
                            @error('entry_date')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" id="quickTypeLabel">Transaction Type <span class="master-required" aria-hidden="true">*</span></label>
                            <div class="master-choice-group" role="radiogroup" aria-labelledby="quickTypeLabel">
                                @foreach($transactionTypeOptions as $key => $label)
                                    <label class="master-choice-chip {{ $key === 'credit' ? 'credit-chip' : 'debit-chip' }}">
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
                                value="{{ old('particular') }}" placeholder="Bank statement particular">
                            @error('particular')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickAmount">Amount <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="quickAmount" type="number" step="0.01" min="0" name="amount"
                                value="{{ old('amount') }}" required>
                            @error('amount')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickAccount">Account <span class="master-required" aria-hidden="true">*</span></label>
                            <select class="master-select" id="quickAccount" name="account_id" required>
                                <option value="">Select account</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->account_name }}</option>
                                @endforeach
                            </select>
                            @error('account_id')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickRelatedParty">Related To</label>
                            <select class="master-select" id="quickRelatedParty" name="related_party_type"
                                data-party-source>
                                @foreach($relatedPartyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($quickPartyType === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('related_party_type')<p class="master-error">{{ $message }}</p>@enderror
                        </div>

                        {{-- One party question at a time. "Related To" says which
                             list you are picking from, and the field below it is
                             that list: a client, a vendor, or the head a cash
                             expense was spent under. The free-text name stays for
                             the cases that have no record to link to. --}}
                        <div class="master-field party-picker" data-party-for="client"
                            @if ($quickPartyType !== 'client') hidden @endif>
                            <label class="master-label" for="quickClient">Client</label>
                            <select class="master-select" id="quickClient" name="client_id">
                                <option value="">No client linked</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->company_name }}</option>
                                @endforeach
                            </select>
                            @error('client_id')<p class="master-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="master-field party-picker" data-party-for="vendor"
                            @if ($quickPartyType !== 'vendor') hidden @endif>
                            <label class="master-label" for="quickVendor">Vendor</label>
                            <select class="master-select" id="quickVendor" name="vendor_id">
                                <option value="">No vendor linked</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" @selected((string) old('vendor_id') === (string) $vendor->id)>{{ $vendor->vendor_name }}</option>
                                @endforeach
                            </select>
                            @error('vendor_id')<p class="master-error">{{ $message }}</p>@enderror
                        </div>

                        {{-- A person paid from the account is a party like any
                             other, and the link is what files the payment against
                             them: linked here, it shows up in the employee report
                             and on their own record instead of only in the notes.
                             The words are task 60's, so the office recognises the
                             field it has been using. --}}
                        <div class="master-field party-picker" data-party-for="employee"
                            @if ($quickPartyType !== 'employee') hidden @endif>
                            <label class="master-label" for="quickEmployee">Paid to employee</label>
                            <select class="master-select" id="quickEmployee" name="employee_id">
                                <option value="">No employee</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}@if($employee->designation) — {{ $employee->designation }}@endif</option>
                                @endforeach
                            </select>
                            @error('employee_id')<p class="master-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="master-field party-picker" data-party-for="expense"
                            @if ($quickPartyType !== 'expense') hidden @endif>
                            <label class="master-label" for="quickExpenseHead">Expense head</label>
                            <input class="master-input" id="quickExpenseHead" name="expense_head"
                                value="{{ old('expense_head') }}" placeholder="Petrol, tea, courier …">
                            @error('expense_head')<p class="master-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickProject">Project / Deal</label>
                            <select id="quickProject" name="project_id" class="master-select">
                                <option value="">No project mapping</option>
                                @foreach(\App\Models\Project::query()->latest('id')->get() as $project)
                                    <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->project_number }} - {{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickCategory">Category</label>
                            <select class="master-select" id="quickCategory" name="category_id">
                                <option value="">Uncategorized</option>
                                @forelse($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }} - {{ $category->typeLabel() }}</option>
                                @empty
                                    <option value="" disabled>No categories found - add from Cashflow Settings</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="quickPartyName">Party / Expense Name</label>
                            <input class="master-input" id="quickPartyName" name="related_party_name"
                                value="{{ old('related_party_name') }}"
                                placeholder="The name on the line, when it is not linked above">
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

    {{-- A failed save comes back here with the input kept and the errors on the
         bag. `_dialog` (posted by the dialog's own form) says which one was open,
         so the right form reopens with the office's typing still in it. One
         marker for the page, not one per dialog. --}}
    <span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

    <div class="master-modal" id="accountModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
            <form method="POST" action="{{ route('cashflows.accounts.store') }}">@csrf
                <input type="hidden" name="_dialog" value="accountModal">
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
                            <input class="master-input" id="accountName" name="account_name" required
                                value="{{ old('account_name') }}" placeholder="Hardik HDFC">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountType">Account Type</label>
                            <select class="master-select" id="accountType" name="account_type">
                                @foreach($accountTypeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('account_type') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountBank">Bank Name</label>
                            <input class="master-input" id="accountBank" name="bank_name" value="{{ old('bank_name') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountOpening">Opening Balance</label>
                            <input class="master-input" id="accountOpening" type="number" step="0.01" name="opening_balance"
                                value="{{ old('opening_balance', '0') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountCurrency">Currency</label>
                            <select class="master-select" id="accountCurrency" name="currency">
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('currency') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="accountNumber">Account Number</label>
                            <input class="master-input" id="accountNumber" name="account_number" value="{{ old('account_number') }}">
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
