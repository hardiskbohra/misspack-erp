@extends('layouts.app')

@section('title', 'Lead Settings')
@section('page-title', 'Lead Settings')

@section('content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/leads.css') }}">
@endpush
<div class="lead-settings-page">
    <div class="ls-card ls-header">
        <div>
            <h1>Lead Settings</h1>
            <p>Manage lead and vendor quote dropdown master data.</p>
        </div>
        <div class="ls-actions">
            <a href="{{ route('leads.index') }}" class="ls-btn ls-btn-light">Back to Leads</a>
            <a href="{{ route('vendor-quotes.index') }}" class="ls-btn ls-btn-light">Vendor Quotes</a>
        </div>
    </div>

    @if(session('success'))
        <div class="ls-alert ls-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="ls-alert ls-alert-error">{{ $errors->first() }}</div>
    @endif

    @php($icons = [
        'lead_status' => 'fa-list-check',
        'lead_source' => 'fa-share-nodes',
        'lead_priority' => 'fa-arrow-up-wide-short',
        'finish' => 'fa-brush',
        'printing' => 'fa-print',
        'currency' => 'fa-coins',
        'quote_status' => 'fa-file-invoice-dollar',
        'incoterm' => 'fa-ship',
        'capacity_unit' => 'fa-ruler-combined',
    ])

    <div class="ls-card">
        <div class="ls-tabs">
            @foreach($groupOptions as $groupKey => $groupLabel)
                <a href="{{ route('leads.settings.index', ['tab' => $groupKey]) }}"
                   class="ls-tab {{ $activeTab === $groupKey ? 'active' : '' }}">
                    <i class="fa-solid {{ $icons[$groupKey] ?? 'fa-gear' }}"></i>
                    {{ $groupLabel }}
                </a>
            @endforeach
        </div>

        <div class="ls-panel">
            @php($activeLabel = $groupOptions[$activeTab] ?? 'Master Options')

            <div class="ls-panel-title">
                <div>
                    <h2>{{ $activeLabel }}</h2>
                    <p>Create, edit and deactivate {{ strtolower($activeLabel) }} options used in lead and vendor quote forms.</p>
                </div>
            </div>

            <div class="ls-sub-card">
                <h3>Add {{ $activeLabel }} Option</h3>
                <form method="POST" action="{{ route('leads.settings.store') }}">
                    @csrf
                    <input type="hidden" name="group" value="{{ $activeTab }}">

                    <div class="ls-form-grid">
                        <div>
                            <label class="ls-label">Key</label>
                            <input class="ls-input" name="key" required placeholder="{{ in_array($activeTab, ['currency', 'incoterm']) ? 'INR' : 'new_option' }}">
                        </div>
                        <div>
                            <label class="ls-label">Label</label>
                            <input class="ls-input" name="label" required placeholder="Display label">
                        </div>
                        <div>
                            <label class="ls-label">Color</label>
                            <input class="ls-input" type="color" name="color" value="#4f83f1">
                        </div>
                        <div>
                            <label class="ls-label">Sort</label>
                            <input class="ls-input" type="number" min="0" name="sort_order" value="10">
                        </div>
                        <div>
                            <label class="ls-check">
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active
                            </label>
                        </div>
                    </div>

                    <div style="margin-top:14px;">
                        <button class="ls-btn ls-btn-primary" type="submit">Create Option</button>
                    </div>
                </form>
            </div>

            <div class="ls-list">
                @forelse($options as $option)
                    <form method="POST" action="{{ route('leads.settings.update', $option) }}" class="ls-row">
                        @csrf
                        <input type="hidden" name="group" value="{{ $option->group }}">

                        <div>
                            <label class="ls-label">Key</label>
                            <input class="ls-input" name="key" value="{{ $option->key }}" required>
                        </div>
                        <div>
                            <label class="ls-label">Label</label>
                            <input class="ls-input" name="label" value="{{ $option->label }}" required>
                        </div>
                        <div>
                            <label class="ls-label">Color</label>
                            <input class="ls-input" type="color" name="color" value="{{ $option->color ?: '#4f83f1' }}">
                            <div class="ls-color-preview" style="margin-top:6px;">
                                <span class="ls-color-dot" style="background:{{ $option->color ?: '#4f83f1' }}"></span>
                                {{ $option->color ?: '#4f83f1' }}
                            </div>
                        </div>
                        <div>
                            <label class="ls-label">Sort</label>
                            <input class="ls-input" type="number" min="0" name="sort_order" value="{{ $option->sort_order }}">
                        </div>
                        <div>
                            <label class="ls-check">
                                <input type="checkbox" name="is_active" value="1" {{ $option->is_active ? 'checked' : '' }}>
                                Active
                            </label>
                        </div>
                        <div class="ls-row-actions">
                            <button class="ls-btn ls-btn-primary" type="submit" name="_method" value="PUT">Save</button>
                            <button class="ls-btn ls-btn-danger"
                                    type="submit"
                                    name="_method"
                                    value="DELETE"
                                    formaction="{{ route('leads.settings.destroy', $option) }}"
                                    onclick="return confirm('Delete this option? Existing records using this key will keep their saved value.')">
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
@endsection
