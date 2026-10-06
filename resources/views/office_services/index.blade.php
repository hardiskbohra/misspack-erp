@extends('layouts.app')

@section('title', 'Office services')
@section('page-title', 'Office services')

@section('page-actions')
    <button type="button" class="master-btn master-btn-primary" data-open-add-modal>
        <i class="fas fa-plus" aria-hidden="true"></i> Add service
    </button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/employees.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/users.css') }}">
    <link rel="stylesheet" href="{{ $assetVer('assets/css/office-services.css') }}">
@endpush

@php
    $chipUrl = function (string $key) {
        $keep = collect(request()->except([$key, 'page']))
            ->reject(fn ($value) => $value === null || $value === '' || $value === 'all');

        return route('office-services.index', $keep->all());
    };

    $classLabels = ['all' => 'All'] + $classOptions;
@endphp

@if ($errors->any())
    <div hidden data-open-dialog="add"></div>
@endif

<div class="office-services user-index master-list">

    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true">🧹</span>
            <div>
                <p class="master-stat-title">Services</p>
                <p class="master-stat-value">{{ $stats['total'] }}</p>
                <p class="master-sub">{{ $stats['active'] }} active</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true">🏷</span>
            <div>
                <p class="master-stat-title">Classes</p>
                <p class="master-stat-value">{{ $stats['classes'] }}</p>
                <p class="master-sub">housekeeping, water, flowers…</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Monthly retainers</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['retainers']) }}</p>
                <p class="master-sub">active monthly agreements</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat teal">
            <span class="icon" aria-hidden="true">₹</span>
            <div>
                <p class="master-stat-title">Paid in {{ $stats['month_label'] }}</p>
                <p class="master-stat-value">{{ \App\Helpers\CommonHelper::indianCurrency($stats['paid_this_month']) }}</p>
                <p class="master-sub">cashflow debits this month</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $stats['missing_pay'] ? 'orange' : 'green' }}">
            <span class="icon" aria-hidden="true">{{ $stats['missing_pay'] ? '!' : '✓' }}</span>
            <div>
                <p class="master-stat-title">No bank / UPI</p>
                <p class="master-stat-value">{{ $stats['missing_pay'] }}</p>
                <p class="master-sub">active {{ \Illuminate\Support\Str::plural('service', $stats['missing_pay']) }} still to file pay details</p>
            </div>
        </div>
    </div>

    <div class="master-card master-card--flat">
        <div class="master-list-bar">
            <div class="master-list-chips">
                @foreach ($classLabels as $key => $label)
                    <a class="master-list-chip {{ ($class ?: 'all') === $key ? 'is-active' : '' }}"
                        href="{{ route('office-services.index', collect(request()->except(['class', 'page']))->reject(fn ($v) => $v === null || $v === '' || $v === 'all')->all() + ($key !== 'all' ? ['class' => $key] : [])) }}">
                        {{ $label }}
                        <span class="master-list-chip-count">{{ $classCounts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <form method="GET" action="{{ route('office-services.index') }}">
            @if ($class !== 'all')
                <input type="hidden" name="class" value="{{ $class }}">
            @endif

            <div class="master-filter-row core-filter-toolbar">
                <div class="master-search">
                    <span aria-hidden="true">⌕</span>
                    <input class="master-input" type="text" name="search" value="{{ $search }}"
                        placeholder="Name, number, phone…" aria-label="Search office services">
                </div>
                <x-filter-trigger drawer="officeServiceFiltersDrawer" label="Filters" :count="count($filterChips)" />
            </div>

            <x-drawer id="officeServiceFiltersDrawer" title="Filter office services" eyebrow="Office service filters"
                subtitle="Narrow the list by status." size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Record</h3>
                    <div class="core-drawer-fields">
                        <div class="master-field">
                            <label class="master-label" for="ofsFilterStatus">Status</label>
                            <select class="master-select" id="ofsFilterStatus" name="status" aria-label="Filter by status">
                                <option value="all">Any status</option>
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>
                <x-slot:footer>
                    @if ($filtersActive)
                        <a class="master-btn master-btn-soft" href="{{ route('office-services.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">Apply filters</button>
                </x-slot:footer>
            </x-drawer>

            @if ($filtersActive)
                <div class="master-list-applied">
                    <span class="master-list-applied-title">Filtered by</span>
                    @foreach ($filterChips as $chip)
                        <span class="master-list-applied-chip">
                            <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                            <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                            <a class="master-list-applied-x" href="{{ $chipUrl($chip['key']) }}"
                                aria-label="Remove the {{ strtolower($chip['label']) }} filter"
                                title="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                        </span>
                    @endforeach
                    <a class="master-list-applied-clear" href="{{ route('office-services.index') }}">Clear all filters</a>
                </div>
            @endif
        </form>
    </div>

    <div class="master-card master-table-card master-card--flat">
        <div class="master-list-toolbar">
            <p class="master-list-hint">Facility retainers — not employees, not purchase vendors</p>
            <div class="master-list-toolbar-actions">
                <div class="master-list-density desktop-only" role="group" aria-label="Table density">
                    <button type="button" class="master-list-density-btn" data-density="standard" aria-pressed="true">Standard</button>
                    <button type="button" class="master-list-density-btn" data-density="comfortable" aria-pressed="false">Comfortable</button>
                    <button type="button" class="master-list-density-btn" data-density="compact" aria-pressed="false">Compact</button>
                </div>
            </div>
        </div>

        <div class="master-table-wrap">
            <table class="master-table" data-table-settings data-table-key="office-services">
                <thead>
                    <tr>
                        <th scope="col">Service</th>
                        <th scope="col">Class</th>
                        <th scope="col" class="ui-mobile-secondary">Contact</th>
                        <th scope="col" class="ui-mobile-secondary">Retainer</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $row)
                        <tr class="user-row is-clickable" data-href="{{ route('office-services.show', $row) }}">
                            <td data-label="Service">
                                <a class="user-cell" href="{{ route('office-services.show', $row) }}">
                                    <span class="user-avatar-mini" aria-hidden="true">{{ $row->initials() }}</span>
                                    <span class="user-cell-text">
                                        <span class="user-cell-name">{{ $row->name }}</span>
                                        <span class="master-sub">{{ $row->service_number }}</span>
                                    </span>
                                </a>
                            </td>
                            <td data-label="Class">
                                <span class="emp-pill is-info">{{ $row->classLabel() }}</span>
                            </td>
                            <td data-label="Contact" class="ui-mobile-secondary">
                                {{ $row->phone ?: '—' }}
                                <span class="master-sub">{{ $row->contact_name ?: $row->email ?: '' }}</span>
                            </td>
                            <td data-label="Retainer" class="ui-mobile-secondary">
                                @if ($row->retainer_amount)
                                    {{ \App\Helpers\CommonHelper::indianCurrency($row->retainer_amount) }}
                                    <span class="master-sub">{{ $row->cycleLabel() }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td data-label="Status">
                                <span class="emp-pill {{ $row->status === 'active' ? 'is-ok' : 'is-off' }}">{{ $row->statusLabel() }}</span>
                            </td>
                            <td data-label="Action">
                                <div class="master-row-actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $row->name }}"
                                            aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('office-services.show', $row) }}">
                                                <i class="fas fa-folder-open"></i>
                                                Open record
                                            </a>
                                            <a href="{{ route('office-services.show', [$row, 'tab' => 'details']) }}">
                                                <i class="fas fa-pen"></i>
                                                Edit
                                            </a>
                                            <a href="{{ route('office-services.show', [$row, 'tab' => 'money']) }}">
                                                <i class="fas fa-indian-rupee-sign"></i>
                                                Payments
                                            </a>
                                            <form method="POST" action="{{ route('office-services.destroy', $row) }}" data-ofs-delete>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="danger">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="master-list-empty">
                                    <span class="master-list-empty-icon" aria-hidden="true">🧹</span>
                                    <p class="master-list-empty-title">
                                        {{ $filtersActive ? 'Nobody matches these filters' : 'No office services yet' }}
                                    </p>
                                    <p class="master-list-empty-text">
                                        {{ $filtersActive
                                            ? 'Adjust the search or the filters above.'
                                            : 'Add the maid, water supplier, florist and the rest here — not under Users or Vendors.' }}
                                    </p>
                                    <div class="master-list-empty-actions">
                                        @if ($filtersActive)
                                            <a class="master-btn master-btn-soft" href="{{ route('office-services.index') }}">Clear filters</a>
                                        @endif
                                        <button type="button" class="master-btn master-btn-primary" data-open-add-modal>+ Add service</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="master-list-total">
                        <td colspan="5">
                            <strong>Total — {{ $services->count() }} {{ \Illuminate\Support\Str::plural('service', $services->count()) }} shown</strong>
                            <span class="master-sub">{{ $filtersActive ? 'of '.$stats['total'].' in the file, with these filters' : $stats['active'].' active' }}</span>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <x-pagination :items="$services" />
    </div>

    <div class="master-modal" id="addModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="addOfsTitle">
            <form method="POST" action="{{ route('office-services.store') }}" id="addForm">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true">＋</span>
                        <div>
                            <h3 class="master-modal-title" id="addOfsTitle">Add an office service</h3>
                            <p class="master-modal-subtitle">A facility retainer — they do not log in</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="addModal" aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    @if ($errors->any())
                        <div class="master-info-box is-danger">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <div class="master-section-label">Who is this?</div>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="addName">Name <span class="master-required">*</span></label>
                            <input class="master-input" id="addName" name="name" required value="{{ old('name') }}" placeholder="e.g. Meena — housekeeping">
                            @error('name')<span class="master-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addClass">Class <span class="master-required">*</span></label>
                            <select class="master-select" id="addClass" name="service_class" required>
                                @foreach ($classOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('service_class', 'housekeeping') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addContact">Person</label>
                            <input class="master-input" id="addContact" name="contact_name" value="{{ old('contact_name') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addPhone">Phone</label>
                            <input class="master-input" id="addPhone" name="phone" value="{{ old('phone') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addEmail">Email</label>
                            <input class="master-input" id="addEmail" type="email" name="email" value="{{ old('email') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addStatus">Status</label>
                            <select class="master-select" id="addStatus" name="status">
                                @foreach ($statusOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('status', 'active') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="master-section-label">Retainer</div>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="addRetainer">Amount (₹)</label>
                            <input class="master-input" id="addRetainer" name="retainer_amount" type="number" step="0.01" min="0" value="{{ old('retainer_amount') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addCycle">Cycle</label>
                            <select class="master-select" id="addCycle" name="retainer_cycle">
                                @foreach ($cycleOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('retainer_cycle', 'monthly') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="currency" value="INR">
                    </div>
                    <div class="master-section-label">Pay them</div>
                    <div class="master-form-grid">
                        <div class="master-field">
                            <label class="master-label" for="addBank">Bank</label>
                            <input class="master-input" id="addBank" name="bank_name" value="{{ old('bank_name') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addAccName">Account name</label>
                            <input class="master-input" id="addAccName" name="bank_account_name" value="{{ old('bank_account_name') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addAcc">Account number</label>
                            <input class="master-input" id="addAcc" name="bank_account_number" value="{{ old('bank_account_number') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addIfsc">IFSC</label>
                            <input class="master-input" id="addIfsc" name="bank_ifsc" value="{{ old('bank_ifsc') }}">
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="addUpi">UPI</label>
                            <input class="master-input" id="addUpi" name="upi_id" value="{{ old('upi_id') }}">
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="addAddress">Address</label>
                            <textarea class="master-input" id="addAddress" name="address" rows="2">{{ old('address') }}</textarea>
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="addNotes">Notes</label>
                            <textarea class="master-input" id="addNotes" name="notes" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="addModal">Cancel</button>
                    <button type="submit" class="master-btn master-btn-primary">Add service</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ $assetVer('assets/js/office-services.js') }}"></script>
@endpush
@endsection
