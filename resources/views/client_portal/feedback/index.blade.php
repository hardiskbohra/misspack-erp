@extends('client_portal.layouts.app')

@section('title', 'Feedback')
@section('page-title', 'Your feedback')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/feedback.css') }}">
@endpush
@php
    $open = $asks->filter(fn ($ask) => $ask->isLive());
    $closed = $asks->reject(fn ($ask) => $ask->isLive());
@endphp

<div class="fb fb-portal-index">
    <div class="cp-page-head">
        <div>
            <p class="cp-eyebrow">Your words, our record</p>
            <h1>Feedback</h1>
            <p>
                What you tell us goes to the people who ran your project. A low score opens a follow-up with an owner
                and a two-day clock, and we come back to you with what changed.
            </p>
        </div>
    </div>

    @unless ($available)
        <div class="cp-card">
            <div class="master-empty-state">
                <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                <p>Nothing has been asked of you yet, or the module is not set up on this install.</p>
            </div>
        </div>
    @else
        @if ($open->isNotEmpty())
            <h2 class="fb-subhead">Waiting for you</h2>
            @foreach ($open as $ask)
                <div class="cp-card fb-portal-ask">
                    <div>
                        <p class="cp-eyebrow">{{ $ask->title() }}</p>
                        <h3>{{ $ask->project?->name ?? 'A project' }}</h3>
                        <p class="fb-cell-sub">
                            Sent {{ $ask->created_at?->format('d M Y') }}
                            @if ($ask->expires_at) · open until {{ $ask->expires_at->format('d M Y') }} @endif
                        </p>
                    </div>
                    <a class="master-btn master-btn-primary" href="{{ route('client-portal.feedback.show', $ask) }}">Give feedback</a>
                </div>
            @endforeach
        @endif

        @if ($history->isNotEmpty())
            <h2 class="fb-subhead">What you have told us</h2>
            @foreach ($history as $response)
                <div class="cp-card fb-portal-answer">
                    <div class="fb-card-head">
                        <div>
                            <p class="cp-eyebrow">
                                {{ $response->project?->name ?? 'Project' }} · {{ $response->submitted_at?->format('d M Y') }}
                            </p>
                            <h3>{{ $response->overall_rating }}/5 · {{ $response->bandLabel() }}</h3>
                        </div>
                        <span class="fb-badge fb-badge--{{ $response->bandTone() }}">{{ $response->overall_rating }}/5</span>
                    </div>

                    @if ($response->went_well)
                        <p class="fb-cell-sub">You said: “{{ \Illuminate\Support\Str::limit($response->went_well, 160) }}”</p>
                    @elseif ($response->could_improve)
                        <p class="fb-cell-sub">You said: “{{ \Illuminate\Support\Str::limit($response->could_improve, 160) }}”</p>
                    @endif

                    @php
                        $resolved = $response->actions->firstWhere('client_notified_at', '!=', null);
                    @endphp
                    @if ($resolved)
                        <p class="fb-alert fb-alert--info">
                            <strong>We acted on this:</strong>
                            {{ $resolved->resolution_note ?: 'The issue was reviewed and addressed.' }}
                        </p>
                    @elseif ($response->hasOpenAction())
                        <p class="fb-cell-sub">The team has an open follow-up on this one.</p>
                    @endif
                </div>
            @endforeach
        @endif

        @if ($open->isEmpty() && $history->isEmpty())
            <div class="cp-card">
                <div class="master-empty-state">
                    <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                    <p>When a project finishes, we send a short form here and by link. It takes about two minutes.</p>
                </div>
            </div>
        @endif
    @endunless
</div>
@endsection
