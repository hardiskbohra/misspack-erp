@extends('layouts.app')

@section('title', 'Purchases')
@section('page-title', 'Purchases')

@section('page-actions')
    <a class="master-btn master-btn-primary" href="{{ route('purchase-invoices.create', ['type' => 'bill']) }}">+ New Purchase Bill</a>
    <a class="master-btn master-btn-soft" href="{{ route('purchase-invoices.create', ['type' => 'order']) }}">New Purchase Order</a>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/sales-invoices.css') }}">
@endpush

@php
    $chipBase = collect(request()->except(['invoice_type', 'status', 'payment', 'date_from', 'date_to', 'page']))
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');
    $chipActive = [
        'all' => $type === 'all' && $status === 'all' && $payment === 'all' && blank($dateFrom) && blank($dateTo),
        'order' => $type === 'order',
        'bill' => $type === 'bill',
        'draft' => $status === 'draft',
    ];
    $filtersActive = $appliedChips !== [];
    $chipUrl = function (array $keys) {
        $keep = collect(request()->except(array_merge($keys, ['page'])))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('purchase-invoices.index', $keep->all());
    };
@endphp

<div class="si-index master-list">
    <div class="master-stats">
        @foreach ($stats as $stat)
            <div class="master-stat master-stat--flat {{ $stat['tone'] }}">
                <span class="icon">{{ ($stat['money'] ?? true) ? '₹' : '✎' }}</span>
                <div>
                    <p class="master-stat-title">{{ $stat['label'] }}</p>
                    <p class="master-stat-value">
                        @if ($stat['money'] ?? true)
                            {{ \App\Helpers\CommonHelper::indianCurrency($stat['value']) }}
                        @else
                            {{ $stat['value'] }}
                        @endif
                    </p>
                    @if (! empty($stat['note']))
                        <p class="master-sub">{{ $stat['note'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                <a class="master-list-chip {{ $chipActive['all'] ? 'is-active' : '' }}"
                    href="{{ route('purchase-invoices.index', $chipBase->all()) }}">All purchases</a>
                <a class="master-list-chip {{ $chipActive['order'] ? 'is-active' : '' }}"
                    href="{{ route('purchase-invoices.index', $chipBase->all() + ['invoice_type' => 'order']) }}">
                    Orders <span class="master-list-chip-count">{{ $chipCounts['order'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['bill'] ? 'is-active' : '' }}"
                    href="{{ route('purchase-invoices.index', $chipBase->all() + ['invoice_type' => 'bill']) }}">
                    Bills <span class="master-list-chip-count">{{ $chipCounts['bill'] ?? 0 }}</span>
                </a>
                <a class="master-list-chip {{ $chipActive['draft'] ? 'is-active' : '' }}"
                    href="{{ route('purchase-invoices.index', $chipBase->all() + ['status' => 'draft']) }}">
                    Drafts <span class="master-list-chip-count">{{ $chipCounts['draft'] ?? 0 }}</span>
                </a>
                @foreach ($dateRanges as $rangeKey => $range)
                    <a class="master-list-chip {{ $activeRange === $rangeKey ? 'is-active' : '' }}"
                        href="{{ route('purchase-invoices.index', $chipBase->all() + ['date_from' => $range['from'], 'date_to' => $range['to']]) }}">
                        {{ $dateRangeLabels[$rangeKey] }}
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('purchase-invoices.index') }}">
            <div class="si-filter-toolbar" role="search">
                <div class="master-search si-filter-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Search number, vendor, bill no...">
                    <button class="si-search-submit" type="submit" aria-label="Search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    </button>
                </div>
                <x-filter-trigger drawer="purchaseFiltersDrawer" label="Filters" :count="count($appliedChips)" />
            </div>

            <x-drawer id="purchaseFiltersDrawer" title="Filter purchases" eyebrow="Purchase filters"
                subtitle="Refine by type, vendor, payment or date." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Document</h3>
                    <div class="si-filter-grid">
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterType">Type</label>
                            <select class="master-select" name="invoice_type" id="piFilterType">
                                <option value="all">All types</option>
                                @foreach($typeOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterStatus">Status</label>
                            <select class="master-select" name="status" id="piFilterStatus">
                                <option value="all">All statuses</option>
                                @foreach($allStatusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                                <option value="overdue" @selected($status === 'overdue')>Overdue</option>
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterCurrency">Currency</label>
                            <select class="master-select" name="currency" id="piFilterCurrency">
                                <option value="all">Any currency</option>
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($currency === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterDateFrom">From</label>
                            <input class="master-input" type="date" name="date_from" id="piFilterDateFrom" value="{{ $dateFrom }}">
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterDateTo">To</label>
                            <input class="master-input" type="date" name="date_to" id="piFilterDateTo" value="{{ $dateTo }}">
                        </div>
                    </div>
                </section>
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Vendor &amp; project</h3>
                    <div class="si-filter-grid">
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterVendor">Vendor</label>
                            <select class="master-select" name="vendor" id="piFilterVendor">
                                <option value="0">All vendors</option>
                                @foreach($vendors as $vendorRow)
                                    <option value="{{ $vendorRow->id }}" @selected((int) $vendor === (int) $vendorRow->id)>{{ $vendorRow->vendor_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterProject">Project</label>
                            <select class="master-select" name="project" id="piFilterProject">
                                <option value="0">All projects</option>
                                @foreach($projects as $projectRow)
                                    <option value="{{ $projectRow->id }}" @selected((int) $project === (int) $projectRow->id)>{{ $projectRow->project_number }} - {{ $projectRow->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="si-filter-field">
                            <label class="master-label" for="piFilterPayment">Payment</label>
                            <select class="master-select" name="payment" id="piFilterPayment">
                                <option value="all">Any payment state</option>
                                @foreach($paymentLabels as $key => $label)
                                    <option value="{{ $key }}" @selected($payment === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('purchase-invoices.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>

            @if ($filtersActive)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>
                    @foreach ($appliedChips as $chip)
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                            <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl($chip['query'] ?? []) }}">&times;</a>
                        </span>
                    @endforeach
                    <a class="master-list-applied-clear" href="{{ route('purchase-invoices.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-table-wrap">
            <table class="master-table si-table">
                <thead>
                    <tr>
                        <th scope="col">Document</th>
                        <th scope="col">Vendor</th>
                        <th scope="col" class="ui-mobile-secondary">Project</th>
                        <th scope="col" class="is-num">Total</th>
                        <th scope="col" class="is-num ui-mobile-secondary">Paid</th>
                        <th scope="col" class="is-num">Balance</th>
                        <th scope="col">Due</th>
                        <th scope="col">State</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $paidAmt = $invoice->paidAmount();
                            $balance = $invoice->balanceDue();
                            $stateKey = $invoice->stateKey();
                            $daysLate = $invoice->daysOverdue();
                        @endphp
                        <tr data-href="{{ route('purchase-invoices.show', $invoice) }}">
                            <td data-label="Document">
                                <a class="si-number" href="{{ route('purchase-invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                                <span class="si-invoice-chips">
                                    <span class="si-type type-{{ $invoice->invoice_type }}">{{ $invoice->typeLabel() }}</span>
                                    @if ($invoice->vendor_bill_number)
                                        <span class="master-chip">Vendor {{ $invoice->vendor_bill_number }}</span>
                                    @endif
                                </span>
                                @if ($invoice->invoice_date)
                                    <span class="master-sub si-date">{{ $invoice->invoice_date->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td data-label="Vendor">
                                <span class="si-client">{{ $invoice->vendor_company_name ?: 'No vendor' }}</span>
                                <span class="master-sub ui-mobile-secondary">{{ $invoice->vendor_gstin ?: 'No GSTIN on file' }}</span>
                            </td>
                            <td data-label="Project" class="ui-mobile-secondary">
                                @if ($invoice->project)
                                    {{ $invoice->project->project_number }}
                                    <span class="master-sub">{{ $invoice->project->name }}</span>
                                @else
                                    <span class="master-empty-value">No project mapped</span>
                                @endif
                            </td>
                            <td data-label="Total" class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($invoice->total_amount) }}</strong>
                            </td>
                            <td data-label="Paid" class="is-num ui-mobile-secondary">
                                @if ($invoice->isBill())
                                    <strong>{{ \App\Helpers\CommonHelper::indianCurrency($paidAmt) }}</strong>
                                @else
                                    <span class="master-empty-value">Not payable</span>
                                @endif
                            </td>
                            <td data-label="Balance" class="is-num">
                                @if ($invoice->isSuperseded())
                                    <span class="master-sub">Moved to {{ $invoice->convertedInvoice?->invoice_number }}</span>
                                @elseif ($invoice->isOrder())
                                    <span class="master-empty-value">Raise a bill</span>
                                @else
                                    <strong class="si-balance {{ $balance > 0 ? ($invoice->isOverdue() ? 'is-due' : '') : 'is-clear' }}">
                                        {{ \App\Helpers\CommonHelper::indianCurrency($balance) }}
                                    </strong>
                                @endif
                            </td>
                            <td data-label="Due">
                                @if ($invoice->due_date)
                                    <span class="si-date">{{ $invoice->due_date->format('d M Y') }}</span>
                                @elseif ($invoice->expected_date)
                                    <span class="si-date">Exp {{ $invoice->expected_date->format('d M Y') }}</span>
                                @else
                                    <span class="master-empty-value">No due date</span>
                                @endif
                                @if ($daysLate > 0)
                                    <span class="master-sub">{{ $daysLate }} {{ \Illuminate\Support\Str::plural('day', $daysLate) }} late</span>
                                @endif
                            </td>
                            <td data-label="State">
                                <span class="si-status status-{{ $stateKey }}">{{ $invoice->stateLabel() }}</span>
                                @if ($invoice->isSuperseded())
                                    <a class="master-sub si-converted-link" href="{{ route('purchase-invoices.show', $invoice->convertedInvoice) }}">
                                        Became {{ $invoice->convertedInvoice?->invoice_number }}
                                    </a>
                                @endif
                            </td>
                            <td data-label="Action">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('purchase-invoices.show', $invoice) }}"><i class="fas fa-eye"></i> View</a>
                                            <a href="{{ route('purchase-invoices.edit', $invoice) }}"><i class="fas fa-pen"></i> Edit</a>
                                            @if ($invoice->isBill() && $balance > 0)
                                                <button type="button" data-open-payment
                                                    data-invoice-id="{{ $invoice->id }}"
                                                    data-invoice-number="{{ $invoice->invoice_number }}"
                                                    data-invoice-amount="{{ number_format($balance, 2, '.', '') }}"
                                                    data-invoice-balance="{{ \App\Helpers\CommonHelper::indianCurrency($balance) }}">
                                                    <i class="fas fa-indian-rupee-sign"></i> Record payment
                                                </button>
                                            @endif
                                            <a href="{{ route('purchase-invoices.print', $invoice) }}" target="_blank"><i class="fas fa-print"></i> Print</a>
                                            @if ($invoice->canConvert())
                                                <form method="POST" action="{{ route('purchase-invoices.convert', $invoice) }}"
                                                    data-confirm="Raise a purchase bill from {{ $invoice->invoice_number }}?">
                                                    @csrf
                                                    <button type="submit"><i class="fas fa-file-invoice"></i> Convert to bill</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('purchase-invoices.destroy', $invoice) }}"
                                                data-confirm="Delete {{ $invoice->invoice_number }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger"><i class="fas fa-trash"></i> Delete</button>
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
                                    <span class="master-list-empty-icon">₹</span>
                                    <p class="master-list-empty-title">{{ $filtersActive ? 'No documents match these filters' : 'No purchase documents yet' }}</p>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Adjust the search or the filters above.'
                                            : 'Raise a purchase order to ask for supply, or a purchase bill once the vendor invoices you.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a class="master-btn master-btn-soft" href="{{ route('purchase-invoices.index') }}">Clear filters</a>
                                        @endif
                                        <a class="master-btn master-btn-primary" href="{{ route('purchase-invoices.create', ['type' => 'bill']) }}">+ New Purchase Bill</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($invoices->isNotEmpty())
                    <tfoot class="ui-mobile-secondary">
                        <tr class="master-list-total">
                            <td colspan="3">
                                <strong>Total — {{ $invoices->count() }} shown</strong>
                                <span class="master-sub">Filtered totals cover every page · a converted order counts once</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['billed'] + $pageTotals['open']) }}</strong>
                                <span class="master-sub">Billed + open orders</span>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['paid']) }}</strong>
                            </td>
                            <td class="is-num">
                                <strong>{{ \App\Helpers\CommonHelper::indianCurrency($pageTotals['outstanding']) }}</strong>
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <x-pagination :items="$invoices" />
    </div>

    @include('purchase_invoices.partials.payment-modal')
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/purchase-invoices.js') }}"></script>
@endpush
