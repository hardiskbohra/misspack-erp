@extends('layouts.app')

@section('page-title', 'Feedback')

@section('page-actions')
    @if ($can['settings'])
        <a class="master-btn master-btn-ghost" href="{{ route('feedback.settings') }}">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i> Scorecard
        </a>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/feedback.css') }}">
@endpush
@php
    /* Every chip is one URL away from the others, so a chip keeps what it does
       not own: the kind, the period and the client travel together. */
    $chipUrl = function (array $overrides) {
        $keep = array_merge(request()->except(['page']), $overrides);

        return route('feedback.index', array_filter(
            $keep,
            fn ($value) => $value !== null && $value !== '' && $value !== 'all',
            ARRAY_FILTER_USE_BOTH
        ));
    };

    $npsShown = $summary['nps'] !== null;
    $firstAsk = $requests->firstItem() ?? 0;
    $lastAsk = $requests->lastItem() ?? 0;
    $attentionCount = $attention->count();
    $filtered = $applied !== [];
@endphp

<div class="fb fb-index master-list">

    {{-- The figures. The average is beside its denominator on purpose: an NPS of
         62 from four answers is a different claim from 62 out of forty. --}}
    <div class="master-stats desktop-only" aria-label="Feedback overview">
        <div class="master-stat master-stat--flat blue tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-paper-plane"></i></span>
            <div>
                <p class="master-stat-title">Asked</p>
                <p class="master-stat-value">{{ number_format($summary['asked']) }}</p>
                <p class="master-sub">
                    {{ number_format($summary['answered']) }} answered
                    @if ($summary['response_rate'] !== null)
                        · {{ rtrim(rtrim(number_format($summary['response_rate'], 1), '0'), '.') }}% response
                    @endif
                </p>
                <span class="tooltip-text">One ask is one link. A link nobody opened is the row worth chasing, which is why this list is of asks and not only of answers.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $summary['average'] !== null && $summary['average'] < 3.5 ? 'orange' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-star"></i></span>
            <div>
                <p class="master-stat-title">Average score</p>
                <p class="master-stat-value">{{ $summary['average'] !== null ? number_format($summary['average'], 1) : '—' }}<small>/5</small></p>
                <p class="master-sub">{{ \App\Services\FeedbackVocabulary::averageLabel($summary['average']) }}</p>
                <span class="tooltip-text">The average of answers, not of asks — a link nobody opened is not a zero.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $npsShown && $summary['nps'] < 0 ? 'red' : 'purple' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-bullhorn"></i></span>
            <div>
                <p class="master-stat-title">NPS</p>
                <p class="master-stat-value">{{ $npsShown ? $summary['nps'] : '—' }}</p>
                <p class="master-sub">
                    @if ($npsShown)
                        {{ $summary['promoters'] }} promoter{{ $summary['promoters'] === 1 ? '' : 's' }},
                        {{ $summary['detractors'] }} detractor{{ $summary['detractors'] === 1 ? '' : 's' }}
                        of {{ $summary['answered'] }}
                    @else
                        No answers yet
                    @endif
                </p>
                <span class="tooltip-text">Promoters minus detractors, as a share of everyone who answered. Never shown without its denominator.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $attentionCount > 0 ? 'red' : 'teal' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-flag"></i></span>
            <div>
                <p class="master-stat-title">Needs attention</p>
                <p class="master-stat-value">{{ number_format($summary['attention']) }}</p>
                <p class="master-sub">of {{ $summary['detractors'] }} detractor{{ $summary['detractors'] === 1 ? '' : 's' }} still unowned</p>
                <span class="tooltip-text">A low score raises its own follow-up. This counts the ones where nothing has been closed yet — the only number on this page that is still costing money.</span>
            </div>
        </div>
    </div>

    {{-- ───────────────────────────────────────────────────── needs attention --}}
    @if ($attention->isNotEmpty())
        <div class="master-card master-card--flat fb-attention fb-card--flush">
            <div class="fb-card-head">
                <div>
                    <p class="master-eyebrow">Act first</p>
                    <h2 class="master-section-title">Needs attention</h2>
                </div>
                <a class="master-btn master-btn-soft master-btn-sm" href="{{ $chipUrl(['band' => 'attention']) }}">See all</a>
            </div>

            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Client</th>
                            <th scope="col" class="desktop-only">Project</th>
                            <th scope="col">Score</th>
                            <th scope="col" class="desktop-only">Said</th>
                            <th scope="col" class="desktop-only">Waiting</th>
                            <th scope="col" class="fb-col-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attention as $response)
                            <tr>
                                <td>
                                    <strong>{{ $response->clientName() }}</strong>
                                    <span class="fb-cell-sub">{{ $response->respondent_name }}</span>
                                </td>
                                <td class="desktop-only">{{ $response->project?->name ?? '—' }}</td>
                                <td>
                                    <span class="fb-badge fb-badge--{{ $response->bandTone() }}">{{ $response->overall_rating }}/5</span>
                                    <span class="fb-cell-sub">{{ $response->nps_score }}/10 recommend</span>
                                </td>
                                <td class="desktop-only fb-cell-quote">
                                    {{ \Illuminate\Support\Str::limit($response->could_improve ?: $response->went_well ?: 'No comment left.', 70) }}
                                </td>
                                <td class="desktop-only">{{ $response->submitted_at?->diffForHumans(null, true) ?? '—' }}</td>
                                <td class="fb-col-actions">
                                    @if ($response->request)
                                        <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('feedback.show', $response->request) }}">Open</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────────────────────────── the list --}}
    <div class="master-card master-card--flat">
        <form method="GET" action="{{ route('feedback.index') }}">
            <div class="master-list-bar">
                <div class="master-list-chips">
                    <a class="master-list-chip {{ $state === 'all' ? 'is-active' : '' }}"
                        href="{{ $chipUrl(['state' => 'all']) }}">Everything
                        <span class="master-list-chip-count">{{ number_format($stateCounts['all']) }}</span></a>
                    @foreach ($stateOptions as $key => $label)
                        <a class="master-list-chip {{ $state === $key ? 'is-active' : '' }}"
                            href="{{ $chipUrl(['state' => $key]) }}">
                            {{ $label }}
                            <span class="master-list-chip-count">{{ number_format($stateCounts[$key] ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="master-list-chips">
                    @foreach ($kindOptions as $key => $label)
                        <a class="master-list-chip {{ $kind === $key ? 'is-active' : '' }}"
                            href="{{ $chipUrl(['kind' => $key]) }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            <div class="master-filter-row core-filter-toolbar">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="q" value="{{ $q }}"
                        placeholder="Search a client, a project or a sentence..." aria-label="Search feedback">
                </div>

                <x-filter-trigger drawer="feedbackFiltersDrawer" label="Filters"
                    :count="count($applied)" />
            </div>

            <x-drawer id="feedbackFiltersDrawer" title="Filter feedback" eyebrow="Feedback filters"
                subtitle="Keep the score, the ask and the period together — the chips carry the rest." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">The ask</h3>
                    <label class="master-field">
                        <span class="master-label">Kind</span>
                        <select class="master-select" name="kind">
                            <option value="all">Every ask</option>
                            @foreach ($kindOptions as $key => $label)
                                <option value="{{ $key }}" @selected($kind === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">The score</h3>
                    <label class="master-field">
                        <span class="master-label">Verdict</span>
                        <select class="master-select" name="band">
                            <option value="all">Any answer</option>
                            @foreach ($bandOptions as $key => $label)
                                <option value="{{ $key }}" @selected($band === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Who</h3>
                    <label class="master-field">
                        <span class="master-label">Client</span>
                        <select class="master-select" name="client">
                            <option value="0">Every client</option>
                            @foreach ($clients as $clientOption)
                                <option value="{{ $clientOption->id }}" @selected((int) $client === (int) $clientOption->id)>
                                    {{ $clientOption->company_name ?: $clientOption->brand_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    @if ($projects->isNotEmpty())
                        <label class="master-field">
                            <span class="master-label">Project</span>
                            <select class="master-select" name="project">
                                <option value="0">Every project</option>
                                @foreach ($projects as $projectOption)
                                    <option value="{{ $projectOption->id }}" @selected((int) $project === (int) $projectOption->id)>
                                        {{ $projectOption->project_number ? $projectOption->project_number.' · ' : '' }}{{ $projectOption->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Period</h3>
                    <div class="master-form-grid">
                        <label class="master-field">
                            <span class="master-label">From</span>
                            <input class="master-input" type="date" name="date_from" value="{{ $date_from }}">
                        </label>
                        <label class="master-field">
                            <span class="master-label">To</span>
                            <input class="master-input" type="date" name="date_to" value="{{ $date_to }}">
                        </label>
                    </div>
                </section>

                <x-slot:footer>
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('feedback.index') }}">Reset</a>
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
                <a class="master-list-applied-clear" href="{{ route('feedback.index') }}">Clear all</a>
            </div>
        @endif

        <div class="master-list-toolbar">
            <p class="master-list-hint"
                title="Newest ask on top. Every link is listed, whether it came back or not.">
                {{ $requests->total() === 0
                    ? 'No matching asks'
                    : 'Newest first · Showing '.$firstAsk.'–'.$lastAsk.' of '.number_format($requests->total()) }}
            </p>

            <div class="master-list-toolbar-actions">
                <a class="master-btn master-btn-light master-btn-sm"
                    href="{{ route('feedback.export', request()->query()) }}"
                    title="Every answer these filters match, as a spreadsheet">
                    <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV
                </a>

                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table" data-table-settings data-table-key="feedback-asks">
                <thead>
                    <tr>
                        <th scope="col">Client</th>
                        <th scope="col" class="desktop-only">Project</th>
                        <th scope="col">Ask</th>
                        <th scope="col">State</th>
                        <th scope="col" class="desktop-only">Score</th>
                        <th scope="col" class="desktop-only">Opens</th>
                        <th scope="col" class="desktop-only">Sent</th>
                        <th scope="col" class="fb-col-actions">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $ask)
                        <tr>
                            <td data-label="Client">
                                <strong>{{ $ask->client?->company_name ?: $ask->client?->brand_name ?: '—' }}</strong>
                                @if ($ask->shared_via)
                                    <span class="fb-cell-sub">via {{ $ask->channelLabel() }}</span>
                                @endif
                            </td>
                            <td class="desktop-only" data-label="Project">
                                {{ $ask->project?->name ?? '—' }}
                                <span class="fb-cell-sub">{{ $ask->project?->project_number }}</span>
                            </td>
                            <td data-label="Ask">{{ $ask->title() }}</td>
                            <td data-label="State">
                                <span class="fb-badge fb-badge--{{ $ask->stateTone() }}">{{ $ask->stateLabel() }}</span>
                                @if ($ask->reminder_count > 0 && $ask->state() !== \App\Models\FeedbackRequest::STATE_ANSWERED)
                                    <span class="fb-cell-sub">{{ $ask->reminder_count }} reminder{{ $ask->reminder_count === 1 ? '' : 's' }}</span>
                                @endif
                            </td>
                            <td class="desktop-only" data-label="Score">
                                @if ($ask->response)
                                    <span class="fb-badge fb-badge--{{ $ask->response->bandTone() }}">
                                        {{ $ask->response->overall_rating }}/5
                                    </span>
                                    <span class="fb-cell-sub">{{ $ask->response->nps_score }}/10</span>
                                @else
                                    <span class="fb-cell-sub">—</span>
                                @endif
                            </td>
                            <td class="desktop-only" data-label="Opens">{{ number_format($ask->views) }}</td>
                            <td class="desktop-only" data-label="Sent">
                                {{ $ask->created_at?->format('d M Y') }}
                                <span class="fb-cell-sub">expires {{ $ask->expiresLabel() }}</span>
                            </td>
                            <td class="fb-col-actions" data-label="">
                                <a class="master-btn master-btn-soft master-btn-sm" href="{{ route('feedback.show', $ask) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">💬</span>
                                    <h3 class="master-list-empty-title">No feedback asks yet</h3>
                                    <p class="master-list-empty-text">
                                        Open a project and use <strong>Ask for feedback</strong> — on the record page, or the
                                        moment it is marked complete.
                                    </p>
                                    <div class="master-list-empty-actions">
                                        <a class="master-btn master-btn-primary" href="{{ route('projects.index') }}">Go to projects</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :items="$requests" />
    </div>

    {{-- ─────────────────────────────────────── the lines and the good words --}}
    <div class="master-grid fb-split">
        <div class="master-card master-card--flat fb-card--flush">
            <div class="fb-card-head">
                <div>
                    <p class="master-eyebrow">Where we win and lose</p>
                    <h2 class="master-section-title">The lines</h2>
                </div>
            </div>

            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th scope="col">Line</th>
                            <th scope="col">Average</th>
                            <th scope="col" class="desktop-only">Scored</th>
                            <th scope="col" class="desktop-only">Low</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dimensions as $dimension)
                            <tr>
                                <td>
                                    <i class="fb-dot fb-dot--{{ $dimension['color'] }}" aria-hidden="true"></i>
                                    {{ $dimension['label'] }}
                                </td>
                                <td>
                                    @if ($dimension['average'] !== null)
                                        <span class="fb-badge fb-badge--{{ \App\Services\FeedbackVocabulary::scoreTone((int) round($dimension['average'])) }}">
                                            {{ number_format($dimension['average'], 1) }}/5
                                        </span>
                                        <span class="fb-meter" aria-hidden="true">
                                            <span style="width: {{ (int) round($dimension['average'] / 5 * 100) }}%"></span>
                                        </span>
                                    @else
                                        <span class="fb-cell-sub">No answers</span>
                                    @endif
                                </td>
                                <td class="desktop-only">{{ number_format($dimension['count']) }}</td>
                                <td class="desktop-only">
                                    {{ $dimension['low'] > 0 ? number_format($dimension['low']) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="fb-trend">
                <p class="fb-trend-title">Last {{ count($trend) }} months</p>
                <div class="fb-trend-bars">
                    @foreach ($trend as $month)
                        <div class="fb-trend-month" title="{{ $month['count'] }} answer{{ $month['count'] === 1 ? '' : 's' }}">
                            <span class="fb-trend-bar" style="height: {{ $month['average'] !== null ? max(6, (int) round($month['average'] / 5 * 100)) : 2 }}%"></span>
                            <span class="fb-trend-label">{{ $month['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="master-card master-card--flat fb-card">
            <div class="fb-card-head">
                <div>
                    <p class="master-eyebrow">Consented, quotable</p>
                    <h2 class="master-section-title">Testimonial bank</h2>
                </div>
            </div>

            @forelse ($quotable as $response)
                <figure class="fb-quote">
                    <blockquote>{{ $response->testimonial }}</blockquote>
                    <figcaption>
                        <strong>{{ $response->respondent_name }}</strong>
                        @if ($response->respondent_designation) · {{ $response->respondent_designation }} @endif
                        · {{ $response->clientName() }}
                        <span class="fb-cell-sub">{{ $response->project?->name }} · {{ $response->submitted_at?->format('M Y') }}</span>
                        <span class="fb-consent">
                            @foreach ($response->consentChannels() as $channel)
                                <span class="fb-badge fb-badge--blue">{{ \App\Services\FeedbackVocabulary::consentOptions()['publish_'.$channel] ?? $channel }}</span>
                            @endforeach
                        </span>
                        <button type="button" class="master-btn master-btn-ghost master-btn-sm" data-copy-quote
                            data-quote="{{ $response->testimonial }} — {{ $response->respondent_name }}, {{ $response->clientName() }}">
                            Copy quote
                        </button>
                    </figcaption>
                </figure>
            @empty
                <div class="master-list-empty">
                    <span class="master-list-empty-icon" aria-hidden="true">❝</span>
                    <h3 class="master-list-empty-title">No quotable answers yet</h3>
                    <p class="master-list-empty-text">
                        A client who scores us well is offered the consent boxes at the end of the form; their words land here.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/feedback.js') }}" defer></script>
@endpush
@endsection
