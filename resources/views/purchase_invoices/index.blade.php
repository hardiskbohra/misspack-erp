@extends('layouts.app')

@section('title', $docType === 'order' ? 'Purchase Orders' : 'Purchase Bills')
@section('page-title', $docType === 'order' ? 'Purchase Orders' : 'Purchase Bills')

{{-- What the office buys, in the two documents it buys with: the order that
     goes out, and the bill that comes back. The primary action lives in the
     header so it stays reachable however far the list scrolls. --}}
@section('page-actions')
    @if ($docType === 'order')
        <a class="master-btn master-btn-primary" href="{{ route('purchase-orders.create') }}">+ New Purchase Order</a>
        <a class="master-btn master-btn-soft" href="{{ route('purchase-bills.index') }}">Purchase Bills</a>
    @else
        <a class="master-btn master-btn-primary" href="{{ route('purchase-bills.create') }}">+ New Purchase Bill</a>
        <a class="master-btn master-btn-soft" href="{{ route('purchase-orders.index') }}">Purchase Orders</a>
    @endif
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/purchase-invoices.css') }}">
@endpush

@php
    $isOrder = $docType === 'order';
    $prefix = $isOrder ? 'purchase-orders' : 'purchase-bills';
    $noun = $isOrder ? 'purchase order' : 'purchase bill';

    /* Which chips are lit. The counts come from the controller, asked of the
       same query the rows come from — a chip that claims a number has to be
       showing it. */
    $chipBase = collect(request()->except(['status', 'payment', 'currency', 'date_from', 'date_to', 'page']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

    $filtersActive = $appliedChips !== [];

    /* Removing a chip drops the query keys it owns. */
    $chipUrl = function (array $keys) use ($prefix) {
        $keep = collect(request()->except(array_merge($keys, ['page'])))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route($prefix.'.index', $keep->all());
    };

    $statusChips = $isOrder
        ? ['draft' => 'Drafts', 'sent' => 'Sent', 'approved' => 'Approved', 'billed' => 'Billed']
        : ['draft' => 'Drafts', 'received' => 'Received', 'partial' => 'Partly paid', 'paid' => 'Paid', 'overdue' => 'Overdue'];
@endphp

<div class="pi-index master-list">
    {{-- The figures: what the filtered rows add up to, and the one number that is
         deliberately not filtered (the drafts waiting to go out). Every figure is
         the model's money rule asked of the whole filtered set in one aggregate,
         never a sum of what happens to be on this page. --}}
    <div class="master-stats">
        @foreach ($stats as $stat)
            <div class="master-stat master-stat--flat {{ $stat['tone'] }} tooltip-container">
                <span class="icon" aria-hidden="true">
                    <i class="fa-solid {{ $stat['tone'] === 'red' ? 'fa-triangle-exclamation' : ($stat['money'] ?? true ? 'fa-indian-rupee-sign' : 'fa-pen-ruler') }}"></i>
                </span>
                <div>
                    <p class="master-stat-title">{{ $stat['label'] }}</p>
                    <p class="master-stat-value">
                        {{ ($stat['money'] ?? true) ? \App\Helpers\CommonHelper::indianCurrency($stat['value']) : $stat['value'] }}
                    </p>
                    @if ($stat['note'] ?? null)
                        <p class="master-sub">{{ $stat['note'] }}</p>
                    @endif
                </div>
                @if ($stat['label'] === 'Drafts')
                    <span class="tooltip-text">Across the module, not the filtered set — the chip that asks for drafts is the one that makes them the filtered set.</span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ $status === 'all' ? 'is-active' : '' }}"
                    href="{{ route($prefix.'.index', $chipBase->all()) }}">All {{ $isOrder ? 'orders' : 'bills' }}</a>

                @foreach ($statusChips as $statusKey => $statusLabel)
                    <a class="master-list-chip {{ $status === $statusKey ? 'is-active' : '' }}"
                        href="{{ route($prefix.'.index', $chipBase->all() + ['status' => $statusKey]) }}">
                        {{ $statusLabel }} <span class="master-list-chip-count">{{ $chipCounts[$statusKey] ?? 0 }}</span>
                    </a>
                @endforeach

                {{-- The money questions (nothing paid / part paid / paid) and how
                     late a bill is live in the filter row, where they read as the
                     lists they are. --}}
            </div>
        </div>

        <form method="GET" action="{{ route($prefix.'.index') }}">
            <div class="pi-filter-toolbar" role="search" aria-label="Search and filter {{ $noun }}s">
                <div class="master-search pi-filter-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Search number, vendor, bill number, reference..."
                        aria-label="Search {{ $noun }}s">
                    <button class="pi-search-submit" type="submit" aria-label="Search {{ $noun }}s">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                </div>
                <x-filter-trigger drawer="purchaseFiltersDrawer" label="Filters" :count="count($appliedChips)" />
            </div>

            <x-drawer id="purchaseFiltersDrawer" title="Filter {{ $noun }}s"
                eyebrow="{{ $isOrder ? 'Purchase order' : 'Purchase bill' }} filters"
                subtitle="Refine the list by vendor, payment state, currency, or date." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Document</h3>
                    <div class="pi-filter-grid">
                        <div class="pi-filter-field">
                            <label class="master-label" for="piFilterStatus">Status</label>
                            <select class="master-select" name="status" id="piFilterStatus" aria-label="Filter by status">
                                <option value="all">All statuses</option>
                                @foreach ($allStatusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                                <option value="overdue" @selected($status === 'overdue')>Overdue</option>
                            </select>
                        </div>
                        <div class="pi-filter-field">
                            <label class="master-label" for="piFilterCurrency">Currency</label>
                            <select class="master-select" name="currency" id="piFilterCurrency" aria-label="Filter by currency">
                                <option value="all">Any currency</option>
                                @foreach ($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($currency === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pi-filter-field">
                            <label class="master-label" for="piFilterDateFrom">Dated from</label>
                            <input class="master-input" type="date" name="date_from" id="piFilterDateFrom"
                                value="{{ $dateFrom }}" aria-label="Dated from">
                        </div>
                        <div class="pi-filter-field">
                            <label class="master-label" for="piFilterDateTo">Dated to</label>
                            <input class="master-input" type="date" name="date_to" id="piFilterDateTo"
                                value="{{ $dateTo }}" aria-label="Dated to">
                        </div>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Vendor &amp; project</h3>
                    <div class="pi-filter-grid">
                        <div class="pi-filter-field">
                            <label class="master-label" for="piFilterVendor">Vendor</label>
                            <select class="master-select" name="vendor" id="piFilterVendor" aria-label="Filter by vendor">
                                <option value="all">All vendors</option>
                                @foreach ($vendors as $vendorOption)
                                    <option value="{{ $vendorOption->id }}" @selected((string) request('vendor') === (string) $vendorOption->id)>{{ $vendorOption->vendor_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pi-filter-field">
                            <label class="master-label" for="piFilterProject">Project</label>
                            <select class="master-select" name="project" id="piFilterProject" aria-label="Filter by project">
                                <option value="all">All projects</option>
                                @foreach ($projects as $projectOption)
                                    <option value="{{ $projectOption->id }}" @selected((string) request('project') === (string) $projectOption->id)>{{ $projectOption->project_number }} - {{ $projectOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                @if (! $isOrder)
                    <section class="core-drawer-section">
                        <h3 class="core-drawer-section-title">Payment</h3>
                        <div class="pi-filter-grid">
                            <div class="pi-filter-field">
                                <label class="master-label" for="piFilterPayment">Payment state</label>
                                <select class="master-select" name="payment" id="piFilterPayment" aria-label="Filter by what has been paid">
                                    <option value="all">Any payment state</option>
                                    <option value="nothing" @selected($payment === 'nothing')>Nothing paid</option>
                                    <option value="partial" @selected($payment === 'partial')>Part paid</option>
                                    <option value="paid" @selected($payment === 'paid')>Fully paid</option>
                                </select>
                            </div>
                        </div>
                    </section>
                @endif

                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route($prefix.'.index') }}">Reset</a>
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

                    <a class="master-list-applied-clear" href="{{ route($prefix.'.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint"
                title="{{ $isOrder
                    ? 'Newest document date on top. Open value is what has not been billed yet.'
                    : 'Newest document date on top. The balance is what the vendor ledger says is still owed.' }}">
                {{ $isOrder ? 'Newest first · open value is what is not billed yet' : 'Newest first · balance is what the ledger says' }}
            </p>

            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap">
            <table class="master-table pi-table" data-table-settings data-table-key="{{ $prefix }}">
                {{-- Ten columns, in the order the headings are written: the pick
                     box, the document (number, type chip, date), the vendor, the
                     project, what it is worth, what has been paid, what is left,
                     the due date, the state chip, the one action. The widths live
                     in the sheet, next to the other rules about this table. --}}
                <colgroup>
                    <col class="pi-col-pick">
                    <col class="pi-col-doc">
                    <col class="pi-col-vendor">
                    <col class="pi-col-project">
                    <col class="pi-col-total">
                    <col class="pi-col-paid">
                    <col class="pi-col-balance">
                    <col class="pi-col-due">
                    <col class="pi-col-state">
                    <col class="pi-col-action">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="master-list-pick ui-mobile-secondary">
                            <input type="checkbox" data-bulk-all aria-label="Select every {{ $noun }} on this page">
                        </th>
                        <th scope="col">Document</th>
                        <th scope="col">Vendor</th>
                        <th scope="col" class="ui-mobile-secondary">Project</th>
                        <th scope="col" class="is-num">Total</th>
                        <th scope="col" class="is-num ui-mobile-secondary">{{ $isOrder ? 'Billed' : 'Paid' }}</th>
                        <th scope="col" class="is-num">{{ $isOrder ? 'Open value' : 'Balance' }}</th>
                        <th scope="col">{{ $isOrder ? 'Expected' : 'Due' }}</th>
                        <th scope="col">State</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            /* Every one of these is the model's rule, not the
                               view's arithmetic: the list, the record page and the
                               figures cannot disagree. */
                            $paid = $invoice->paidAmount();
                            $due = $invoice->balanceDue();
                            $stateKey = $invoice->stateKey();
                            $daysLate = $invoice->daysOverdue();
                        @endphp
                        <tr data-href="{{ route($prefix.'.show', $invoice) }}">
                            <td data-label="Pick" class="master-list-pick ui-mobile-secondary">
                                <input type="checkbox" value="{{ $invoice->id }}" disabled
                                    aria-label="Select {{ $invoice->invoice_number }}" title="Bulk actions arrive with the second stage">
                            </td>
                            <td data-label="Document">
                                <a class="pi-number" href="{{ route($prefix.'.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                                <span class="pi-doc-chips">
                                    @if ($invoice->isSuperseded())
                                        <span class="pi-type type-bill">Billed</span>
                                    @endif
                                    @if ($invoice->vendor_bill_number)
                                        <span class="master-chip">Vendor {{ $invoice->vendor_bill_number }}</span>
                                    @endif
                                    @if ($invoice->our_reference)
                                        <span class="master-chip">Ref {{ $invoice->our_reference }}</span>
                                    @endif
                                </span>
                                @if ($invoice->invoice_date)
                                    <span class="master-sub pi-date">{{ $invoice->invoice_date->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Vendor">
                                <span class="pi-vendor">{{ $invoice->vendor_company_name ?: 'No vendor' }}</span>
                                <span class="master-sub ui-mobile-secondary">{{ $invoice->vendor_gstin ?: 'No GSTIN on file' }}</span>
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
                                <strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong>
                                <span class="master-sub">
                                    {{ $invoice->items->count() }} {{ \Illuminate\Support\Str::plural('item', $invoice->items->count()) }}
                                </span>
                            </td>
                            <td data-label="{{ $isOrder ? 'Billed' : 'Paid' }}" class="is-num ui-mobile-secondary">
                                @if ($isOrder)
                                    @if ($invoice->isSuperseded())
                                        <strong>{{ \App\Helpers\CommonHelper::amount($invoice->billedAmount(), $invoice->currency) }}</strong>
                                        <span class="master-sub">Billed in full</span>
                                    @else
                                        <span class="master-empty-value">—</span>
                                        <span class="master-sub">Not billed yet</span>
                                    @endif
                                @else
                                    <strong>{{ \App\Helpers\CommonHelper::amount($paid, $invoice->currency) }}</strong>
                                    <span class="master-sub">
                                        @if ($invoice->payments_count > 0)
                                            {{ $invoice->payments_count }} {{ \Illuminate\Support\Str::plural('payment', $invoice->payments_count) }}
                                        @else
                                            {{ $invoice->amount_paid > 0 ? 'Opening figure' : 'Nothing paid' }}
                                        @endif
                                    </span>
                                @endif
                            </td>
                            <td data-label="{{ $isOrder ? 'Open value' : 'Balance' }}" class="is-num">
                                @if ($isOrder)
                                    @if ($invoice->isSuperseded())
                                        <span class="master-sub">Became {{ $invoice->convertedInvoice?->invoice_number }}</span>
                                    @else
                                        <strong>{{ \App\Helpers\CommonHelper::amount($invoice->total_amount, $invoice->currency) }}</strong>
                                        <span class="master-sub">To be billed</span>
                                    @endif
                                @else
                                    <strong class="pi-balance {{ $due > 0 ? ($invoice->isOverdue() ? 'is-due' : '') : 'is-clear' }}">
                                        {{ \App\Helpers\CommonHelper::amount($due, $invoice->currency) }}
                                    </strong>
                                @endif
                            </td>
                            <td data-label="{{ $isOrder ? 'Expected' : 'Due' }}">
                                @php($milestone = $isOrder ? $invoice->expected_date : $invoice->due_date)
                                @if ($milestone)
                                    <span class="pi-date">{{ $milestone->format('d M Y') }}</span>
                                @else
                                    <span class="master-empty-value">{{ $isOrder ? 'No expected date' : 'No due date' }}</span>
                                @endif
                                @if ($daysLate > 0)
                                    <span class="master-sub">{{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} late</span>
                                @elseif (! $isOrder && $invoice->due_date && $due > 0)
                                    <span class="master-sub">Not late yet</span>
                                @endif
                            </td>
                            <td data-label="State">
                                <span class="pi-status status-{{ $stateKey }}">{{ $invoice->stateLabel() }}</span>
                                @if ($invoice->isSuperseded())
                                    <a class="master-sub pi-converted-link"
                                        href="{{ route('purchase-bills.show', $invoice->convertedInvoice) }}">Became
                                        {{ $invoice->convertedInvoice?->invoice_number }}</a>
                                @elseif ($invoice->purchaseOrder)
                                    <a class="master-sub pi-converted-link"
                                        href="{{ route('purchase-orders.show', $invoice->purchaseOrder) }}">From
                                        {{ $invoice->purchaseOrder->invoice_number }}</a>
                                @endif
                                <span class="master-sub">
                                    <span class="pi-public {{ $invoice->public_token ? 'is-public' : 'is-private' }}">
                                        {{ $invoice->public_token ? 'Link live' : 'No link' }}
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
                                            <a href="{{ route($prefix.'.show', $invoice) }}">
                                                <i class="fas fa-eye" aria-hidden="true"></i> View
                                            </a>
                                            <a href="{{ route($prefix.'.edit', $invoice) }}">
                                                <i class="fas fa-pen" aria-hidden="true"></i> Edit
                                            </a>
                                            @if (! $isOrder && $due > 0)
                                                {{-- The payment goes into the vendor ledger, where the
                                                     bank line is reconciled — not into a second number
                                                     on the bill. --}}
                                                <button type="button" data-open-payment
                                                    data-invoice-id="{{ $invoice->id }}"
                                                    data-invoice-number="{{ $invoice->invoice_number }}"
                                                    data-invoice-currency="{{ $invoice->currency }}"
                                                    data-invoice-rate="{{ (float) ($invoice->currency === 'INR' ? 1 : ($invoice->exchange_rate ?: 1)) }}"
                                                    data-invoice-balance-figure="{{ number_format($due, 2, '.', '') }}"
                                                    data-invoice-balance="{{ \App\Helpers\CommonHelper::amount($due, $invoice->currency) }}">
                                                    <i class="fas fa-indian-rupee-sign" aria-hidden="true"></i> Record payment
                                                </button>
                                            @endif
                                            <a href="{{ route($prefix.'.print', $invoice) }}" target="_blank">
                                                <i class="fas fa-print" aria-hidden="true"></i> Print
                                            </a>
                                            <button type="button"
                                                data-copy-link="{{ route('purchase-invoices.public', $invoice->public_token) }}">
                                                <i class="fas fa-link" aria-hidden="true"></i> Copy print link
                                            </button>
                                            @if ($isOrder && $invoice->canConvert())
                                                <form method="POST" action="{{ route('purchase-orders.convert', $invoice) }}"
                                                    data-confirm="Raise a purchase bill from {{ $invoice->invoice_number }}? Its lines and terms are copied onto the bill.">
                                                    @csrf
                                                    <button type="submit">
                                                        <i class="fas fa-file-invoice" aria-hidden="true"></i> Convert to purchase bill
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($stateKey === 'draft')
                                                <form method="POST" action="{{ route($prefix.'.status', $invoice) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $isOrder ? 'sent' : 'received' }}">
                                                    <button type="submit">
                                                        <i class="fas fa-paper-plane" aria-hidden="true"></i> {{ $isOrder ? 'Mark sent' : 'Mark received' }}
                                                    </button>
                                                </form>
                                            @endif
                                            @if (in_array($stateKey, ['draft', 'sent', 'approved', 'received'], true))
                                                <form method="POST" action="{{ route($prefix.'.status', $invoice) }}"
                                                    data-confirm="Cancel {{ $invoice->invoice_number }}?">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit">
                                                        <i class="fas fa-ban" aria-hidden="true"></i> Cancel
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route($prefix.'.destroy', $invoice) }}"
                                                data-confirm="Delete {{ $invoice->invoice_number }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger">
                                                    <i class="far fa-trash-alt" aria-hidden="true"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">
                                        <i class="fa-solid {{ $isOrder ? 'fa-file-signature' : 'fa-file-invoice' }}"></i>
                                    </span>
                                    <h3 class="master-list-empty-title">No {{ $noun }}s yet</h3>
                                    <p class="master-list-empty-text">
                                        {{ $isOrder
                                            ? 'Raise an order for what you are buying; the bill that arrives from it is raised from the order itself.'
                                            : 'A bill can be raised from an approved purchase order, or entered on its own when the vendor’s paperwork arrives first.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        <a class="master-btn master-btn-primary" href="{{ route($prefix.'.create') }}">
                                            <i class="fas fa-plus" aria-hidden="true"></i> New {{ $isOrder ? 'order' : 'bill' }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                @if ($invoices->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="4">
                                <span class="master-sub">{{ $invoices->total() }} {{ \Illuminate\Support\Str::plural($noun, $invoices->total()) }} · filtered</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['total']) }}</strong>
                                <span class="master-sub">{{ $isOrder ? 'Ordered' : 'Billed' }} · all pages</span>
                            </td>
                            <td class="is-num ui-mobile-secondary">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($isOrder ? $pageTotals['open'] : $pageTotals['paid']) }}</strong>
                                <span class="master-sub">{{ $isOrder ? 'Open' : 'Paid' }} · all pages</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($isOrder ? $pageTotals['total'] - $pageTotals['open'] : $pageTotals['outstanding']) }}</strong>
                                <span class="master-sub">{{ $isOrder ? 'Billed' : 'Outstanding' }} · all pages</span>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <x-pagination :items="$invoices" />
    </div>

    @unless ($isOrder)
        @include('purchase_invoices.partials.payment-modal')
    @endunless
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/purchase-invoices.js') }}"></script>
@endpush
