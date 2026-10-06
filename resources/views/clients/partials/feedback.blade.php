{{--
    What this client has said, across every project.

    This is the panel sales opens before quoting the next order, so it leads with
    the trend and the verdicts, not with a count of forms: a client whose scores
    are sliding is a client to call before the renewal is due, and the panel says
    so in one line.
--}}
@php
    $available = $feedbackAvailable ?? false;
    $history = $available ? $feedbackHistory : collect();
    $asks = $available ? $feedbackAsks : collect();
    $summary = $feedbackSummary;
@endphp

@unless ($available)
    <div class="master-empty-state">
        <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
        <p>Feedback is not set up on this install yet. Run the feedback migrations and this panel fills itself from the module's own rows.</p>
    </div>
@else
    <div class="fb fb-client-panel client-detail-tools">
        @if ($history->isNotEmpty())
            <div class="master-stats">
                <div class="master-stat master-stat--flat blue">
                    <span class="icon" aria-hidden="true">✉️</span>
                    <div>
                        <p class="master-stat-title">Asked</p>
                        <p class="master-stat-value">{{ number_format($summary['asked']) }}</p>
                        <p class="master-sub">{{ number_format($summary['answered']) }} answered</p>
                    </div>
                </div>
                <div class="master-stat master-stat--flat {{ $summary['average'] !== null && $summary['average'] < 3.5 ? 'orange' : 'green' }}">
                    <span class="icon" aria-hidden="true">⭐</span>
                    <div>
                        <p class="master-stat-title">Average</p>
                        <p class="master-stat-value">{{ $summary['average'] !== null ? number_format($summary['average'], 1) : '—' }}<small>/5</small></p>
                        <p class="master-sub">{{ \App\Services\FeedbackVocabulary::averageLabel($summary['average']) }}</p>
                    </div>
                </div>
                <div class="master-stat master-stat--flat {{ $summary['nps'] !== null && $summary['nps'] < 0 ? 'red' : 'purple' }}">
                    <span class="icon" aria-hidden="true">📣</span>
                    <div>
                        <p class="master-stat-title">NPS</p>
                        <p class="master-stat-value">{{ $summary['nps'] ?? '—' }}</p>
                        <p class="master-sub">{{ $summary['detractors'] }} detractor{{ $summary['detractors'] === 1 ? '' : 's' }} of {{ $summary['answered'] }}</p>
                    </div>
                </div>
                <div class="master-stat master-stat--flat {{ $summary['attention'] > 0 ? 'red' : 'teal' }}">
                    <span class="icon" aria-hidden="true">🚩</span>
                    <div>
                        <p class="master-stat-title">Open follow-ups</p>
                        <p class="master-stat-value">{{ number_format($history->filter(fn ($response) => $response->hasOpenAction())->count()) }}</p>
                        <p class="master-sub">still being worked</p>
                    </div>
                </div>
            </div>

            @php
                $latest = $history->first();
                $previous = $history->skip(1)->first();
                $sliding = $latest && $previous && $latest->overall_rating < $previous->overall_rating;
            @endphp

            <div class="fb-alert {{ $sliding ? 'fb-alert--attention' : 'fb-alert--info' }}">
                @if ($sliding)
                    <strong>Sliding:</strong> the last answer scored {{ $latest->overall_rating }}/5 against
                    {{ $previous->overall_rating }}/5 the time before. Worth a call before the next order is quoted.
                @elseif ($latest)
                    <strong>Steady:</strong> the last answer scored {{ $latest->overall_rating }}/5 on
                    {{ $latest->submitted_at?->format('d M Y') }}. Nothing to chase.
                @else
                    No answers yet — the asks below are still open.
                @endif
            </div>
        @endif

        @forelse ($history as $response)
            <div class="master-card master-card--flat client-detail-card fb-client-answer">
                <div class="fb-card-head">
                    <div>
                        <p class="master-eyebrow">
                            {{ $response->project?->name ?? 'Project' }} ·
                            {{ $response->request?->kindLabel() }} ·
                            {{ $response->submitted_at?->format('d M Y') }}
                        </p>
                        <h3 class="master-section-title">{{ $response->displayName() }} scored {{ $response->overall_rating }}/5</h3>
                    </div>
                    <span class="fb-badge fb-badge--{{ $response->bandTone() }}">{{ $response->bandLabel() }}</span>
                </div>

                <div class="fb-scoreline">
                    <div class="fb-scoreline-item">
                        <strong>{{ $response->nps_score }}<small>/10</small></strong>
                        <span>{{ $response->npsLabel() }} · recommends</span>
                    </div>
                    @foreach ($response->answers as $answer)
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

                @if ($response->actions->isNotEmpty())
                    <ul class="fb-facts">
                        @foreach ($response->actions as $action)
                            <li>
                                <span>{{ $action->typeLabel() }}</span>
                                <strong>{{ $action->statusLabel() }}{{ $action->owner ? ' · '.$action->owner->name : '' }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($response->request)
                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('feedback.show', $response->request) }}">Open in Feedback</a>
                @endif
            </div>
        @empty
            <div class="master-empty-state">
                <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                <p>
                    @if ($asks->isNotEmpty())
                        {{ $asks->count() }} ask{{ $asks->count() === 1 ? '' : 's' }} sent; none answered yet.
                    @else
                        No feedback has been asked of this client. Open a completed project and use <strong>Ask for feedback</strong>.
                    @endif
                </p>
            </div>
        @endforelse
    </div>
@endunless
