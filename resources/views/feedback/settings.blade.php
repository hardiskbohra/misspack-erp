@extends('layouts.app')

@section('page-title', 'Feedback scorecard')

@section('page-actions')
    <a class="master-btn master-btn-ghost" href="{{ route('feedback.index') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All feedback
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/feedback.css') }}">
@endpush

<div class="fb fb-settings">
    <div class="master-card master-card--flat fb-card">
        <div class="fb-card-head">
            <div>
                <p class="master-eyebrow">The questions</p>
                <h1 class="master-section-title">What we ask about</h1>
            </div>
        </div>

        <p class="fb-lede">
            These are the lines on the form — the same list the report groups by and the "needs attention" queue
            scores against. Add one when the business starts caring about something new, retire one when it stops.
            <strong>Retiring is safe</strong>: the answers that scored it keep their numbers and keep wearing its name.
            Deleting is only possible while nothing has ever been scored on it.
        </p>

        @if ($dimensions->isEmpty())
            <div class="fb-alert fb-alert--info">
                No dimensions are saved, so the built-in six are on the form. Edit one below and the list becomes yours.
            </div>
        @endif

        <div class="fb-dimension-list">
            @foreach ($dimensions as $index => $dimension)
                <form method="POST" action="{{ route('feedback.dimensions.update', $dimension) }}" class="fb-dimension">
                    @csrf
                    @method('PATCH')

                    <div class="fb-dimension-head">
                        <span class="fb-dot fb-dot--{{ $dimension->color ?: 'blue' }}" aria-hidden="true"></span>
                        <code class="fb-dimension-key">{{ $dimension->key }}</code>
                        @unless ($dimension->is_active)
                            <span class="fb-badge fb-badge--grey">Retired</span>
                        @endunless
                    </div>

                    <label class="master-field">
                        <span class="master-label">Question</span>
                        <input class="master-input" type="text" name="label" value="{{ $dimension->label }}" required maxlength="80">
                    </label>

                    <label class="master-field">
                        <span class="master-label">Hint under it</span>
                        <input class="master-input" type="text" name="hint" value="{{ $dimension->hint }}" maxlength="255"
                            placeholder="What the client should think about when scoring this line.">
                    </label>

                    <div class="fb-dimension-row">
                        <label class="master-field">
                            <span class="master-label">Colour</span>
                            <select class="master-select" name="color">
                                @foreach (['blue', 'teal', 'green', 'orange', 'purple', 'red'] as $color)
                                    <option value="{{ $color }}" @selected(($dimension->color ?: 'blue') === $color)>{{ ucfirst($color) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Order</span>
                            <input class="master-input" type="number" name="sort_order" value="{{ $dimension->sort_order }}" min="0" max="999">
                        </label>
                        <label class="fb-choice-item fb-choice-item--inline">
                            <input type="checkbox" name="is_active" value="1" @checked($dimension->is_active)>
                            <span>On the form</span>
                        </label>
                    </div>

                    <div class="fb-dimension-actions">
                        <button class="master-btn master-btn-primary master-btn-sm" type="submit">Save</button>
                        <span class="fb-cell-sub">Answers scored so far: {{ \App\Models\FeedbackAnswer::query()->where('dimension', $dimension->key)->count() }}</span>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

    <div class="master-card master-card--flat fb-card">
        <div class="fb-card-head">
            <div>
                <p class="master-eyebrow">Add a line</p>
                <h2 class="master-section-title">New dimension</h2>
            </div>
        </div>

        <form method="POST" action="{{ route('feedback.dimensions.store') }}" class="fb-dimension-new">
            @csrf
            <div class="fb-dimension-row">
                <label class="master-field">
                    <span class="master-label">Question</span>
                    <input class="master-input" type="text" name="label" required maxlength="80"
                        placeholder="e.g. Support after delivery">
                </label>
                <label class="master-field">
                    <span class="master-label">Hint</span>
                    <input class="master-input" type="text" name="hint" maxlength="255"
                        placeholder="Did somebody answer when something went wrong?">
                </label>
                <label class="master-field">
                    <span class="master-label">Colour</span>
                    <select class="master-select" name="color">
                        @foreach (['blue', 'teal', 'green', 'orange', 'purple', 'red'] as $color)
                            <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <button class="master-btn master-btn-primary" type="submit">Add dimension</button>
            <p class="fb-cell-sub">
                It appears on the next ask, not on the ones already sent — a client halfway through a form should not
                see it change under them.
            </p>
        </form>
    </div>
</div>
@endsection
