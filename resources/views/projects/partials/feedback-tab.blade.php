{{--
    The Feedback tab on a project.

    The ask is issued from here — the project record is where the office is
    standing when a project finishes, and a module that needs a separate page to
    be useful is a module that gets forgotten. Everything after issuing (reading
    the answer, owning the follow-up) links across to the module's own page,
    because that is where the queue lives.
--}}
@php
    $asks = $project->feedbackRequests ?? collect();
    $liveAsk = $asks->first(fn ($ask) => $ask->isLive());
    $answered = $asks->filter(fn ($ask) => $ask->response !== null);
@endphp

<div class="fb fb-project-tab">
    @if ($liveAsk)
        <div class="master-card master-card--flat pd-card fb-inline-ask">
            <div>
                <p class="master-eyebrow">{{ $liveAsk->title() }} · {{ $liveAsk->stateLabel() }}</p>
                <p class="fb-inline-link">
                    <input class="master-input" type="text" readonly value="{{ $liveAsk->url() }}" id="fb-project-link" aria-label="Feedback link">
                    <button type="button" class="master-btn master-btn-soft master-btn-sm" data-copy-target="fb-project-link">Copy</button>
                </p>
                <p class="fb-cell-sub">
                    {{ number_format($liveAsk->views) }} open{{ $liveAsk->views === 1 ? '' : 's' }}
                    · {{ $liveAsk->expires_at ? 'expires '.$liveAsk->expiresLabel() : 'no expiry' }}
                    · waiting {{ $liveAsk->created_at?->diffForHumans(null, true) }}
                </p>
            </div>
            <div class="fb-inline-actions">
                @if ($liveAsk->whatsappUrl($liveAsk->contactPhone()))
                    <a class="master-btn master-btn-soft master-btn-sm" target="_blank" rel="noopener"
                        href="{{ $liveAsk->whatsappUrl($liveAsk->contactPhone()) }}"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                @endif
                @if ($liveAsk->mailUrl($liveAsk->contactEmail()))
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $liveAsk->mailUrl($liveAsk->contactEmail()) }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</a>
                @endif
                <a class="master-btn master-btn-primary master-btn-sm" href="{{ route('feedback.show', $liveAsk) }}">Open</a>
            </div>
        </div>
    @endif

    @if ($answered->isNotEmpty())
        @foreach ($answered as $ask)
            @php
                $response = $ask->response;
            @endphp
            <div class="master-card master-card--flat pd-card fb-project-answer">
                <div class="fb-card-head">
                    <div>
                        <p class="master-eyebrow">{{ $ask->title() }} · {{ $response->submitted_at?->format('d M Y') }}</p>
                        <h3 class="master-section-title">{{ $response->displayName() }} scored {{ $response->overall_rating }}/5</h3>
                    </div>
                    <span class="fb-badge fb-badge--{{ $response->bandTone() }}">{{ $response->bandLabel() }}</span>
                </div>

                <div class="fb-scoreline">
                    <div class="fb-scoreline-item">
                        <strong>{{ $response->nps_score }}<small>/10</small></strong>
                        <span>{{ $response->npsLabel() }} · recommends</span>
                    </div>
                    @foreach ($response->answers->take(3) as $answer)
                        <div class="fb-scoreline-item">
                            <strong>{{ $answer->score }}<small>/5</small></strong>
                            <span>{{ $answer->dimensionLabel() }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($response->could_improve)
                    <div class="fb-verbatim fb-verbatim--attention">
                        <p class="fb-verbatim-title">What could be better</p>
                        <p>{{ $response->could_improve }}</p>
                    </div>
                @elseif ($response->went_well)
                    <div class="fb-verbatim">
                        <p class="fb-verbatim-title">What went well</p>
                        <p>{{ $response->went_well }}</p>
                    </div>
                @endif

                <div class="fb-inline-actions">
                    @if ($response->hasOpenAction())
                        <span class="fb-badge fb-badge--orange">Follow-up open</span>
                    @elseif ($response->isDetractor())
                        <span class="fb-badge fb-badge--red">No follow-up — open one</span>
                    @endif
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('feedback.show', $ask) }}">Read and act</a>
                </div>
            </div>
        @endforeach
    @endif

    @if (! $liveAsk)
        <div class="master-card master-card--flat pd-card">
            <div class="fb-card-head">
                <div>
                    <p class="master-eyebrow">
                        @if ($project->status === 'completed')
                            The project is complete
                        @else
                            Still running
                        @endif
                    </p>
                    <h3 class="master-section-title">Ask for feedback</h3>
                </div>
            </div>

            <p class="fb-lede">
                @if ($project->status === 'completed')
                    This is the moment: the work is done, the pressure is off, and an honest answer is worth more than
                    any compliment later. The client gets a link that needs no login.
                @else
                    A close-out ask belongs after delivery. If something is worth catching early, send a
                    <strong>mid-project pulse</strong> instead — a print or fitment problem found now costs less than
                    one found after the run.
                @endif
            </p>

            <form method="POST" action="{{ route('feedback.store', $project) }}" class="fb-issue-form">
                @csrf
                <div class="fb-action-grid">
                    <label class="master-field">
                        <span class="master-label">What to ask for</span>
                        <select class="master-select" name="kind" required>
                            @foreach (\App\Models\FeedbackRequest::kindOptions() as $key => $label)
                                <option value="{{ $key }}" @selected($project->status === 'completed' ? $key === 'close_out' : $key === 'pulse')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="master-field">
                        <span class="master-label">Link open for</span>
                        <select class="master-select" name="expires_in_days">
                            @foreach (\App\Models\FeedbackRequest::expiryChoices() as $days => $label)
                                <option value="{{ $days }}" @selected($days === \App\Models\FeedbackRequest::DEFAULT_EXPIRY_DAYS)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <label class="master-field">
                    <span class="master-label">A line above the form <small>(optional)</small></span>
                    <input class="master-input" type="text" name="note" maxlength="255"
                        placeholder="e.g. Thank you for the Diwali order — two minutes would help us do better next time.">
                </label>
                <button class="master-btn master-btn-primary" type="submit">
                    <i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Issue feedback link
                </button>
            </form>
        </div>
    @endif

    @if ($asks->isEmpty())
        <p class="fb-cell-sub">
            This client has never been asked about {{ $project->name }}. One live link per ask kind is allowed; issuing
            a second close-out ask replaces nothing — it is refused while the first is still open.
        </p>
    @endif
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/feedback.js') }}" defer></script>
@endpush
