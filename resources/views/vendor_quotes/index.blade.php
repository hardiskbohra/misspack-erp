@extends('layouts.app')

@section('page-title', 'Vendor Quote Management')

@section('content')
    {{-- The --master-* custom properties are already defined in master-index.css (loaded globally). --}}
    <div class="master">

        <div class="master-stats">
            <div class="master-stat blue"><span class="icon">₹</span>
                <div>
                    <p class="master-stat-title">Total Quotes</p>
                    <p class="master-stat-value">{{ $stats['total'] }}</p>
                </div>
            </div>
            <div class="master-stat purple"><span class="icon">📩</span>
                <div>
                    <p class="master-stat-title">Received</p>
                    <p class="master-stat-value">{{ $stats['received'] }}</p>
                </div>
            </div>
            <div class="master-stat teal"><span class="icon">★</span>
                <div>
                    <p class="master-stat-title">Shortlisted</p>
                    <p class="master-stat-value">{{ $stats['shortlisted'] }}</p>
                </div>
            </div>
            <div class="master-stat orange"><span class="icon">✓</span>
                <div>
                    <p class="master-stat-title">Approved</p>
                    <p class="master-stat-value">{{ $stats['approved'] }}</p>
                </div>
            </div>
        </div>

        <div class="master-card">
            <form method="GET" action="{{ route('vendor-quotes.index') }}">
                <div class="master-toolbar">
                    <div class="master-search"><span>⌕</span><input class="master-input" name="search"
                            value="{{ $search }}" placeholder="Search vendor, quote, lead, product..."></div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap"><button type="button"
                            class="master-btn master-btn-primary" id="openQuickQuoteModal">+ Quick Quote</button><a
                            class="master-btn master-btn-soft" href="{{ route('vendor-quotes.create') }}">Detailed
                            Form</a><a class="master-btn master-btn-light" href="{{ route('leads.index') }}">Leads</a><a
                            class="master-btn master-btn-light" href="{{ route('leads.settings.index') }}">Settings</a>
                    </div>
                </div>
                <div class="master-filter-row"><select class="master-select" name="status">
                        <option value="all">All Status</option>
                        @foreach ($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select class="master-select" name="lead_id">
                        <option value="all">All Leads</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((string) $leadId === (string) $lead->id)>{{ $lead->lead_number }} -
                                {{ $lead->title }}</option>
                        @endforeach
                    </select>
                    <select class="master-select" name="currency">
                        <option value="all">All Currency</option>
                        @foreach ($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected($currency === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="master-btn master-btn-primary" type="submit">Filter</button><a
                        class="master-btn master-btn-light" href="{{ route('vendor-quotes.index') }}">Reset</a>
                </div>
            </form>
        </div>

        <div class="master-card master-table-card">
            <div class="master-table-wrap">
                <table class="master-table">
                    <thead>
                        <tr>
                            <th>Quote / Product</th>
                            <th>Vendor</th>
                            <th>Quantity</th>
                            <th>Vendor Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotes as $quote)
                            <tr>
                                <td>@php($productMedia = $quote->product?->primaryMedia())
                                    @php($quoteImage = $quote->product_image_path ?: ($productMedia?->file_path ?: $quote->lead?->product_image_path))<div class="master-product">
                                        @if ($quoteImage)
                                            <a href="{{ route('vendor-quotes.image', $quote) }}"><img
                                                    src="{{ asset('storage/' . $quoteImage) }}" alt="Product"
                                                class="master-product-img"></a>@else<span
                                                class="master-product-img">📦</span>
                                        @endif
                                        <div>
                                            <span class="master-id">{{ $quote->quote_number }}</span><span
                                                class="master-sub">{{ $quote->product?->name ?? $quote->product_name ?: $quote->lead?->product_name }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $quote->vendor?->vendor_contact_name ?? ($quote->vendor_contact_name ?? '-') }}
                                    <span class="master-sub">
                                        {{ \Illuminate\Support\Str::limit($quote->vendor_name ?: '-', 25, '...') }}
                                    </span></td>
                                <td>{!! $quote->items->isNotEmpty()
                                    ? $quote->items->map(fn($item) => number_format($item->quantity) . ' ' . $item->unit)->implode('<br>')
                                    : ($quote->quantity ? number_format($quote->quantity) . ' ' . $quote->unit : '-')
                                !!}</td>
                                <td>
                                    {!! $quote->items->isNotEmpty()
                                    ? $quote->items->map(fn($item) => $quote->currency . ' ' . number_format($item->vendor_unit_price, 2) . ($quote->incoterm ? ' ' . $quote->incoterm : ''))->implode('<br>')
                                    : ($quote->vendor_unit_price
                                        ? $quote->currency . ' ' . number_format((float) $quote->vendor_unit_price, 2) . ($quote->incoterm ? ' ' . $quote->incoterm : '')
                                        : '-')
                                !!}</td>
                                <td><span
                                        class="master-badge status-{{ $quote->status }}">{{ $quote->statusLabel() }}</span>
                                </td>
                                <td>
                                    
                                    <div class="master-row-actions">

                                        <div class="master-dropdown">
                                            <button type="button" class="master-dropdown-toggle">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                    
                                            <div class="master-dropdown-menu">
                                    
                                                <a href="{{ route('vendor-quotes.show', $quote) }}">
                                                    <i class="fas fa-eye"></i>
                                                    View Quote
                                                </a>
                                    
                                                <a href="{{ route('vendor-quotes.edit', $quote) }}">
                                                    <i class="fas fa-pen"></i>
                                                    Edit Quote
                                                </a>
                                    
                                                <form method="POST"
                                                      action="{{ route('vendor-quotes.destroy', $quote) }}"
                                                      onsubmit="return confirm('Delete this Quote?')">
                                    
                                                    @csrf
                                                    @method('DELETE')
                                    
                                                    <button type="submit" class="danger">
                                                        <i class="fas fa-trash"></i>
                                                        Delete Quote
                                                    </button>
                                    
                                                </form>
                                    
                                            </div>
                                        </div>
                            
                                    </div>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="8" style="padding:70px;text-align:center;color:#687386;font-weight:900">No
                                    vendor quotes found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-pagination :items="$quotes" />
        </div>

        <div class="master-modal" id="quickQuoteModal" aria-hidden="true">
            <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickQuoteTitle">
                <form method="POST" action="{{ route('vendor-quotes.quickStore') }}" enctype="multipart/form-data">@csrf
                    <div class="master-modal-header">
                        <div>
                            <h3 class="master-modal-title" id="quickQuoteTitle">Quick Vendor Quote</h3>
                            <p class="master-modal-subtitle">Create a simple quote for a lead or standalone enquiry.</p>
                        </div><button type="button" class="master-modal-close" data-close-modal="quickQuoteModal">×</button>
                    </div>
                    <div class="master-modal-body">
                        <div class="master-modal-grid">
                            <div><label class="master-label">Lead</label><select class="master-select" name="lead_id">
                                    <option value="">Standalone quote</option>
                                    @foreach ($leads as $lead)
                                        <option value="{{ $lead->id }}">{{ $lead->lead_number }} -
                                            {{ $lead->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Product</label><select class="master-select"
                                    name="product_id" required>
                                    <option value="">Select Product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->product_number }} - {{ $product->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div><label class="master-label">Vendor Name</label><input class="master-input"
                                    name="vendor_contact_name" placeholder="Vendor name if not selected" required></div>
                        </div>
                    </div>
                    <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                            data-close-modal="quickQuoteModal">Cancel</button><button
                            class="master-btn master-btn-primary" type="submit">Create Quote</button></div>
                </form>
            </div>
        </div>
    </div>

@push('scripts')
    <script src="{{ asset('assets/js/vendors.js') }}"></script>
@endpush
@endsection
