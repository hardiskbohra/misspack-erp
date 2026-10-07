@extends('layouts.app')

@section('page-title', 'Recurring cashflow')

@section('page-actions')
    {{-- Two doors: back to the ledger, and the one write this page has. The
         button is a button because it opens a dialog — and it lives in the
         header so a rule is two clicks from anywhere on the page. --}}
    <a class="master-btn master-btn-ghost" href="{{ route('cashflows.index') }}">Ledger</a>
    <button type="button" class="master-btn master-btn-primary" data-open-rule-modal>
        <i class="fa-solid fa-plus" aria-hidden="true"></i> New rule
    </button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflow-recurring.css') }}">
@endpush

@php
    /* Every chip is one URL away from the others, so a chip keeps what it does
       not own: the search, the rhythm, the plan window and the order travel with
       it. */
    $chipUrl = function (array $overrides) {
        $keep = array_merge(request()->except(['page']), $overrides);

        return route('cashflows.recurring.index', array_filter(
            $keep,
            fn ($value) => $value !== null && $value !== '' && $value !== 'all',
            ARRAY_FILTER_USE_BOTH
        ));
    };

    $money = fn ($value, $currency) => \App\Helpers\CommonHelper::amount($value, $currency ?: 'INR');
    $firstRule = $rules->firstItem() ?? 0;
    $lastRule = $rules->lastItem() ?? 0;
@endphp

<div class="cfr cfr-index master-list">

    {{-- The figures. Four numbers cut from one query — how many rules are
         running, what is waiting on an answer, which dates are asking today,
         and how much the next month will ask for. --}}
    <div class="master-stats desktop-only" aria-label="Recurring rules">
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
            <div>
                <p class="master-stat-title">Running rules</p>
                <p class="master-stat-value">{{ number_format($figures['running']) }}</p>
                <p class="master-sub">{{ number_format($figures['total']) }} on this view</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat orange">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
            <div>
                <p class="master-stat-title">Approvals due</p>
                <p class="master-stat-value">{{ number_format($figures['due']) }}</p>
                <p class="master-sub">{{ $figures['overdue'] > 0 ? number_format($figures['overdue']).' already late' : 'due today or earlier' }}</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-paper-plane"></i></span>
            <div>
                <p class="master-stat-title">Waiting for approval</p>
                <p class="master-stat-value">{{ number_format($figures['approval']) }}</p>
                <p class="master-sub">rules sent to the office</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true"><i class="fa-regular fa-calendar-check"></i></span>
            <div>
                <p class="master-stat-title">Asking in 30 days</p>
                <p class="master-stat-value">{{ number_format($figures['soon']) }}</p>
                <p class="master-sub">{{ number_format($figures['planned']) }} dates on the plans</p>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────────────── the day's work --}}
    {{-- Approving is a button, not a trip: every date that has reached its
         effective date is answered from here. It obeys the chips above it, so
         the list and this queue are always the same set of rules read twice. --}}
    @if ($due->isNotEmpty())
        <section class="master-card master-card--flat cfr-card" aria-labelledby="cfrDueTitle">
            <div class="cfr-card-head">
                <div>
                    <p class="master-eyebrow">Today</p>
                    <h2 class="master-section-title" id="cfrDueTitle">Waiting on your approval</h2>
                </div>
                <p class="master-help">
                    @if ($figures['due'] > $due->count())
                        Showing the first {{ number_format($dueLimit) }} of {{ number_format($figures['due']) }} —
                        the rules below hold the rest
                    @else
                        {{ number_format($due->count()) }} date{{ $due->count() === 1 ? '' : 's' }} at or past the effective date
                    @endif
                </p>
            </div>

            <ul class="cfr-due-list">
                @foreach ($due as $occurrence)
                    <li class="cfr-due">
                        <div class="cfr-due-what">
                            <span class="cfr-due-date {{ $occurrence->isOverdue() ? 'is-late' : '' }}">
                                {{ $occurrence->effective_date?->format('d M') }}
                            </span>
                            <div>
                                <a class="cfr-due-title" href="{{ route('cashflows.recurring.show', $occurrence->rule) }}">
                                    {{ $occurrence->rule->title }}
                                </a>
                                <p class="cfr-due-meta">
                                    #{{ $occurrence->sequence }}
                                    @if ($occurrence->rule->partyLabel()) · {{ $occurrence->rule->partyLabel() }}@endif
                                    · {{ $occurrence->rule->cadenceLabel() }}
                                    @if ($occurrence->latenessLabel())
                                        · <span class="cfr-late">{{ $occurrence->latenessLabel() }}</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="cfr-due-money">
                            {{ $money($occurrence->rule->amount, $occurrence->rule->currency) }}
                        </div>

                        <div class="cfr-due-actions">
                            <form method="POST" action="{{ route('cashflows.recurring.occurrences.approve', [$occurrence->rule, $occurrence]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="master-btn master-btn-primary master-btn-sm">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i> Approve
                                </button>
                            </form>

                            <form method="POST" action="{{ route('cashflows.recurring.occurrences.skip', [$occurrence->rule, $occurrence]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="master-btn master-btn-light master-btn-sm">Skip this date</button>
                            </form>

                            <a class="master-btn master-btn-ghost master-btn-sm" href="{{ route('cashflows.recurring.show', $occurrence->rule) }}">Open</a>
                        </div>

                        @if ($occurrence->decider)
                            <p class="cfr-due-note">Last decided by {{ $occurrence->decider->name }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ─────────────────────────────────────────────── search and filter --}}
    <section class="master-card master-card--flat" aria-label="Search and filter recurring rules">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Filter rules by state">
                @foreach ($stateOptions as $key => $label)
                    <a class="master-list-chip {{ $state === $key ? 'is-active' : '' }}"
                        href="{{ $chipUrl(['state' => $key]) }}">
                        {{ $label }}
                        <span class="master-list-chip-count">{{ number_format($stateCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <form method="GET" action="{{ route('cashflows.recurring.index') }}">
            {{-- The chips are links, so they are not part of this form. Carrying
                 them as hidden fields is what keeps a search from quietly
                 dropping the state the reader had chosen. --}}
            @if ($state !== 'everything')
                <input type="hidden" name="state" value="{{ $state }}">
            @endif

            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="q" value="{{ $q }}"
                        placeholder="Search a rule, a party or a note" aria-label="Search recurring rules">
                </label>

                <x-filter-trigger drawer="recurringFiltersDrawer" label="Filters" :count="count($applied)" />
            </div>

            <x-drawer id="recurringFiltersDrawer" title="Filter rules" eyebrow="Recurring filters"
                subtitle="The chips carry the state; this carries the rhythm, the plan's window and the order."
                size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Rhythm</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">How often it pays</span>
                            <select class="master-select" name="frequency">
                                <option value="all" @selected($frequency === 'all')>Every rhythm</option>
                                @foreach ($frequencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($frequency === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">The plan</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Asking when</span>
                            <select class="master-select" name="window">
                                @foreach ($windowOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($window === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="master-help">Only rules whose plan still holds a date in that window.</span>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Order</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Read the list</span>
                            <select class="master-select" name="sort">
                                @foreach ($sortOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <x-slot:footer>
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('cashflows.recurring.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>
        </form>

        @if ($filtered)
            <div class="master-list-applied">
                <span class="master-list-applied-title">Filtered by</span>
                @foreach ($applied as $chip)
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                        <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                        <a class="master-list-applied-x" href="{{ $chipUrl(array_fill_keys($chip['query'], null)) }}"
                            aria-label="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                    </span>
                @endforeach
                <a class="master-list-applied-clear" href="{{ route('cashflows.recurring.index') }}">Clear all</a>
            </div>
        @endif
    </section>

    {{-- ──────────────────────────────────────────────────────────── the rules --}}
    @if ($rules->total() > 0)
        <section class="master-card master-table-card master-card--flat" aria-label="Recurring rules">
            <div class="master-list-toolbar">
                <p class="master-list-hint"
                    title="Every standing payment this office keeps, with where its plan has got to.">
                    Showing {{ $firstRule }}–{{ $lastRule }} of {{ number_format($rules->total()) }}
                </p>

                <div class="master-list-toolbar-actions">
                    <button type="button" class="master-btn master-btn-light master-btn-sm" data-open-rule-modal>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> New rule
                    </button>
                </div>
            </div>

            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table cfr-table">
                    <thead>
                        <tr>
                            <th scope="col">Rule</th>
                            <th scope="col" class="cfr-col-money">Amount</th>
                            <th scope="col">Rhythm</th>
                            <th scope="col" class="cfr-col-state">State</th>
                            <th scope="col" class="cfr-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            <tr class="cfr-row is-clickable" data-href="{{ route('cashflows.recurring.show', $rule) }}">
                                <td data-label="Rule">
                                    <a class="cfr-rule-link" href="{{ route('cashflows.recurring.show', $rule) }}">{{ $rule->title }}</a>
                                    <span class="cfr-cell-sub" title="{{ $rule->particular }}">
                                        {{ $rule->particular }}@if ($rule->partyLabel()) · {{ $rule->partyLabel() }}@endif
                                    </span>
                                </td>

                                <td class="cfr-col-money" data-label="Amount">
                                    <span class="cfr-money">{{ $money($rule->amount, $rule->currency) }}</span>
                                    <span class="cfr-cell-sub">{{ $rule->transaction_type === 'credit' ? 'Money in' : 'Money out' }}</span>
                                </td>

                                <td data-label="Rhythm">
                                    {{ $rule->cadenceLabel() }}
                                    <span class="cfr-cell-sub">
                                        from {{ $rule->starts_on?->format('d M Y') }} · {{ $rule->windowLabel() }}
                                    </span>
                                </td>

                                <td class="cfr-col-state" data-label="State">
                                    <span class="core-badge core-badge-{{ $rule->stateTone() }}">{{ $rule->stateLabel() }}</span>
                                    <span class="cfr-cell-sub">
                                        @if ($rule->nextDate())
                                            next {{ $rule->nextDate()->format('d M Y') }}
                                        @elseif ($rule->isActive())
                                            nothing left to ask
                                        @else
                                            {{ $rule->releasedCount() }} posted
                                        @endif
                                    </span>
                                </td>

                                <td class="cfr-col-actions" data-label="">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $rule->title }}" aria-haspopup="true"
                                            aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('cashflows.recurring.show', $rule) }}">
                                                <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                                                Open the rule
                                            </a>

                                            @if ($rule->isDraft() && ! $rule->isWaitingApproval())
                                                <form method="POST" action="{{ route('cashflows.recurring.request', $rule) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">
                                                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                                        Ask for approval
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($rule->isWaitingApproval())
                                                <form method="POST" action="{{ route('cashflows.recurring.approve', $rule) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">
                                                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                        Approve it
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($rule->isActive())
                                                <form method="POST" action="{{ route('cashflows.recurring.pause', $rule) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">
                                                        <i class="fa-solid fa-pause" aria-hidden="true"></i>
                                                        Pause it
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($rule->isPaused())
                                                <form method="POST" action="{{ route('cashflows.recurring.resume', $rule) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">
                                                        <i class="fa-solid fa-play" aria-hidden="true"></i>
                                                        Start asking again
                                                    </button>
                                                </form>
                                            @endif

                                            @if (! $rule->isEnded())
                                                <form method="POST" action="{{ route('cashflows.recurring.end', $rule) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">
                                                        <i class="fa-solid fa-flag-checkered" aria-hidden="true"></i>
                                                        End the rule
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- A draft nobody has decided is disposable; anything
                                                 else is ended instead, and the page says why. --}}
                                            @if ($rule->isDraft() && $rule->releasedCount() === 0)
                                                <form method="POST" action="{{ route('cashflows.recurring.destroy', $rule) }}"
                                                    data-confirm="Delete this draft? Nothing was ever paid from it.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="is-danger">
                                                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                                        Delete the draft
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$rules" />
        </section>
    @else
        <section class="master-card master-card--flat cfr-card">
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">🔁</span>
                <h3 class="master-list-empty-title">
                    {{ $filtered ? 'No rules match this view' : 'Nothing repeats yet' }}
                </h3>
                <p class="master-list-empty-text">
                    @if ($filtered)
                        Nothing left after the chips and filters above. Widen them, or write a new rule.
                    @else
                        Salary, rent, the internet bill, a monthly supplier — this is where a payment
                        that happens every month stops being retyped every month. A rule starts as a
                        draft: it posts nothing until the office approves it, and after that every date
                        asks for its own approval on the day it is due.
                    @endif
                </p>
                <div class="master-list-empty-actions">
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('cashflows.recurring.index') }}">Clear the filters</a>
                    @endif
                    <button type="button" class="master-btn master-btn-primary" data-open-rule-modal>
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Write a rule
                    </button>
                </div>
            </div>
        </section>
    @endif
</div>

{{-- ─────────────────────────────────────────────────────────── a new rule --}}
{{-- The form is a dialog, not a page: a rule is five fields and a rhythm, and a
     screen of its own would be a screen to come back from. `_dialog` is how it
     comes back — a validation failure redirects here with the typing kept, the
     marker below names the dialog, and `cashflow-recurring.js` reopens it. --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

<div class="master-modal" id="recurrenceRuleModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="recurrenceRuleTitle">
        <form method="POST" action="{{ route('cashflows.recurring.store') }}">
            @csrf
            <input type="hidden" name="_dialog" value="recurrenceRuleModal">

            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-arrows-rotate"></i></span>
                    <div>
                        <h3 class="master-modal-title" id="recurrenceRuleTitle">A standing payment</h3>
                        <p class="master-modal-subtitle">
                            It is saved as a draft: nothing is planned and nothing is posted until the office approves it.
                        </p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="recurrenceRuleModal" aria-label="Close">&times;</button>
            </div>

            <div class="master-modal-body">
                @if ($errors->any())
                    <div class="master-info-box is-danger" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                @include('cashflows.recurring.partials.rule-form', ['rule' => null, 'dialogId' => 'recurrenceRule'])
            </div>

            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light" data-close-modal="recurrenceRuleModal">Cancel</button>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-solid fa-file-pen" aria-hidden="true"></i> Save the draft
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflow-recurring.js') }}" defer></script>
@endpush
@endsection
