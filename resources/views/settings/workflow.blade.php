@extends('layouts.app')

@section('title', 'Settings · Workflow')
@section('page-title', 'Settings · Workflow')

@section('page-actions')
    <button type="submit" form="workflowSettingsForm" class="master-btn master-btn-primary"><i class="fa-solid fa-check"></i> Save settings</button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/settings.css') }}">
@endpush
<div class="set master-list">
    @include('settings.partials.nav', ['current' => 'workflow'])

    <div class="set-area">
        <div class="master-card master-card--flat set-head">
            <span class="set-head-mark" aria-hidden="true"><i class="fa-solid fa-diagram-project"></i></span>
            <div><h1 class="set-head-title">Standard workflow controls</h1><p class="master-sub">Organisation defaults used by every project. Existing evidence and completed decisions remain unchanged.</p></div>
        </div>

        <div class="master-tabs-card">
            <nav class="master-tabs" aria-label="Workflow setting sections">
                <a class="master-tab {{ $tab === 'documents' ? 'is-active' : '' }}" href="{{ route('settings.workflow',['tab'=>'documents']) }}"><i class="fa-solid fa-file-circle-check"></i> Dispatch documents</a>
                <a class="master-tab {{ $tab === 'deadlines' ? 'is-active' : '' }}" href="{{ route('settings.workflow',['tab'=>'deadlines']) }}"><i class="fa-regular fa-clock"></i> Deadlines &amp; escalation</a>
                <a class="master-tab {{ $tab === 'quality' ? 'is-active' : '' }}" href="{{ route('settings.workflow',['tab'=>'quality']) }}"><i class="fa-solid fa-clipboard-check"></i> QC checklist</a>
            </nav>
        </div>

        <form id="workflowSettingsForm" method="POST" action="{{ route('settings.workflow.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="section" value="{{ $tab }}">

            @if($tab === 'documents')
                <section class="master-card master-card--flat master-section">
                    <div class="master-section-head"><div><h2 class="master-section-title">Required dispatch evidence</h2><p class="master-sub">The Workflow Action Centre blocks authorization until each selected category exists in Project attachments. Packing List evidence is satisfied automatically by a confirmed list linked to the shipment.</p></div></div>
                    <div class="master-grid is-even">
                        @foreach($shipmentTypes as $typeKey=>$typeLabel)
                            <fieldset class="master-card master-card--flat master-section">
                                <legend class="master-section-title">{{ $typeLabel }}</legend>
                                @foreach($documentTypes as $documentKey=>$documentLabel)
                                    <div class="master-toggle-group"><label class="master-label" for="doc_{{ $typeKey }}_{{ $documentKey }}">{{ $documentLabel }}</label><label class="master-switch"><input id="doc_{{ $typeKey }}_{{ $documentKey }}" type="checkbox" name="documents[{{ $typeKey }}][]" value="{{ $documentKey }}" @checked(in_array($documentKey,(array)data_get($settings,'documents.'.$typeKey,[]),true))><span class="master-slider"></span></label></div>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                </section>
            @elseif($tab === 'deadlines')
                <section class="master-card master-card--flat master-section">
                    <div class="master-section-head"><div><h2 class="master-section-title">Default response and review windows</h2><p class="master-sub">These defaults apply to new approval requests and newly detected workflow exceptions. Existing due dates are never rewritten.</p></div></div>
                    <div class="master-form-grid">
                        <label class="master-field"><span class="master-label">Client approval response (days)</span><input class="master-input" type="number" min="1" max="90" name="approval_response_days" value="{{ data_get($settings,'deadlines.approval_response_days') }}" required><small class="master-help">Prefills Artwork and PPS response deadlines.</small></label>
                        <label class="master-field"><span class="master-label">Unassigned action review (days)</span><input class="master-input" type="number" min="1" max="30" name="unassigned_review_days" value="{{ data_get($settings,'deadlines.unassigned_review_days') }}" required></label>
                        <label class="master-field"><span class="master-label">Blocked action review (days)</span><input class="master-input" type="number" min="1" max="30" name="blocked_review_days" value="{{ data_get($settings,'deadlines.blocked_review_days') }}" required></label>
                        <label class="master-field"><span class="master-label">Standard exception review (days)</span><input class="master-input" type="number" min="1" max="30" name="standard_review_days" value="{{ data_get($settings,'deadlines.standard_review_days') }}" required></label>
                    </div>
                    <div class="master-alert"><i class="fa-solid fa-circle-info"></i><span>Email, popup and watcher delivery remains under Briefings settings.</span></div>
                </section>
            @else
                <section class="master-card master-card--flat master-section">
                    <div class="master-section-head"><div><h2 class="master-section-title">Standard quality inspection checklist</h2><p class="master-sub">At least one check is required. New inspections use this list; saved inspections retain their immutable checklist snapshot.</p></div></div>
                    @foreach($qualityChecks as $checkKey=>$checkLabel)
                        <div class="master-toggle-group"><label class="master-label" for="qc_{{ $checkKey }}">{{ $checkLabel }}</label><label class="master-switch"><input id="qc_{{ $checkKey }}" type="checkbox" name="qc_checks[]" value="{{ $checkKey }}" @checked(in_array($checkKey,(array)$settings['qc_checks'],true))><span class="master-slider"></span></label></div>
                    @endforeach
                    @error('qc_checks')<p class="master-field-error">{{ $message }}</p>@enderror
                </section>
            @endif
        </form>
    </div>
</div>
@endsection
