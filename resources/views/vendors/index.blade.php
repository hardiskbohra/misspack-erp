@extends('layouts.app')

@section('page-title', 'Vendor Management')

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendors.css') }}">
@endpush

<div class="vendor vendor-index">

    <div class="master-stats">
        <div class="master-stat blue"><span class="icon">🏭</span>
            <div>
                <p class="master-stat-title">Total Vendors</p>
                <p class="master-stat-value">{{ $stats['total'] }}</p>
            </div>
        </div>
        <div class="master-stat teal"><span class="icon">✓</span>
            <div>
                <p class="master-stat-title">Active</p>
                <p class="master-stat-value">{{ $stats['active'] }}</p>
            </div>
        </div>
        <div class="master-stat purple"><span class="icon">🌍</span>
            <div>
                <p class="master-stat-title">International</p>
                <p class="master-stat-value">{{ $stats['international'] }}</p>
            </div>
        </div>
        <div class="master-stat orange"><span class="icon">!</span>
            <div>
                <p class="master-stat-title">Hold / Blacklisted</p>
                <p class="master-stat-value">{{ $stats['on_hold'] + $stats['blacklisted'] }}</p>
            </div>
        </div>
    </div>

    <div class="master-card">
        <form method="GET" action="{{ route('vendors.index') }}">
            <div class="master-toolbar">
                <div class="master-search"><span>⌕</span><input class="master-input" type="text" name="search"
                        value="{{ $search }}" placeholder="Search vendor, brand, category, country, contact..."></div>
                <div class="master-actions-top"><button type="button" class="master-btn master-btn-primary"
                        id="openQuickVendorModal">+ Quick Vendor</button><a href="{{ route('vendors.create') }}"
                        class="master-btn master-btn-soft">Detailed Form</a></div>
            </div>
            <div class="master-filter-row">
                <select class="master-select" name="status">
                    <option value="all">All Status</option>@foreach($statusOptions as $key => $label)<option
                        value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach
                </select>
                <select class="master-select" name="type">
                    <option value="all">All Types</option>@foreach($typeOptions as $key => $label)<option
                        value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach
                </select>
                <select class="master-select" name="country">
                    <option value="all">All Countries</option>@foreach($countries as $countryName)<option
                        value="{{ $countryName }}" @selected($country === $countryName)>{{ $countryName }}</option>
                    @endforeach
                </select>
                <button class="master-btn master-btn-primary" type="submit">Filter</button><a
                    class="master-btn master-btn-light" href="{{ route('vendors.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="master-card master-table-card">
        <div class="master-table-wrap ui-mobile-cards">
            <table class="master-table" data-table-settings data-table-key="vendors">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Type</th>
                        <th class="ui-mobile-secondary">Contact</th>
                        <th class="ui-mobile-secondary">Location</th>
                        <th class="ui-mobile-secondary">Website / Alibaba</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $vendor)
                    @php($statusClass = str_replace('_', '-', $vendor->status))
                    @php($typeClass = str_replace('_', '-', $vendor->vendor_type))
                    <tr>
                        <td data-label="Vendor">
                            <a href="{{ route('vendors.show', $vendor) }}" style="text-decoration:none;">
                                <div class="master-profile">
                                    @if($vendor->image_path)
                                        <img class="master-avatar" src="{{ asset('storage/' . $vendor->image_path) }}"
                                            alt="{{ $vendor->vendor_name }}">
                                    @else
                                        <span class="master-avatar">{{ strtoupper(substr($vendor->vendor_name, 0, 1)) }}</span>
                                    @endif
                                    <div><span class="master-sub">{{ $vendor->vendor_number }}</span><span
                                            class="master-id ui-mobile-secondary">{{ $vendor->contact_person_name ?: '-' }}</span><span
                                            class="master-sub">{{ \Illuminate\Support\Str::limit($vendor->vendor_name, 25, '…') }}</span></div>
                                </div>
                            </a>
                        </td>
                        <td data-label="Type"><span class="master-badge type-{{ $typeClass }}">{{ $vendor->typeLabel() }}</span>
                            <span class="master-sub">{{ $vendor->category ?: '-' }}</span></td>
                        <td data-label="Contact" class="ui-mobile-secondary"><span
                                class="master-sub">{{ $vendor->contact_person_email ?: '-' }}</span><span
                                class="master-sub">{{ $vendor->contact_person_mobile ?: '-' }}</span></td>
                        <td data-label="Location" class="ui-mobile-secondary">{{ $vendor->city ?: '-' }}<span class="master-sub">{{ $vendor->country ?: '-' }}</span></td>
                        <td data-label="Website / Alibaba" class="ui-mobile-secondary">
                            @if($vendor->website)<a href="{{ $vendor->website }}" target="_blank">Website</a>@else -
                            @endif
                            <span class="master-sub">@if($vendor->alibaba_link)<a href="{{ $vendor->alibaba_link }}"
                            target="_blank">Alibaba Link</a>@else No Alibaba link @endif</span>
                        </td>
                        <td data-label="Status"><span class="master-badge status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span>
                        </td>
                        <td data-label="Action">
                            <div class="master-row-actions">
                                <button type="button" class="master-icon-btn" title="Quick details"
                                    aria-label="Quick details for {{ $vendor->vendor_name }}"
                                    aria-haspopup="dialog" aria-controls="vendorQuickDetails" aria-expanded="false"
                                    data-drawer-open="vendorQuickDetails" data-drawer-eyebrow="Vendor"
                                    data-drawer-title="{{ $vendor->vendor_name }}"
                                    data-drawer-subtitle="{{ $vendor->vendor_number }}"
                                    data-drawer-contact-name="{{ $vendor->contact_person_name }}"
                                    data-drawer-contact-email="{{ $vendor->contact_person_email }}"
                                    data-drawer-contact-mailto="{{ $vendor->contact_person_email ? 'mailto:'.$vendor->contact_person_email : '' }}"
                                    data-drawer-contact-mobile="{{ $vendor->contact_person_mobile }}"
                                    data-drawer-contact-tel="{{ $vendor->contact_person_mobile ? 'tel:'.preg_replace('/[^0-9+]/', '', $vendor->contact_person_mobile) : '' }}"
                                    data-drawer-type="{{ $vendor->typeLabel() }}"
                                    data-drawer-category="{{ $vendor->category }}"
                                    data-drawer-location="{{ collect([$vendor->city, $vendor->state, $vendor->country])->filter()->implode(', ') }}"
                                    data-drawer-status="{{ $vendor->statusLabel() }}"
                                    data-drawer-website="{{ $vendor->website }}"
                                    data-drawer-alibaba="{{ $vendor->alibaba_link }}"
                                    data-drawer-record-url="{{ route('vendors.show', $vendor) }}">
                                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                </button>
                                <a href="{{ route('vendors.edit', $vendor) }}" class="master-icon-btn"
                                    title="Edit" aria-label="Edit {{ $vendor->vendor_name }}">✎</a>
                                <button type="button" class="master-icon-btn danger master-delete-btn" title="Delete"
                                    aria-label="Delete {{ $vendor->vendor_name }}" data-id="{{ $vendor->id }}"
                                    data-name="{{ $vendor->vendor_name }}"
                                    data-delete-url="{{ route('vendors.destroy', $vendor) }}">🗑</button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7">
                            <div class="master-empty">No vendors found. Create your first vendor.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :items="$vendors" />
    </div>

    <x-drawer id="vendorQuickDetails" title="Vendor details" eyebrow="Vendor quick view" size="medium">
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Vendor profile</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Vendor type</span>
                    <span class="core-drawer-field-value" data-drawer-bind="type"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Category</span>
                    <span class="core-drawer-field-value" data-drawer-bind="category" data-drawer-empty="Not set"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Status</span>
                    <span class="core-drawer-field-value" data-drawer-bind="status"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Location</span>
                    <span class="core-drawer-field-value" data-drawer-bind="location" data-drawer-empty="Not provided"></span>
                </div>
            </div>
        </section>
        <section class="core-drawer-section">
            <h3 class="core-drawer-section-title">Contact</h3>
            <div class="core-drawer-fields">
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Contact person</span>
                    <span class="core-drawer-field-value" data-drawer-bind="contact-name" data-drawer-empty="Not provided"></span>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Mobile</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="contact-tel" data-drawer-hide-if-empty>
                        <span data-drawer-bind="contact-mobile"></span>
                    </a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Email</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="contact-mailto" data-drawer-hide-if-empty>
                        <span data-drawer-bind="contact-email"></span>
                    </a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Website</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="website" target="_blank" rel="noopener noreferrer"
                        data-drawer-hide-if-empty>Visit website</a>
                </div>
                <div class="core-drawer-field" data-drawer-field>
                    <span class="core-drawer-field-label">Alibaba</span>
                    <a class="core-drawer-field-value" data-drawer-href-bind="alibaba" target="_blank" rel="noopener noreferrer"
                        data-drawer-hide-if-empty>Open Alibaba profile</a>
                </div>
            </div>
        </section>
        <x-slot:footer>
            <a class="master-btn master-btn-primary" data-drawer-href-bind="record-url">Open full vendor record</a>
        </x-slot:footer>
    </x-drawer>

    <div class="master-modal" id="quickVendorModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="quickVendorTitle">
            <form method="POST" action="{{ route('vendors.quickStore') }}" enctype="multipart/form-data">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading"><span class="master-modal-icon">🏭</span>
                        <div>
                            <h3 class="master-modal-title" id="quickVendorTitle">Quick Vendor</h3>
                            <p class="master-modal-subtitle">Create vendor with basic important details</p>
                        </div>
                    </div><button type="button" class="master-modal-close" id="closeQuickVendorModal">×</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field full"><label class="master-label">Vendor Image</label><input
                                class="master-input" type="file" name="vendor_image" accept="image/*"></div>
                        <div class="master-field full"><label class="master-label">Company Name <span
                                    class="master-required">*</span></label><input class="master-input"
                                name="vendor_name" required placeholder="Vendor company name"></div>
                        {{-- Defaults required by validation; surfaced in the detailed form. --}}
                        <input hidden class="master-input" name="brand_name">
                        <select hidden class="master-select" name="vendor_type">
                            @foreach($typeOptions as $key => $label)
                                <option value="{{ $key }}" @selected($key === 'manufacturer')>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select hidden class="master-select" name="status">@foreach($statusOptions as $key => $label)<option
                                    value="{{ $key }}" @selected($key === 'active')>{{ $label }}</option>
                            @endforeach</select>
                        <div class="master-field"><label class="master-label">Category</label><input
                                class="master-input" name="category" placeholder="Raw material / Packaging"></div>
                        <div class="master-field"><label class="master-label">Contact Person</label><input
                                class="master-input" name="contact_person_name"></div>
                        <div class="master-field"><label class="master-label">Email</label><input class="master-input"
                                type="email" name="contact_person_email"></div>
                        <div class="master-field"><label class="master-label">Mobile</label><input class="master-input"
                                name="contact_person_mobile"></div>
                        <div class="master-field"><label class="master-label">Preferred Currency</label><select
                            class="master-select" name="preferred_currency">@foreach($currencyOptions as $key => $label)
                            <option value="{{ $key }}" @selected(old('preferred_currency', 'INR') === $key)>{{ $label }}</option>@endforeach</select></div>
                        <div class="master-field"><label class="master-label">Country</label><input class="master-input"
                            name="country" value="China"></div>
                    </div>
                </div>
                <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                        id="cancelQuickVendorModal">Cancel</button><button type="submit"
                        class="master-btn master-btn-primary">Create Vendor</button></div>
            </form>
        </div>
    </div>

    <div class="master-modal" id="deleteVendorModal" aria-hidden="true">
        <div class="master-modal-card" style="max-width: 440px;" role="dialog" aria-modal="true"
            aria-labelledby="deleteVendorTitle">
            <div class="master-modal-header">
                <div class="master-modal-heading"><span class="master-modal-icon"
                        style="background:#fff0f4;color:var(--master-red);">🗑</span>
                    <div>
                        <h3 class="master-modal-title" id="deleteVendorTitle">Delete Vendor</h3>
                        <p class="master-modal-subtitle">This action cannot be undone</p>
                    </div>
                </div><button type="button" class="master-modal-close" id="closeDeleteVendorModal">×</button>
            </div>
            <div class="master-modal-body">
                <p id="deleteVendorDesc" style="margin:0;font-weight:700;color:#536079;">Are you sure you want to delete
                    this vendor?</p>
            </div>
            <form method="POST" action="" id="deleteVendorForm">@csrf @method('DELETE')
                <div class="master-modal-footer"><button type="button" class="master-btn master-btn-light"
                        id="cancelDeleteVendorModal">Cancel</button><button type="submit"
                        class="master-btn master-btn-danger">Delete Vendor</button></div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/vendors.js') }}"></script>
@endpush
@endsection
