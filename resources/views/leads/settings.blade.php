@extends('layouts.app')

@section('title', 'Lead Settings')
@section('page-title', 'Lead Settings')

@section('content')
<style>
    :root {
        --ls-primary: #4f83f1;
        --ls-primary-2: #6366f1;
        --ls-dark: #17233b;
        --ls-muted: #687386;
        --ls-border: #dfe7f3;
        --ls-bg: #eef3ff;
        --ls-soft: #edf5ff;
        --ls-white: #ffffff;
        --ls-red: #ef4770;
        --ls-green: #10b981;
        --ls-shadow: 0 14px 35px rgba(25, 42, 70, 0.08);
    }

    .lead-settings-page,
    .lead-settings-page * {
        box-sizing: border-box;
    }

    .lead-settings-page {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
        color: var(--ls-dark);
        font-size: 14px;
    }

    .ls-card {
        background: var(--bg-card, var(--ls-white));
        border: 1px solid var(--border, var(--ls-border));
        border-radius: 18px;
        box-shadow: var(--shadow, var(--ls-shadow));
    }

    .ls-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 22px 26px;
        margin-bottom: 22px;
    }

    .ls-header h1 {
        margin: 0;
        font-size: 22px;
        font-weight: 900;
        color: var(--text-primary, var(--ls-dark));
    }

    .ls-header p {
        margin: 5px 0 0;
        color: var(--text-muted, var(--ls-muted));
        font-weight: 700;
    }

    .ls-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .ls-btn {
        min-height: 40px;
        border: 0;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap;
        transition: .2s ease;
    }

    .ls-btn-primary {
        background: linear-gradient(135deg, var(--ls-primary), var(--ls-primary-2));
        color: #fff;
    }

    .ls-btn-light {
        background: var(--bg-input, #f3f6fb);
        color: var(--text-primary, var(--ls-dark));
        border: 1px solid var(--border, var(--ls-border));
    }

    .ls-btn-danger {
        background: #fff0f4;
        color: #e11d48;
        border: 1px solid #fecdd3;
    }

    .ls-tabs {
        display: flex;
        gap: 0;
        overflow-x: auto;
        border-bottom: 1px solid var(--border, var(--ls-border));
        background: rgba(79, 131, 241, 0.03);
    }

    .ls-tab {
        padding: 16px 20px;
        color: var(--text-secondary, var(--ls-muted));
        text-decoration: none;
        font-weight: 900;
        border-bottom: 3px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        transition: .2s ease;
    }

    .ls-tab.active {
        color: var(--ls-primary);
        border-bottom-color: var(--ls-primary);
        background: var(--bg-card, #fff);
    }

    .ls-panel {
        padding: 22px;
    }

    .ls-panel-title {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
    }

    .ls-panel-title h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 900;
        color: var(--text-primary, var(--ls-dark));
    }

    .ls-panel-title p {
        margin: 4px 0 0;
        color: var(--text-muted, var(--ls-muted));
        font-weight: 700;
        font-size: 13px;
    }

    .ls-sub-card {
        padding: 18px;
        margin-bottom: 22px;
        border: 1px solid var(--border, var(--ls-border));
        border-radius: 16px;
        background: var(--bg-secondary, #fbfdff);
    }

    .ls-sub-card h3 {
        margin: 0 0 14px;
        font-size: 16px;
        font-weight: 900;
        color: var(--text-primary, var(--ls-dark));
    }

    .ls-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 150px 130px 130px;
        gap: 12px;
        align-items: end;
    }

    .ls-label {
        display: block;
        margin-bottom: 7px;
        color: var(--text-secondary, #536079);
        font-size: 12px;
        font-weight: 900;
    }

    .ls-input,
    .ls-select {
        width: 100%;
        height: 42px;
        border: 1px solid var(--border, #d8e2ef);
        border-radius: 12px;
        background: var(--bg-input, #fff);
        color: var(--text-primary, var(--ls-dark));
        padding: 9px 12px;
        font-size: 14px;
        font-weight: 700;
        outline: none;
    }

    .ls-input:focus,
    .ls-select:focus {
        border-color: var(--ls-primary);
        box-shadow: 0 0 0 3px rgba(79, 131, 241, .12);
    }

    .ls-check {
        min-height: 42px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 900;
        color: var(--text-secondary, #536079);
    }

    .ls-list {
        display: grid;
        gap: 12px;
    }

    .ls-row {
        display: grid;
        grid-template-columns: minmax(160px, 1fr) minmax(180px, 1.2fr) 120px 100px 110px auto;
        gap: 10px;
        align-items: end;
        padding: 14px;
        border: 1px solid var(--border, var(--ls-border));
        border-radius: 14px;
        background: var(--bg-card, #fff);
    }

    .ls-row-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .ls-color-preview {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-muted, var(--ls-muted));
        font-weight: 800;
        font-size: 12px;
    }

    .ls-color-dot {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 1px var(--border, var(--ls-border));
        display: inline-block;
        flex: 0 0 18px;
    }

    .ls-empty {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted, var(--ls-muted));
        font-weight: 800;
        border: 1px dashed var(--border, var(--ls-border));
        border-radius: 16px;
    }

    .ls-alert {
        padding: 14px 16px;
        margin-bottom: 18px;
        border-radius: 14px;
        font-weight: 800;
    }

    .ls-alert-success {
        color: #047857;
        background: #e8fff7;
        border: 1px solid #a7f3d0;
    }

    .ls-alert-error {
        color: #be123c;
        background: #fff0f4;
        border: 1px solid #fecdd3;
    }

    @media (max-width: 1199px) {
        .ls-form-grid,
        .ls-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ls-row-actions {
            grid-column: 1 / -1;
            justify-content: flex-end;
        }
    }

    @media (max-width: 767px) {
        .ls-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 18px;
        }

        .ls-actions,
        .ls-actions .ls-btn {
            width: 100%;
        }

        .ls-tabs {
            padding: 0 4px;
        }

        .ls-tab {
            padding: 14px 15px;
            font-size: 13px;
        }

        .ls-panel {
            padding: 16px;
        }

        .ls-panel-title {
            flex-direction: column;
        }

        .ls-form-grid,
        .ls-row {
            grid-template-columns: 1fr;
        }

        .ls-row-actions {
            justify-content: stretch;
            flex-direction: column;
        }

        .ls-row-actions .ls-btn {
            width: 100%;
        }
    }
</style>

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
