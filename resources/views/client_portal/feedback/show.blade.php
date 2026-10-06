@extends('client_portal.layouts.app')

@section('title', 'Feedback')
@section('page-title', 'How did we do?')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/feedback.css') }}">
@endpush

<div class="fb fb-portal-show">
    <div class="cp-page-head">
        <div>
            <p class="cp-eyebrow">{{ $ask->title() }}</p>
            <h1>{{ $ask->project?->name ?? 'Your project' }}</h1>
            <p>{{ $intro }}</p>
            @if ($ask->note)
                <p class="fb-intro-note">{{ $ask->note }}</p>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="fb-alert fb-alert--error" role="alert">
            <strong>Not quite submitted.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('feedback.partials.form', [
        'ask' => $ask,
        'dimensions' => $dimensions,
        'action' => $action,
        'minimumAnswers' => $minimumAnswers,
        'prefill' => $prefill,
    ])
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/feedback.js') }}" defer></script>
@endpush
@endsection
