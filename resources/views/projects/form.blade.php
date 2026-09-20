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
                <div class="pf-field">
                    <label>Project Number</label>
                    <input type="text" name="project_number"
                        value="{{ old('project_number', $project->project_number) }}" placeholder="Auto generated if blank">
                </div>
                <div class="pf-field">
                    <label>Client <span>*</span></label>
                    <select name="client_id" required>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ (string) old('client_id', $project->client_id) === (string) $client->id ? 'selected' : '' }}>
                                {{ $client->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pf-field">
                    <label>Accepted Customer Quote</label>
                    <select name="customer_quote_id">
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
                <div class="pf-field pf-span-2">
                    <label>Project Name <span>*</span></label>
                    <input type="text" name="name" value="{{ old('name', $project->name) }}" required
                        placeholder="e.g. 100ml Mist Spray Bottle July Order">
                </div>
                <div class="pf-field">
                    <label>Assigned To</label>
                    <select name="assigned_to">
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
                    <input type="checkbox" name="import_quote_items" value="1" checked>
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
                <div class="pf-field">
                    <label>Status <span>*</span></label>
                    <select name="status" required>
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('status', $project->status) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pf-field">
                    <label>Stage <span>*</span></label>
                    <select name="stage" required>
                        @foreach ($stageOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('stage', $project->stage) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pf-field">
                    <label>Priority <span>*</span></label>
                    <select name="priority" required>
                        @foreach ($priorityOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('priority', $project->priority) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pf-field">
                    <label>Health <span>*</span></label>
                    <select name="health" required>
                        @foreach ($healthOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('health', $project->health) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pf-field">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="{{ $startValue }}">
                </div>
                <div class="pf-field">
                    <label>Target Date</label>
                    <input type="date" name="target_date" value="{{ $targetValue }}">
                </div>
                <div class="pf-field">
                    <label>Progress %</label>
                    <input type="number" min="0" max="100" name="progress_percent"
                        value="{{ old('progress_percent', $project->progress_percent) }}">
                </div>
                <div class="pf-field">
                    <label>Currency <span>*</span></label>
                    <select name="currency" required>
                        @foreach ($currencyOptions as $key => $label)
                            <option value="{{ $key }}"
                                {{ old('currency', $project->currency) === $key ? 'selected' : '' }}>{{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="pf-field">
                    <label>Estimated Deal Value</label>
                    <input type="number" step="0.01" min="0" name="estimated_value"
                        value="{{ old('estimated_value', $project->estimated_value) }}">
                </div>
                <div class="pf-field">
                    <label>Project Budget</label>
                    <input type="number" step="0.01" min="0" name="budget_amount"
                        value="{{ old('budget_amount', $project->budget_amount) }}">
                </div>
                <label class="pf-toggle-card">
                    <input type="checkbox" name="show_client_portal" value="1"
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
                <div class="pf-field">
                    <label>Scope Summary</label>
                    <textarea name="scope_summary" rows="4" placeholder="Project scope, order details, expected output...">{{ old('scope_summary', $project->scope_summary) }}</textarea>
                </div>
                <div class="pf-field">
                    <label>Deliverables</label>
                    <textarea name="deliverables" rows="4" placeholder="Products, packaging list, documents, shipment handover...">{{ old('deliverables', $project->deliverables) }}</textarea>
                </div>
                <div class="pf-field">
                    <label>Client Notes</label>
                    <textarea name="client_notes" rows="4" placeholder="Notes visible/useful for client portal">{{ old('client_notes', $project->client_notes) }}</textarea>
                </div>
                <div class="pf-field">
                    <label>Internal Notes</label>
                    <textarea name="internal_notes" rows="4" placeholder="Private team notes">{{ old('internal_notes', $project->internal_notes) }}</textarea>
                </div>
            </div>

            <div class="pf-submit-row">
                <a href="{{ $isEdit ? route('projects.show', $project) : route('projects.index') }}"
                    class="pf-btn pf-btn-soft">Cancel</a>
                <button type="submit" class="pf-btn pf-btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> {{ $isEdit ? 'Update Project' : 'Create Project' }}
                </button>
            </div>
        </form>
    </div>

    <style>
        .project-form-page {
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .project-form-hero {
            background: linear-gradient(135deg, #152238, #284b8f);
            border-radius: 24px;
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 18px 45px rgba(21, 34, 56, .22)
        }

        .pf-eyebrow {
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .12em;
            font-size: 11px;
            font-weight: 600;
            opacity: .78
        }

        .project-form-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600
        }

        .project-form-hero p {
            margin: 8px 0 0;
            opacity: .86;
            max-width: 780px
        }

        .pf-actions {
            display: flex;
            align-items: center
        }

        .pf-btn {
            border: 0;
            border-radius: 14px;
            padding: 11px 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: .2s
        }

        .pf-btn:hover {
            transform: translateY(-1px);
            text-decoration: none
        }

        .pf-btn-primary {
            background: #ef4770;
            color: #fff;
            box-shadow: 0 12px 24px rgba(239, 71, 112, .24)
        }

        .pf-btn-light {
            background: rgba(255, 255, 255, .14);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .28)
        }

        .pf-btn-soft {
            background: #eef3ff;
            color: #4f83f1
        }

        .pf-card {
            background: #fff;
            border: 1px solid #edf0f7;
            border-radius: 24px;
            padding: 22px;
            box-shadow: 0 14px 34px rgba(22, 34, 51, .06)
        }

        .pf-section-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px
        }

        .pf-section-head h3 {
            margin: 0;
            color: #172033;
            font-size: 18px
        }

        .pf-section-head p {
            margin: 5px 0 0;
            color: #7b8495
        }

        .pf-section-gap {
            margin-top: 28px
        }

        .pf-quote-badge {
            background: #fff7e6;
            color: #b54708;
            border: 1px solid #fedf89;
            border-radius: 999px;
            padding: 8px 12px;
            font-weight: 600;
            font-size: 12px
        }

        .pf-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px
        }

        .pf-grid-4 {
            grid-template-columns: repeat(4, minmax(0, 1fr))
        }

        .pf-grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .pf-span-2 {
            grid-column: span 2
        }

        .pf-field {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .pf-field label {
            font-size: 12px;
            color: #5d6b82;
            font-weight: 600
        }

        .pf-field label span {
            color: #ef4770
        }

        .pf-field input,
        .pf-field select,
        .pf-field textarea {
            width: 100%;
            border: 1px solid #dfe5f2;
            border-radius: 14px;
            padding: 11px 12px;
            background: #fff;
            color: #1e2a3b;
            outline: none
        }

        .pf-field input:focus,
        .pf-field select:focus,
        .pf-field textarea:focus {
            border-color: #4f83f1;
            box-shadow: 0 0 0 4px rgba(79, 131, 241, .1)
        }

        .pf-field small {
            color: #8a94a6
        }

        .pf-checkbox-card,
        .pf-toggle-card {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            border: 1px solid #dce8ff;
            background: #f7fbff;
            border-radius: 18px;
            padding: 14px;
            margin-top: 16px;
            cursor: pointer
        }

        .pf-toggle-card {
            margin: 0;
            align-items: center
        }

        .pf-checkbox-card input,
        .pf-toggle-card input {
            margin-top: 4px;
            accent-color: #4f83f1
        }

        .pf-checkbox-card strong,
        .pf-toggle-card strong {
            display: block;
            color: #172033
        }

        .pf-checkbox-card small,
        .pf-toggle-card small {
            display: block;
            color: #7b8495;
            margin-top: 3px
        }

        .pf-submit-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid #edf0f7
        }

        @media(max-width:1199px) {

            .pf-grid,
            .pf-grid-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .pf-span-2 {
                grid-column: span 1
            }

            .pf-toggle-card {
                grid-column: 1/-1
            }
        }

        @media(max-width:767px) {

            .project-form-hero,
            .pf-section-head,
            .pf-submit-row {
                flex-direction: column
            }

            .pf-grid,
            .pf-grid-4,
            .pf-grid-2 {
                grid-template-columns: 1fr
            }

            .pf-submit-row .pf-btn,
            .pf-actions .pf-btn {
                width: 100%
            }

            .pf-card {
                padding: 16px
            }
        }
    </style>
@endsection
