@extends('layouts.app')

@section('page-title', $vendor->vendor_name)

@section('content')
    @php
        $statusClass = str_replace('_', '-', $vendor->status);
        $typeClass = str_replace('_', '-', $vendor->vendor_type);
        $money = function ($amount, $currency = '₹') {
            return $currency . ' ' . number_format((float) $amount, 2);
        };
    @endphp

    <div class="vendor-show">
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
                    <div class="master-chip-row" style="padding-top:10px;">
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

                    <div class="vendor-grid-3" style="margin-top:18px;">
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
                    <div class="vendor-card vendor-section" style="margin-bottom:20px;">
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
                    <div class="vendor-section-head" style="padding:15px;">
                        <div>
                            <p class="vendor-eyebrow">Files</p>
                            <h2>Attachments</h2>
                        </div>
                        <div>
                            <span class="vendor-count">{{ $vendor->attachments->count() }} Files</span> &nbsp;
                            <button type="button" class="master-btn master-btn-primary" id="openAddAttachmentModal"><i class="fas fa-plus"></i> Add Attachment</button>
                        </div>
                    </div>
                        
                    <div class="vendor-attachment-grid vendor-card" style="padding: 15px;">
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
                    <div class="vendor-section-head" style="padding:15px;">
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
                    <div class="vendor-section-head" style="padding:15px;">
                        <div>
                            <p class="vendor-eyebrow">Products</p>
                            <h2>Products Supplied</h2>
                        </div>
                        <div>
                            <span class="vendor-count">{{ $products->count() }} Products</span>
                        </div>
                    </div>
                        
                    <div class="vendor-info-grid vendor-card" style="padding:15px;">
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
                    <div class="vendor-section-head" style="padding:15px;">
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

                    <div class="vendor-section-head" style="margin-top:18px;padding:15px;">
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
                    <div class="vendor-section-head" style="padding:15px;">
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
                    <div class="vendor-card vendor-table-wrap" style="margin-top:16px;">
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
                                            {{ $paymentEntry->transaction_type === 'credit' ? $currency . ' ' . number_format((float) $paymentEntry->foreign_amount, 2) : '-' }}
                                        </td>
                                        <td class="green">
                                            {{ $paymentEntry->transaction_type === 'debit' ? $currency . ' ' . number_format((float) $paymentEntry->foreign_amount, 2) : '-' }}
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
                    <div class="vendor-section-head" style="padding:15px;">
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
                    <div class="vendor-section-head" style="padding:15px;">
                        <div>
                            <p class="vendor-eyebrow">Comments</p>
                            <h2>Add Vendor Comment</h2>
                        </div>
                        <div>
                            <span class="vendor-pill">{{ $vendor->comments->count() }} Comments</span> &nbsp;
                            <button type="button" class="master-btn master-btn-primary addCommentBtn" id="openAddCommentModal"><i class="fas fa-plus"></i> Add Comment</button>
                        </div>
                    </div>
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

    <style>
        :root {
            --vendor-primary: #4f83f1;
            --vendor-primary-2: #6366f1;
            --vendor-dark: #17233b;
            --vendor-muted: #687386;
            --vendor-border: #dfe7f3;
            --vendor-bg: #eef3ff;
            --vendor-soft: #edf5ff;
            --vendor-white: #fff;
            --vendor-red: #ef4770;
            --vendor-green: #10b981;
            --vendor-orange: #f59e0b;
            --vendor-shadow: 0 14px 35px rgba(25, 42, 70, .08)
        }

        .vendor-show,
        .vendor-show * {
            box-sizing: border-box
        }

        .vendor-show {
            background: var(--vendor-bg);
            color: var(--vendor-dark);
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 18px
        }

        .vendor-hero {
            background: linear-gradient(135deg, #4f83f1, #7b61ff);
            border-radius: 26px;
            padding: 24px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 18px 45px rgba(79, 131, 241, .22)
        }

        .vendor-head-left {
            display: flex;
            gap: 18px;
            align-items: center;
            min-width: 0
        }

        .vendor-image {
            width: 82px;
            height: 82px;
            border-radius: 20px;
            object-fit: cover;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            flex: 0 0 82px
        }

        .vendor-eyebrow {
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: .13em;
            font-size: 11px;
            font-weight: 600;
            opacity: .78
        }

        .vendor-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 600
        }

        .vendor-row-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 8px
        }

        .vendor-row-actions a {
            font-weight: 600;
            text-decoration: none
        }

        .vendor-hero p {
            margin: 6px 0 0;
            opacity: .9
        }

        .vendor-actions,
        .vendor-chip-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center
        }

        .vendor-actions {
            justify-content: flex-end
        }

        .vendor-btn {
            min-height: 42px;
            border: 0;
            border-radius: 14px;
            padding: 11px 16px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap
        }

        .vendor-btn-primary {
            background: #ef4770;
            color: #fff;
            box-shadow: 0 10px 24px rgba(239, 71, 112, .24)
        }

        .vendor-btn-soft {
            background: var(--vendor-soft);
            color: var(--vendor-primary)
        }

        .vendor-btn-light {
            background: rgba(255, 255, 255, .16);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .3)
        }

        .status-active {
            background: #e8fff7;
            color: #0e9f6e
        }

        .status-inactive {
            background: #f3f6fb;
            color: #536079
        }

        .status-on-hold {
            background: #fff4e5;
            color: #d97706
        }

        .status-blacklisted {
            background: #ffeaf0;
            color: #e11d48
        }
        
        .vendor-attachment-form {
            grid-template-columns: 1fr 1fr 1fr 1fr auto 1fr auto
        }

        .vendor-attachment-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px
        }

        .type-manufacturer {
            background: #eaf1ff;
            color: #3f7cf4
        }

        .type-trader {
            background: #fff4e5;
            color: #d97706
        }

        .type-distributor {
            background: #ecfdf5;
            color: #059669
        }

        .type-service-provider {
            background: #ece7ff;
            color: #7c3aed
        }

        .currency {
            background: rgba(255, 255, 255, .16);
            color: #fff
        }

        .vendor-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px
        }

        .vendor-kpi,
        .vendor-card,
        .vendor-tabs-card {
            background: #fff;
            border: 1px solid var(--vendor-border);
            border-radius: 22px;
            box-shadow: var(--vendor-shadow)
        }

        .vendor-kpi {
            padding: 16px
        }

        .vendor-kpi span {
            display: block;
            color: #687386;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase
        }

        .vendor-kpi strong {
            display: block;
            margin-top: 7px;
            font-size: 22px
        }

        .vendor-kpi small {
            display: block;
            margin-top: 5px;
            color: #8792a5;
            font-weight: 500
        }

        .vendor-kpi.green strong,
        .green {
            color: #0e9f6e !important
        }

        .vendor-kpi.red strong,
        .red {
            color: #e11d48 !important
        }

        .vendor-kpi.orange strong,
        .orange {
            color: #d97706 !important
        }

        .vendor-kpi.blue strong {
            color: #4f83f1
        }

        .vendor-kpi.purple strong {
            color: #7c3aed
        }

        .vendor-tabs-card {
            overflow: hidden
        }

        .vendor-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 12px;
            background: #f8fbff;
            border-bottom: 1px solid var(--vendor-border)
        }

        .vendor-tab {
            border: 1px solid var(--vendor-border);
            background: #fff;
            color: #687386;
            border-radius: 14px;
            padding: 10px 13px;
            font-weight: 600;
            white-space: nowrap;
            cursor: pointer
        }

        .vendor-tab.active {
            background: #4f83f1;
            color: #fff;
            border-color: #4f83f1
        }

        .vendor-tab span {
            background: #eef3ff;
            color: #4f83f1;
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 11px;
            margin-left: 5px
        }

        .vendor-tab.active span {
            background: rgba(255, 255, 255, .2);
            color: #fff
        }

        .vendor-tab-panels {
            padding: 18px
        }

        .vendor-panel {
            display: none
        }

        .vendor-panel.active {
            display: block
        }

        .vendor-card {
            box-shadow: none
        }

        .vendor-section {
            padding: 20px
        }

        .vendor-section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px
        }

        .vendor-section-head h2 {
            margin: 0;
            color: #172033;
            font-weight: 600;
            font-size: 20px
        }

        .vendor-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px
        }

        .vendor-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px
        }

        .vendor-info-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px
        }

        .vendor-info {
            padding: 14px;
            border: 1px solid var(--vendor-border);
            border-radius: 14px;
            background: #fbfdff
        }

        .vendor-info.full {
            grid-column: 1/-1
        }

        .vendor-info span {
            display: block;
            color: #7d8aa0;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 5px
        }

        .vendor-info strong,
        .vendor-info small {
            display: block;
            overflow-wrap: anywhere
        }

        .vendor-info small {
            color: #687386;
            margin-top: 4px;
            font-weight: 500
        }

        .vendor-rating {
            font-size: 20px;
            color: #f59e0b;
            letter-spacing: 2px
        }

        .vendor-finance-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px
        }

        .vendor-finance-grid div {
            background: #f8fbff;
            border: 1px solid var(--vendor-border);
            border-radius: 16px;
            padding: 14px
        }

        .vendor-finance-grid span {
            display: block;
            color: #687386;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase
        }

        .vendor-finance-grid strong {
            display: block;
            margin-top: 7px;
            font-size: 20px
        }

        .vendor-quick-links {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px
        }

        .vendor-quick-links a {
            background: #f8fbff;
            border: 1px solid var(--vendor-border);
            border-radius: 14px;
            padding: 13px;
            text-decoration: none;
            color: #4f83f1;
            font-weight: 600
        }

        .vendor-mini-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid var(--vendor-border);
            padding: 10px 0
        }

        .vendor-mini-row:last-child {
            border-bottom: 0
        }

        .vendor-mini-row strong,
        .vendor-mini-row small {
            display: block
        }

        .vendor-mini-row small {
            color: #687386;
            margin-top: 3px
        }

        .vendor-table-wrap {
            overflow: auto
        }

        .vendor-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px
        }

        .vendor-table.compact {
            min-width: 640px
        }

        .vendor-table th,
        .vendor-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--vendor-border);
            text-align: left;
            vertical-align: top
        }

        .vendor-table th {
            background: #fbfdff;
            color: #7d8aa0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .07em
        }

        .vendor-table small {
            display: block;
            color: #687386;
            margin-top: 3px;
            font-weight: 500
        }

        .vendor-pill {
            background: #eef3ff;
            color: #4f83f1;
            border-radius: 999px;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 600
        }

        .vendor-pill.green {
            background: #e8fff7;
            color: #0e9f6e
        }

        .vendor-product-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px
        }

        .vendor-product-card {
            display: grid;
            grid-template-columns: 62px 1fr;
            gap: 10px;
            align-items: center;
            border: 1px solid var(--vendor-border);
            border-radius: 16px;
            padding: 10px
        }

        .vendor-product-card img,
        .vendor-product-empty {
            width: 62px;
            height: 62px;
            border-radius: 14px;
            object-fit: cover;
            background: #edf5ff;
            color: #4f83f1;
            display: grid;
            place-items: center;
            font-size: 24px
        }

        .vendor-product-card strong,
        .vendor-product-card small {
            display: block
        }

        .vendor-product-card small {
            color: #687386;
            margin-top: 3px
        }

        .vendor-empty {
            padding: 28px;
            text-align: center;
            border: 1px dashed #d8deea;
            border-radius: 16px;
            color: #687386;
            font-weight: 600;
            background: #fbfcff
        }

        .vendor-empty.small {
            padding: 16px
        }

        .vendor-comment-mini,
        .vendor-comment {
            border: 1px solid var(--vendor-border);
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 10px;
            background: #fff
        }

        .vendor-comment-mini strong {
            display: block
        }

        .vendor-comment-mini p,
        .vendor-comment p {
            margin: 6px 0 0;
            color: #536079;
            white-space: pre-wrap
        }

        .vendor-comment.pinned {
            background: #fffdf7;
            border-color: #fedf89
        }

        .vendor-comment span {
            display: block;
            color: #687386;
            font-size: 12px;
            margin-top: 3px
        }

        .vendor-comment form {
            margin-top: 8px
        }

        .vendor-comment button {
            border: 0;
            background: #fff0f4;
            color: #e11d48;
            border-radius: 10px;
            padding: 8px 10px;
            font-weight: 600;
            cursor: pointer
        }

        .vendor-comment-form {
            background: #f8fbff;
            border: 1px solid var(--vendor-border);
            border-radius: 18px;
            padding: 16px
        }

        .vendor-comment-form textarea {
            width: 100%;
            min-height: 140px;
            border: 1px solid #d8e2ef;
            border-radius: 14px;
            padding: 12px;
            resize: vertical
        }

        .vendor-comment-form label {
            font-weight: 600;
            color: #536079
        }

        @media(max-width:1500px) {
            .vendor-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr))
            }

            .vendor-grid-3,
            .vendor-grid-2 {
                grid-template-columns: 1fr
            }

            .vendor-finance-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:767px) {
            .vendor-show {
                padding: 14px
            }

            .vendor-hero {
                flex-direction: column
            }

            .vendor-head-left {
                align-items: flex-start
            }

            .vendor-actions,
            .vendor-actions .vendor-btn {
                width: 100%
            }

            .vendor-kpi-grid,
            .vendor-info-grid,
            .vendor-finance-grid,
            .vendor-product-grid,
            .vendor-quick-links {
                grid-template-columns: 1fr
            }

            .vendor-tab-panels {
                padding: 12px
            }

            .vendor-section {
                padding: 15px
            }

            .vendor-image {
                width: 68px;
                height: 68px;
                flex-basis: 68px
            }

            .vendor-hero h1 {
                font-size: 24px
            }
        }
        
        .vendor-attachment {
            display: grid;
            grid-template-columns: 64px 1fr;
            border: 1px solid #edf0f7;
            background: #fff;
            border-radius: 18px;
            padding: 14px;
            gap: 12px
        }

        .vendor-attachment img,
        .vendor-file-icon {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            object-fit: cover;
            background: #eef3ff;
            color: #4f83f1;
            display: grid;
            place-items: center;
            font-size: 22px
        }

        .vendor-attachment strong,
        .vendor-attachment span {
            display: block
        }

        .vendor-attachment span {
            color: #7b8495;
            font-size: 12px;
            margin-top: 3px
        }

        .vendor-payment-form {
            background: #f8fbff;
            border: 1px solid var(--vendor-border);
            border-radius: 18px;
            padding: 16px
        }

        .vendor-form-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px
        }

        .vendor-field {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .vendor-field.full,
        .vendor-check.full {
            grid-column: 1/-1
        }

        .vendor-field label {
            font-size: 12px;
            color: #536079;
            font-weight: 600
        }

        .vendor-field label span {
            color: #ef4770
        }

        .vendor-field input,
        .vendor-field select,
        .vendor-field textarea {
            width: 100%;
            border: 1px solid #d8e2ef;
            border-radius: 13px;
            padding: 10px 12px;
            background: #fff;
            color: #17233b;
            outline: none
        }

        .vendor-field input,
        .vendor-field select {
            height: 42px
        }

        .vendor-field textarea {
            min-height: 86px;
            resize: vertical
        }

        .vendor-field input:focus,
        .vendor-field select:focus,
        .vendor-field textarea:focus {
            border-color: #4f83f1;
            box-shadow: 0 0 0 3px rgba(79, 131, 241, .12)
        }

        .vendor-check {
            display: flex;
            gap: 8px;
            align-items: center;
            font-weight: 600;
            color: #536079
        }

        .vendor-check input {
            accent-color: #4f83f1
        }

        .vendor-form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px
        }

        .vendor-file-link {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 8px;
            background: #eef3ff;
            color: #4f83f1;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            margin: 2px
        }

        .vendor-row-delete {
            border: 0;
            border-radius: 10px;
            background: #fff0f4;
            color: #e11d48;
            padding: 8px 10px;
            font-weight: 600;
            cursor: pointer
        }

        .vendor-currency-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px
        }

        .vendor-currency-summary div {
            background: #f8fbff;
            border: 1px solid var(--vendor-border);
            border-radius: 16px;
            padding: 14px
        }

        .vendor-currency-summary span {
            display: block;
            color: #687386;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase
        }

        .vendor-currency-summary strong {
            display: block;
            margin-top: 7px;
            font-size: 18px
        }

        @media(max-width:1200px) {
            .vendor-form-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .vendor-currency-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }
        }

        @media(max-width:767px) {

            .vendor-form-grid,
            .vendor-currency-summary {
                grid-template-columns: 1fr
            }

            .vendor-form-actions .vendor-btn {
                width: 100%
            }
        }
        
        .no-decorate {
            text-decoration: none;
        }
        
        .master-modal {
            display: none;
        }
        
        .master-modal.show {
            display: flex;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.vendor-tab').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.vendor-tab').forEach(function(b) {
                        b.classList.remove('active');
                    });
                    document.querySelectorAll('.vendor-panel').forEach(function(panel) {
                        panel.classList.remove('active');
                    });
                    btn.classList.add('active');
                    const panel = document.querySelector('[data-panel="' + btn.dataset.tab + '"]');
                    if (panel) panel.classList.add('active');
                    try {
                        localStorage.setItem('vendor_show_tab_{{ $vendor->id }}', btn.dataset
                            .tab);
                    } catch (e) {}
                });
            });

            try {
                const saved = localStorage.getItem('vendor_show_tab_{{ $vendor->id }}');
                if (saved) {
                    const btn = document.querySelector('.vendor-tab[data-tab="' + saved + '"]');
                    if (btn) btn.click();
                }
            } catch (e) {}

            const foreignAmount = document.getElementById('foreignAmount');
            const foreignCurrency = document.getElementById('foreignCurrency');
            const exchangeRate = document.getElementById('exchangeRate');
            const amountInInr = document.getElementById('amountInInr');

            function calculateInrAmount() {
                if (!foreignAmount || !foreignCurrency || !exchangeRate || !amountInInr) return;
                const amount = parseFloat(foreignAmount.value || '0');
                const rate = parseFloat(exchangeRate.value || '0');
                if (foreignCurrency.value === 'INR' && amount > 0 && !amountInInr.value) {
                    amountInInr.value = amount.toFixed(2);
                    return;
                }
                if (amount > 0 && rate > 0) {
                    amountInInr.value = (amount * rate).toFixed(2);
                }
            }

            foreignAmount?.addEventListener('input', calculateInrAmount);
            exchangeRate?.addEventListener('input', calculateInrAmount);
            foreignCurrency?.addEventListener('change', calculateInrAmount);
        });
        
        
        document.addEventListener('DOMContentLoaded', function() {
            
            function openModal(modal){
                modal.classList.add('show');
                document.body.classList.add('master-modal-open');
            }
            
            function closeModal(modal){
                modal.classList.remove('show');
                document.body.classList.remove('master-modal-open');
            }
            
            function setValue(form, name, value) {
                const field = form.elements[name];
                if (!field) return;
            
                if (field.type === 'checkbox') {
                    field.checked = !!value;
                } else {
                    field.value = value ?? '';
                }
            }
        
            document.addEventListener('keydown',function(e){
                if(e.key !== 'Escape') return;
                document.querySelectorAll('.master-modal.show').forEach(modal=>{
                    closeModal(modal);
                });
            });
            
            document.querySelectorAll('.master-modal').forEach(modal=>{
                modal.addEventListener('click',function(e){
                    if(e.target===modal){
                        closeModal(modal);
                    }
                });
            });
                
            document.querySelectorAll('[data-close-modal]').forEach(btn=>{
                btn.addEventListener('click',()=>{
                    closeModal(btn.closest('.master-modal'));
                });
            });
            
            const editPaymentModal = document.getElementById('editPaymentModal');
            const editPaymentForm = document.getElementById('editPaymentForm');
            
            document.querySelectorAll('.editPaymentBtn').forEach(btn => {

                btn.addEventListener('click', function () {
        
                    const payment = JSON.parse(this.dataset.payment);
        
                    editPaymentForm.action = `/vendors/${payment.vendor_id}/payments/${payment.id}`;
        
                    setValue(editPaymentForm, 'invoice_number', payment.invoice_number ?? '');
                    
                    let paymentDate = payment.transaction_date;

                    if (paymentDate) {
                        paymentDate = paymentDate.substring(0, 10);
                    }
                    setValue(editPaymentForm, 'transaction_date', paymentDate);
                    setValue(editPaymentForm, 'particular', payment.particular ?? '');
                    setValue(editPaymentForm, 'foreign_amount', payment.foreign_amount);
                    setValue(editPaymentForm, 'foreign_currency', payment.foreign_currency);
                    setValue(editPaymentForm, 'exchange_rate', payment.exchange_rate);
                    setValue(editPaymentForm, 'amount_in_inr', payment.amount_in_inr);
                    setValue(editPaymentForm, 'transaction_type', payment.transaction_type);
                    setValue(editPaymentForm, 'entry_category', payment.entry_category);
                    setValue(editPaymentForm, 'status', payment.status);
                    setValue(editPaymentForm, 'project_id', payment.project_id);
                    setValue(editPaymentForm, 'paid_account_id', payment.paid_account_id);
                    setValue(editPaymentForm, 'payment_mode', payment.payment_mode);
                    setValue(editPaymentForm, 'bank_reference_number', payment.bank_reference_number);
                    setValue(editPaymentForm, 'remarks', payment.remarks ?? '');
                    setValue(editPaymentForm, 'also_create_cashflow', payment.also_create_cashflow);
                    
        
                    openModal(editPaymentModal);
                });
            });
            
            const addPaymentModal = document.getElementById('addPaymentModal');
            document.getElementById('openAddPaymentModal')?.addEventListener('click', () => openModal(addPaymentModal));
            
            const addAttachmentModal = document.getElementById('addAttachmentModal');
            document.getElementById('openAddAttachmentModal')?.addEventListener('click', () => openModal(addAttachmentModal));
            
            const addCommentModal = document.getElementById('addCommentModal');
            document.getElementById('openAddCommentModal')?.addEventListener('click', () => openModal(addCommentModal));
        });
    </script>
@endsection
