@extends('layouts.app')

@section('title', 'Settings · Leads')
@section('page-title', 'Settings · Leads')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/leads.css') }}">
        <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
    @endpush
    <div class="set master-list">

        @include('settings.partials.nav', ['current' => 'leads'])

        <div class="set-area">

            @if ($errors->any())
                <div class="master-info-box is-danger" role="alert">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="lead-settings-page">
                <div class="master-card master-card--flat set-head">
                    <span class="set-head-mark" aria-hidden="true"><i class="fa-solid fa-bullseye"></i></span>
                    <div>
                        <h1 class="set-head-title">Leads</h1>
                        <p class="master-sub">
                            The dropdown master data every lead form is filled from — its status and source, the finish and
                            printing asked for, and the currency a quote was given in.
                        </p>
                    </div>
                    <div class="set-head-actions">
                        <a href="{{ route('leads.index') }}" class="master-btn master-btn-light">Back to Leads</a>
                    </div>
                </div>

                @php($icons = [
                    'lead_status' => 'fa-list-check',
                    'lead_source' => 'fa-share-nodes',
                    'lead_priority' => 'fa-arrow-up-wide-short',
                    'finish' => 'fa-brush',
                    'printing' => 'fa-print',
                    'currency' => 'fa-coins',
                    'incoterm' => 'fa-ship',
                    'capacity_unit' => 'fa-ruler-combined',
                ])

                <div class="master-tabs-card">
                    <nav class="master-tabs" role="tablist" aria-label="Lead settings">
                        @foreach($groupOptions as $groupKey => $groupLabel)
                            <a class="master-tab {{ $activeTab === $groupKey ? 'is-active' : '' }}" role="tab" aria-selected="{{ $activeTab === $groupKey ? 'true' : 'false' }}" href="{{ route('settings.leads', ['tab' => $groupKey]) }}">
                                <i class="fa-solid {{ $icons[$groupKey] ?? 'fa-gear' }}" aria-hidden="true"></i>
                                {{ $groupLabel }}
                            </a>
                        @endforeach
                    </nav>

                    <div class="master-tab-panel ls-panel">
                        @php($activeLabel = $groupOptions[$activeTab] ?? 'Master Options')

                        <div class="ls-panel-title">
                            <div>
                                <h2>{{ $activeLabel }}</h2>
                                <p>Create, edit and deactivate {{ strtolower($activeLabel) }} options used in the lead forms.</p>
                            </div>
                        </div>

                        <div class="ls-sub-card">
                            <h3>Add {{ $activeLabel }} Option</h3>
                            <form method="POST" action="{{ route('settings.leads.store') }}">
                                @csrf
                                <input type="hidden" name="group" value="{{ $activeTab }}">

                                <div class="ls-form-grid">
                                    <div>
                                        <label class="master-label">Key</label>
                                        <input class="master-input" name="key" required placeholder="{{ in_array($activeTab, ['currency', 'incoterm']) ? 'INR' : 'new_option' }}">
                                    </div>
                                    <div>
                                        <label class="master-label">Label</label>
                                        <input class="master-input" name="label" required placeholder="Display label">
                                    </div>
                                    <div>
                                        <label class="master-label">Color</label>
                                        <input class="master-input" type="color" name="color" value="#4f83f1">
                                    </div>
                                    <div>
                                        <label class="master-label">Sort</label>
                                        <input class="master-input" type="number" min="0" name="sort_order" value="10">
                                    </div>
                                    <div>
                                        <label class="master-check">
                                            <input type="checkbox" name="is_active" value="1" checked>
                                            Active
                                        </label>
                                    </div>
                                </div>

                                <div style="margin-top:14px;">
                                    <button class="master-btn master-btn-primary" type="submit">Create Option</button>
                                </div>
                            </form>
                        </div>

                        <div class="ls-list">
                            @forelse($options as $option)
                                <form method="POST" action="{{ route('settings.leads.update', $option) }}" class="ls-row">
                                    @csrf
                                    <input type="hidden" name="group" value="{{ $option->group }}">

                                    <div>
                                        <label class="master-label">Key</label>
                                        <input class="master-input" name="key" value="{{ $option->key }}" required>
                                    </div>
                                    <div>
                                        <label class="master-label">Label</label>
                                        <input class="master-input" name="label" value="{{ $option->label }}" required>
                                    </div>
                                    <div>
                                        <label class="master-label">Color</label>
                                        <input class="master-input" type="color" name="color" value="{{ $option->color ?: '#4f83f1' }}">
                                        <div class="ls-color-preview" style="margin-top:6px;">
                                            <span class="ls-color-dot" style="background:{{ $option->color ?: '#4f83f1' }}"></span>
                                            {{ $option->color ?: '#4f83f1' }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="master-label">Sort</label>
                                        <input class="master-input" type="number" min="0" name="sort_order" value="{{ $option->sort_order }}">
                                    </div>
                                    <div>
                                        <label class="master-check">
                                            <input type="checkbox" name="is_active" value="1" {{ $option->is_active ? 'checked' : '' }}>
                                            Active
                                        </label>
                                    </div>
                                    <div class="ls-row-actions">
                                        <button class="master-btn master-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                                        <button class="master-btn master-btn-danger"
                                                type="submit"
                                                name="_method"
                                                value="DELETE"
                                                formaction="{{ route('settings.leads.destroy', $option) }}"
                                                data-confirm="Delete this option? Existing records using this key will keep their saved value.">
                                            Delete
                                        </button>
                                    </div>
                                </form>
                            @empty
                                <div class="ls-empty">No options found for {{ $activeLabel }}.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
