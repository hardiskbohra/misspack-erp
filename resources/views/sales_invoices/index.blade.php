@extends('layouts.app')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

{{-- The module's primary action lives in the header, so it stays reachable
     however far the list scrolls — the same place the cashflow ledger keeps
     its own. --}}
@section('page-actions')
    <a class="master-btn master-btn-primary" href="{{ route('sales-invoices.create', ['type' => 'tax']) }}">+ New Tax Invoice</a>
    <a class="master-btn master-btn-soft" href="{{ route('sales-invoices.create', ['type' => 'proforma']) }}">New Proforma</a>
    <a class="master-btn master-btn-ghost desktop-only" href="{{ route('sales-invoices.export', request()->query()) }}">Export CSV</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/sales-invoices.css') }}">
@endpush

@php
    /* Which chips are lit, and what each one would show. The counts come from
       the controller, asked of the same query the rows come from — a chip that
       claims a number has to be showing it. */
    $chipBase = collect(request()->except([
        'invoice_type', 'status', 'payment', 'ageing', 'date_from', 'date_to', 'page', 'saved_view',
    ]))->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    $chipActive = [
        'all' => $type === 'all' && $status === 'all' && $payment === 'all' && $ageing === 'all'
            && blank($dateFrom) && blank($dateTo),
        'proforma' => $type === 'proforma',
        'tax' => $type === 'tax',
        'draft' => $status === 'draft',
    ];

    /* The applied strip is built from the same list of dimensions that filtered
       the query, so a filter that arrived by link — a saved view, a client
       record — is always visible and always removable. */
    $filtersActive = $appliedChips !== [];

    /* Removing a chip drops the query keys it owns — one for a filter, both ends
       for a period. The service says which, so the strip and the CSV's own
       "Filtered by" line list the same filters in the same words. */
    $chipUrl = function (array $keys) {
        $keep = collect(request()->except(array_merge($keys, ['page', 'saved_view'])))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('sales-invoices.index', $keep->all());
    };
@endphp

<div class="si-index master-list">
    {{-- The figures: what the filtered rows add up to, and the one number that
         is deliberately not filtered (the drafts waiting to be sent). Every
         figure is the model's money rule asked of the whole filtered set in one
         aggregate — never a sum of what happens to be on this page. --}}
    <div class="master-stats">
        <div class="master-stat master-stat--flat blue tooltip-container">
            <span class="icon">₹</span>
            <div>
                <p class="master-stat-title">Sales (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['sales']) }}</p>
                <span class="tooltip-text">The tax invoices matching the filters, drafts included, cancellations aside. A proforma is never sales — it becomes this the moment a tax invoice is raised from it.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon">↗</span>
            <div>
                <p class="master-stat-title">Potential revenue (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['potential']) }}</p>
                <span class="tooltip-text">Proformas matching the filters that no tax invoice has been raised from yet — the money asked for, not the money sold. A converted proforma leaves this figure for Sales; it is never in both.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal tooltip-container">
            <span class="icon">↙</span>
            <div>
                <p class="master-stat-title">Received (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['received']) }}</p>
                <span class="tooltip-text">Money in: each standing invoice's own opening figure plus every receipt filed against it in the cashflow ledger.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat orange tooltip-container">
            <span class="icon">=</span>
            <div>
                <p class="master-stat-title">Outstanding (filtered)</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['outstanding']) }}</p>
                <span class="tooltip-text">The Balance column, added up: what each standing document still owes, drafts included — the tax invoice a conversion creates starts as a draft with the advance already on it. Only a cancelled document is left out.</span>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon">✎</span>
            <div>
                <p class="master-stat-title">Drafts</p>
                <p class="master-stat-value">{{ $stats['drafts'] }}</p>
                <p class="master-sub">{{ \Illuminate\Support\Str::plural('invoice', $stats['drafts']) }} not sent yet</p>
                <span class="tooltip-text">Across the module, not the filtered set — the chip that asks for drafts is the one that makes them the filtered set.</span>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ $chipActive['all'] ? 'is-active' : '' }}"
                    href="{{ route('sales-invoices.index', $chipBase->all()) }}">All invoices</a>
                <a class="master-list-chip {{ $chipActive['proforma'] ? 'is-active' : '' }}"
                    href="{{ route('sales-invoices.index', $chipBase->all() + ['invoice_type' => 'proforma']) }}">
                    Proforma <span class="master-list-chip-count">{{ $chipCounts['proforma'] }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['tax'] ? 'is-active' : '' }}"
                    href="{{ route('sales-invoices.index', $chipBase->all() + ['invoice_type' => 'tax']) }}">
                    Tax <span class="master-list-chip-count">{{ $chipCounts['tax'] }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['draft'] ? 'is-active' : '' }}"
                    href="{{ route('sales-invoices.index', $chipBase->all() + ['status' => 'draft']) }}">
                    Drafts <span class="master-list-chip-count">{{ $chipCounts['draft'] }}</span>
                </a>
                {{-- Four chips, and nothing that repeats a filter the strip
                     below already offers: the money questions (nothing
                     received / partly paid / paid), how late it is, and who to
                     chase are all in the filter row, where the ageing buckets
                     read as the list they are. --}}

                {{-- The periods read left to right, nearest first — the same four
                     ranges every list gets from App\Helpers\DateRanges. --}}
                @foreach ($dateRanges as $rangeKey => $range)
                    <a class="master-list-chip {{ $activeRange === $rangeKey ? 'is-active' : '' }}"
                        href="{{ route('sales-invoices.index', $chipBase->all() + ['date_from' => $range['from'], 'date_to' => $range['to']]) }}">
                        {{ $dateRangeLabels[$rangeKey] }}
                        <span class="master-list-chip-count">{{ $chipCounts[$rangeKey] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            <div class="master-list-saved">
                @foreach ($savedViews as $view)
                    <span class="master-list-saved-chip">
                        <a href="{{ route('sales-invoices.index', ['saved_view' => $view->id]) }}"
                            title="{{ $view->is_shared ? 'Shared view' : 'Your view' }}">{{ $view->name }}</a>
                        @if ((int) $view->user_id === (int) auth()->id())
                            <form method="POST" action="{{ route('sales-invoices.saved-views.destroy', $view) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove saved view">&times;</button>
                            </form>
                        @endif
                    </span>
                @endforeach
                <button type="button" class="master-btn master-btn-soft master-btn-sm" id="toggleSaveView">☆ Save this view</button>
                <form method="POST" action="{{ route('sales-invoices.saved-views.store', request()->except(['page', 'saved_view'])) }}"
                    class="master-list-save-view" id="saveViewForm" hidden>
                    @csrf
                    <input class="master-input" name="name" placeholder="View name" maxlength="60"
                        aria-label="Saved view name" required>
                    <label class="master-check"><input type="checkbox" name="is_shared" value="1"> Share</label>
                    <button class="master-btn master-btn-primary master-btn-sm">Save</button>
                </form>
            </div>
        </div>

        <form method="GET" action="{{ route('sales-invoices.index') }}">
            <div class="si-filter-toolbar" role="search" aria-label="Search and filter invoices">
                <div class="master-search si-filter-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Search invoice, client, GSTIN, PO..." aria-label="Search invoices">
                    <button class="si-search-submit" type="submit" aria-label="Search invoices">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                </div>
                <x-filter-trigger drawer="invoiceFiltersDrawer" label="Filters" :count="count($appliedChips)" />
            </div>

            <x-drawer id="invoiceFiltersDrawer" title="Filter invoices" eyebrow="Invoice filters"
                subtitle="Refine the invoice list by type, customer, collection state, or date." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Invoice</h3>
                    <div class="si-filter-grid">
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterInvoiceType">Invoice type</label>
                            <select class="master-select" name="invoice_type" id="siFilterInvoiceType" aria-label="Filter by type">
                                <option value="all">All types</option>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterStatus">Status</label>
                            <select class="master-select" name="status" id="siFilterStatus" aria-label="Filter by status">
                                <option value="all">All statuses</option>
                                @foreach($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterDateFrom">Invoiced from</label>
                            <input class="master-input" type="date" name="date_from" id="siFilterDateFrom"
                                value="{{ $dateFrom }}" aria-label="Invoiced from">
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterDateTo">Invoiced to</label>
                            <input class="master-input" type="date" name="date_to" id="siFilterDateTo"
                                value="{{ $dateTo }}" aria-label="Invoiced to">
                        </div>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Customer &amp; project</h3>
                    <div class="si-filter-grid">
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterClient">Client</label>
                            <select class="master-select" name="client_id" id="siFilterClient" aria-label="Filter by client">
                                <option value="all">All clients</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((string) $clientId === (string) $client->id)>{{ $client->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterProject">Project</label>
                            <select class="master-select" name="project_id" id="siFilterProject" aria-label="Filter by project">
                                <option value="all">All projects</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) $projectId === (string) $project->id)>{{ $project->project_number }} - {{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Collections</h3>
                    <div class="si-filter-grid">
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterPayment">Payment state</label>
                            <select class="master-select" name="payment" id="siFilterPayment" aria-label="Filter by what has been received">
                                <option value="all">Any payment state</option>
                                @foreach($paymentLabels as $key => $label)
                                    <option value="{{ $key }}" @selected($payment === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterChase">Collection focus</label>
                            <select class="master-select" name="chase" id="siFilterChase" aria-label="Filter by what to chase">
                                <option value="all">Nothing to chase</option>
                                @foreach($chaseLabels as $chaseKey => $chaseLabel)
                                    <option value="{{ $chaseKey }}" @selected($chase === $chaseKey)>{{ $chaseLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="siFilterAgeing">Ageing</label>
                            <select class="master-select" name="ageing" id="siFilterAgeing" aria-label="Filter by how late">
                                <option value="all">Any age</option>
                                <option value="overdue" @selected($ageing === 'overdue')>Late (all of it)</option>
                                @foreach($ageingBuckets as $key => $label)
                                    <option value="{{ $key }}" @selected($ageing === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('sales-invoices.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>

            @if ($filtersActive)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>

                    @foreach ($appliedChips as $chip)
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                            <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl($chip['query']) }}"
                                aria-label="Remove the {{ strtolower($chip['label']) }} filter"
                                title="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                        </span>
                    @endforeach

                    <a class="master-list-applied-clear" href="{{ route('sales-invoices.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint"
                title="Newest invoice date on top. The balance is the invoice's opening figure plus every receipt filed against it in the ledger.">
                Newest first &middot; balance is what the ledger says
            </p>

            <div class="master-list-toolbar-actions">
                <a class="master-btn master-btn-ghost master-btn-sm" href="{{ route('sales-invoices.export', request()->query()) }}">
                    <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
                </a>
                <a class="master-btn master-btn-ghost master-btn-sm desktop-only"
                    href="{{ route('sales-invoices.gstExport', request()->query()) }}">
                    <i class="fas fa-percent" aria-hidden="true"></i> GST summary
                </a>

            </div>

            {{-- This form has its own toolbar row. The table checkboxes point to
                 it by id; wrapping the table would nest each row menu's forms. --}}
            <form id="bulkForm" method="POST" action="{{ route('sales-invoices.bulk') }}"
                class="master-list-bulk" data-bulk-bar hidden>
                @csrf
                <span class="master-list-bulk-count" data-bulk-count>0 selected</span>
                <select class="master-select" name="action" aria-label="Action for the selected invoices">
                    @foreach ($bulkActions as $actionKey => $actionLabel)
                        <option value="{{ $actionKey }}">{{ $actionLabel }}</option>
                    @endforeach
                </select>
                <button class="master-btn master-btn-primary master-btn-sm" type="submit">Apply</button>
                <a class="master-btn master-btn-soft master-btn-sm" data-bulk-export
                    href="{{ route('sales-invoices.export') }}">
                    <i class="fas fa-file-csv" aria-hidden="true"></i> Export selected
                </a>
                <a class="master-btn master-btn-soft master-btn-sm" data-bulk-gst
                    href="{{ route('sales-invoices.gstExport') }}">
                    <i class="fas fa-percent" aria-hidden="true"></i> GST summary
                </a>
                <button class="master-btn master-btn-light master-btn-sm" type="button" data-bulk-clear>Clear</button>
            </form>
        </div>

        <div class="master-table-wrap">
            <table class="master-table si-table">
                {{-- The ten columns, in the order the headings are written: the
                     pick box, the invoice (number, chips, date), the client, the
                     project, the three money columns, the due date, the state
                     chips and the one action. The widths live in the sheet, next
                     to the other rules about this table's geometry. --}}
                <colgroup>
                    <col class="si-col-pick">
                    <col class="si-col-invoice">
                    <col class="si-col-client">
                    <col class="si-col-project">
                    <col class="si-col-total">
                    <col class="si-col-received">
                    <col class="si-col-balance">
                    <col class="si-col-due">
                    <col class="si-col-state">
                    <col class="si-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="master-list-pick ui-mobile-secondary">
                            <input type="checkbox" data-bulk-all aria-label="Select every invoice on this page">
                        </th>
                        <th scope="col">Invoice</th>
                        <th scope="col">Client</th>
                        <th scope="col" class="ui-mobile-secondary">Project</th>
                        <th scope="col" class="is-num">Total</th>
                        <th scope="col" class="is-num ui-mobile-secondary">Received</th>
                        <th scope="col" class="is-num">Balance</th>
                        <th scope="col">Due</th>
                        <th scope="col">State</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            /* Every one of these is the model's rule, not the
                               view's arithmetic: the list, the record page, the
                               figures and the CSV cannot disagree. */
                            $received = $invoice->receivedAmount();
                            $balance = $invoice->balanceDue();
                            $stateKey = $invoice->stateKey();
                            $daysLate = $invoice->daysOverdue();
                        @endphp
                        <tr data-href="{{ route('sales-invoices.show', $invoice) }}">
                            <td data-label="Pick" class="master-list-pick ui-mobile-secondary">
                                <input type="checkbox" name="ids[]" value="{{ $invoice->id }}" form="bulkForm"
                                    data-bulk-pick aria-label="Select {{ $invoice->invoice_number }}">
                            </td>
                            <td data-label="Invoice">
                                <a class="si-number" href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                                <span class="si-invoice-chips">
                                    <span class="si-type type-{{ $invoice->invoice_type }}">{{ $invoice->typeLabel() }}</span>
                                    @if ($invoice->po_number)
                                        <span class="master-chip">PO {{ $invoice->po_number }}</span>
                                    @endif
                                </span>
                                @if ($invoice->invoice_date)
                                    <span class="master-sub si-date">{{ $invoice->invoice_date->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Client">
                                <span class="si-client">{{ $invoice->client_company_name ?: 'No client' }}</span>
                                <span class="master-sub ui-mobile-secondary">{{ $invoice->client_gstin ?: 'No GSTIN on file' }}</span>
                            </td>
                            <td data-label="Project" class="ui-mobile-secondary">
                                @if ($invoice->project?->project_number)
                                    {{ $invoice->project->project_number }}
                                    <span class="master-sub">{{ $invoice->project->name ?: 'Unnamed project' }}</span>
                                @else
                                    <span class="master-empty-value">No project mapped</span>
                                @endif
                            </td>
                            <td data-label="Total" class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</strong>
                                <span class="master-sub">
                                    {{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('item', $invoice->items->count()) }}
                                </span>
                            </td>
                            <td data-label="Received" class="is-num ui-mobile-secondary">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($received) }}</strong>
                                <span class="master-sub">
                                    @if ($invoice->payments_count > 0)
                                        {{ $invoice->payments_count }} {{ \Illuminate\Support\Str::plural('receipt', $invoice->payments_count) }}
                                    @else
                                        {{ $invoice->amount_paid > 0 ? 'Opening figure' : 'Nothing recorded' }}
                                    @endif
                                </span>
                            </td>
                            <td data-label="Balance" class="is-num">
                                @if ($invoice->isSuperseded())
                                    <span class="master-sub">Moved to {{ $invoice->convertedInvoice?->invoice_number }}</span>
                                @else
                                    <strong class="si-balance {{ $balance > 0 ? ($invoice->isOverdue() ? 'is-due' : '') : 'is-clear' }}">
                                        {{ \App\Helpers\CommonHelper::indianCurrency($balance) }}
                                    </strong>
                                @endif
                            </td>
                            <td data-label="Due">
                                @if ($invoice->due_date)
                                    <span class="si-date">{{ $invoice->due_date->format('d M Y') }}</span>
                                @else
                                    <span class="master-empty-value">No due date</span>
                                @endif
                                @if ($daysLate > 0)
                                    <span class="master-sub">{{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} late</span>
                                @elseif ($invoice->due_date && $balance > 0)
                                    <span class="master-sub">Not late yet</span>
                                @endif
                            </td>
                            <td data-label="State">
                                <span class="si-status status-{{ $stateKey }}">{{ $invoice->stateLabel() }}</span>
                                @if ($invoice->isSuperseded())
                                    <a class="master-sub si-converted-link"
                                        href="{{ route('sales-invoices.show', $invoice->convertedInvoice) }}">Became
                                        {{ $invoice->convertedInvoice?->invoice_number }}</a>
                                @endif
                                <span class="master-sub">
                                    <span class="si-portal {{ $invoice->show_client_portal ? 'is-public' : 'is-private' }}">
                                        {{ $invoice->show_client_portal ? 'In portal' : 'Not in portal' }}
                                    </span>
                                </span>
                            </td>
                            <td data-label="Action">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $invoice->invoice_number }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>

                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('sales-invoices.show', $invoice) }}">
                                                <i class="fas fa-eye" aria-hidden="true"></i> View invoice
                                            </a>
                                            <a href="{{ route('sales-invoices.edit', $invoice) }}">
                                                <i class="fas fa-pen" aria-hidden="true"></i> Edit
                                            </a>
                                            @if ($balance > 0)
                                                {{-- The receipt goes into the ledger, where the
                                                     bank line is reconciled — not into a
                                                     second number on the invoice. --}}
                                                <button type="button" data-open-payment
                                                    data-invoice-id="{{ $invoice->id }}"
                                                    data-invoice-number="{{ $invoice->invoice_number }}"
                                                    data-invoice-amount="{{ number_format($balance, 2, '.', '') }}"
                                                    data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balance) }}">
                                                    <i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Record payment
                                                </button>
                                            @endif
                                            <button type="button" data-open-reminder
                                                data-invoice-id="{{ $invoice->id }}"
                                                data-invoice-number="{{ $invoice->invoice_number }}"
                                                data-invoice-message="{{ $invoice->reminderMessage() }}">
                                                <i class="fas fa-bell" aria-hidden="true"></i> Log reminder
                                            </button>
                                            <button type="button" data-copy-text="{{ $invoice->reminderMessage() }}">
                                                <i class="fas fa-comment-dots" aria-hidden="true"></i> Copy reminder text
                                            </button>
                                            <a href="{{ route('sales-invoices.print', $invoice) }}" target="_blank">
                                                <i class="fas fa-print" aria-hidden="true"></i> Print
                                            </a>
                                            <button type="button" data-copy-link="{{ route('sales-invoices.public', $invoice->public_token) }}">
                                                <i class="fas fa-link" aria-hidden="true"></i> Copy client link
                                            </button>
                                            @if ($invoice->status === 'draft')
                                                <form method="POST" action="{{ route('sales-invoices.markSent', $invoice) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit">
                                                        <i class="fas fa-paper-plane" aria-hidden="true"></i> Mark sent
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('sales-invoices.portal', $invoice) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit">
                                                    <i class="fas fa-toggle-{{ $invoice->show_client_portal ? 'on' : 'off' }}" aria-hidden="true"></i>
                                                    {{ $invoice->show_client_portal ? 'Hide from portal' : 'Show in portal' }}
                                                </button>
                                            </form>
                                            @if ($invoice->invoice_type === 'proforma' && ! $invoice->isSuperseded())
                                                <form method="POST" action="{{ route('sales-invoices.convert', $invoice) }}"
                                                    data-confirm="Create a tax invoice from {{ $invoice->invoice_number }}? The advance and every receipt on it will move to the new tax invoice.">
                                                    @csrf
                                                    <button type="submit">
                                                        <i class="fas fa-file-invoice" aria-hidden="true"></i> Convert to tax invoice
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('sales-invoices.duplicate', $invoice) }}">
                                                @csrf
                                                <button type="submit">
                                                    <i class="fas fa-copy" aria-hidden="true"></i> Duplicate as draft
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('sales-invoices.destroy', $invoice) }}"
                                                data-confirm="Delete {{ $invoice->invoice_number }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger">
                                                    <i class="fas fa-trash" aria-hidden="true"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">₹</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtersActive ? 'No invoices match these filters' : 'No invoices yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Adjust the search or the filters above — the counts on each chip show what is available.'
                                            : 'Raise a proforma to ask for the money, or a tax invoice once it is agreed.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a class="master-btn master-btn-soft" href="{{ route('sales-invoices.index') }}">Clear filters</a>
                                        @endif
                                        <a class="master-btn master-btn-primary" href="{{ route('sales-invoices.create', ['type' => 'tax']) }}">+ New Tax Invoice</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($invoices->isNotEmpty())
                    <tfoot class="ui-mobile-secondary">
                        <tr class="master-list-total">
                            <td colspan="4">
                                <strong>Total — {{ $invoices->count() }} {{ \Illuminate\Support\Str::plural('invoice', $invoices->count()) }} shown</strong>
                                <span class="master-sub">Filtered totals cover every page · a converted proforma counts
                                    once</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['counted']) }}</strong>
                                <span class="master-sub">Sales + potential</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['received']) }}</strong>
                                <span class="master-sub">Received · all pages</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['outstanding']) }}</strong>
                                <span class="master-sub">Outstanding · all pages</span>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <x-pagination :items="$invoices" />
    </div>

    @include('sales_invoices.partials.payment-modal')
    @include('sales_invoices.partials.reminder-modal')
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/sales-invoices.js') }}"></script>
@endpush
