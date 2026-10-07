@extends('layouts.app')

@section('title', 'Fixed assets')
@section('page-title', 'Fixed assets')

@section('page-actions')
    {{-- Whatever the reader narrowed the register to is what the file carries: the
         export is the same query, not a second list that happens to look alike. --}}
    <a class="master-btn master-btn-ghost" href="{{ route('assets.export', request()->query()) }}">
        <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export register
    </a>
    <a class="master-btn master-btn-soft" href="{{ route('assets.depreciation') }}">
        <i class="fa-solid fa-calculator" aria-hidden="true"></i> Depreciation report
    </a>
    {{-- The classes are a setting and live in Settings with the other five
         areas; this is the door between the register and the recipe. --}}
    <a class="master-btn master-btn-ghost" href="{{ route('settings.assets') }}">
        <i class="fa-solid fa-sliders" aria-hidden="true"></i> Asset classes
    </a>
    <button type="button" class="master-btn master-btn-primary" data-open-asset-modal="register">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Register an asset
    </button>
@endsection

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ $assetVer('assets/css/assets.css') }}">
@endpush
@php
    $money = fn ($amount, $currency = 'INR') => \App\Helpers\CommonHelper::amount($amount, $currency);

    /* A chip owns one dimension and keeps the others; `page` restarts because a
       filter change is a new list. The state chip is the one dimension whose
       "off" value is a word rather than an empty field, so it is added rather
       than removed. */
    $chipUrl = function (string $state) {
        $keep = collect(request()->except(['status', 'page']))
            ->reject(fn ($value) => $value === null || $value === '');

        return route('assets.index', $state === 'everything' ? $keep->all() : $keep->all() + ['status' => $state]);
    };

    /* A link that keeps the whole filter and changes one thing — how an attention
       card opens the rows behind its own number. */
    $viewUrl = fn (array $extra) => route('assets.index', collect(request()->query())
        ->except('page')
        ->merge($extra)
        ->reject(fn ($value) => $value === null || $value === '' || $value === 'all' || $value === 'any' || $value === 'everything')
        ->all());

    /* A share of the company's capitalised value, said as a percentage and never
       as a second total that could disagree with the first. It goes through the
       vocabulary's formatter like every other number this module prints. */
    $share = fn ($part) => $figures['capitalised'] > 0
        ? \App\Services\AssetVocabulary::percentLabel($part / $figures['capitalised'] * 100, 1).'%'
        : '—';
@endphp

<div class="fixed-assets master-list ast-index">

    {{-- ───────────────────────────────────────────────────── what we own --}}
    {{-- Every figure here is the register's own figures service reading the same
         builder the table below reads: narrowing to a place narrows the totals,
         because the totals are the view. --}}
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat blue tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-industry"></i></span>
            <div>
                <p class="master-stat-title">On the register</p>
                <p class="master-stat-value">{{ number_format($figures['total']) }}</p>
                <p class="master-sub">
                    {{ number_format($figures['in_use']) }} in use ·
                    {{ number_format($figures['spare']) }} in store ·
                    {{ number_format($figures['disposed']) }} disposed
                </p>
                <span class="tooltip-text">Every asset this view holds. The states are the same four the chips filter by.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat green tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <div>
                <p class="master-stat-title">Total fixed assets</p>
                <p class="master-stat-value">{{ $money($figures['capitalised']) }}</p>
                <p class="master-sub">
                    on a total cost of {{ $money($figures['invoice_total']) }}
                    @if ($figures['gst'] > 0)
                        (incl. {{ $money($figures['gst']) }} GST)
                    @endif
                </p>
                <span class="tooltip-text">What the books carry the assets at: the invoice value, with GST capitalised where the company cannot claim the credit. It excludes nothing else — it is the basis the depreciation below is charged on.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat teal tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
            <div>
                <p class="master-stat-title">Net book value today</p>
                <p class="master-stat-value">{{ $money($figures['net_book_value']) }}</p>
                <p class="master-sub">{{ $share($figures['net_book_value']) }} of the capitalised value</p>
                <span class="tooltip-text">Capitalised value less depreciation to today, asset by asset on its own life and method — not a flat percentage of the total.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat orange tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-trend-down"></i></span>
            <div>
                <p class="master-stat-title">Depreciation to date</p>
                <p class="master-stat-value">{{ $money($figures['accumulated']) }}</p>
                <p class="master-sub">{{ $share($figures['accumulated']) }} of the capitalised value</p>
                <span class="tooltip-text">Everything the assets have been written down by since the day each was bought — disposed assets stop where they were sold.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat purple tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-calendar-day"></i></span>
            <div>
                <p class="master-stat-title">Charge this year</p>
                <p class="master-stat-value">{{ $money($figures['charge_this_year']) }}</p>
                <p class="master-sub">financial year {{ $figures['financial_year'] }}</p>
                <span class="tooltip-text">The depreciation this view's assets carry for the year now running — pro-rated for anything bought or sold during it, which is why it is not simply a twelfth a month.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-dollar"></i></span>
            <div>
                <p class="master-stat-title">Disposed, realised</p>
                <p class="master-stat-value">{{ $money($figures['disposal_value']) }}</p>
                <p class="master-sub">what sold assets fetched, across all years</p>
                <span class="tooltip-text">The sale value recorded when an asset left the books. Whether each one made a profit is on the asset's own page and in the year's schedule.</span>
            </div>
        </div>
    </div>

    {{-- ────────────────────────────────────────── what needs doing this week --}}
    {{-- Three counts and a total: each one is the same query its link opens, so
         a card that says four opens four rows. --}}
    <div class="master-stats desktop-only">
        <div class="master-stat master-stat--flat {{ $figures['warranty_soon'] > 0 ? 'orange' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></span>
            <div>
                <p class="master-stat-title">Warranty running out</p>
                <p class="master-stat-value">{{ number_format($figures['warranty_soon']) }}</p>
                <p class="master-sub"><a href="{{ $viewUrl(['warranty' => 'expiring', 'status' => 'everything']) }}">see them</a></p>
                <span class="tooltip-text">Under cover for sixty days or fewer. Ends in this number, and the same rows the filter opens — a repair inside the window is somebody else's bill.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $figures['insurance_soon'] > 0 ? 'orange' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-file-shield"></i></span>
            <div>
                <p class="master-stat-title">Insurance expiring</p>
                <p class="master-stat-value">{{ number_format($figures['insurance_soon']) }}</p>
                <p class="master-sub">policies ending within sixty days</p>
                <span class="tooltip-text">Assets whose recorded cover runs out inside two months. A register with an insurance column and no reminder is a register nobody renews from.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $figures['verify_due'] > 0 ? 'red' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-clipboard-check"></i></span>
            <div>
                <p class="master-stat-title">Verification overdue</p>
                <p class="master-stat-value">{{ number_format($figures['verify_due']) }}</p>
                <p class="master-sub"><a href="{{ $viewUrl(['verification' => 'overdue', 'status' => 'everything']) }}">start the round</a></p>
                <span class="tooltip-text">Assets nobody has physically checked in the last year, or ever. This is the reading an auditor asks for, and the register answers it without a spreadsheet.</span>
            </div>
        </div>

        <div class="master-stat master-stat--flat {{ $figures['service_due'] > 0 ? 'orange' : 'green' }} tooltip-container">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <div>
                <p class="master-stat-title">Service due</p>
                <p class="master-stat-value">{{ number_format($figures['service_due']) }}</p>
                <p class="master-sub"><a href="{{ $viewUrl(['service' => 'due', 'status' => 'everything']) }}">the diary</a></p>
                <span class="tooltip-text">Assets whose last recorded service asked for the next one inside sixty days — including the ones already past their date.</span>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────── search and filter --}}
    <section class="master-card master-card--flat" aria-label="Search and filter the register">
        <div class="master-list-bar">
            <nav class="master-list-chips" aria-label="Filter assets by state">
                @foreach ($stateOptions as $key => $label)
                    <a class="master-list-chip {{ $status === $key ? 'is-active' : '' }}"
                        href="{{ $chipUrl($key) }}">
                        {{ $label }}
                        <span class="master-list-chip-count">{{ number_format($stateCounts[$key] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        <form method="GET" action="{{ route('assets.index') }}">
            {{-- The chips are links, so they are not part of this form. Carrying
                 the chosen state keeps a search from quietly dropping it. --}}
            @if ($status !== 'everything')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif

            <div class="master-filter-row core-filter-toolbar">
                <label class="master-search">
                    <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input class="master-input" type="search" name="q" value="{{ $q }}"
                        placeholder="Search an asset, a serial number, an invoice or a holder"
                        aria-label="Search the fixed asset register">
                </label>

                <x-filter-trigger drawer="assetFiltersDrawer" label="Filters" :count="count($applied)" />
            </div>

            <x-drawer id="assetFiltersDrawer" title="Filter the register" eyebrow="Asset filters"
                subtitle="The place, the person and the compliance readings. The chips above carry the state."
                size="medium">
                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">What it is</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Class</span>
                            <select class="master-select" name="category">
                                <option value="all" @selected($category === 'all')>Every class</option>
                                @foreach ($categoryOptions as $option)
                                    <option value="{{ $option->id }}" @selected((string) $category === (string) $option->id)>
                                        {{ $option->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Condition</span>
                            <select class="master-select" name="condition">
                                <option value="all" @selected($condition === 'all')>Any condition</option>
                                @foreach ($conditionOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($condition === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Where it is</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Location</span>
                            <select class="master-select" name="location">
                                <option value="all" @selected($location === 'all')>Everywhere</option>
                                @foreach ($locations as $option)
                                    <option value="{{ $option }}" @selected($location === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Department</span>
                            <select class="master-select" name="department">
                                <option value="all" @selected($department === 'all')>Every department</option>
                                @foreach ($departments as $option)
                                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Custodian</span>
                            <select class="master-select" name="custodian">
                                <option value="all" @selected($custodian === 'all')>Anyone</option>
                                @foreach ($peopleOptions as $person)
                                    <option value="{{ $person->id }}" @selected((string) $custodian === (string) $person->id)>
                                        {{ $person->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">The compliance readings</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Warranty</span>
                            <select class="master-select" name="warranty">
                                @foreach ($warrantyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($warranty === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Physical verification</span>
                            <select class="master-select" name="verification">
                                @foreach ($verificationOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($verification === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="master-help">The year's sighting round: who was checked, who was never.</span>
                        </label>
                        <label class="master-field">
                            <span class="master-label">Service diary</span>
                            <select class="master-select" name="service">
                                @foreach (\App\Services\AssetFilters::SERVICE_LABELS as $key => $label)
                                    <option value="{{ $key }}" @selected($service === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="master-help">Next service asked for within sixty days, overdue ones included.</span>
                        </label>
                    </div>
                </section>

                <section class="core-drawer-section">
                    <h3 class="core-drawer-section-title">Order</h3>
                    <div class="core-drawer-fields">
                        <label class="master-field">
                            <span class="master-label">Read the list</span>
                            <select class="master-select" name="sort">
                                @foreach ($sortOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="master-help">There is no "net book value" order here: that figure is a curve, and the cards above answer it.</span>
                        </label>
                    </div>
                </section>

                <x-slot:footer>
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('assets.index') }}">Reset</a>
                    @endif
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-filter" aria-hidden="true"></i> Apply filters
                    </button>
                </x-slot:footer>
            </x-drawer>
        </form>

        @if ($filtered)
            <div class="master-list-applied">
                <span class="master-list-applied-title">Filtered by</span>
                @foreach ($applied as $chip)
                    <span class="master-list-applied-chip">
                        <span class="master-list-applied-key">{{ $chip['label'] }}</span>
                        <span class="master-list-applied-value">{{ $chip['value'] }}</span>
                        <a class="master-list-applied-x"
                            href="{{ route('assets.index', collect(request()->query())->except([...$chip['query'], 'page'])->all()) }}"
                            aria-label="Remove the {{ strtolower($chip['label']) }} filter">&times;</a>
                    </span>
                @endforeach
                <a class="master-list-applied-clear" href="{{ route('assets.index') }}">Clear all</a>
            </div>
        @endif
    </section>

    {{-- ─────────────────────────────────────────────────────────── the register --}}
    <section class="master-card master-card--flat ast-table-card" aria-label="The fixed asset register">
        @if ($assets->isEmpty())
            <div class="master-list-empty">
                <span class="master-list-empty-icon" aria-hidden="true">🏢</span>
                <h3 class="master-list-empty-title">
                    {{ $filtered ? 'No assets match this view' : 'Nothing on the register yet' }}
                </h3>
                <p class="master-list-empty-text">
                    @if ($filtered)
                        Nothing left after the chips and filters above. Widen them, or export what there is.
                    @else
                        Laptops, machinery, furniture, the vehicle — everything the company owns and
                        claims depreciation on. Register the first one and the register starts keeping
                        its own book value, hand-overs and repairs.
                    @endif
                </p>
                <div class="master-list-empty-actions">
                    @if ($filtered)
                        <a class="master-btn master-btn-soft" href="{{ route('assets.index') }}">Clear the filters</a>
                    @endif
                    <button type="button" class="master-btn master-btn-primary" data-open-asset-modal="register">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Register an asset
                    </button>
                </div>
            </div>
        @else
            <div class="master-table-wrap ui-mobile-cards">
                <table class="master-table ast-table">
                    <thead>
                        <tr>
                            <th scope="col">Asset</th>
                            <th scope="col">Class</th>
                            <th scope="col">Purchased</th>
                            <th scope="col" class="ast-col-money">Cost</th>
                            <th scope="col" class="ast-col-money">Book value</th>
                            <th scope="col">Where it is</th>
                            <th scope="col" class="ast-col-state">State</th>
                            <th scope="col">Warranty</th>
                            <th scope="col" class="ast-col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            <tr class="ast-row is-clickable" data-href="{{ route('assets.show', $asset) }}">
                                <td data-label="Asset">
                                    <a class="ast-asset-link" href="{{ route('assets.show', $asset) }}">
                                        {{ $asset->name }}
                                    </a>
                                    <span class="ast-cell-sub">
                                        <span class="ast-code">{{ $asset->asset_code }}</span>
                                        @if (filled($asset->make) || filled($asset->model))
                                            · {{ trim($asset->make.' '.$asset->model) }}
                                        @endif
                                        @if (filled($asset->serial_no))
                                            · <span class="ast-mono">{{ $asset->serial_no }}</span>
                                        @endif
                                    </span>
                                </td>

                                <td data-label="Class">
                                    {{ $asset->category?->name ?: 'Unclassified' }}
                                    <span class="ast-cell-sub">{{ $asset->recipeLabel() }}</span>
                                </td>

                                <td data-label="Purchased">
                                    {{ $asset->purchase_date?->format('d M Y') ?: '—' }}
                                    <span class="ast-cell-sub">{{ $asset->ageLabel() ?: 'age not known' }}</span>
                                </td>

                                <td class="ast-col-money" data-label="Cost">
                                    <span class="ast-money">{{ $money($asset->totalCost()) }}</span>
                                    <span class="ast-cell-sub">on the books at {{ $money($asset->capitalisedCost()) }}</span>
                                </td>

                                {{-- The two figures the office used to keep on the
                                     spreadsheet and get wrong: both are computed
                                     today, from the asset's own life and method. --}}
                                <td class="ast-col-money" data-label="Book value">
                                    <span class="ast-money">{{ $money($asset->netBookValue()) }}</span>
                                    <span class="ast-cell-sub">{{ $money($asset->accumulatedDepreciation()) }} written off</span>
                                </td>

                                <td data-label="Where it is">
                                    {{ $asset->placeLabel() }}
                                    <span class="ast-cell-sub">{{ $asset->holderLabel() }}</span>
                                </td>

                                <td class="ast-col-state" data-label="State">
                                    <span class="core-badge core-badge-{{ $asset->stateTone() }}">{{ $asset->stateLabel() }}</span>
                                    <span class="ast-cell-sub">{{ $asset->conditionLabel() }}</span>
                                </td>

                                <td data-label="Warranty">
                                    {{ $asset->warrantyLabel() }}
                                    @if ($asset->insuranceExpiring() || $asset->insuranceExpired())
                                        <span class="ast-cell-sub">
                                            insurance {{ $asset->insuranceExpired() ? 'expired' : 'expiring' }}
                                        </span>
                                    @endif
                                </td>

                                <td class="ast-col-actions" data-label="Actions">
                                    <div class="master-dropdown">
                                        <button type="button" class="master-dropdown-toggle"
                                            aria-label="Actions for {{ $asset->name }}" aria-haspopup="true"
                                            aria-expanded="false">
                                            <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                        </button>
                                        <div class="master-dropdown-menu">
                                            <a href="{{ route('assets.show', $asset) }}">
                                                <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                                                Open the asset
                                            </a>

                                            <button type="button" data-open-asset-modal="allocate"
                                                data-action="{{ route('assets.allocate', $asset) }}"
                                                data-subject="{{ $asset->asset_code }} · {{ $asset->name }}"
                                                data-current="Currently {{ $asset->holderLabel() }} at {{ $asset->placeLabel() }}.">
                                                <i class="fa-solid fa-hand-holding-hand" aria-hidden="true"></i>
                                                Hand it over
                                            </button>

                                            @if ($asset->openAllocation)
                                                <button type="button" data-open-asset-modal="return"
                                                    data-action="{{ route('assets.takeBack', $asset) }}"
                                                    data-subject="{{ $asset->asset_code }} · {{ $asset->name }}"
                                                    data-current="{{ $asset->holderLabel() }} has held it since {{ $asset->openAllocation->allocated_on?->format('d M Y') }} — {{ number_format($asset->openAllocation->daysHeld()) }} days.">
                                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                                    Take it back
                                                </button>
                                            @endif

                                            <button type="button" data-open-asset-modal="maintenance"
                                                data-action="{{ route('assets.maintain', $asset) }}"
                                                data-subject="{{ $asset->asset_code }} · {{ $asset->name }}">
                                                <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                                                Log a repair
                                            </button>

                                            <button type="button" data-open-asset-modal="verify"
                                                data-action="{{ route('assets.verify', $asset) }}"
                                                data-subject="{{ $asset->asset_code }} · {{ $asset->name }}"
                                                data-current="Last checked {{ $asset->last_verified_on?->format('d M Y') ?: 'never' }} — {{ $asset->verificationLabel() }}.">
                                                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i>
                                                Verified today
                                            </button>

                                            @unless ($asset->isDisposed())
                                                <button type="button" data-open-asset-modal="dispose"
                                                    data-action="{{ route('assets.dispose', $asset) }}"
                                                    data-subject="{{ $asset->asset_code }} · {{ $asset->name }}"
                                                    data-current="Carried at {{ $money($asset->netBookValue()) }} today. Depreciation stops on the date below.">
                                                    <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                                                    Dispose of it
                                                </button>
                                            @endunless

                                            <form method="POST" action="{{ route('assets.destroy', $asset) }}"
                                                data-confirm="Delete {{ $asset->asset_code }} — {{ $asset->name }}? Its hand-over and repair history goes with it, and the register drops {{ $money($asset->netBookValue()) }} of book value that no longer exists."
                                                data-confirm-title="Delete the asset"
                                                data-confirm-text="Delete it">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                    Delete it
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$assets" />
        @endif
    </section>
</div>

{{-- ─────────────────────────────────────────────────────── a new asset --}}
<span hidden data-open-dialog="{{ $errors->any() ? old('_dialog') : '' }}"></span>

@include('assets.partials.modal-register')

{{-- The four things that happen to an asset while it is in the register. They are
     one dialog each rather than one per row: the row's menu names which asset it
     means in `data-action`, and `assets.js` points the form at it. A dialog per
     row would be thirty copies of the same eleven fields. --}}
@include('assets.partials.modal-allocate')
@include('assets.partials.modal-return')
@include('assets.partials.modal-maintenance')
@include('assets.partials.modal-verify')
@include('assets.partials.modal-dispose')
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/assets.js') }}" defer></script>
@endpush
