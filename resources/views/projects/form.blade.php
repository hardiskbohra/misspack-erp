@extends('layouts.app')

@section('page-title', $isEdit ? 'Edit Project' : 'Create Project')

@section('content')
    @php
        $startValue = old(
            'start_date',
            $project->start_date
                ? (is_object($project->start_date)
                    ? $project->start_date->format('Y-m-d')
                    : $project->start_date)
                : '',
        );
        $targetValue = old(
            'target_date',
            $project->target_date
                ? (is_object($project->target_date)
                    ? $project->target_date->format('Y-m-d')
                    : $project->target_date)
                : '',
        );
    @endphp
    <div class="project-form">
        
        <div class="master-card master-header">
            <h1>{{ $isEdit ? 'Edit Project' : 'Create Project' }}</h1>
            <div class="master-breadcrumb">
                <a href="{{ url('/') }}">Home</a><span>•</span>
                <a href="{{ $isEdit ? route('projects.show', $project) : route('projects.index') }}">Projects</a><span>•</span>
                <span class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span>
                </div>
        </div>

        <form method="POST" action="{{ $isEdit ? route('projects.update', $project) : route('projects.store') }}"
            class="pf-card">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="pf-section-head">
                <div>
                    <h3 class="master-section-title">Project Basics</h3>
                </div>
                @if ($quote)
                    <span class="pf-quote-badge"><i class="fa-solid fa-file-signature"></i> From Quote:
                        {{ $quote->quote_number ?? '#' . $quote->id }}</span>
                @endif
            </div>

            <div class="pf-grid">
                <div class="master-field">
                    <label class="master-label">Project Number</label>
                    <input class="master-input" type="text" name="project_number"
                        value="{{ old('project_number', $project->project_number) }}" placeholder="Auto generated if blank">
                </div>
                <div class="master-field">
                    <label class="master-label">Client <span>*</span></label>
                    <select class="master-select" name="client_id" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ (string) old('client_id', $project->client_id) === (string) $client->id ? 'selected' : '' }}>
                                {{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Accepted Customer Quote</label>
                    <select class="master-select" name="customer_quote_id">
                        <option value="">No quote mapping</option>
                        @foreach ($quotes as $customerQuote)
                            <option value="{{ $customerQuote->id }}"
                                {{ (string) old('customer_quote_id', $project->customer_quote_id) === (string) $customerQuote->id ? 'selected' : '' }}>
                                {{ $customerQuote->quote_number }} - {{ $customerQuote->title }}</option>
                        @endforeach
                        @if ($quote && !$quotes->contains('id', $quote->id))
                            <option value="{{ $quote->id }}" selected>
                                {{ $quote->quote_number ?? 'Quote #' . $quote->id }} - {{ $quote->title }}</option>
                        @endif
                    </select>
                    <small>Use this when project is created after quote finalisation.</small>
                </div>
                <div class="master-field pf-span-2">
                    <label class="master-label">Project Name <span>*</span></label>
                    <input class="master-input" type="text" name="name" value="{{ old('name', $project->name) }}" required
                        placeholder="e.g. 100ml Mist Spray Bottle July Order">
                </div>
                <div class="master-field">
                    <label class="master-label">Assigned To</label>
                    <select class="master-select" name="assigned_to">
                        <option value="">Unassigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}"
                                {{ (string) old('assigned_to', $project->assigned_to) === (string) $user->id ? 'selected' : '' }}>
                                {{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($quote && !$isEdit)
                <label class="pf-checkbox-card">
                    <input name="import_quote_items" value="1" checked>
                    <span>
                        <strong>Import quote products into this project</strong>
                        <small>All accepted quote items will become project products with quantity and price.</small>
                    </span>
                </label>
            @endif

            <div class="pf-section-head pf-section-gap" style="padding-top:15px;">
                <div>
                    <h3 class="master-section-title">Execution Status</h3>
                </div>
            </div>

            <div class="pf-grid pf-grid-4">
                <div class="master-field">
                    <label class="master-label">Status <span>*</span></label>
                    <select class="master-select" name="status" required>
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('status', $project->status) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Stage <span>*</span></label>
                    <select class="master-select" name="stage" required>
                        @foreach ($stageOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('stage', $project->stage) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Priority <span>*</span></label>
                    <select class="master-select" name="priority" required>
                        @foreach ($priorityOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('priority', $project->priority) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Health <span>*</span></label>
                    <select class="master-select" name="health" required>
                        @foreach ($healthOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('health', $project->health) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Start Date</label>
                    <input class="master-input" type="date" name="start_date" value="{{ $startValue }}">
                </div>
                <div class="master-field">
                    <label class="master-label">Target Date</label>
                    <input class="master-input" type="date" name="target_date" value="{{ $targetValue }}">
                </div>
                <div class="master-field">
                    <label class="master-label">Progress %</label>
                    <input class="master-input" type="number" min="0" max="100" name="progress_percent"
                        value="{{ old('progress_percent', $project->progress_percent) }}">
                </div>
                <div class="master-field">
                    <label class="master-label">Currency <span>*</span></label>
                    <select class="master-select" name="currency" required>
                        @foreach ($currencyOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('currency', $project->currency) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="master-field">
                    <label class="master-label">Estimated Deal Value</label>
                    <input class="master-input" type="number" step="0.01" min="0" name="estimated_value"
                        value="{{ old('estimated_value', $project->estimated_value) }}">
                </div>
                <div class="master-field">
                    <label class="master-label">Project Budget</label>
                    <input class="master-input" type="number" step="0.01" min="0" name="budget_amount"
                        value="{{ old('budget_amount', $project->budget_amount) }}">
                </div>
                <label class="pf-toggle-card">
                    <input name="show_client_portal" value="1"
                        {{ old('show_client_portal', $project->show_client_portal) ? 'checked' : '' }}>
                    <span>
                        <strong>Client Portal</strong>
                        <small>Allow client to view project.</small>
                    </span>
                </label>
            </div>

            <div class="pf-section-head pf-section-gap" style="padding-top:15px;">
                <div>
                    <h3 class="master-section-title">Scope & Notes</h3>
                </div>
            </div>

            <div class="pf-grid pf-grid-2">
                <div class="master-field">
                    <label class="master-label">Scope Summary</label>
                    <textarea class="master-textarea" name="scope_summary" rows="4" placeholder="Project scope, order details, expected output...">{{ old('scope_summary', $project->scope_summary) }}</textarea>
                </div>
                <div class="master-field">
                    <label class="master-label">Deliverables</label>
                    <textarea class="master-textarea" name="deliverables" rows="4" placeholder="Products, packaging list, documents, shipment handover...">{{ old('deliverables', $project->deliverables) }}</textarea>
                </div>
                <div class="master-field">
                    <label class="master-label">Client Notes</label>
                    <textarea class="master-textarea" name="client_notes" rows="4" placeholder="Notes visible/useful for client portal">{{ old('client_notes', $project->client_notes) }}</textarea>
                </div>
                <div class="master-field">
                    <label class="master-label">Internal Notes</label>
                    <textarea class="master-textarea" name="internal_notes" rows="4" placeholder="Private team notes">{{ old('internal_notes', $project->internal_notes) }}</textarea>
                </div>
            </div>

            <div class="pf-submit-row">
                <a href="{{ $isEdit ? route('projects.show', $project) : route('projects.index') }}"
                    class="master-btn master-btn-soft">Cancel</a>
                <button type="submit" class="master-btn master-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> {{ $isEdit ? 'Update Project' : 'Create Project' }}
                </button>
            </div>
        </form>
    </div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/projects.css') }}">
@endpush
@endsection
