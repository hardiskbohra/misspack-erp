@extends('layouts.app')

@section('page-title', 'Cashflow Documents')

@section('page-actions')
    <a class="master-btn master-btn-soft" href="{{ route('cashflows.index') }}">Back to Ledger</a>
    <a class="master-btn master-btn-primary" href="{{ route('cashflows.index', ['documents' => 'missing']) }}">
        Missing documents
    </a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    {{-- the shared list chrome, after the module sheet like every other list --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush
@php
    /* Every filter is one URL away from the others: the chips own their own
       dimension (type, period), and 'page' restarts because a filter change is
       a new list. */
    $filtered = trim((string) $q) !== ''
        || ($documentType && $documentType !== 'all')
        || filled($dateFrom)
        || filled($dateTo);

    $chipBase = collect(request()->except(['document_type', 'date_from', 'date_to', 'page']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    $chipActive = ['all' => ! $documentType || $documentType === 'all'];
    foreach ($documentTypeOptions as $key => $label) {
        $chipActive[$key] = $documentType === $key;
    }
    foreach ($dateRanges as $rangeKey => $range) {
        $chipActive[$rangeKey] = $activeRange === $rangeKey;
    }
    if (filled($dateFrom) || filled($dateTo)) {
        $chipActive['all'] = false;
    }

    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('cashflows.documents', $keep->all());
    };

    $dateUrl = route('cashflows.documents', collect(request()->except(['date_from', 'date_to', 'page']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all')
        ->all());
@endphp

<div class="cf cashflow-documents master-list">

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue tooltip-container">
            <span class="icon" aria-hidden="true">📎</span>
            <div>
                <p class="master-stat-title">Documents (filtered)</p>
                <p class="master-stat-value">{{ number_format($chipCounts['all']) }}</p>
                <span class="tooltip-text">Every file attached to an entry matching the filters above.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat green tooltip-container">
            <span class="icon" aria-hidden="true">⤓</span>
            <div>
                <p class="master-stat-title">On file</p>
                <p class="master-stat-value">{{ number_format($totalSize / 1048576, 1) }} MB</p>
                <p class="master-sub">of documents matching the filters</p>
                <span class="tooltip-text">Total size of the files in this view — handy before mailing a month to the accountant.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $missingCount > 0 ? 'orange' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true">!</span>
            <div>
                <p class="master-stat-title">Entries without a document</p>
                <p class="master-stat-value">{{ number_format($missingCount) }}</p>
                <p class="master-sub">
                    <a href="{{ route('cashflows.index', array_filter(['documents' => 'missing', 'date_from' => $dateFrom, 'date_to' => $dateTo])) }}">
                        open the to-do list
                    </a>
                </p>
                <span class="tooltip-text">Entries in the same period with nothing filed yet — the ledger filters straight to them.</span>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ $chipActive['all'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.documents', $chipBase->all()) }}">All documents</a>
                @foreach ($documentTypeOptions as $key => $label)
                    <a class="master-list-chip {{ $chipActive[$key] ? 'is-active' : '' }}"
                        href="{{ route('cashflows.documents', $chipBase->all() + ['document_type' => $key]) }}">
                        {{ $label }} <span class="master-list-chip-count">{{ $chipCounts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
                @foreach ($dateRanges as $rangeKey => $range)
                    <a class="master-list-chip {{ $chipActive[$rangeKey] ? 'is-active' : '' }}"
                        href="{{ route('cashflows.documents', $chipBase->all() + ['date_from' => $range['from'], 'date_to' => $range['to']]) }}">
                        {{ $dateRangeLabels[$rangeKey] }}
                        <span class="master-list-chip-count">{{ $chipCounts[$rangeKey] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('cashflows.documents') }}">
            <div class="master-filter-row">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="q" value="{{ $q }}"
                        placeholder="Search file name, entry, bill number, party..." aria-label="Search documents">
                </div>
                <select class="master-select" name="document_type" aria-label="Filter by document type">
                    <option value="all">All Types</option>
                    @foreach ($documentTypeOptions as $key => $label)
                        <option value="{{ $key }}" @selected($documentType === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="master-input desktop-only" type="date" name="date_from" value="{{ $dateFrom }}"
                    aria-label="Booked from" title="Booked from">
                <input class="master-input desktop-only" type="date" name="date_to" value="{{ $dateTo }}"
                    aria-label="Booked to" title="Booked to">

                <div class="master-list-filter-group">
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('cashflows.documents') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </div>

            @if ($filtered)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @if (trim((string) $q) !== '')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $q }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('q') }}"
                                aria-label="Clear the search" title="Clear the search">&times;</a>
                        </span>
                    @endif

                    @if ($documentType && $documentType !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Type</span>
                            <span class="master-list-applied-value">{{ $documentTypeOptions[$documentType] ?? $documentType }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('document_type') }}"
                                aria-label="Remove the type filter" title="Remove the type filter">&times;</a>
                        </span>
                    @endif

                    @if (filled($dateFrom) || filled($dateTo))
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $activeRange ? 'Period' : 'Dates' }}</span>
                            <span class="master-list-applied-value">
                                @if ($activeRange)
                                    {{ $dateRangeLabels[$activeRange] }}
                                @else
                                    {{ $dateFrom ? \Illuminate\Support\Carbon::parse($dateFrom)->format('d M Y') : 'start' }}
                                    →
                                    {{ $dateTo ? \Illuminate\Support\Carbon::parse($dateTo)->format('d M Y') : 'today' }}
                                @endif
                            </span>
                            <a class="master-list-applied-x" href="{{ $dateUrl }}"
                                aria-label="Remove the period filter" title="Remove the period filter">&times;</a>
                        </span>
                    @endif

                    <a class="master-list-applied-clear" href="{{ route('cashflows.documents') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint" title="Newest upload first, grouped by the entry it was filed against.">
                Newest first &middot; {{ $documents->total() }} {{ \Illuminate\Support\Str::plural('document', $documents->total()) }}
            </p>

            {{-- The same right-hand slot the ledger uses: a list's own
                 destinations sit beside the density switch. --}}
            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Row density">
                    <button type="button" class="master-list-density-btn" data-density="comfortable"
                        aria-pressed="true">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact"
                        aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap">
            <table class="master-table">
                <thead>
                    <tr>
                        <th scope="col">File</th>
                        <th scope="col">Entry</th>
                        <th scope="col">Party</th>
                        <th scope="col">Type</th>
                        <th scope="col" class="is-num">Size</th>
                        <th scope="col">Filed</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        @php($entry = $document->cashflowEntry)
                        <tr>
                            <td class="cf-doc-cell" data-label="File">
                                <a class="cf-doc-name" href="{{ $document->url() }}" target="_blank" rel="noopener">
                                    <i class="fa-solid {{ $document->icon() }}" aria-hidden="true"></i>
                                    {{ $document->name() }}
                                </a>
                                @if ($document->original_name && $document->original_name !== $document->name())
                                    <span class="master-sub">{{ $document->original_name }}</span>
                                @endif
                            </td>
                            <td data-label="Entry">
                                @if ($entry)
                                    <a class="cf-doc-entry" href="{{ route('cashflows.show', $entry) }}#documents">
                                        {{ $entry->particular }}
                                    </a>
                                    <span class="master-sub">
                                        {{ $entry->entry_date?->format('d M Y') }}
                                        @if ($entry->invoice_bill_number) · {{ $entry->invoice_bill_number }} @endif
                                    </span>
                                @else
                                    <span class="master-sub">Entry deleted</span>
                                @endif
                            </td>
                            <td data-label="Party">
                                {{ $entry?->client?->company_name ?? $entry?->vendor?->vendor_name ?? $entry?->related_party_name ?? '—' }}
                                <span class="master-sub">{{ \App\Models\CashflowEntry::relatedPartyOptions()[$entry?->related_party_type] ?? '—' }}</span>
                            </td>
                            <td data-label="Type">
                                <span class="cf-doc-type">{{ $document->documentTypeLabel() }}</span>
                            </td>
                            <td class="is-num" data-label="Size">{{ $document->sizeLabel() }}</td>
                            <td data-label="Filed">
                                {{ $document->created_at?->format('d M Y') }}
                                <span class="master-sub">{{ $document->uploader?->name ?? 'System' }}</span>
                            </td>
                            <td data-label="Action">
                                <div class="master-row-actions">
                                    <a class="master-btn master-btn-soft master-btn-sm" href="{{ $document->url() }}"
                                        target="_blank" rel="noopener">Open</a>
                                    <form method="POST" action="{{ route('cashflows.attachments.destroy', $document) }}"
                                        data-confirm="Remove this document?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="master-btn master-btn-light master-btn-sm"
                                            aria-label="Remove {{ $document->name() }}">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">📎</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtered ? 'No documents match these filters' : 'No documents filed yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtered
                                            ? 'Adjust the search or the filters above — the counts on each chip show what is available.'
                                            : 'Attach the bill to a cashflow entry and it lands here, ready for the month-end pack.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtered)
                                            <a class="master-btn master-btn-soft" href="{{ route('cashflows.documents') }}">Clear filters</a>
                                        @endif
                                        <a class="master-btn master-btn-primary" href="{{ route('cashflows.index') }}">Open the ledger</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="master-list-total">
                        <td colspan="4">
                            <strong>Total — {{ $documents->count() }} {{ \Illuminate\Support\Str::plural('document', $documents->count()) }} shown</strong>
                            <span class="master-sub">Counts and sizes cover every page</span>
                        </td>
                        <td class="is-num">
                            <strong>{{ number_format($totalSize / 1048576, 1) }} MB</strong>
                            <span class="master-sub">All pages</span>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <x-pagination :items="$documents" />
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflows.js') }}"></script>
@endpush
