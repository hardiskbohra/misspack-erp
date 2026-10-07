@extends('layouts.app')

@section('title', $rule->title)
@section('page-title', $rule->title)

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('cashflows.recurring.index') }}">All rules</a>

    @if ($rule->isDraft())
        <button type="button" class="master-btn master-btn-soft" data-open-rule-edit-modal>
            <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit the draft
        </button>
    @endif

    {{-- The header carries the one door the state is actually asking for —
         the same door the card below explains. --}}
    @if ($rule->isDraft() && ! $rule->isWaitingApproval())
        <form class="cfr-action-form" method="POST" action="{{ route('cashflows.recurring.request', $rule) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="master-btn master-btn-primary">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Ask for approval
            </button>
        </form>
    @elseif ($rule->isWaitingApproval())
        <form class="cfr-action-form" method="POST" action="{{ route('cashflows.recurring.approve', $rule) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="master-btn master-btn-primary">
                <i class="fa-solid fa-check" aria-hidden="true"></i> Approve it
            </button>
        </form>
    @elseif ($rule->isActive())
        <form class="cfr-action-form" method="POST" action="{{ route('cashflows.recurring.pause', $rule) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="master-btn master-btn-soft">
                <i class="fa-solid fa-pause" aria-hidden="true"></i> Pause it
            </button>
        </form>
    @elseif ($rule->isPaused())
        <form class="cfr-action-form" method="POST" action="{{ route('cashflows.recurring.resume', $rule) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="master-btn master-btn-primary">
                <i class="fa-solid fa-play" aria-hidden="true"></i> Start asking again
            </button>
        </form>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflow-recurring.css') }}">
@endpush

@php
    $money = fn ($value, $currency = null) => \App\Helpers\CommonHelper::amount($value, $currency ?: $rule->currency ?: 'INR');
    $openDates = $planned;
    $windowTotal = $rule->occurrence_limit;
@endphp

<div class="cfr cfr-record master-list">

    {{-- ─────────────────────────────────────────────────────────── the rule --}}
    <div class="master-card master-card--flat cfr-record-head">
        <div class="cfr-record-who">
            <span class="cfr-record-avatar {{ $rule->transaction_type === 'credit' ? 'is-ok' : 'is-warn' }}" aria-hidden="true">
                {{ $rule->transaction_type === 'credit' ? 'In' : 'Out' }}
            </span>
            <div>
                <h1>{{ $rule->title }}</h1>
                <p class="master-sub cfr-record-line">
                    {{ $rule->particular }}
                    @if ($rule->account) · {{ $rule->account->account_name }}@endif
                    @if ($rule->partyLabel()) · {{ $rule->partyLabel() }}@endif
                </p>
                <p class="cfr-record-tags">
                    <span class="core-badge core-badge-{{ $rule->stateTone() }}">{{ $rule->stateLabel() }}</span>
                    <span class="cfr-tag {{ $rule->transaction_type === 'credit' ? 'is-ok' : 'is-warn' }}">
                        {{ $rule->transaction_type === 'credit' ? 'Money in' : 'Money out' }}
                    </span>
                    <span class="cfr-tag">{{ $rule->cadenceLabel() }}</span>
                    @if ($rule->category)<span class="cfr-tag">{{ $rule->category->name }}</span>@endif
                    @if ($rule->expense_head)<span class="cfr-tag">{{ $rule->expense_head }}</span>@endif
                </p>
            </div>
        </div>

        <div class="cfr-record-when">
            @if ($nextDate)
                <p class="cfr-next-label">Next approval</p>
                <p class="cfr-next-date">{{ $nextDate->format('d M Y') }}</p>
                <p class="master-help">{{ $nextDate->diffForHumans() }}</p>
            @elseif ($rule->isActive())
                <p class="cfr-next-label">Nothing left to ask</p>
                <p class="master-help">The window is complete — the rule will end itself.</p>
            @elseif ($rule->isDraft())
                <p class="cfr-next-label">Draft</p>
                <p class="master-help">No dates exist yet. Approval writes the plan.</p>
            @endif
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────── four figures --}}
    <div class="master-stats desktop-only" aria-label="This rule">
        <div class="master-stat master-stat--flat {{ $rule->transaction_type === 'credit' ? 'green' : 'orange' }}">
            <span class="icon" aria-hidden="true">{{ $rule->transaction_type === 'credit' ? '↓' : '↑' }}</span>
            <div>
                <p class="master-stat-title">Each occurrence</p>
                <p class="master-stat-value">{{ $money($rule->amount) }}</p>
                <p class="master-sub">{{ $rule->transaction_type === 'credit' ? 'received' : 'paid' }} · {{ $rule->frequencyLabel() }}</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-regular fa-calendar"></i></span>
            <div>
                <p class="master-stat-title">The window</p>
                <p class="master-stat-value">{{ $rule->starts_on?->format('d M Y') }}</p>
                <p class="master-sub">{{ $rule->windowLabel() }}</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
            <div>
                <p class="master-stat-title">Posted</p>
                <p class="master-stat-value">{{ number_format($released) }}</p>
                <p class="master-sub">{{ number_format($openDates) }} still waiting for approval</p>
            </div>
        </div>

        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
            <div>
                <p class="master-stat-title">Progress</p>
                <p class="master-stat-value">{{ $windowTotal ? $released.' / '.$windowTotal : number_format($released) }}</p>
                <p class="master-sub">{{ $windowTotal ? 'occurrences answered' : 'occurrences posted, no count set' }}</p>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────── the decision --}}
    {{-- The rule's own loop: somebody asks, somebody answers. Both halves are on
         the record — a status column cannot say who wanted this or why it was
         sent back, and those are the two questions actually asked about a
         standing payment. --}}
    <section class="master-card master-card--flat cfr-card" aria-labelledby="cfrDecisionTitle">
        <div class="cfr-card-head">
            <div>
                <p class="master-eyebrow">Approval</p>
                <h2 class="master-section-title" id="cfrDecisionTitle">Who asked, who answered</h2>
            </div>
            <p class="master-help">
                @if ($rule->isDraft() && ! $rule->isWaitingApproval())
                    A draft posts nothing. Send it for approval when it reads right.
                @elseif ($rule->isWaitingApproval())
                    Waiting on the office — approving writes the plan, and every date then asks on its own day.
                @elseif ($rule->isActive())
                    Approved. Nothing else needs a decision until the dates come due.
                @elseif ($rule->isPaused())
                    Paused — the undecided dates were withdrawn and will not come back on resume.
                @else
                    Ended. The record stays; nothing further will be asked.
                @endif
            </p>
        </div>

        <div class="cfr-decision-grid">
            <div class="cfr-decision-fact">
                <p class="cfr-decision-label">Written by</p>
                <p class="cfr-decision-value">{{ $rule->creator?->name ?: '—' }}</p>
                <p class="master-help">{{ $rule->created_at?->format('d M Y, H:i') }}</p>
            </div>

            <div class="cfr-decision-fact">
                <p class="cfr-decision-label">Asked by</p>
                <p class="cfr-decision-value">{{ $rule->requester?->name ?: '—' }}</p>
                <p class="master-help">{{ $rule->requested_at?->format('d M Y, H:i') ?: 'Not sent for approval yet' }}</p>
            </div>

            <div class="cfr-decision-fact">
                <p class="cfr-decision-label">Decided by</p>
                <p class="cfr-decision-value">{{ $rule->decider?->name ?: '—' }}</p>
                <p class="master-help">{{ $rule->decided_at?->format('d M Y, H:i') ?: 'No decision yet' }}</p>
            </div>

            <div class="cfr-decision-fact">
                <p class="cfr-decision-label">And what was said</p>
                <p class="cfr-decision-value">{{ $rule->decision_note ?: '—' }}</p>
                @if ($rule->paused_at)
                    <p class="master-help">Paused since {{ $rule->paused_at->format('d M Y, H:i') }}</p>
                @elseif ($rule->ended_at)
                    <p class="master-help">Ended {{ $rule->ended_at->format('d M Y, H:i') }}</p>
                @endif
            </div>
        </div>

        <div class="cfr-decision-doors">
            @if ($rule->isWaitingApproval())
                <form method="POST" action="{{ route('cashflows.recurring.sendBack', $rule) }}" class="cfr-decision-form">
                    @csrf
                    @method('PATCH')
                    <label class="master-label" for="cfrSendBack">Send it back with a reason</label>
                    <textarea class="master-textarea" id="cfrSendBack" name="decision_note" rows="2" required
                        placeholder="What has to change before this can run?">{{ old('decision_note') }}</textarea>
                    <button type="submit" class="master-btn master-btn-light master-btn-sm">Send it back</button>
                </form>
            @endif

            @if (! $rule->isEnded())
                <form method="POST" action="{{ route('cashflows.recurring.end', $rule) }}" class="cfr-decision-form">
                    @csrf
                    @method('PATCH')
                    <label class="master-label" for="cfrEnd">End the rule</label>
                    <textarea class="master-textarea" id="cfrEnd" name="decision_note" rows="2"
                        placeholder="Why is it stopping? (optional)"></textarea>
                    <button type="submit" class="master-btn master-btn-light master-btn-sm">End it</button>
                    <p class="master-help">Ending withdraws the undecided dates and keeps every decision already made.</p>
                </form>
            @endif

            @if ($rule->isDraft() && $released === 0)
                <form method="POST" action="{{ route('cashflows.recurring.destroy', $rule) }}" class="cfr-decision-form"
                    data-confirm="Delete this draft? Nothing was ever paid from it.">
                    @csrf
                    @method('DELETE')
                    <label class="master-label" for="cfrDelete">Delete the draft</label>
                    <p class="master-help">Only a draft nothing has been decided on can be removed.</p>
                    <button type="submit" class="master-btn master-btn-light master-btn-sm">Delete it</button>
                </form>
            @endif
        </div>
    </section>

    {{-- ──────────────────────────────────────────────────────────── the plan --}}
    <section class="master-card master-table-card master-card--flat" aria-labelledby="cfrPlanTitle">
        <div class="master-list-toolbar">
            <div>
                <p class="master-eyebrow">The plan</p>
                <h2 class="master-section-title" id="cfrPlanTitle">
                    {{ $rule->isDraft() ? 'What approval will write down' : 'Every date this rule has asked about' }}
                </h2>
            </div>
            <p class="master-list-hint">
                @if ($rule->isDraft())
                    Nothing here exists yet — the {{ number_format(count($preview)) }} date{{ count($preview) === 1 ? '' : 's' }}
                    below are what approving would plan, from the same calculator that writes them.
                @else
                    {{ number_format($occurrences->total()) }} date{{ $occurrences->total() === 1 ? '' : 's' }},
                    oldest first. A date may be approved from its effective date; it may be skipped any time.
                @endif
            </p>
        </div>

        @if ($rule->isDraft())
            {{-- The preview: deliberately the same table shape as the real plan,
                 so approving a draft does not change the shape of the page. --}}
            @if ($preview === [])
                <div class="master-list-empty">
                    <h3 class="master-list-empty-title">No dates at all</h3>
                    <p class="master-list-empty-text">
                        The window is empty — the end date is before the effective date, or the count is spent.
                        Edit the draft and give it a window worth planning.
                    </p>
                </div>
            @else
                <div class="master-table-wrap ui-mobile-cards">
                    <table class="master-table cfr-plan-table">
                        <thead>
                            <tr>
                                <th scope="col" class="cfr-col-seq">#</th>
                                <th scope="col">Effective date</th>
                                <th scope="col" class="cfr-col-money">Amount</th>
                                <th scope="col">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview as $sequence => $date)
                                <tr class="cfr-preview-row">
                                    <td class="cfr-col-seq" data-label="#">{{ $sequence }}</td>
                                    <td data-label="Effective date">{{ $date->format('d M Y') }}<span class="cfr-cell-sub">{{ $date->format('l') }}</span></td>
                                    <td class="cfr-col-money" data-label="Amount">{{ $money($rule->amount) }}</td>
                                    <td data-label="State"><span class="core-badge core-badge-neutral">Not written yet</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="master-list-hint cfr-plan-note">
                    The plan holds {{ number_format(\App\Services\RecurrenceVocabulary::PLAN_WINDOW) }} undecided dates at a
                    time and fills the window as the dates are answered, so “{{ strtolower($rule->frequencyLabel()) }}” costs the
                    same whether it runs for a year or for ten.
                </p>
            @endif
        @elseif ($occurrences->total() === 0)
            <div class="master-list-empty">
                <h3 class="master-list-empty-title">The plan is empty</h3>
                <p class="master-list-empty-text">
                    This rule has nothing to ask — every date it planned has been withdrawn.
                    @if ($rule->isPaused())
                        Resuming plans the next dates from today, on this rule's own day.
                    @endif
                </p>
            </div>
        @else
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table cfr-plan-table">
                    <thead>
                        <tr>
                            <th scope="col" class="cfr-col-seq">#</th>
                            <th scope="col">Effective date</th>
                            <th scope="col">State</th>
                            <th scope="col" class="cfr-col-money">Amount</th>
                            <th scope="col">Decided</th>
                            <th scope="col" class="cfr-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($occurrences as $occurrence)
                            <tr class="cfr-plan-row {{ $occurrence->isOverdue() ? 'is-late' : '' }}">
                                <td class="cfr-col-seq" data-label="#">{{ $occurrence->sequence }}</td>

                                <td data-label="Effective date">
                                    {{ $occurrence->effective_date?->format('d M Y') }}
                                    <span class="cfr-cell-sub">{{ $occurrence->effective_date?->format('l') }}</span>
                                </td>

                                <td data-label="State">
                                    <span class="core-badge core-badge-{{ $occurrence->cellTone() }}">
                                        {{ $occurrence->cellLabel() }}
                                    </span>
                                    @if ($occurrence->latenessLabel())
                                        <span class="cfr-cell-sub cfr-late">{{ $occurrence->latenessLabel() }}</span>
                                    @endif
                                </td>

                                <td class="cfr-col-money" data-label="Amount">
                                    {{ $money($occurrence->isApproved() && $occurrence->entry ? $occurrence->entry->amount() : $rule->amount) }}
                                    @if ($occurrence->decision_note)
                                        <span class="cfr-cell-sub" title="{{ $occurrence->decision_note }}">{{ $occurrence->decision_note }}</span>
                                    @endif
                                </td>

                                <td data-label="Decided">
                                    @if ($occurrence->decided_at)
                                        {{ $occurrence->decider?->name ?: 'Somebody' }}
                                        <span class="cfr-cell-sub">{{ $occurrence->decided_at->format('d M Y, H:i') }}</span>
                                    @else
                                        <span class="cfr-cell-sub">Not yet</span>
                                    @endif
                                </td>

                                <td class="cfr-col-actions" data-label="">
                                    @if ($occurrence->isApproved() && $occurrence->entry)
                                        <a class="master-btn master-btn-ghost master-btn-sm"
                                            href="{{ route('cashflows.show', $occurrence->entry) }}">Open the entry</a>
                                    @elseif ($occurrence->isPending() && $occurrence->isDue())
                                        <div class="cfr-plan-doors">
                                            <form method="POST" action="{{ route('cashflows.recurring.occurrences.approve', [$rule, $occurrence]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="master-btn master-btn-primary master-btn-sm">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('cashflows.recurring.occurrences.skip', [$rule, $occurrence]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="master-btn master-btn-light master-btn-sm">Skip</button>
                                            </form>
                                        </div>
                                    @elseif ($occurrence->isPending())
                                        <span class="cfr-cell-sub">Opens {{ $occurrence->effective_date?->format('d M Y') }}</span>
                                    @else
                                        <span class="cfr-cell-sub">{{ $occurrence->statusLabel() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$occurrences" />
        @endif
    </section>

    {{-- ──────────────────────────────────────────────────────────── the facts --}}
    <section class="master-card master-card--flat cfr-card" aria-labelledby="cfrFactsTitle">
        <div class="cfr-card-head">
            <div>
                <p class="master-eyebrow">The rule</p>
                <h2 class="master-section-title" id="cfrFactsTitle">Facts</h2>
            </div>
        </div>

        <dl class="cfr-facts">
            <div><dt>Effective from</dt><dd>{{ $rule->starts_on?->format('d M Y') }}</dd></div>
            <div><dt>Rhythm</dt><dd>{{ $rule->cadenceLabel() }}</dd></div>
            <div><dt>Window</dt><dd>{{ $rule->windowLabel() }}</dd></div>
            <div><dt>Account</dt><dd>{{ $rule->account?->account_name ?: '—' }}</dd></div>
            <div><dt>Category</dt><dd>{{ $rule->category?->name ?: '—' }}</dd></div>
            <div><dt>Payment mode</dt><dd>{{ $rule->payment_mode ? strtoupper($rule->payment_mode) : '—' }}</dd></div>
            <div><dt>Expense head</dt><dd>{{ $rule->expense_head ?: '—' }}</dd></div>
            <div><dt>Party</dt><dd>{{ $rule->partyLabel() ?: '—' }}</dd></div>
        </dl>

        @if ($rule->notes)
            <div class="cfr-notes">
                <p class="cfr-decision-label">Notes</p>
                <p>{!! nl2br(e($rule->notes)) !!}</p>
            </div>
        @endif
    </section>
</div>

{{-- ──────────────────────────────────────────────────────── editing a draft --}}
{{-- Only drafts open here, and the modal is the same form the list uses — one
     definition of a rule's fields, two doors to it. --}}
@if ($rule->isDraft())
    <span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

    <div class="master-modal" id="recurrenceRuleEditModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="recurrenceRuleEditTitle">
            <form method="POST" action="{{ route('cashflows.recurring.update', $rule) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="_dialog" value="recurrenceRuleEditModal">

                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-pen"></i></span>
                        <div>
                            <h3 class="master-modal-title" id="recurrenceRuleEditTitle">Edit the draft</h3>
                            <p class="master-modal-subtitle">A rule is editable until it is approved — after that it is ended and written again.</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="recurrenceRuleEditModal" aria-label="Close">&times;</button>
                </div>

                <div class="master-modal-body">
                    @if ($errors->any())
                        <div class="master-info-box is-danger" role="alert">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @include('cashflows.recurring.partials.rule-form', ['rule' => $rule, 'dialogId' => 'recurrenceRuleEdit'])
                </div>

                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="recurrenceRuleEditModal">Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save the draft
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflow-recurring.js') }}" defer></script>
@endpush
@endsection
