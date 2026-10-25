@extends('layouts.app')
@section('title', 'Audit register')
@section('page-title', 'Audit register')



@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/audit-access.css') }}">
@endpush

@section('content')
<div class="master-list audit-page">
    <div class="master-stats" aria-label="Audit register summary">
        @foreach ([['Matching events', number_format($logs->total()), 'fa-list-check'], ['Denied on this page', $logs->getCollection()->where('outcome','denied')->count(), 'fa-shield-halved'], ['Failed on this page', $logs->getCollection()->where('outcome','failed')->count(), 'fa-triangle-exclamation'], ['Register state', 'Immutable', 'fa-lock']] as [$label,$value,$icon])
            <div class="master-stat master-stat--flat">
                <span class="icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                <div><p class="master-stat-title">{{ $label }}</p><p class="master-stat-value">{{ $value }}</p></div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('audit.index') }}" class="audit-search-form">
        <div class="audit-search-row">
            <div class="master-field">
                <label class="master-label" for="auditSearch">Search audit events</label>
                <input class="master-input" id="auditSearch" name="search" value="{{ $filters['search'] }}" placeholder="Actor, event or route" data-audit-search>
            </div>
            <x-filter-trigger drawer="auditFilterDrawer" label="Audit filters" :count="collect($filters)->filter()->count()" />
            <button class="master-btn master-btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search</button>
            @if (collect($filters)->filter()->isNotEmpty())
                <a class="master-btn master-btn-light" href="{{ route('audit.index') }}">Clear filters</a>
            @endif
        </div>
        <x-drawer id="auditFilterDrawer" title="Filter audit evidence" eyebrow="Security register" subtitle="Narrow by event, actor, outcome or date." size="medium">
            <section class="core-drawer-section"><div class="core-drawer-fields">
                <div class="master-field"><label class="master-label" for="auditEvent">Event</label><select class="master-select" id="auditEvent" name="event"><option value="">All events</option>@foreach ($events as $event)<option value="{{ $event }}" @selected($filters['event']===$event)>{{ $event }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label" for="auditOutcome">Outcome</label><select class="master-select" id="auditOutcome" name="outcome"><option value="">Any outcome</option>@foreach (['success'=>'Success','failed'=>'Failed','denied'=>'Denied'] as $key=>$label)<option value="{{ $key }}" @selected($filters['outcome']===$key)>{{ $label }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label" for="auditActor">Actor type</label><select class="master-select" id="auditActor" name="actor_type"><option value="">All actor types</option>@foreach ($actorTypes as $type)<option value="{{ $type }}" @selected($filters['actor_type']===$type)>{{ \Illuminate\Support\Str::headline($type) }}</option>@endforeach</select></div>
                <div class="master-field"><label class="master-label" for="auditFrom">From</label><input class="master-input" id="auditFrom" name="from" type="date" value="{{ $filters['from'] }}"></div>
                <div class="master-field"><label class="master-label" for="auditTo">To</label><input class="master-input" id="auditTo" name="to" type="date" value="{{ $filters['to'] }}"></div>
            </div></section>
            <x-slot:footer><a class="master-btn master-btn-light" href="{{ route('audit.index') }}">Clear</a><button class="master-btn master-btn-primary" type="submit">Apply filters</button></x-slot:footer>
        </x-drawer>
    </form>

    <section class="master-card master-table-card" aria-label="Audit events">
        <div class="master-table-wrap"><table class="master-table audit-table">
            <thead><tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Event</th><th scope="col">Subject</th><th scope="col">Outcome</th><th scope="col">Evidence</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td data-label="When"><strong>{{ $log->occurred_at->format('d M Y') }}</strong><span class="master-sub">{{ $log->occurred_at->format('H:i:s') }}</span></td>
                        <td data-label="Actor"><strong>{{ $log->actor_name ?: 'Public / system' }}</strong><span class="master-sub">{{ $log->actor_identifier ?: \Illuminate\Support\Str::headline($log->actor_type ?: 'system') }}</span></td>
                        <td data-label="Event"><strong>{{ $log->event }}</strong><span class="master-sub">{{ $log->method }} · {{ $log->route_name ?: 'No named route' }}</span></td>
                        <td data-label="Subject"><strong>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : 'Request' }}</strong><span class="master-sub">{{ $log->ip_address }}</span></td>
                        <td data-label="Outcome"><span class="master-status-chip {{ $log->outcome==='success'?'success':'danger' }}">{{ $log->outcomeLabel() }}</span></td>
                        <td data-label="Evidence"><button type="button" class="master-btn master-btn-soft master-btn-sm" data-audit-evidence data-audit-template="audit-evidence-{{ $log->id }}" data-audit-title="Audit evidence #{{ $log->id }}" aria-haspopup="dialog" aria-controls="auditEvidenceModal" aria-label="View evidence for audit event {{ $log->id }}"><i class="fa-regular fa-eye" aria-hidden="true"></i> View</button></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="master-empty-state"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><p>No audit events match these filters.</p></div></td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="master-sub audit-result-count" role="status">Showing {{ number_format($logs->firstItem() ?? 0) }}–{{ number_format($logs->lastItem() ?? 0) }} of {{ number_format($logs->total()) }} events</p>
        <nav aria-label="Audit event pages"><x-pagination :items="$logs" /></nav>
    </section>
</div>

{{-- Outside the scrollable table: one shared, read-only dialog, never a row popover. --}}
<div class="master-modal" id="auditEvidenceModal" aria-hidden="true">
    <section class="master-modal-card is-wide" role="dialog" aria-modal="true" aria-labelledby="auditEvidenceTitle" tabindex="-1">
        <header class="master-modal-header">
            <div class="master-modal-heading"><span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span><div><h2 class="master-modal-title" id="auditEvidenceTitle">Audit evidence</h2><p class="master-modal-subtitle">Read-only record · Audit history cannot be edited or deleted</p></div></div>
            <button type="button" class="master-modal-close" data-close-modal="auditEvidenceModal" aria-label="Close audit evidence">&times;</button>
        </header>
        <div class="master-modal-body" data-audit-body tabindex="0"></div>
        <footer class="master-modal-footer"><button type="button" class="master-btn master-btn-light" data-close-modal="auditEvidenceModal">Close</button></footer>
    </section>
</div>
@foreach ($logs as $log)
    <template id="audit-evidence-{{ $log->id }}">
        <div class="master-facts">
            @foreach (['Occurred at'=>$log->occurred_at->format('d M Y, H:i:s'),'Actor'=>$log->actor_name ?: 'Public / system','Actor identifier'=>$log->actor_identifier ?: 'Not recorded','Actor type'=>\Illuminate\Support\Str::headline($log->actor_type ?: 'system'),'Event'=>$log->event,'Route'=>$log->route_name ?: 'No named route','Method'=>$log->method,'HTTP status'=>$log->response_status ?: 'Not recorded','Subject'=>$log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : 'Request','Outcome'=>$log->outcomeLabel(),'IP address'=>$log->ip_address ?: 'Not recorded'] as $label=>$value)
                <div class="master-info"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
            @endforeach
        </div>
        <section class="audit-evidence-section"><h3>Changed fields</h3><p>{{ collect($log->changed_fields ?: [])->join(', ') ?: 'None recorded' }}</p></section>
        <section class="audit-evidence-section"><h3>Context</h3><pre>{{ json_encode($log->context ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></section>
        <section class="audit-evidence-section"><h3>URL</h3><p>{{ $log->url ?: 'Not recorded' }}</p></section>
        <section class="audit-evidence-section"><h3>User agent</h3><p>{{ $log->user_agent ?: 'Not recorded' }}</p></section>
    </template>
@endforeach
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/audit-register.js') }}"></script>
@endpush
