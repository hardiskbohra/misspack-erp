@extends('layouts.app')

@section('page-title', $ask->title())

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('feedback.index') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All feedback
    </a>
    @if ($ask->project)
        <a class="master-btn master-btn-soft" href="{{ route('projects.show', $ask->project) }}#pd-tab-feedback">
            <i class="fa-solid fa-briefcase" aria-hidden="true"></i> Project
        </a>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/feedback.css') }}">
@endpush

@php
    $response = $ask->response;
    $canShare = $ask->isLive();
@endphp

<div class="fb fb-show">
    <header class="master-card master-header fb-ask-head">
        <div>
            <p class="master-eyebrow">
                {{ $ask->project?->project_number ?: 'Feedback' }} · {{ $ask->kindLabel() }}
            </p>
            <h1>{{ $ask->project?->name ?? 'Feedback ask' }}</h1>
            <p class="fb-ask-sub">
                {{ $ask->client?->company_name ?: $ask->client?->brand_name ?: 'Client' }}
                @if ($ask->contactName()) · {{ $ask->contactName() }} @endif
            </p>
        </div>
        <div class="fb-ask-state">
            <span class="fb-badge fb-badge--{{ $ask->stateTone() }}">{{ $ask->stateLabel() }}</span>
            <p class="fb-cell-sub">
                {{ number_format($ask->views) }} open{{ $ask->views === 1 ? '' : 's' }}
                · {{ $ask->expires_at ? 'expires '.$ask->expiresLabel() : 'no expiry' }}
                @if ($ask->reminder_count > 0) · {{ $ask->reminder_count }} reminder{{ $ask->reminder_count === 1 ? '' : 's' }} @endif
            </p>
        </div>
    </header>

    {{-- ─────────────────────────────────────────────── the link, while it is live --}}
    @if ($canShare)
        <div class="master-card master-card--flat fb-share">
            <div class="fb-card-head">
                <div>
                    <p class="master-eyebrow">Send it</p>
                    <h2 class="master-section-title">The link</h2>
                </div>
                @if ($ask->last_reminded_at)
                    <span class="fb-cell-sub">Last reminder {{ $ask->last_reminded_at->diffForHumans() }}</span>
                @endif
            </div>

            <div class="fb-link-row">
                <input class="master-input fb-link-input" type="text" readonly value="{{ $ask->url() }}"
                    aria-label="Feedback link" id="fb-link-value">
                <button type="button" class="master-btn master-btn-primary" data-copy-target="fb-link-value">
                    <i class="fa-regular fa-copy" aria-hidden="true"></i> Copy link
                </button>

                @if ($ask->whatsappUrl($ask->contactPhone()))
                    <a class="master-btn master-btn-soft" target="_blank" rel="noopener"
                        href="{{ $ask->whatsappUrl($ask->contactPhone()) }}">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp
                    </a>
                @endif

                @if ($ask->mailUrl($ask->contactEmail()))
                    <a class="master-btn master-btn-soft" href="{{ $ask->mailUrl($ask->contactEmail()) }}">
                        <i class="fa-regular fa-envelope" aria-hidden="true"></i> Email
                    </a>
                @endif
            </div>

            <p class="fb-cell-sub">
                The link is the whole of the authentication — whoever holds it can answer once. Every open is counted,
                so “they never looked at it” is a fact and not a guess.
            </p>

            <div class="fb-share-actions">
                @foreach (\App\Models\FeedbackRequest::CHANNELS as $channel => $label)
                    @continue($channel === 'portal')
                    <form method="POST" action="{{ route('feedback.shared', $ask) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="shared_via" value="{{ $channel }}">
                        <button class="master-btn master-btn-ghost master-btn-sm" type="submit">
                            Mark sent by {{ strtolower($label) }}
                        </button>
                    </form>
                @endforeach

                <form method="POST" action="{{ route('feedback.remind', $ask) }}">
                    @csrf
                    <button class="master-btn master-btn-soft master-btn-sm" type="submit"
                        @disabled(! $ask->canRemind())>
                        Remind ({{ $ask->reminder_count }}/{{ \App\Models\FeedbackRequest::MAX_REMINDERS }})
                    </button>
                </form>

                <form method="POST" action="{{ route('feedback.revoke', $ask) }}"
                    onsubmit="return confirm('Revoke this link? Answers already given are kept.')">
                    @csrf
                    @method('PATCH')
                    <button class="master-btn master-btn-danger-ghost master-btn-sm" type="submit">Revoke link</button>
                </form>
            </div>
        </div>
    @elseif (! $response)
        <div class="master-card master-card--flat">
            <div class="master-empty-state">
                <i class="fa-regular fa-circle-xmark" aria-hidden="true"></i>
                <p>
                    This link is {{ strtolower($ask->stateLabel()) }} and cannot be answered. Issue a fresh ask from the
                    project page if the client is still worth hearing from.
                </p>
                <form method="POST" action="{{ route('feedback.destroy', $ask) }}">
                    @csrf
                    @method('DELETE')
                    <button class="master-btn master-btn-ghost master-btn-sm" type="submit">Delete this ask</button>
                </form>
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────────────────────────── the answer --}}
    @if ($response)
        <div class="fb-split">
            <div class="master-card master-card--flat">
                <div class="fb-card-head">
                    <div>
                        <p class="master-eyebrow">Answered {{ $response->submitted_at?->format('d M Y') }}</p>
                        <h2 class="master-section-title">{{ $response->displayName() }}</h2>
                    </div>
                    <span class="fb-badge fb-badge--{{ $response->bandTone() }}">{{ $response->bandLabel() }}</span>
                </div>

                <div class="fb-scoreline">
                    <div class="fb-scoreline-item">
                        <strong>{{ $response->overall_rating }}<small>/5</small></strong>
                        <span>Overall</span>
                    </div>
                    <div class="fb-scoreline-item">
                        <strong>{{ $response->nps_score }}<small>/10</small></strong>
                        <span>{{ $response->npsLabel() }} · recommends</span>
                    </div>
                    <div class="fb-scoreline-item">
                        <strong>{{ $response->wouldOrderAgainLabel() }}</strong>
                        <span>Would work again</span>
                    </div>
                    <div class="fb-scoreline-item">
                        <strong>{{ $response->source === 'portal' ? 'Portal' : 'Link' }}</strong>
                        <span>Answered from</span>
                    </div>
                </div>

                <div class="fb-lines fb-lines--readonly">
                    @foreach ($response->answers as $answer)
                        <div class="fb-line">
                            <div class="fb-line-head">
                                <p class="fb-line-label">{{ $answer->dimensionLabel() }}</p>
                            </div>
                            <span class="fb-badge fb-badge--{{ $answer->tone() }}">{{ $answer->score }}/5 · {{ $answer->scoreLabel() }}</span>
                            @if ($answer->comment)
                                <p class="fb-line-hint">{{ $answer->comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($response->went_well)
                    <div class="fb-verbatim">
                        <p class="fb-verbatim-title">What went well</p>
                        <p>{{ $response->went_well }}</p>
                    </div>
                @endif

                @if ($response->could_improve)
                    <div class="fb-verbatim fb-verbatim--attention">
                        <p class="fb-verbatim-title">What could be better</p>
                        <p>{{ $response->could_improve }}</p>
                    </div>
                @endif

                @if ($response->testimonial)
                    <figure class="fb-quote">
                        <blockquote>{{ $response->testimonial }}</blockquote>
                        <figcaption>
                            <button type="button" class="master-btn master-btn-ghost master-btn-sm" data-copy-quote
                                data-quote="{{ $response->testimonial }} — {{ $response->respondent_name }}, {{ $response->clientName() }}">
                                Copy quote
                            </button>
                        </figcaption>
                    </figure>
                @endif
            </div>

            <div class="fb-side">
                {{-- Consent is the client's to give and the client's to change; the
                     score is not editable here, on purpose. --}}
                <div class="master-card master-card--flat">
                    <div class="fb-card-head">
                        <div>
                            <p class="master-eyebrow">Publishing</p>
                            <h2 class="master-section-title">Consent</h2>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('feedback.consent', $response) }}" class="fb-consent-form">
                        @csrf
                        @method('PATCH')
                        @foreach (\App\Services\FeedbackVocabulary::consentOptions() as $key => $label)
                            <label class="fb-choice-item">
                                <input type="checkbox" name="{{ $key }}" value="1" @checked($response->{$key})>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        <label class="fb-choice-item">
                            <input type="checkbox" name="attribution_consent" value="1" @checked($response->attribution_consent)>
                            <span>May be named beside the quote</span>
                        </label>
                        <p class="fb-cell-sub">The score and the words above never change. Only the client may ask for a different answer, and that is a fresh ask.</p>
                        <button class="master-btn master-btn-soft master-btn-sm" type="submit">Save consent</button>
                    </form>
                </div>

                <div class="master-card master-card--flat fb-timeline">
                    <p class="master-eyebrow">The record</p>
                    <h2 class="master-section-title">What the ask did</h2>
                    <ul class="fb-facts">
                        <li><span>Sent</span><strong>{{ $ask->created_at?->format('d M Y H:i') }}</strong></li>
                        <li><span>First opened</span><strong>{{ $ask->first_viewed_at?->format('d M Y H:i') ?? 'Never' }}</strong></li>
                        <li><span>Opens</span><strong>{{ number_format($ask->views) }}</strong></li>
                        <li><span>Answered</span><strong>{{ $response->submitted_at?->format('d M Y H:i') }}</strong></li>
                        <li><span>Channel</span><strong>{{ $ask->channelLabel() }}</strong></li>
                        <li><span>Reminders</span><strong>{{ $ask->reminder_count }} of {{ \App\Models\FeedbackRequest::MAX_REMINDERS }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- ────────────────────────────────────────────────────────── the loop --}}
        <div class="master-card master-card--flat">
            <div class="fb-card-head">
                <div>
                    <p class="master-eyebrow">Nobody has to notice it</p>
                    <h2 class="master-section-title">What we are doing about it</h2>
                </div>
                @if ($response->isDetractor() && ! $response->hasOpenAction())
                    <span class="fb-badge fb-badge--red">Unowned detractor</span>
                @endif
            </div>

            @forelse ($actions as $action)
                <div class="fb-action">
                    <div class="fb-action-head">
                        <span class="fb-badge fb-badge--{{ $action->severityTone() }}">{{ $action->severityLabel() }}</span>
                        <span class="fb-badge fb-badge--{{ $action->statusTone() }}">{{ $action->statusLabel() }}</span>
                        <strong>{{ $action->typeLabel() }}</strong>
                        <span class="fb-cell-sub">
                            {{ $action->owner?->name ? 'Owner: '.$action->owner->name : 'No owner yet' }}
                            · {{ $action->dueLabel() }}
                            @if ($action->client_notified_at) · client told {{ $action->client_notified_at->format('d M') }} @endif
                        </span>
                    </div>

                    @if ($action->task)
                        <p class="fb-cell-sub">Task: {{ $action->task->title }} ({{ $action->task->status }})</p>
                    @endif

                    <form method="POST" action="{{ route('feedback.actions.update', $action) }}" class="fb-action-form">
                        @csrf
                        @method('PATCH')
                        <div class="fb-action-grid">
                            <label class="master-field fb-field">
                                <span>Status</span>
                                <select name="status" required>
                                    @foreach ($actionStatuses as $key => $label)
                                        <option value="{{ $key }}" @selected($action->status === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="master-field fb-field">
                                <span>Owner</span>
                                <select name="owner_id">
                                    <option value="">Unassigned</option>
                                    @foreach ($actionOwners as $id => $name)
                                        <option value="{{ $id }}" @selected((int) $action->owner_id === (int) $id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="master-field fb-field">
                                <span>Due</span>
                                <input class="master-input" type="date" name="due_on" value="{{ $action->due_on?->toDateString() }}">
                            </label>
                            <label class="master-field fb-field">
                                <span>Severity</span>
                                <select name="severity">
                                    @foreach ($actionSeverities as $key => $label)
                                        <option value="{{ $key }}" @selected($action->severity === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <label class="master-field fb-field">
                            <span>What was done</span>
                            <textarea name="resolution_note" rows="2" maxlength="2000"
                                placeholder="The sentence the client may be told, in plain words.">{{ $action->resolution_note }}</textarea>
                        </label>

                        <div class="fb-action-foot">
                            <label class="fb-choice-item">
                                <input type="checkbox" name="notify_client" value="1"
                                    @disabled(! $action->isOpen() && $action->client_notified_at)>
                                <span>Tell the client what changed (portal notification + project log)</span>
                            </label>
                            <button class="master-btn master-btn-primary master-btn-sm" type="submit">Save</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="master-empty-state">
                    <i class="fa-regular fa-flag" aria-hidden="true"></i>
                    <p>
                        @if ($response->isDetractor())
                            No follow-up yet, and this answer scored low — that is the one thing this module exists to prevent.
                            Open one below.
                        @else
                            Nothing needed doing about this answer. Open a follow-up below if the words deserve one.
                        @endif
                    </p>
                </div>
            @endforelse

            <form method="POST" action="{{ route('feedback.actions.store', $response) }}" class="fb-action-new">
                @csrf
                <h3 class="fb-subhead">Open a follow-up</h3>
                <div class="fb-action-grid">
                    <label class="master-field fb-field">
                        <span>Type</span>
                        <select name="type" required>
                            @foreach ($actionTypes as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="master-field fb-field">
                        <span>Severity</span>
                        <select name="severity" required>
                            @foreach ($actionSeverities as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'normal')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="master-field fb-field">
                        <span>Owner</span>
                        <select name="owner_id">
                            <option value="">Unassigned</option>
                            @foreach ($actionOwners as $id => $name)
                                <option value="{{ $id }}" @selected((int) $ask->project?->assigned_to === (int) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="master-field fb-field">
                        <span>Due</span>
                        <input class="master-input" type="date" name="due_on" value="{{ now()->addDay()->toDateString() }}">
                    </label>
                </div>
                <label class="master-field fb-field">
                    <span>Note</span>
                    <textarea name="note" rows="2" maxlength="2000" placeholder="Optional — what this follow-up is for."></textarea>
                </label>
                <button class="master-btn master-btn-primary master-btn-sm" type="submit">Open follow-up</button>
            </form>
        </div>
    @endif
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/feedback.js') }}" defer></script>
@endpush
@endsection
