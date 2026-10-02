@extends('layouts.app')

@section('page-title', 'Cashflow Documents')

@section('page-actions')
    <button type="button" class="master-btn master-btn-primary" id="openFileDocumentModal">
        + File a document
    </button>
    <a class="master-btn master-btn-soft" href="{{ route('cashflows.index', ['documents' => 'missing']) }}">
        Missing documents
    </a>
    <a class="master-btn master-btn-ghost" href="{{ route('cashflows.index') }}">Back to Ledger</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/cashflows.css') }}">
    {{-- the shared list chrome, after the module sheet like every other list --}}
    <link rel="stylesheet" href="{{ $assetVer('assets/css/master-list.css') }}">
@endpush
@php
    /* Every filter is one URL away from the others: the chips own their own
       dimension (claim state, period), and 'page' restarts because a filter
       change is a new list. */
    $filtered = trim((string) $q) !== ''
        || trim((string) $party) !== ''
        || ($state && $state !== 'all')
        || filled($dateFrom)
        || filled($dateTo);

    /* A chip owns one dimension and keeps the others. The document's *type* is
       not a dimension here any more: the row prints it, and filtering by it
       added a second set of chips that said what the row already said — the
       month and whether an entry has claimed the file are the questions this
       list is asked. */
    $chipBase = collect(request()->except(['state', 'date_from', 'date_to', 'page']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    $chipActive = ['all' => true];
    $chipActive['linked'] = ($state ?? 'all') === 'linked';
    $chipActive['unlinked'] = ($state ?? 'all') === 'unlinked';

    foreach ($dateRanges as $rangeKey => $range) {
        $chipActive[$rangeKey] = $activeRange === $rangeKey;
    }
    if (filled($dateFrom) || filled($dateTo) || (($state ?? 'all') !== 'all')) {
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
                <span class="tooltip-text">Every document filed in this view — matched to a cashflow entry, or standing on its own.</span>
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
        <div class="master-stat master-stat--flat {{ $unlinkedCount > 0 ? 'purple' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true">✂</span>
            <div>
                <p class="master-stat-title">Not matched to an entry</p>
                <p class="master-stat-value">{{ number_format($unlinkedCount) }}</p>
                <p class="master-sub">
                    <a href="{{ route('cashflows.documents', $chipBase->all() + ['state' => 'unlinked']) }}">see them</a>
                </p>
                <span class="tooltip-text">Bills and receipts filed on their own — a bill booked to a project, a document that arrived before the payment did. They wait here until an entry claims them, or stay as paperwork the ledger never needed. The count is the same one the chip above carries.</span>
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
                {{-- A document does not have to belong to an entry. These two
                     chips are how the month-end question "what is still not
                     matched" is asked, and how a matched bill is found again. --}}
                <a class="master-list-chip {{ $chipActive['linked'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.documents', $chipBase->all() + ['state' => 'linked']) }}">
                    Matched <span class="master-list-chip-count">{{ $chipCounts['linked'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['unlinked'] ? 'is-active' : '' }}"
                    href="{{ route('cashflows.documents', $chipBase->all() + ['state' => 'unlinked']) }}">
                    Not matched <span class="master-list-chip-count">{{ $chipCounts['unlinked'] ?? 0 }}</span>
                </a>
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
                {{-- The chips own the claim state; the form carries it so that
                     applying a filter or downloading a pack keeps the chip the
                     user clicked instead of silently dropping it. --}}
                <input type="hidden" name="state" value="{{ $state ?? 'all' }}">

                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="q" value="{{ $q }}"
                        placeholder="Search file name, entry, bill number, party..." aria-label="Search documents">
                </div>
                {{-- Whose paperwork: a month is usually sent to the accountant one
                     party at a time, so the party is a control of its own. --}}
                <select class="master-select" name="party" aria-label="Filter by party">
                    <option value="">All parties</option>
                    @foreach ($partyOptions as $partyName)
                        <option value="{{ $partyName }}" @selected($party === $partyName)>{{ $partyName }}</option>
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
                    {{-- Same form, same values: what the filters above match is
                         what lands in the pack. --}}
                    <button class="master-btn master-btn-soft" type="submit"
                        formaction="{{ route('cashflows.documents.pack') }}"
                        title="Download everything the filters above match — the files themselves in a folder per type, plus an index — ready to send to the accountant">
                        Download pack
                    </button>
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </div>
            </div>

            @if ($filtered)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @if (($state ?? 'all') !== 'all')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Entry</span>
                            <span class="master-list-applied-value">
                                {{ $state === 'unlinked' ? 'Not matched to one' : 'Matched to one' }}
                            </span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('state') }}"
                                aria-label="Remove the matched/not matched filter"
                                title="Remove the matched/not matched filter">&times;</a>
                        </span>
                    @endif

                    @if (trim((string) $q) !== '')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Search</span>
                            <span class="master-list-applied-value">{{ $q }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('q') }}"
                                aria-label="Clear the search" title="Clear the search">&times;</a>
                        </span>
                    @endif

                    @if (trim((string) $party) !== '')
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">Party</span>
                            <span class="master-list-applied-value">{{ $party }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl('party') }}"
                                aria-label="Remove the party filter" title="Remove the party filter">&times;</a>
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
                        <th scope="col" class="is-num">Amount</th>
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
                                    {{-- Filed on its own: a bill with no bank line
                                         behind it yet. It keeps its own date until
                                         an entry claims it — or for good. --}}
                                    <span class="cf-doc-state is-unlinked"
                                        title="Filed on its own. Open the entry it belongs to and match it from the Documents card there.">Not matched</span>
                                    <span class="master-sub">{{ $document->displayDate()?->format('d M Y') ?? 'No date on the bill' }}</span>
                                @endif
                            </td>
                            <td data-label="Party">
                                {{ $document->partyLabel() }}
                                @if ($entry)
                                    <span class="master-sub">{{ \App\Models\CashflowEntry::relatedPartyOptions()[$entry->related_party_type] ?? '—' }}</span>
                                @elseif (filled($document->party_name))
                                    <span class="master-sub">written on the bill</span>
                                @endif
                            </td>
                            <td data-label="Type">
                                <span class="cf-doc-type">{{ $document->documentTypeLabel() }}</span>
                            </td>
                            <td class="is-num" data-label="Amount">{{ $document->amountLabel() ?? '—' }}</td>
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
                            <td colspan="8">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">📎</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtered ? 'No documents match these filters' : 'No documents filed yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtered
                                            ? 'Adjust the search or the filters above — the counts on each chip show what is available.'
                                            : 'Attach a bill to a cashflow entry, or file one on its own — a document with no entry yet still belongs in the month.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtered)
                                            <a class="master-btn master-btn-soft" href="{{ route('cashflows.documents') }}">Clear filters</a>
                                        @endif
                                        <button type="button" class="master-btn master-btn-primary" id="emptyFileDocument">File a document</button>
                                        <a class="master-btn master-btn-soft" href="{{ route('cashflows.index') }}">Open the ledger</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="master-list-total">
                        <td colspan="5">
                            <strong>Total — {{ $documents->count() }} {{ \Illuminate\Support\Str::plural('document', $documents->count()) }} shown</strong>
                            <span class="master-sub">Counts and sizes cover every page</span>
                        </td>
                        <td class="is-num" data-label="Size">
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

{{-- A bill that stands on its own: filed here, matched to an entry later — or
     never, which is a valid answer for paperwork the ledger does not carry.
     The entry card files through the same controller with the same fields. --}}
<div class="master-modal" id="fileDocumentModal" aria-hidden="true">
    <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="fileDocumentTitle">
        <form method="POST" action="{{ route('cashflows.attachments.storeStandalone') }}"
            enctype="multipart/form-data">
            @csrf
            <div class="master-modal-header">
                <div class="master-modal-heading">
                    <span class="master-modal-icon">📄</span>
                    <div>
                        <h3 class="master-modal-title" id="fileDocumentTitle">File a document</h3>
                        <p class="master-modal-subtitle">A bill or receipt with no cashflow entry behind it</p>
                    </div>
                </div>
                <button type="button" class="master-modal-close" data-close-modal="fileDocumentModal"
                    aria-label="Close">&times;</button>
            </div>
            <div class="master-modal-body">
                <div class="master-modal-grid">
                    <div class="master-field">
                        <label class="master-label" for="standaloneType">Document type
                            <span class="master-required" aria-hidden="true">*</span></label>
                        <select class="master-select" id="standaloneType" name="document_type" required>
                            @foreach ($documentTypeOptions as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'bill')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="standaloneDate">Document date</label>
                        <input class="master-input" id="standaloneDate" type="date" name="document_date">
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="standaloneTitle">Title (optional)</label>
                        <input class="master-input" id="standaloneTitle" name="title" maxlength="255"
                            placeholder="e.g. Bill 2418 — Shree Traders">
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="standaloneParty">Party (who the bill is from)</label>
                        <input class="master-input" id="standaloneParty" name="party_name" maxlength="255"
                            placeholder="e.g. Shree Traders">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="standaloneAmount">Amount</label>
                        <input class="master-input" id="standaloneAmount" type="number" step="0.01" min="0"
                            name="amount">
                    </div>
                    <div class="master-field">
                        <label class="master-label" for="standaloneCurrency">Currency</label>
                        <select class="master-select" id="standaloneCurrency" name="currency">
                            @foreach ($currencyOptions as $code => $label)
                                <option value="{{ $code }}" @selected($code === 'INR')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field full">
                        <label class="master-label" for="standaloneFiles">Files
                            <span class="master-required" aria-hidden="true">*</span></label>
                        <input class="master-input" id="standaloneFiles" type="file" name="attachments[]" multiple required
                            accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                        <p class="master-sub">PDF, image, Word, Excel, CSV or ZIP · up to 20 MB each.</p>
                    </div>
                </div>
            </div>
            <div class="master-modal-footer">
                <button type="button" class="master-btn master-btn-light"
                    data-close-modal="fileDocumentModal">Cancel</button>
                <button type="submit" class="master-btn master-btn-primary">File document</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/cashflows.js') }}"></script>
@endpush
