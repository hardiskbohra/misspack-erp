{{--
    The Feedback tab on a project.

    The ask is issued from here — the project record is where the office is
    standing when a project finishes, and a module that needs a separate page to
    be useful is a module that gets forgotten. Everything after issuing (reading
    the answer, owning the follow-up) links across to the module's own page,
    because that is where the queue lives.

    The markup is the record page's vocabulary, not the feedback sheet's: this is
    a `master-tab-panel` of shell cards like every other tab, and `feedback.css`
    (`.fb-*`) is only loaded by the module's own pages — a class from that sheet
    landing here is a class with no rules on this page at all.
--}}
@php
    $asks = $project->feedbackRequests ?? collect();
    $liveAsk = $asks->first(fn ($ask) => $ask->isLive());
    $answered = $asks->filter(fn ($ask) => $ask->response !== null);
@endphp

<section class="master-tab-panel" id="project-panel-feedback" role="tabpanel" aria-labelledby="project-tab-feedback">
    <div class="project-blocks">
        @if ($liveAsk)
            <section class="master-card master-card--flat project-feedback-ask" aria-labelledby="project-feedback-live">
                <div>
                    <p class="master-eyebrow">{{ $liveAsk->title() }} · {{ $liveAsk->stateLabel() }}</p>
                    <h2 class="master-section-title" id="project-feedback-live">The live link</h2>
                    <div class="project-feedback-link">
                        <input class="master-input" type="text" readonly value="{{ $liveAsk->url() }}"
                            id="project-feedback-link" aria-label="Feedback link">
                        <button type="button" class="master-btn master-btn-soft master-btn-sm"
                            data-copy-target="project-feedback-link">Copy</button>
                    </div>
                    <p class="project-feedback-meta">
                        {{ number_format($liveAsk->views) }} open{{ $liveAsk->views === 1 ? '' : 's' }}
                        · {{ $liveAsk->expires_at ? 'expires '.$liveAsk->expiresLabel() : 'no expiry' }}
                        · waiting {{ $liveAsk->created_at?->diffForHumans(null, true) }}
                    </p>
                </div>
                <div class="project-feedback-actions">
                    @if ($liveAsk->whatsappUrl($liveAsk->contactPhone()))
                        <a class="master-btn master-btn-soft master-btn-sm" target="_blank" rel="noopener"
                            href="{{ $liveAsk->whatsappUrl($liveAsk->contactPhone()) }}"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                    @endif
                    @if ($liveAsk->mailUrl($liveAsk->contactEmail()))
                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ $liveAsk->mailUrl($liveAsk->contactEmail()) }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</a>
                    @endif
                    <a class="master-btn master-btn-primary master-btn-sm" href="{{ route('feedback.show', $liveAsk) }}">Open</a>
                </div>
            </section>
        @endif

        @foreach ($answered as $ask)
            @php
                $response = $ask->response;
            @endphp
            <section class="master-card master-card--flat" aria-labelledby="project-feedback-answer-{{ $ask->id }}">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">{{ $ask->title() }} · {{ $response->submitted_at?->format('d M Y') }}</p>
                        <h2 class="master-section-title" id="project-feedback-answer-{{ $ask->id }}">
                            {{ $response->displayName() }} scored {{ $response->overall_rating }}/5
                        </h2>
                    </div>
                    <div class="master-section-meta">
                        <span class="master-badge band-{{ $response->band() }}">{{ $response->bandLabel() }}</span>
                    </div>
                </div>

                <div class="project-feedback-scores">
                    <div class="project-feedback-score">
                        <strong>{{ $response->nps_score }}<small>/10</small></strong>
                        <span>{{ $response->npsLabel() }} · recommends</span>
                    </div>
                    @foreach ($response->answers->take(3) as $answer)
                        <div class="project-feedback-score">
                            <strong>{{ $answer->score }}<small>/5</small></strong>
                            <span>{{ $answer->dimensionLabel() }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($response->could_improve)
                    <div class="project-feedback-note project-feedback-note--attention">
                        <p class="project-feedback-note-title">What could be better</p>
                        <p>{{ $response->could_improve }}</p>
                    </div>
                @elseif ($response->went_well)
                    <div class="project-feedback-note">
                        <p class="project-feedback-note-title">What went well</p>
                        <p>{{ $response->went_well }}</p>
                    </div>
                @endif

                <div class="project-feedback-foot">
                    @if ($response->hasOpenAction())
                        <span class="master-badge status-waiting">Follow-up open</span>
                    @elseif ($response->isDetractor())
                        <span class="master-badge status-issue">No follow-up — open one</span>
                    @endif
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('feedback.show', $ask) }}">Read and act</a>
                </div>
            </section>
        @endforeach

        @if (! $liveAsk)
            <section class="master-card master-card--flat" aria-labelledby="project-feedback-issue">
                <div class="master-section-head">
                    <div>
                        <p class="master-eyebrow">
                            {{ $project->status === 'completed' ? 'The project is complete' : 'Still running' }}
                        </p>
                        <h2 class="master-section-title" id="project-feedback-issue">Ask for feedback</h2>
                    </div>
                </div>

                <p class="project-feedback-lede">
                    @if ($project->status === 'completed')
                        This is the moment: the work is done, the pressure is off, and an honest answer is worth more than
                        any compliment later. The client gets a link that needs no login.
                    @else
                        A close-out ask belongs after delivery. If something is worth catching early, send a
                        <strong>mid-project pulse</strong> instead — a print or fitment problem found now costs less than
                        one found after the run.
                    @endif
                </p>

                <form method="POST" action="{{ route('feedback.store', $project) }}" class="project-feedback-form">
                    @csrf
                    <div class="project-feedback-fields">
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
            </section>
        @endif

        @if ($asks->isEmpty())
            <section class="master-card master-card--flat" aria-labelledby="project-feedback-never">
                <div class="master-empty-state">
                    <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                    <h2 id="project-feedback-never">This client has never been asked about this project</h2>
                    <p>
                        One live link per ask kind is allowed; issuing a second close-out ask replaces nothing — it is
                        refused while the first is still open.
                    </p>
                </div>
            </section>
        @endif
    </div>
</section>

@push('scripts')
    <script src="{{ $assetVer('assets/js/feedback.js') }}" defer></script>
@endpush
