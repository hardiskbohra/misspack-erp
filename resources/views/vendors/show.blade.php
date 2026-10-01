@extends('layouts.app')

@section('page-title', $vendor->vendor_name)

@section('content')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendors.css') }}">
@endpush

    @php
        $statusClass = str_replace('_', '-', $vendor->status);
        $typeClass = str_replace('_', '-', $vendor->vendor_type);
        $money = function ($amount, $currency = '₹') {
            return $currency . ' ' . number_format((float) $amount, 2);
        };
    @endphp

    <div class="vendor-show" data-vendor-id="{{ $vendor->id }}">
        <div class="vendor-hero">
            <div class="vendor-head-left">
                @if ($vendor->image_path)
                    <img class="master-image" src="{{ asset('storage/' . $vendor->image_path) }}"
                        alt="{{ $vendor->vendor_name }}">
                @else
                    <span class="master-image">{{ strtoupper(substr($vendor->vendor_name, 0, 1)) }}</span>
                @endif
                <div>
                    <h1>{{ $vendor->vendor_name }} ({{ $vendor->contact_person_name }})</h1>
                    <p>{{ $vendor->vendor_number }} · {{ $vendor->category ?: 'No category' }} · {{ $vendor->country ?: '-' }}</p>
                    <div class="master-chip-row">
                        <span class="master-badge status-{{ $statusClass }}">{{ $vendor->statusLabel() }}</span>
                        <span class="master-badge type-{{ $typeClass }}">{{ $vendor->typeLabel() }}</span>
                        <span class="master-badge currency">{{ $vendor->preferred_currency }}</span>
                    </div>
                </div>
            </div>
            <div class="vendor-actions">
                <a href="{{ route('vendors.index') }}" class="vendor-btn vendor-btn-light">Back</a>
                <a href="{{ route('vendors.edit', $vendor) }}" class="vendor-btn vendor-btn-primary">Edit Vendor</a>
            </div>
        </div>

        <div class="vendor-kpi-grid">
            <div class="vendor-kpi"><span>Running
                    Projects</span><strong>{{ $summary['running_projects'] }}</strong><small>{{ $summary['project_products_count'] }}
                    project product rows</small></div>
            <div class="vendor-kpi blue"><span>Bill Generated</span><strong>{{ $summary['vendor_currency'] }}
                    {{ number_format((float) $summary['vendor_bill_foreign'], 2) }}</strong><small>Vendor currency
                    payable</small></div>
            <div class="vendor-kpi orange"><span>Vendor Expenses</span><strong>{{ $summary['vendor_currency'] }}
                    {{ number_format((float) $summary['vendor_expense_foreign'], 2) }}</strong><small>INR eq:
                    {{ $money($summary['expenses_on_behalf']) }}</small></div>
            <div class="vendor-kpi green"><span>Paid To Vendor</span><strong>{{ $summary['vendor_currency'] }}
                    {{ number_format((float) $summary['vendor_paid_foreign'], 2) }}</strong><small>INR paid:
                    {{ $money($summary['paid_to_vendor']) }}</small></div>
            <div class="vendor-kpi red"><span>Need To Pay</span><strong>{{ $summary['vendor_currency'] }}
                    {{ number_format((float) $summary['vendor_balance_foreign'], 2) }}</strong><small>INR balance:
                    {{ $money($summary['need_to_pay']) }}</small></div>
        </div>

        <div class="vendor-tabs-card">
            <div class="vendor-tabs" role="tablist">
                <button type="button" class="vendor-tab active" data-tab="overview">Overview</button>
                <button type="button" class="vendor-tab" data-tab="basic">Basic Details</button>
                <button type="button" class="vendor-tab" data-tab="projects">Projects
                    <span>{{ $summary['project_products_count'] }}</span></button>
                <button type="button" class="vendor-tab" data-tab="products">Products
                    <span>{{ $summary['products_count'] }}</span></button>
                <button type="button" class="vendor-tab" data-tab="payments">Payments
                    <span>{{ $vendorPaymentEntries->count() }}</span></button>
                <button type="button" class="vendor-tab" data-tab="statement">Statement</button>
                <button type="button" class="vendor-tab" data-tab="shipments">Shipments
                    <span>{{ $summary['shipments_count'] }}</span></button>
                <button type="button" class="vendor-tab" data-tab="attachments">Attachments
                    <span>{{ $summary['attachments_count'] }}</span></button>
                <button type="button" class="vendor-tab" data-tab="comments">Comments
                    <span>{{ $vendor->relationLoaded('comments') ? $vendor->comments->count() : 0 }}</span></button>
            </div>

            <div class="vendor-tab-panels">
                <section class="vendor-panel active" data-panel="overview">

                    <div class="vendor-grid-3">
                        <div class="vendor-card vendor-section">
                            <div class="vendor-section-head">
                                <div>
                                    <p class="vendor-eyebrow">Latest</p>
                                    <h2>Project Products</h2>
                                </div>
                            </div>
                            @forelse($projectProducts->take(5) as $row)
                                <div class="vendor-mini-row">
                                    <div><strong>{{ $row->product_name }}</strong><small>{{ $row->project?->project_number ?? 'No Project' }}
                                            · {{ $row->statusLabel() }}</small></div>
                                    <b>{{ $money($row->total_amount) }}</b>
                                </div>
                            @empty
                                <div class="vendor-empty small">No project product mapping yet.</div>
                            @endforelse
                        </div>
                        <div class="vendor-card vendor-section">
                            <div class="vendor-section-head">
                                <div>
                                    <p class="vendor-eyebrow">Latest</p>
                                    <h2>Payments</h2>
                                </div>
                            </div>
                            @forelse($statementEntries->where('debit_amount', '>', 0)->take(5) as $entry)
                                <div class="vendor-mini-row">
                                    <div><strong>{{ $entry->particular }}</strong><small>{{ optional($entry->entry_date)->format('d M Y') }}
                                            · {{ $entry->payment_mode ?: '-' }}</small></div><b
                                        class="green">{{ $entry->currency }}
                                        {{ number_format((float) $entry->debit_amount, 2) }}</b>
                                </div>
                            @empty
                                <div class="vendor-empty small">No payment entries found.</div>
                            @endforelse
                        </div>
                        <div class="vendor-card vendor-section">
                            <div class="vendor-section-head">
                                <div>
                                    <p class="vendor-eyebrow">Latest</p>
                                    <h2>Comments</h2>
                                </div>
                            </div>
                            @if ($vendor->relationLoaded('comments'))
                                @forelse($vendor->comments->take(5) as $comment)
                                    <div class="vendor-comment-mini">
                                        <strong>{{ $comment->creator?->name ?? 'Team' }}</strong>
                                        <p>{{ \Illuminate\Support\Str::limit($comment->body, 90) }}</p>
                                    </div>
                                @empty
                                    <div class="vendor-empty small">No comments yet.</div>
                                @endforelse
                            @else
                                <div class="vendor-empty small">Run migration to enable comments.</div>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="vendor-panel" data-panel="basic">
                    <div class="vendor-card vendor-section">
                        <div class="vendor-section-head">
                            <div>
                                <p class="vendor-eyebrow">Basic</p>
                                <h2>Vendor Basic Details</h2>
                            </div>
                        </div>
                        <div class="vendor-info-grid">
                            <div class="vendor-info"><span>Category</span><strong>{{ $vendor->category ?: '-' }}</strong>
                            </div>
                            <div class="vendor-info"><span>Rating</span><strong
                                    class="vendor-rating">{{ $vendor->rating ? str_repeat('★', $vendor->rating) . str_repeat('☆', 5 - $vendor->rating) : '-' }}</strong>
                            </div>
                            <div class="vendor-info"><span>Contact
                                    Person</span><strong>{{ $vendor->contact_person_name ?: '-' }}</strong><small>{{ $vendor->contact_person_email ?: '-' }}</small><small>{{ $vendor->contact_person_mobile ?: '-' }}</small>
                            </div>
                            <div class="vendor-info"><span>WhatsApp /
                                    Alternate</span><strong>{{ $vendor->whatsapp_number ?: '-' }}</strong><small>Alternate:
                                    {{ $vendor->alternate_contact ?: '-' }}</small></div>
                            <div class="vendor-info">
                                <span>Address</span><strong>{{ $vendor->address ?: '-' }}</strong><small>{{ $vendor->city }}
                                    {{ $vendor->state }} {{ $vendor->country }} {{ $vendor->pincode }}</small></div>
                            <div class="vendor-info"><span>Tax Details</span><small>GSTIN:
                                    {{ $vendor->gstin ?: '-' }}</small><small>PAN:
                                    {{ $vendor->pan ?: '-' }}</small><small>Tax ID: {{ $vendor->tax_id ?: '-' }}</small>
                            </div>
                            <div class="vendor-info"><span>Website</span><strong>{{ $vendor->website ?: '-' }}</strong>
                            </div>
                            <div class="vendor-info">
                                <span>Alibaba</span><strong>{{ $vendor->alibaba_link ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>Preferred
                                    Currency</span><strong>{{ $vendor->preferred_currency }}</strong></div>
                        </div>
                    </div>
                    <div class="vendor-card vendor-section">
                        <div class="vendor-section-head">
                            <div>
                                <p class="vendor-eyebrow">Bank</p>
                                <h2>Vendor Bank & Compliance Details</h2>
                            </div>
                        </div>
                        <div class="vendor-info-grid">
                            <div class="vendor-info"><span>Bank
                                    Name</span><strong>{{ $vendor->bank_name ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>Account
                                    Holder</span><strong>{{ $vendor->account_holder_name ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>Account
                                    Number</span><strong>{{ $vendor->account_number ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>IFSC
                                    Code</span><strong>{{ $vendor->ifsc_code ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>SWIFT
                                    Code</span><strong>{{ $vendor->swift_code ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>Bank
                                    Branch</span><strong>{{ $vendor->bank_branch ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>GSTIN</span><strong>{{ $vendor->gstin ?: '-' }}</strong></div>
                            <div class="vendor-info"><span>PAN / Tax ID</span><strong>{{ $vendor->pan ?: '-' }} /
                                    {{ $vendor->tax_id ?: '-' }}</strong></div>
                            <div class="vendor-info">
                                <span>IEC</span><strong>{{ $vendor->import_export_code ?: '-' }}</strong></div>
                        </div>
                    </div>
                </section>
                
                
                <section class="vendor-panel" data-panel="attachments">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Files</p>
                            <h2>Attachments</h2>
                        </div>
                        <div>
                            <span class="vendor-count">{{ $attachmentsAvailable ? $vendor->attachments->count() : 0 }} Files</span> &nbsp;
                            @if($attachmentsAvailable)
                                <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal"><i class="fas fa-plus"></i> Add Attachment</button>
                            @endif
                        </div>
                    </div>

                    @if($attachmentsAvailable)
                        <div class="vendor-attachment-grid vendor-card">
                            @forelse($vendor->attachments as $attachment)
                                <div class="vendor-attachment">
                                    @if ($attachment->isImage())
                                        <img src="{{ $attachment->fileUrl() }}" alt="{{ $attachment->title }}">
                                    @else
                                        <div class="vendor-file-icon"><i class="fa-solid fa-file-lines"></i></div>
                                    @endif
                                    <div>
                                        <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                                        <span>{{ $attachment->categoryLabel() }} ·
                                            {{ strtoupper($attachment->extension) }} ·
                                            {{ $attachment->is_public ? 'Public' : 'Internal' }}</span>
                                        <span>{{ $attachment->notes }}</span>
                                        <div class="vendor-row-actions">
                                            <a href="{{ $attachment->fileUrl() }}" target="_blank" class="master-btn master-btn-primary">Open</a>
                                            @if(\Illuminate\Support\Facades\Route::has('vendors.attachments.destroy'))
                                                <form method="POST" action="{{ route('vendors.attachments.destroy', $attachment) }}" onsubmit="return confirm('Delete this vendor document?')">@csrf @method('DELETE')<button class="master-btn master-btn-primary vendor-row-delete" type="submit">Delete</button></form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="pd-empty">No attachments uploaded yet.</div>
                            @endforelse
                        </div>
                    @else
                        <div class="vendor-empty">Run migration to enable attachments.</div>
                    @endif
                </section>
                
                <!--Add Attachment-->
                <div class="master-modal" id="addAttachmentModal" aria-hidden="true">
                    <div class="master-modal-card" style="max-width:900px;">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('vendors.attachments.store', $vendor) }}">
                            @csrf
                            <div class="master-modal-header">
                                <div class="master-modal-heading">
                                    <div>
                                        <h3 class="master-modal-title">Add Attachment</h3>
                                    </div>
                                </div>
                                <button type="button" class="master-modal-close" id="closeAddAttachmentModal" data-close-modal>×</button>
                            </div>
                            <div class="master-modal-body">
                                <div class="master-modal-grid">
                                    
                                    <div class="master-field">
                                        <label class="master-label">Category</label>
                                        <select class="master-select" name="category">
                                            <option value="">General</option>
                                            @foreach($attachmentOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Title</label>
                                        <input class="master-input" type="text" name="title" placeholder="Document title">
                                    </div>
                                    <div class="master-field">
                                        <label class="master-label">Files</label>
                                        <input class="master-input" type="file" name="attachments[]" multiple
                                            required
                                            accept=".jpg,.jpeg,.png,.webp,.gif,.heic,.heif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
                                    </div>
                                    <div class="master-field pd-span-2">
                                        <label class="master-label">Notes</label>
                                        <textarea class="master-textarea" name="notes" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="master-modal-footer">
                                <button type="button" class="master-btn master-btn-light" id="cancelAddAttachmentModal" data-close-modal>Cancel</button>
                                <button class="master-btn master-btn-primary" type="submit"> Upload</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                

                <section class="vendor-panel" data-panel="projects">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Projects</p>
                            <h2>Project Running With Vendor</h2>
                        </div>
                        <div>
                            <span class="vendor-count">{{ $summary['running_projects'] }} Project Running</span>
                        </div>
                    </div>
                    <div class="vendor-table-wrap vendor-card">
                        <table class="vendor-table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Value</th>
                                    <th>Status / Stage</th>
                                    <th>Dates</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($projectProducts as $row)
                                    <tr>
                                        <td><a class="no-decorate" href="{{ route('projects.show', $row->project) }}"><strong>{{ $row->project?->project_number ?? '-' }}</strong><small>{{ $row->project?->name ?? 'No project loaded' }}</small></a>
                                        </td>
                                        <td>{{ $row->product_name }}<small style="font-weight:400">Vendor Invoice: {{ $row->vendor_invoice_number ?: '-' }}</small></td>
                                        <td>{{ number_format((int)$row->quantity) }} {{ $row->unit }}</td>
                                        <td>{{ $row->currency === 'INR' ? '₹' : $row->currency }} {{ number_format((float) $row->total_amount, 2) }}
                                        </td>
                                        <td>{{ $row->statusLabel() }}<small>{{ $row->stageLabel() }}</small></td>
                                        <td>Expected:
                                            {{ optional($row->expected_ready_date)->format('d M') ?: '-' }}<small>Actual:
                                                {{ optional($row->actual_ready_date)->format('d M') ?: '-' }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div class="vendor-empty">No project products mapped with this vendor.
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="vendor-panel" data-panel="products">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Products</p>
                            <h2>Products Supplied</h2>
                        </div>
                        <div>
                            <span class="vendor-count">{{ $products->count() }} Products</span>
                        </div>
                    </div>
                        
                    <div class="vendor-info-grid vendor-card">
                        @forelse($products as $product)
                            @php($media = method_exists($product, 'primaryMedia') ? $product->primaryMedia() : null)
                            <a class="no-decorate" href="{{ route('products.show', $product) }}">
                                <div class="vendor-product-card">
                                    @if ($media && $media->file_path)
                                    <img src="{{ asset('storage/' . $media->file_path) }}">@else<div
                                            class="vendor-product-empty">📦</div>
                                    @endif
                                    <div>
                                        <strong>{{ $product->name }}</strong><small>{{ $product->product_number ?? '' }}
                                            · {{ $product->category ?: '-' }}</small>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="vendor-empty">No product mapping found.</div>
                        @endforelse
                    </div>
                </section>

                <!--Payments-->
                <section class="vendor-panel" data-panel="payments">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Vendor Currency Ledger</p>
                            <h2>Manual Vendor Payment Entries</h2>
                        </div>
                        <div>
                            <span class="vendor-pill">{{ $vendorPaymentEntries->count() }} entries</span> &nbsp;
                            <button type="button" class="master-btn master-btn-primary addPaymentBtn" id="openAddPaymentModal"><i class="fas fa-plus"></i> Add Entry</button>
                        </div>
                    </div>

                    <div class="vendor-table-wrap vendor-card">
                        <table class="vendor-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice</th>
                                    <th>Particular</th>
                                    <th>Credit / Bill</th>
                                    <th>Debit / Paid</th>
                                    <th>INR Value</th>
                                    <th>Account / Mode</th>
                                    <th>Status</th>
                                    <th>Proof</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vendorPaymentEntries->sortByDesc('transaction_date') as $entry)
                                    <tr>
                                        <td>{{ optional($entry->transaction_date)->format('d M y') ?: '-' }}</td>
                                        <td>{{ $entry->invoice_number ?: '-' }}<small>{{ $entry->categoryLabel() }}</small>
                                        </td>
                                        <td><strong>{{ $entry->particular }}</strong><small>{{ $entry->relationLoaded('project') && $entry->project ? $entry->project->project_number . ' - ' . $entry->project->name : 'No project' }}</small><small>{{ $entry->remarks }}</small>
                                        </td>
                                        <td class="red">
                                            {{ $entry->transaction_type === 'credit' ? $entry->foreign_currency . ' ' . number_format((float) $entry->foreign_amount, 2) : '-' }}
                                        </td>
                                        <td class="green">
                                            {{ $entry->transaction_type === 'debit' ? $entry->foreign_currency . ' ' . number_format((float) $entry->foreign_amount, 2) : '-' }}
                                        </td>
                                        <td>₹ {{ number_format((float) $entry->amount_in_inr, 2) }}<small>Rate:
                                                {{ $entry->exchange_rate ? number_format($entry->exchange_rate, 2) : '-' }}</small></td>
                                        <td>{{ $entry->relationLoaded('paidAccount') && $entry->paidAccount ? $entry->paidAccount->name : '-' }}<small>{{ $entry->payment_mode ?: '-' }}
                                                {{ $entry->bank_reference_number ? '· ' . $entry->bank_reference_number : '' }}</small>
                                        </td>
                                        <td>{{ $entry->statusLabel() }}@if ($entry->cashflow_entry_id)
                                                <small>Cashflow #{{ $entry->cashflow_entry_id }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @forelse($entry->attachments as $attachment)
                                                <a href="{{ $attachment->fileUrl() }}" target="_blank"
                                                class="vendor-file-link">{{ $attachment->extension ?: 'file' }}</a>@empty
                                                -
                                            @endforelse
                                        </td>
                                        <td>
                                            <div class="master-row-actions">
                                                <button type="button" class="master-icon-btn editPaymentBtn" data-payment='@json($entry)'><i class="fas fa-pen"></i></button>
                                                @if (\Illuminate\Support\Facades\Route::has('vendors.payments.destroy'))
                                                    <form method="POST"
                                                        action="{{ route('vendors.payments.destroy', $entry) }}"
                                                        onsubmit="return confirm('Delete this vendor payment entry?')">
                                                        @csrf @method('DELETE')<button class="master-icon-btn danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10">
                                            <div class="vendor-empty">No manual vendor payment entries yet.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">INR Cashflow</p>
                            <h2>Linked / Matched Cashflow Entries</h2>
                        </div>
                        <div>
                            <span class="vendor-pill">{{ $summary['cashflow_count'] }} cashflow</span>
                        </div>
                    </div>
                    <div class="vendor-table-wrap vendor-card">
                        <table class="vendor-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Particular</th>
                                    <th>Credit INR</th>
                                    <th>Debit / Paid INR</th>
                                    <th>Account</th>
                                    <th>Mode</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($statementEntries->filter(fn($entry) => (float)($entry->debit_amount ?? 0) > 0 || (float)($entry->credit_amount ?? 0) > 0) as $entry)
                                    <tr>
                                        <td>{{ optional($entry->entry_date)->format('d M y') ?: '-' }}</td>
                                        <td>{{ $entry->category?->name ?? ($entry->expense_head ?? '-') }}</td>
                                        <td><strong>{{ $entry->particular }}</strong><small>{{ $entry->notes }}</small></td>
                                        <td class="red">{{ $entry->currency }}
                                            {{ number_format((float) $entry->credit_amount, 2) }}</td>
                                        <td class="green">{{ $entry->currency }}
                                            {{ number_format((float) $entry->debit_amount, 2) }}</td>
                                        <td>{{ $entry->account?->name ?? '-' }}</td>
                                        <td>{{ $entry->payment_mode ?: '-' }}</td>
                                        <td>{{ $entry->bank_reference_number ?: $entry->invoice_bill_number ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8">
                                            <div class="vendor-empty">No cashflow entries found for this vendor.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                    
                <!--Add Payment Entry-->
                <div class="master-modal" id="addPaymentModal" aria-hidden="true">
                    
                    <form method="POST" action="{{ route('vendors.payments.store', $vendor) }}"
                        enctype="multipart/form-data" class="vendor-payment-form">
                        @csrf
                        <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">
                        
                        <div class="master-modal-header">
                            <div class="master-modal-heading">
                                <div>
                                    <h3 class="master-modal-title">Add Product</h3>
                                </div>
                            </div>
                            <button type="button" class="master-modal-close" data-close-modal id="closeAddPaymentModal">×</button>
                        </div>
                        <div class="master-modal-body">
                            <div class="master-modal-grid">
                                <div class="master-field">
                                    <label class="master-label">Date <span>*</span></label>
                                    <input class="master-input" type="date" name="transaction_date" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Invoice / Bill No.</label>
                                    <input class="master-input" type="text" name="invoice_number" placeholder="Vendor invoice number">
                                </div>
                                <div class="master-field full">
                                    <label class="master-label">Particular <span>*</span></label>
                                    <input class="master-input"
                                        type="text" name="particular" required
                                        placeholder="Bill generated / advance payment / vendor expense / adjustment">
                                </div>
                                <div class="master-field">
                                    <label class="master-label">RMB/USD Value <span>*</span></label>
                                    <input class="master-input" type="number" step="0.0001" min="0" name="foreign_amount" id="foreignAmount" required>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Currency <span>*</span></label>
                                    <select class="master-select" name="foreign_currency" id="foreignCurrency" required>
                                        @foreach ($paymentOptions['currency'] as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ $key === ($vendor->preferred_currency ?: 'RMB') ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Exchange Rate</label>
                                    <input class="master-input" type="number" step="0.000001" min="0" name="exchange_rate" id="exchangeRate"
                                        placeholder="INR per currency"></div>
                                <div class="master-field">
                                    <label class="master-label">Amount in INR</label>
                                    <input class="master-input" type="number" step="0.01" min="0" name="amount_in_inr" id="amountInInr"
                                        placeholder="Auto if rate entered"></div>
                                <div class="master-field">
                                    <label class="master-label">Credit / Debit <span>*</span></label>
                                    <select class="master-select" name="transaction_type" required>
                                        <option value="credit">Credit / Bill Generated</option>
                                        <option value="debit">Debit / Paid To Vendor</option>
                                    </select></div>
                                <div class="master-field">
                                    <label class="master-label">Entry Category <span>*</span></label>
                                    <select class="master-select" name="entry_category" required>
                                        @foreach ($paymentOptions['category'] as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Status <span>*</span></label>
                                    <select name="status" class="master-select" required>
                                        @foreach ($paymentOptions['status'] as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ $key === 'pending' ? 'selected' : '' }}>{{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Project</label>
                                    <select class="master-select" name="project_id">
                                        <option value="">No project mapping</option>
                                        @foreach ($projectsForPayment as $project)
                                            <option value="{{ $project->id }}">
                                                {{ $project->project_number ?? '#' . $project->id }} -
                                                {{ $project->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Paid Account</label>
                                    <select class="master-select" name="paid_account_id">
                                        <option value="">Select paid account</option>
                                        @foreach ($cashflowAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Payment Mode</label>
                                    <select class="master-select" name="payment_mode">
                                        <option value="">Select mode</option>
                                        @foreach ($paymentOptions['mode'] as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Bank Reference</label>
                                    <input class="master-input" type="text"
                                        name="bank_reference_number" placeholder="UTR / TT / reference no.">
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Remarks</label>
                                    <input class="master-input" name="remarks" placeholder="Additional details">
                                </div>
                                <div class="master-field full">
                                    <label class="master-label">Bill / Proof Attachments</label>
                                    <input class="master-input" type="file" name="attachments[]" multiple
                                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                                </div>
                                <label class="master-check full"><input class="master-check" type="checkbox" name="also_create_cashflow"
                                        value="1"> Also create linked INR cashflow entry</label>
                            </div>
                        </div>
                        <div class="master-modal-footer">
                            <button type="button" class="master-btn master-btn-light" id="cancelAddPaymentModal" data-close-modal>Cancel</button>
                            <button class="master-btn master-btn-primary" type="submit"> Add Entry</button>
                        </div>
                    </form>
                </div>
                    
                <!--Edit Payment Entry-->
                <div class="master-modal" id="editPaymentModal" aria-hidden="true">
                    
                    <form id="editPaymentForm" method="POST" enctype="multipart/form-data" class="vendor-payment-form">
                    
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">
                        
                        <div class="master-modal-header">
                            <div class="master-modal-heading">
                                <div>
                                    <h3 class="master-modal-title">Update Product</h3>
                                </div>
                            </div>
                            <button type="button" class="master-modal-close" data-close-modal id="closeEditPaymentModal">×</button>
                        </div>
                        <div class="master-modal-body">
                            <div class="master-modal-grid">
                                <div class="master-field">
                                    <label class="master-label">Date <span>*</span></label>
                                    <input class="master-input" type="date" name="transaction_date" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Invoice / Bill No.</label>
                                    <input class="master-input" type="text" name="invoice_number" placeholder="Vendor invoice number">
                                </div>
                                <div class="master-field full">
                                    <label class="master-label">Particular <span>*</span></label>
                                    <input class="master-input"
                                        type="text" name="particular" required
                                        placeholder="Bill generated / advance payment / vendor expense / adjustment">
                                </div>
                                <div class="master-field">
                                    <label class="master-label">RMB/USD Value <span>*</span></label>
                                    <input class="master-input" type="number" step="1" min="0" name="foreign_amount" id="foreignAmount" required>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Currency <span>*</span></label>
                                    <select class="master-select" name="foreign_currency" id="foreignCurrency" required>
                                        @foreach ($paymentOptions['currency'] as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ $key === ($vendor->preferred_currency ?: 'RMB') ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Exchange Rate</label>
                                    <input class="master-input" type="number" step="0.1" min="0" name="exchange_rate" id="exchangeRate"
                                        placeholder="INR per currency"></div>
                                <div class="master-field">
                                    <label class="master-label">Amount in INR</label>
                                    <input class="master-input" type="number" step="1" min="0" name="amount_in_inr" id="amountInInr"
                                        placeholder="Auto if rate entered"></div>
                                <div class="master-field">
                                    <label class="master-label">Credit / Debit <span>*</span></label>
                                    <select class="master-select" name="transaction_type" required>
                                        <option value="credit">Credit / Bill Generated</option>
                                        <option value="debit">Debit / Paid To Vendor</option>
                                    </select></div>
                                <div class="master-field">
                                    <label class="master-label">Entry Category <span>*</span></label>
                                    <select class="master-select" name="entry_category" required>
                                        @foreach ($paymentOptions['category'] as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Status <span>*</span></label>
                                    <select name="status" class="master-select" required>
                                        @foreach ($paymentOptions['status'] as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ $key === 'pending' ? 'selected' : '' }}>{{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Project</label>
                                    <select class="master-select" name="project_id">
                                        <option value="">No project mapping</option>
                                        @foreach ($projectsForPayment as $project)
                                            <option value="{{ $project->id }}">
                                                {{ $project->project_number ?? '#' . $project->id }} -
                                                {{ $project->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Paid Account</label>
                                    <select class="master-select" name="paid_account_id">
                                        <option value="">Select paid account</option>
                                        @foreach ($cashflowAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Payment Mode</label>
                                    <select class="master-select" name="payment_mode">
                                        <option value="">Select mode</option>
                                        @foreach ($paymentOptions['mode'] as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Bank Reference</label>
                                    <input class="master-input" type="text"
                                        name="bank_reference_number" placeholder="UTR / TT / reference no.">
                                </div>
                                <div class="master-field">
                                    <label class="master-label">Remarks</label>
                                    <input class="master-input" name="remarks" placeholder="Additional details">
                                </div>
                                <div class="master-field full">
                                    <label class="master-label">Bill / Proof Attachments</label>
                                    <input class="master-input" type="file" name="attachments[]" multiple
                                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                                </div>
                                <label class="master-check full"><input class="master-check" type="checkbox" name="also_create_cashflow"
                                        value="1"> Also create linked INR cashflow entry</label>
                            </div>
                        </div>
                        <div class="master-modal-footer">
                            <button type="button" class="master-btn master-btn-light" id="cancelEditPaymentModal" data-close-modal>Cancel</button>
                            <button class="master-btn master-btn-primary" type="submit"> Update Entry</button>
                        </div>
                    </form>
                </div>

                <!--Statement-->
                <section class="vendor-panel" data-panel="statement">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Vendor Currency Statement</p>
                            <h2>Statement of Accounts</h2>
                        </div>
                        <div>
                            <span class="vendor-pill">{{ $summary['statement_count'] }} manual entries</span>
                        </div>
                    </div>
                    <div class="vendor-currency-summary">
                        @forelse($currencySummary as $currency => $row)
                            <div><span>{{ $currency }} Bills</span><strong>{{ $currency }}
                                    {{ number_format((float) $row['credit'], 2) }}</strong></div>
                            <div><span>{{ $currency }} Paid</span><strong class="green">{{ $currency }}
                                    {{ number_format((float) $row['debit'], 2) }}</strong></div>
                            <div><span>{{ $currency }} Balance</span><strong class="red">{{ $currency }}
                                    {{ number_format((float) $row['balance'], 2) }}</strong></div>
                            <div><span>{{ $currency }} Expenses</span><strong
                                    class="orange">{{ $currency }}
                                    {{ number_format((float) $row['expense'], 2) }}</strong></div>
                        @empty
                            <div><span>No Ledger</span><strong>-</strong></div>
                        @endforelse
                    </div>
                    <div class="vendor-card vendor-table-wrap">
                        <table class="vendor-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Invoice</th>
                                    <th>Particular</th>
                                    <th>Category</th>
                                    <th>Credit / Bill</th>
                                    <th>Debit / Paid</th>
                                    <th>Balance</th>
                                    <th>INR Value</th>
                                    <th>Account / Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vendorPaymentEntries->sortBy('transaction_date') as $paymentEntry)
                                    <tr>
                                        <td>{{ optional($paymentEntry->transaction_date)->format('d M Y') ?: '-' }}
                                        </td>
                                        <td>{{ $paymentEntry->invoice_number ?: '-' }}<small>{{ $paymentEntry->bank_reference_number ?: '' }}</small>
                                        </td>
                                        <td><strong>{{ $paymentEntry->particular }}</strong><small>{{ $paymentEntry->remarks }}</small>
                                        </td>
                                        <td>{{ $paymentEntry->categoryLabel() }}<small>{{ $paymentEntry->statusLabel() }}</small>
                                        </td>
                                        <td class="red">
                                            {{ $paymentEntry->transaction_type === 'credit' ? ($paymentEntry->foreign_currency ?: 'RMB') . ' ' . number_format((float) $paymentEntry->foreign_amount, 2) : '-' }}
                                        </td>
                                        <td class="green">
                                            {{ $paymentEntry->transaction_type === 'debit' ? ($paymentEntry->foreign_currency ?: 'RMB') . ' ' . number_format((float) $paymentEntry->foreign_amount, 2) : '-' }}
                                        </td>
                                        <td><strong>{{ $paymentEntry->foreign_currency ?: 'RMB' }}
                                                {{ number_format($paymentEntry->running_balance, 2) }}</strong>
                                        </td>
                                        <td>₹ {{ number_format((float) $paymentEntry->amount_in_inr, 2) }}<small>Rate:
                                                {{ $paymentEntry->exchange_rate ? number_format($paymentEntry->exchange_rate, 2) : '-' }}</small></td>
                                        <td>{{ $paymentEntry->relationLoaded('paidAccount') && $paymentEntry->paidAccount ? $paymentEntry->paidAccount->name : '-' }}<small>
                                                @foreach ($paymentEntry->attachments as $attachment)
                                                    <a href="{{ $attachment->fileUrl() }}"
                                                        target="_blank">{{ $attachment->extension ?: 'file' }}</a>
                                                @endforeach
                                            </small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9">
                                            <div class="vendor-empty">No manual vendor statement entries found. Add
                                                entries from the Payments tab.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!--Shipments-->
                <section class="vendor-panel" data-panel="shipments">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Shipments</p>
                            <h2>Vendor Related Shipments</h2>
                        </div>
                        <div>
                            <span class="vendor-pill">{{ $shipments->count() }} Shipments</span>
                        </div>
                    </div>
                    <div class="vendor-card vendor-table-wrap">
                        <table class="vendor-table">
                            <thead>
                                <tr>
                                    <th>Shipment</th>
                                    <th>Route</th>
                                    <th>Pickup / Drop</th>
                                    <th>Logistic</th>
                                    <th>Status</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shipments as $shipment)
                                    <tr>
                                        <td><strong>{{ $shipment->shipment_number }}</strong><small>{{ $shipment->identity_name }}</small>
                                        </td>
                                        <td>{{ $shipment->from_name ?: '-' }} →
                                            {{ $shipment->to_name ?: '-' }}<small>{{ $shipment->from_city ?: '-' }}
                                                to {{ $shipment->to_city ?: '-' }}</small></td>
                                        <td>{{ optional($shipment->pickup_date)->format('d M Y') ?: '-' }}<small>Drop:
                                                {{ optional($shipment->drop_date)->format('d M Y') ?: '-' }}</small>
                                        </td>
                                        <td>{{ $shipment->logistic_partner ?: '-' }}<small>{{ $shipment->tracking_number ?: 'No tracking' }}</small>
                                        </td>
                                        <td>{{ method_exists($shipment, 'statusLabel') ? $shipment->statusLabel() : $shipment->status }}
                                        </td>
                                        <td>{{ $shipment->currency }}
                                            {{ number_format((float) $shipment->shipment_cost, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="vendor-empty">No shipments matched with this vendor. Add
                                                vendor_id to shipment in future for exact mapping.</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <!--Comments-->
                <section class="vendor-panel" data-panel="comments">
                    <div class="vendor-section-head">
                        <div>
                            <p class="vendor-eyebrow">Comments</p>
                            <h2>Add Vendor Comment</h2>
                        </div>
                        <div>
                            <span class="vendor-pill">{{ $commentsAvailable ? $vendor->comments->count() : 0 }} Comments</span> &nbsp;
                            @if($commentsAvailable)
                                <button type="button" class="master-btn master-btn-primary addCommentBtn" id="openAddCommentModal"><i class="fas fa-plus"></i> Add Comment</button>
                            @endif
                        </div>
                    </div>
                    @if($commentsAvailable)
                        <div class="vendor-grid-2">
                            @forelse($vendor->comments as $comment)
                                <div class="vendor-comment {{ $comment->is_pinned ? 'pinned' : '' }}">
                                    <div><strong>{{ $comment->creator?->name ?? 'Internal Team' }}</strong><span>{{ $comment->created_at->format('d M Y, h:i A') }}
                                            @if ($comment->is_pinned)
                                                · Pinned
                                            @endif
                                        </span>
                                    </div>
                                    <p>{{ $comment->body }}</p>
                                    @if (\Illuminate\Support\Facades\Route::has('vendors.comments.destroy'))
                                        <form method="POST"
                                            action="{{ route('vendors.comments.destroy', $comment) }}"
                                            onsubmit="return confirm('Delete comment?')">@csrf @method('DELETE')<button
                                            type="submit">Delete</button></form>
                                    @endif
                                </div>
                            @empty
                                <div class="vendor-empty small">No comments yet.</div>
                            @endforelse
                        </div>
                    @else
                        <div class="vendor-empty">Run migration to enable comments.</div>
                    @endif
                </section>
                
                <!--Add Comment-->
                <div class="master-modal" id="addCommentModal" aria-hidden="true">
                    
                    <form method="POST" action="{{ route('vendors.comments.store', $vendor) }}"
                        class="vendor-comment-form">
                        @csrf
                        <input type="hidden" name="vendor_id" value="{{ $vendor->id }}">
                        
                        <div class="master-modal-header">
                            <div class="master-modal-heading">
                                <div>
                                    <h3 class="master-modal-title">Add Comment</h3>
                                </div>
                            </div>
                            <button type="button" class="master-modal-close" data-close-modal id="closeAddCommentModal">×</button>
                        </div>
                        <div class="master-modal-body" style="width:700px;">
                            <div class="master-modal-grid">
                                <div class="master-field full">
                                    <label class="master-label">Comment</label>
                                    <textarea class="master-textarea" name="body" required
                                        placeholder="Add internal vendor comment, payment note, follow-up, issue, reminder..."></textarea>
                                </div>
                                <div class="master-field full">
                                    <label class="master-label"><input class="master-check" type="checkbox" name="is_pinned" value="1"> Pin this
                                    comment</label>
                                </div>
                            </div>
                        </div>
                        <div class="master-modal-footer">
                            <button type="button" class="master-btn master-btn-light" id="cancelAddCommentModal" data-close-modal>Cancel</button>
                            <button class="master-btn master-btn-primary" type="submit"> Add Comment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
    <script src="{{ asset('assets/js/vendors.js') }}"></script>
@endpush
@endsection
