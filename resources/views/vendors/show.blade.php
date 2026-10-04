@extends('layouts.app')

@section('title', $vendor->vendor_name)
@section('page-title', 'Vendor record')

@section('content')

@php
    $statusClass = str_replace('_', '-', $vendor->status);
    $typeClass = str_replace('_', '-', $vendor->vendor_type);
    $money = fn ($amount, $currency = 'INR') => \App\Helpers\CommonHelper::amount($amount, $currency);
    $initials = collect(explode(' ', trim($vendor->vendor_name)))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
    $location = collect([$vendor->city, $vendor->state, $vendor->country])->filter()->implode(', ');
    $recordUrl = fn (string $key) => route('vendors.show', ['vendor' => $vendor, 'tab' => $key]);
@endphp

<div class="vendor vendor-show master-list" data-vendor-id="{{ $vendor->id }}" data-vendor-tab="{{ $tab }}">

    {{-- Identity first, then the figures: both are read at a glance and both are
         the same on every tab, so they sit above the strip rather than inside
         it. Everything else about this vendor is one tab away. --}}
    <header class="master-card master-header vendor-record-header">
        <div class="vendor-record-identity">
            @if ($vendor->image_path)
                <img class="vendor-record-mark" src="{{ asset('storage/' . $vendor->image_path) }}" alt="">
            @else
                <span class="vendor-record-mark" aria-hidden="true">{{ $initials }}</span>
            @endif
            <div class="vendor-record-copy">
                <h1>{{ $vendor->vendor_name }}</h1>
                <div class="vendor-record-meta">
                    <span>{{ $vendor->vendor_number ?: 'Vendor record' }}</span>
                    @if ($vendor->category)
                        <span aria-hidden="true">·</span><span>{{ $vendor->category }}</span>
                    @endif
                    @if ($location)
                        <span aria-hidden="true">·</span><span>{{ $location }}</span>
                    @endif
                    <span class="master-badge status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span>
                    <span class="vendor-type-chip type-{{ $typeClass }}">{{ $vendor->typeLabel() }}</span>
                    <span class="vendor-meta-chip">{{ $vendor->preferred_currency ?: 'INR' }}</span>
                    @if ($vendor->rating)
                        <span class="vendor-meta-chip" aria-label="Rated {{ $vendor->rating }} of 5">{{ str_repeat('★', $vendor->rating) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <nav class="vendor-record-actions" aria-label="Vendor actions">
            <a href="{{ route('vendors.index') }}" class="master-btn master-btn-light">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Vendors
            </a>
            <a href="{{ route('vendors.edit', $vendor) }}" class="master-btn master-btn-primary">
                <i class="fa-solid fa-pen" aria-hidden="true"></i> Edit vendor
            </a>
            {{-- The manual ledger is the vendor-currency view; this is the same
                 account as a statement the vendor can be sent. --}}
            <a href="{{ route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $vendor->id]) }}"
                class="master-btn master-btn-soft">
                <i class="fa-solid fa-file-invoice" aria-hidden="true"></i> Full statement of account
            </a>
        </nav>
    </header>

    <div class="master-stats desktop-only" aria-label="Vendor figures">
        <div class="master-stat master-stat--flat blue">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-diagram-project"></i></span>
            <div>
                <p class="master-stat-title">Running projects</p>
                <p class="master-stat-value">{{ number_format($summary['running_projects']) }}</p>
                <p class="master-sub">{{ $summary['project_products_count'] }} project product {{ \Illuminate\Support\Str::plural('row', $summary['project_products_count']) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat purple">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            <div>
                <p class="master-stat-title">Bill generated</p>
                <p class="master-stat-value">{{ $money($summary['vendor_bill_foreign'], $summary['vendor_currency']) }}</p>
                <p class="master-sub">payable in {{ $summary['vendor_currency'] }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat orange">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-basket-shopping"></i></span>
            <div>
                <p class="master-stat-title">Vendor expenses</p>
                <p class="master-stat-value">{{ $money($summary['vendor_expense_foreign'], $summary['vendor_currency']) }}</p>
                <p class="master-sub">Rupee equivalent {{ $money($summary['expenses_on_behalf']) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat green">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-arrow-up-right-dots"></i></span>
            <div>
                <p class="master-stat-title">Paid to vendor</p>
                <p class="master-stat-value">{{ $money($summary['vendor_paid_foreign'], $summary['vendor_currency']) }}</p>
                <p class="master-sub">Paid in rupees {{ $money($summary['paid_to_vendor']) }}</p>
            </div>
        </div>
        <div class="master-stat master-stat--flat {{ $summary['need_to_pay'] > 0 ? 'red' : 'teal' }}">
            <span class="icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
            <div>
                <p class="master-stat-title">Need to pay</p>
                <p class="master-stat-value">{{ $money($summary['need_to_pay']) }}</p>
                <p class="master-sub">{{ $money($summary['vendor_balance_foreign'], $summary['vendor_currency']) }} ledger balance</p>
            </div>
        </div>
    </div>

    {{-- The tabs are links, not buttons: every panel has its own URL, so a tab
         can be shared, bookmarked, opened in a new window, and the back button
         works. The script only marks the clicked one instantly. --}}
    <div class="master-tabs-card">
        <nav class="master-tabs" role="tablist" aria-label="Vendor record sections">
            @foreach ($tabs as $key => $label)
                <a class="master-tab {{ $tab === $key ? 'is-active' : '' }}"
                    role="tab"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    href="{{ $recordUrl($key) }}"
                    data-vendor-tab-link="{{ $key }}">
                    {{ $label }}
                    @if (($tabCounts[$key] ?? 0) > 0)
                        <span class="master-tab-count">{{ $tabCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="master-tabs-panels">
            @if ($tab === 'overview')
                @include('vendors.partials.overview')
            @elseif ($tab === 'profile')
                @include('vendors.partials.profile')
            @elseif ($tab === 'contacts')
                @include('vendors.partials.contacts')
            @elseif ($tab === 'addresses')
                @include('vendors.partials.addresses')
            @elseif ($tab === 'commercial')
                @include('vendors.partials.commercial')
            @elseif ($tab === 'projects')
                @include('vendors.partials.projects')
            @elseif ($tab === 'products')
                @include('vendors.partials.products')
            @elseif ($tab === 'quotes')
                @include('vendors.partials.quotes')
            @elseif ($tab === 'payments')
                @include('vendors.partials.payments')
            @elseif ($tab === 'statement')
                @include('vendors.partials.statement')
            @elseif ($tab === 'shipments')
                @include('vendors.partials.shipments')
            @elseif ($tab === 'attachments')
                @include('vendors.partials.attachments')
            @elseif ($tab === 'comments')
                @include('vendors.partials.comments')
            @endif
        </div>
    </div>

    {{-- ================= Add payment entry =================
         The shared modal sheet: header and footer pinned, the body carries the
         scroll. Both ledger dialogs carry the same field names, so the script
         wires each form on its own. --}}
    <div class="master-modal" id="addPaymentModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="addPaymentTitle">
            <form method="POST" action="{{ route('vendors.payments.store', $vendor) }}" enctype="multipart/form-data" class="vendor-payment-form">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="addPaymentTitle">Add ledger entry</h2>
                            <p class="master-modal-subtitle">A bill raises the payable; a payment settles it</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="addPaymentModal" aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    @include('vendors.partials.payment-fields', ['formPrefix' => 'add'])
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="addPaymentModal">Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add entry
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Edit payment entry ================= --}}
    <div class="master-modal" id="editPaymentModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="editPaymentTitle">
            {{-- The update route carries both ids. It is emitted as a template so
                 the script never has to build a path by hand: the app may sit
                 under a sub-path, and a hand-built /vendors/… would miss it. --}}
            <form method="POST" action="" id="editPaymentForm" enctype="multipart/form-data" class="vendor-payment-form"
                data-payment-url="{{ route('vendors.payments.update', ['vendor' => $vendor, 'entry' => '__ENTRY__']) }}">
                @csrf
                @method('PUT')
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fas fa-pen"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="editPaymentTitle">Edit ledger entry</h2>
                            <p class="master-modal-subtitle" id="editPaymentSubtitle">Update this vendor ledger row</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="editPaymentModal" aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    @include('vendors.partials.payment-fields', ['formPrefix' => 'edit'])
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="editPaymentModal">Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-check" aria-hidden="true"></i> Update entry
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Add attachment ================= --}}
    <div class="master-modal" id="addAttachmentModal" aria-hidden="true">
        <div class="master-modal-card" role="dialog" aria-modal="true" aria-labelledby="addAttachmentTitle">
            <form method="POST" enctype="multipart/form-data" action="{{ route('vendors.attachments.store', $vendor) }}">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-regular fa-folder-open"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="addAttachmentTitle">Add documents</h2>
                            <p class="master-modal-subtitle">Certificates, bank proofs, price lists and correspondence</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="addAttachmentModal" aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field">
                            <label class="master-label" for="attachment_category">Category</label>
                            <select class="master-select" id="attachment_category" name="category">
                                <option value="">General</option>
                                @foreach($attachmentOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="master-field">
                            <label class="master-label" for="attachment_title">Title</label>
                            <input class="master-input" id="attachment_title" type="text" name="title" placeholder="Document title">
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="attachment_files">Files <span class="master-required" aria-hidden="true">*</span></label>
                            <input class="master-input" id="attachment_files" type="file" name="attachments[]" multiple required
                                accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                            <div class="master-help">Up to 20 MB per file. Images get a preview on the record.</div>
                        </div>
                        <div class="master-field full">
                            <label class="master-label" for="attachment_notes">Notes</label>
                            <textarea class="master-textarea" id="attachment_notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="addAttachmentModal">Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ================= Add comment ================= --}}
    <div class="master-modal" id="addCommentModal" aria-hidden="true">
        <div class="master-modal-card is-narrow" role="dialog" aria-modal="true" aria-labelledby="addCommentTitle">
            <form method="POST" action="{{ route('vendors.comments.store', $vendor) }}">
                @csrf
                <div class="master-modal-header">
                    <div class="master-modal-heading">
                        <span class="master-modal-icon" aria-hidden="true"><i class="fa-regular fa-comment"></i></span>
                        <div>
                            <h2 class="master-modal-title" id="addCommentTitle">Add comment</h2>
                            <p class="master-modal-subtitle">Internal note — the vendor never sees this</p>
                        </div>
                    </div>
                    <button type="button" class="master-modal-close" data-close-modal="addCommentModal" aria-label="Close">&times;</button>
                </div>
                <div class="master-modal-body">
                    <div class="master-modal-grid">
                        <div class="master-field full">
                            <label class="master-label" for="comment_body">Comment <span class="master-required" aria-hidden="true">*</span></label>
                            <textarea class="master-textarea" id="comment_body" name="body" rows="4" required
                                placeholder="Payment note, follow-up, quality issue, reminder…"></textarea>
                        </div>
                        <div class="master-field full">
                            <label class="master-check" for="comment_pinned">
                                <input type="checkbox" id="comment_pinned" name="is_pinned" value="1"> Pin this comment
                            </label>
                        </div>
                    </div>
                </div>
                <div class="master-modal-footer">
                    <button type="button" class="master-btn master-btn-light" data-close-modal="addCommentModal">Cancel</button>
                    <button class="master-btn master-btn-primary" type="submit">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Add comment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ $assetVer('assets/js/vendors.js') }}"></script>
@endpush
