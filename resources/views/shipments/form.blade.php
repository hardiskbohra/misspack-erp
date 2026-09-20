@extends('layouts.app')

@section('page-title', $shipment->exists ? 'Edit Shipment' : 'Add Shipment')

@section('content')

<style>
    .master-items-wrap{width:100%;overflow-x:auto}.master-items-table{width:100%;min-width:1120px;border-collapse:collapse}.master-items-table th,.master-items-table td{padding:10px;border-bottom:1px solid var(--master-border);vertical-align:top}.master-items-table th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#000;text-align:left}.master-remove-row{width:34px;height:34px;border:0;border-radius:9px;background:#fff0f4;color:#e11d48;cursor:pointer;font-weight:900}.master-add-row{margin-top:12px}
    
    .master-history-card{padding:26px;margin-top:22px}.master-history-form{display:grid;gap:14px}.master-history-grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr 1fr;gap:14px}.master-history-row{border:1px solid var(--master-border);border-radius:16px;background:#fbfdff;padding:16px;margin-bottom:14px}.master-history-row-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.master-history-row-title{font-size:14px;font-weight:600;color:var(--master-dark)}.master-history-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.master-history-delete{margin:0}.master-history-note{font-size:12px;color:var(--master-muted);font-weight:500;line-height:1.5}.master-public-check{display:flex;align-items:center;gap:8px;min-height:44px;font-size:13px;font-weight:600;color:#536079}
    
    @media (max-width:1200px){
        .master-history-grid{
            grid-template-columns:repeat(3,1fr);
        }
    }
    
    @media (max-width:768px){
        .master-history-grid{
            grid-template-columns:1fr;
        }
    }
    
    .master-attachments-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.master-attachment-card{border:1px solid var(--master-border);border-radius:16px;background:#fbfdff;overflow:hidden}.master-attachment-card img{width:150px;height:150px;object-fit:cover;background:#f8fafc;display:block}.master-attachment-body{padding:12px;display:grid;gap:10px}.master-attachment-actions{display:flex;gap:8px;flex-wrap:wrap}.master-photo-upload-box{padding:14px;border:1px dashed var(--master-border);border-radius:14px;background:#fbfdff}.master-photo-help{font-size:12px;color:var(--master-muted);font-weight:500;margin-top:6px;line-height:1.5}
    
    .master-history-footer{
        display:flex;
        justify-content:right;
        align-items:center;
        gap:12px;
        margin-top:18px;
        flex-wrap:wrap;
    }
    
    .master-history-footer form{
        margin:0;
    }
    
    .master-history-footer .master-btn-primary{
        min-width:160px;
    }
    
    .master-history-footer .master-btn-danger{
        min-width:160px;
    }
    
    .master-existing-attachments{
        margin-bottom:24px;
    }
    
    .master-attachment-grid{
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(170px,1fr));
        gap:18px;
        margin-top:12px;
    }
    
    .master-attachment-card{
        background:#fff;
        border:1px solid #e7edf7;
        border-radius:16px;
        overflow:hidden;
    }
    
    .master-attachment-preview{
        height:140px;
        background:#f8faff;
        display:flex;
        justify-content:center;
    }
    
    .master-attachment-preview img{
        width:100%;
        height:100%;
        object-fit:cover;
    }
    
    .master-file-icon{
        font-size:42px;
    }
    
    .master-attachment-info{
        padding:12px;
    }
    
    .master-attachment-info strong{
        display:block;
        font-size:13px;
        margin-bottom:4px;
        word-break:break-word;
    }
    
    .master-attachment-info small{
        color:#8a96a8;
    }
    
    .master-attachment-actions{
        display:flex;
        gap:8px;
        padding:12px;
    }
    
    .master-attachment-actions .master-btn{
        flex:1;
    }
    
    .ship-public-toggle {
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        font-weight: 500;
        color: #536079;
        cursor: pointer;
        user-select: none;
    }
    
    .ship-public-toggle input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    
    .ship-public-switch {
        width: 44px;
        height: 24px;
        border-radius: 999px;
        background: #d8e2ef;
        position: relative;
        flex: 0 0 44px;
        transition: .2s ease;
    }
    
    .ship-public-switch:after {
        content: "";
        position: absolute;
        top: 3px;
        left: 3px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 2px 6px rgba(15,23,42,.18);
        transition: .2s ease;
    }
    
    .ship-public-toggle input:checked + .ship-public-switch {
        background: linear-gradient(135deg, var(--ship-primary), var(--ship-primary-2));
    }
    
    .ship-public-toggle input:checked + .ship-public-switch:after {
        transform: translateX(20px);
    }
    
    .ship-public-toggle strong {
        line-height: 1.3;
    }
    
    .ship-public-toggle small {
        display: block;
        font-size: 11px;
        color: var(--ship-muted);
        font-weight: 500;
        margin-top: 2px;
    }
    
    .master-photo-upload-grid{
        display:grid;
        grid-template-columns:2fr 1fr;
        gap:24px;
    }
    
    .master-photo-public{
        display:flex;
    }
    
    @media(max-width:768px) {
        
        .master-items-table,
        .master-items-table thead,
        .master-items-table tbody,
        .master-items-table tr,
        .master-items-table td{
            display:block;
            min-width: 346px;
            width:100%;
        }
        
        .master-items-wrap {
            width:100%;
            overflow-x:auto
            
        }
        
        .master-items-table thead{
            display:none;
        }
        
        .master-items-table tr{
            border:1px solid var(--master-border);
            border-radius:16px;
            padding:16px;
            margin-bottom:16px;
            background:#fff;
        }
        
        .master-items-table input,
        .master-items-table select,
        .master-items-table button,
        .master-items-table textarea{
            width:94%;
        }
        
        .master-items-table td{
            border:none;
            padding:8px 0;
        }
        
        .master-items-table td::before{
            display:block;
            font-size:11px;
            color:#8a96a8;
            font-weight:700;
            margin-bottom:4px;
        }
        
        .master-items-table td:nth-child(1)::before{content:"Product";}
        .master-items-table td:nth-child(2)::before{content:"HS Code";}
        .master-items-table td:nth-child(3)::before{content:"Qty";}
        .master-items-table td:nth-child(4)::before{content:"Unit";}
        .master-items-table td:nth-child(5)::before{content:"Amount";}
        .master-items-table td:nth-child(6)::before{content:"Currency";}
        .master-items-table td:nth-child(7)::before{content:"";}
        
        .master-remove-row{
            width:100%;
        }
        
        .master-attachment-grid{
            grid-template-columns:1fr;
        }
        
        .master-attachment-preview{
            height:220px;
        }
        
        .master-photo-upload-grid{
            grid-template-columns:1fr;
            gap:16px;
        }
    
        .master-photo-public{
            justify-content:flex-start;
            padding-top:0;
        }
        
        .master-actions{
            display:flex;
            flex-direction:column;
            gap:12px;
        }
        
        .master-actions .master-btn{
            width:100%;
        }

        .master-history-footer{
            flex-direction:column;
            align-items:stretch;
        }
        
        .master-history-footer .master-btn{
            width:100%;
        }
        
        .master-attachment-actions{
            flex-direction:column;
        }
        
        .master-attachment-actions .master-btn{
            width:100%;
        }
        
        .master-history-row-head{
            flex-direction:column;
            align-items:flex-start;
            gap:10px;
        }
        
        .master-detail-grid {
            grid-template-columns:1fr;
        }
        
    }
</style>

    @php
        $isEdit = $shipment->exists;
        $oldItems = old('items');
        $items = $oldItems !== null ? $oldItems : $shipment->items->map(fn($item) => $item->only(['product_name', 'sku', 'hs_code', 'quantity', 'unit', 'declared_value', 'currency', 'net_weight', 'gross_weight', 'description']))->toArray();
        if (empty($items)) {
            $items = [['product_name' => '', 'sku' => '', 'hs_code' => '', 'quantity' => 1, 'unit' => 'pcs', 'declared_value' => '', 'currency' => $shipment->currency ?: 'INR', 'net_weight' => '', 'gross_weight' => '', 'description' => '']];
        }
    @endphp

    <div class="master-form">
        <div class="master-card master-header">
            <h1>{{ $isEdit ? 'Edit Shipment' : 'Add Shipment' }}</h1>
            <div class="master-breadcrumb"><a href="{{ url('/') }}">Home</a><span>•</span><a
                    href="{{ route('shipments.index') }}">Shipments</a><span>•</span><span
                    class="active">{{ $isEdit ? 'Edit' : 'Add' }}</span></div>
        </div>

        <form method="POST" action="{{ $isEdit ? route('shipments.update', $shipment) : route('shipments.store') }}"
            class="master-card master-form-card" enctype="multipart/form-data">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="master-section">
                <h3 class="master-section-title">Basic Shipment Information</h3>
                <div class="master-detail-grid">
                    <div class="master-field desktop-only"><label class="master-label">Shipment Number</label><input class="master-input"
                            name="shipment_number" value="{{ old('shipment_number', $shipment->shipment_number) }}"
                            placeholder="Auto generated if blank" readonly>@error('shipment_number')<div class="master-error">
                            {{ $message }}</div>@enderror</div>
                    <div class="master-field two"><label class="master-label">Shipment Identity Name <span
                                class="master-required">*</span></label><input class="master-input" name="identity_name"
                            value="{{ old('identity_name', $shipment->identity_name) }}" required
                            placeholder="e.g. Dafina green pigment import">@error('identity_name')<div class="master-error">
                            {{ $message }}</div>@enderror</div>
                    <div class="master-field desktop-only"><label class="master-label">Shipment Type <span
                                class="master-required">*</span></label><select class="master-select" name="shipment_type"
                            required>@foreach($typeOptions as $key => $label)<option value="{{ $key }}"
                                @selected(old('shipment_type', $shipment->shipment_type) === $key)>{{ $label }}</option>
                            @endforeach</select></div>
                    <div class="master-field">
                        <label class="master-label">Shipment Label</label>
                        <input class="master-input" name="shipment_label"
                            value="{{ old('identity_name', $shipment->shipment_label) }}" >
                    </div>
                    <div class="master-field"><label class="master-label">Status <span
                                class="master-required">*</span></label><select class="master-select" name="status"
                            required>@foreach($statusOptions as $key => $label)<option value="{{ $key }}"
                            @selected(old('status', $shipment->status) === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div class="master-field"><label class="master-label">Pickup Date</label><input class="master-input"
                            type="date" name="pickup_date"
                            value="{{ old('pickup_date', optional($shipment->pickup_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field"><label class="master-label">Drop Date</label><input class="master-input" type="date"
                            name="drop_date"
                            value="{{ old('drop_date', optional($shipment->drop_date)->format('Y-m-d')) }}"></div>
                    <div class="master-field">
                        <label class="master-label">Mapped Vendor</label>
                        <select name="vendor_id" class="master-select">
                            <option value="">No vendor portal mapping</option>
                            @foreach(\App\Models\Vendor::query()->orderBy('vendor_name')->get() as $vendor)
                                <option value="{{ $vendor->id }}" {{ (string) old('vendor_id', $shipment->vendor_id ?? '') === (string) $vendor->id ? 'selected' : '' }}>
                                    {{ $vendor->contact_person_name }} ({{ $vendor->vendor_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Mapped Client</label>
                        <select name="client_id" class="master-select">
                            <option value="">No client portal mapping</option>
                            @foreach(\App\Models\Client::query()->orderBy('company_name')->get() as $client)
                                <option value="{{ $client->id }}" {{ (string) old('client_id', $shipment->client_id ?? '') === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->company_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="master-field">
                        <label class="master-label">Mapped Project</label>
                        <select name="project_id" class="master-select">
                            <option value="">No project mapping</option>
                            @foreach(\App\Models\Project::query()->orderBy('project_number')->get() as $project)
                                <option value="{{ $project->id }}" {{ (string) old('project_id', $shipment->project_id ?? '') === (string) $project->id ? 'selected' : '' }}>
                                    {{ $project->project_number }} - {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <label class="master-chip green-chip"><label class="master-label">Show in Client Portal</label>
                        <input type="checkbox" name="show_client_portal" value="1" {{ old('show_client_portal', $shipment->show_client_portal ?? false) ? 'checked' : '' }}>
                        <span><i class="fa-solid fa-eye"></i> Show</span>
                    </label>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">From / Shipper Details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">Name</label><input class="master-input" name="from_name"
                            value="{{ old('from_name', $shipment->from_name) }}" placeholder="Shipper Name"></div>
                    <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email"
                            name="from_email" value="{{ old('from_email', $shipment->from_email) }}" placeholder="Shipper Email"></div>
                    <div class="master-field"><label class="master-label">Mobile</label><input class="master-input"
                            name="from_mobile" value="{{ old('from_mobile', $shipment->from_mobile) }}" placeholder="Shipper Mobile"></div>
                    <div class="master-field full"><label class="master-label">Address</label><input class="master-input"
                            name="from_address" value="{{ old('from_address', $shipment->from_address) }}" placeholder="Shipper Address"></div>
                    <div class="master-field"><label class="master-label">City</label><input class="master-input" name="from_city"
                            value="{{ old('from_city', $shipment->from_city) }}" placeholder="City"></div>
                    <div class="master-field"><label class="master-label">State</label><input class="master-input"
                            name="from_state" value="{{ old('from_state', $shipment->from_state) }}" placeholder="State"></div>
                    <div class="master-field"><label class="master-label">Country</label><input class="master-input"
                            name="from_country" value="{{ old('from_country', $shipment->from_country) }}" placeholder="Country"></div>
                    <div class="master-field"><label class="master-label">Pincode</label><input class="master-input"
                            name="from_pincode" value="{{ old('from_pincode', $shipment->from_pincode) }}" placeholder="Pincode"></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">To / Receiver Details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">Name</label><input class="master-input" name="to_name"
                            value="{{ old('to_name', $shipment->to_name) }}" placeholder="Receiver Name"></div>
                    <div class="master-field"><label class="master-label">Email</label><input class="master-input" type="email"
                            name="to_email" value="{{ old('to_email', $shipment->to_email) }}" placeholder="Receiver Email"></div>
                    <div class="master-field"><label class="master-label">Mobile</label><input class="master-input"
                            name="to_mobile" value="{{ old('to_mobile', $shipment->to_mobile) }}" placeholder="Receiver Mobile"></div>
                    <div class="master-field full"><label class="master-label">Address</label><input class="master-input"
                            name="to_address" value="{{ old('to_address', $shipment->to_address) }}" placeholder="Receiver Address"></div>
                    <div class="master-field"><label class="master-label">City</label><input class="master-input" name="to_city"
                            value="{{ old('to_city', $shipment->to_city) }}" placeholder="City"></div>
                    <div class="master-field"><label class="master-label">State</label><input class="master-input" name="to_state"
                            value="{{ old('to_state', $shipment->to_state) }}" placeholder="State"></div>
                    <div class="master-field"><label class="master-label">Country</label><input class="master-input"
                            name="to_country" value="{{ old('to_country', $shipment->to_country) }}" placeholder="Country"></div>
                    <div class="master-field"><label class="master-label">Pincode</label><input class="master-input"
                            name="to_pincode" value="{{ old('to_pincode', $shipment->to_pincode) }}" placeholder="Pincode"></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">Logistic / Import / Cost Details</h3>
                <div class="master-detail-grid">
                    <div class="master-field"><label class="master-label">Logistic Partner</label><input class="master-input"
                            name="logistic_partner" value="{{ old('logistic_partner', $shipment->logistic_partner) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Tracking Number</label><input class="master-input"
                            name="tracking_number" value="{{ old('tracking_number', $shipment->tracking_number) }}"></div>
                    <div class="master-field desktop-only"><label class="master-label">Bill of Entry Number</label><input class="master-input"
                            name="bill_of_entry_number"
                            value="{{ old('bill_of_entry_number', $shipment->bill_of_entry_number) }}"
                            placeholder="For import shipments"></div>
                    <div class="master-field desktop-only"><label class="master-label">Origin Port</label><input class="master-input"
                            name="origin_port" value="{{ old('origin_port', $shipment->origin_port) }}"></div>
                    <div class="master-field desktop-only"><label class="master-label">Destination Port</label><input class="master-input"
                            name="destination_port" value="{{ old('destination_port', $shipment->destination_port) }}">
                    </div>
                    <div class="master-field"><label class="master-label">Package Count</label><input class="master-input"
                            type="number" min="0" name="package_count"
                            value="{{ old('package_count', $shipment->package_count) }}"></div>
                    <div class="master-field"><label class="master-label">Shipment Cost</label><input class="master-input"
                            type="number" min="0" step="0.01" name="shipment_cost"
                            value="{{ old('shipment_cost', $shipment->shipment_cost) }}"></div>
                    <div class="master-field"><label class="master-label">Currency</label><select class="master-select"
                            name="currency">@foreach($currencyOptions as $key => $label)<option value="{{ $key }}"
                                @selected(old('currency', $shipment->currency) === $key)>{{ $label }}</option>
                            @endforeach</select></div>
                    <div class="master-field"><label class="master-label">Cost Borne By</label><select class="master-select"
                            name="cost_borne_by">@foreach($costBorneByOptions as $key => $label)<option value="{{ $key }}"
                                @selected(old('cost_borne_by', $shipment->cost_borne_by) === $key)>{{ $label }}</option>
                            @endforeach</select></div>
                    <div class="master-field desktop-only"><label class="master-label">Gross Weight</label><input class="master-input"
                            type="number" min="0" step="0.001" name="gross_weight"
                            value="{{ old('gross_weight', $shipment->gross_weight) }}"></div>
                    <div class="master-field desktop-only"><label class="master-label">Chargeable Weight</label><input class="master-input"
                            type="number" min="0" step="0.001" name="chargeable_weight"
                            value="{{ old('chargeable_weight', $shipment->chargeable_weight) }}"></div>
                    <div class="master-field desktop-only full"><label class="master-label">Notes</label><textarea class="master-textarea"
                            name="notes">{{ old('notes', $shipment->notes) }}</textarea></div>
                </div>
            </div>

            <div class="master-section">
                <h3 class="master-section-title">Shipment Data / Products</h3>
                <div class="master-items-wrap">
                    <table class="master-items-table" id="shipmentItemsTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>HS Code</th>
                                <th>Qty</th>
                                <th class=" desktop-only">Unit</th>
                                <th>Amount</th>
                                <th>Currency</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                                <tr>
                                    <td><input class="master-input" name="items[{{ $index }}][product_name]"
                                            value="{{ $item['product_name'] ?? '' }}" placeholder="Product">
                                        <input class="master-input" name="items[{{ $index }}][sku]"
                                            value="{{ $item['sku'] ?? '' }}" hidden>
                                    </td>
                                    <td><input class="master-input" name="items[{{ $index }}][hs_code]"
                                            value="{{ $item['hs_code'] ?? '' }}"></td>
                                    <td><input class="master-input" type="number" step="0.001" min="0"
                                            name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}"></td>
                                    <td class="desktop-only"><input class="master-input" name="items[{{ $index }}][unit]"
                                            value="{{ $item['unit'] ?? 'pcs' }}"></td>
                                    <td><input class="master-input" type="number" step="0.01" min="0"
                                            name="items[{{ $index }}][declared_value]"
                                            value="{{ $item['declared_value'] ?? '' }}"></td>
                                    <td><select class="master-select"
                                            name="items[{{ $index }}][currency]">@foreach($currencyOptions as $key => $label)
                                            <option value="{{ $key }}" @selected(($item['currency'] ?? $shipment->currency ?? 'INR') === $key)>{{ $label }}</option>@endforeach</select>
                                        <input class="master-input" type="number" step="0.001" min="0"
                                            name="items[{{ $index }}][net_weight]" value="{{ $item['net_weight'] ?? '' }}"
                                            hidden>
                                    </td>
                                    <td><button type="button" class="master-remove-row"
                                            onclick="removeShipmentItemRow(this)">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="master-btn master-btn-soft master-add-row" id="addShipmentItemRow">+ Add Product
                    Row</button>
            </div>
            
            <div class="master-section">
                <h3 class="master-section-title">Shipment Photo Attachments</h3>
                <div class="master-photo-upload-box">
                    <div class="master-photo-upload-grid">
                
                        <div>
                            <label class="master-label">Upload Shipment Photos</label>
                
                            <input
                                class="master-input"
                                type="file"
                                name="attachment_photos[]"
                                accept="
                                    image/*,
                                    .jpg,.jpeg,.png,.webp,.gif,.heic,.heif,
                                    .pdf,
                                    .doc,.docx,
                                    .xls,.xlsx,
                                    .csv,
                                    .ppt,.pptx,
                                    .txt
                                "
                                multiple>
                
                            <div class="master-photo-help">
                                Upload shipment photos such as package photos, dispatch photos,
                                delivery proof, custom hold photos etc. Allowed JPG, PNG, WEBP
                                or GIF. If document then it should be PDF, DOC, Excel file. Max 8MB each.
                            </div>
                        </div>
                
                        <div>
                            <label class="master-label">Public Tracking Link</label>
                            <div class="master-photo-public">
                                <label class="ship-public-toggle">
                                    <input type="checkbox" name="is_public" value="1">
                    
                                    <span class="ship-public-switch"></span>
                    
                                    <strong style="font-weight:600;">Visible</strong>
                                </label>
                            </div>
                        </div>
                
                    </div>
                </div>
                
                @if($shipment->attachments->count())
                    <div class="master-existing-attachments">
                        <label class="master-label">Existing Attachments</label>
                    
                        <div class="master-attachment-grid">
                            @foreach($shipment->attachments as $attachment)
                                @php
                                    $isImage = Str::startsWith($attachment->mime_type, 'image/');
                                @endphp
                    
                                <div class="master-attachment-card" data-attachment="{{ $attachment->id }}">
                                    <div class="master-attachment-preview">
                    
                                        @if($isImage)
                                            <a href="{{ asset('storage/'.$attachment->file_path) }}" target="_blank">
                                                <img src="{{ asset('storage/'.$attachment->file_path) }}">
                                            </a>
                                        @else
                                            <div class="master-file-icon">
                                                📄
                                            </div>
                                        @endif
                    
                                    </div>
                    
                                    <div class="master-attachment-info">
                                        <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                    
                                        <small>
                                            {{ number_format($attachment->file_size / 1024,1) }} KB
                                        </small>
                                        
                                        <label class="ship-public-toggle">
                                            <input
                                                type="checkbox"
                                                value="1"
                                                @checked($attachment->is_public)
                                                onchange="toggleAttachmentPublic({{ $attachment->id }}, this)">
                                            <span class="ship-public-switch"></span>
                                        
                                            <strong style="font-weight:600;">
                                                Public photo
                                                <small>Visible on public tracking link</small>
                                            </strong>
                                        </label>
                                    </div>
                    
                                    <div class="master-attachment-actions">
                    
                                        <button
                                            type="button"
                                            class="master-btn master-btn-danger master-btn-sm"
                                            onclick="removeAttachment({{ $attachment->id }}, this)">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
            </div>

            <div class="master-actions">
                <a href="{{ $isEdit ? route('shipments.show', $shipment) : route('shipments.index') }}"
                    class="master-btn master-btn-light">Cancel</a>
                <button type="submit"
                    class="master-btn master-btn-primary">{{ $isEdit ? 'Update Shipment' : 'Create Shipment' }}</button>
            </div>
        </form>
    @if($isEdit)
        <div class="master-card master-history-card">
            <h3 class="master-section-title">Shipment Tracking History Management</h3>
            <p class="master-history-note" style="margin-bottom:16px;">Add, edit or delete shipment tracking stages from this edit page. The shipment current status is automatically synced with the latest tracking history entry.</p>

            <div class="master-history-row" style="background:#fff;">
                <div class="master-history-row-head">
                    <div class="master-history-row-title">Add New Tracking Stage</div>
                </div>
                <form method="POST" action="{{ route('shipments.history.store', $shipment) }}" class="master-history-form">
                    @csrf
                    <div class="master-history-grid">
                        <div><label class="master-label">Status</label><select class="master-select" name="status">@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" @selected($shipment->status === $key)>{{ $label }}</option>@endforeach</select></div>
                        <div><label class="master-label">Location</label><input class="master-input" name="location" placeholder="Current location"></div>
                        <div><label class="master-label">Event Time</label><input class="master-input" type="datetime-local" name="event_time" value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                        <div class="master-field two"><label class="master-label">Remarks</label><input class="master-input" name="remarks" placeholder="Tracking remarks"></div>
                        <div class="master-field"><label class="master-label">Public Tracking Link</label><label class="master-public-check"><input type="checkbox" name="is_public" value="1" checked> Visible</label></div>
                    </div>
                    <div class="master-history-actions"><button class="master-btn master-btn-primary" type="submit">Add Tracking Stage</button></div>
                </form>
            </div>

            @forelse($shipment->histories as $history)
                <div class="master-history-row">
                    <div class="master-history-row-head">
                        <div>
                            <div class="master-history-row-title">{{ $statusOptions[$history->status] ?? ucfirst($history->status) }}</div>
                            <div class="master-history-note">Created {{ $history->created_at ? $history->created_at->format('d M Y, h:i A') : '-' }} @if($history->creator) by {{ $history->creator->name ?? $history->creator->email }} @endif</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('shipments.history.update', $history) }}" class="master-history-form">
                        @csrf
                        @method('PUT')
                        <div class="master-history-grid">
                            <div><label class="master-label">Status</label><select class="master-select" name="status">@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" @selected($history->status === $key)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="master-label">Location</label><input class="master-input" name="location" value="{{ $history->location }}"></div>
                            <div><label class="master-label">Event Time</label><input class="master-input" type="datetime-local" name="event_time" value="{{ $history->event_time ? $history->event_time->format('Y-m-d\TH:i') : '' }}"></div>
                            <div class="master-field two"><label class="master-label">Remarks</label><input class="master-input" name="remarks" value="{{ $history->remarks }}"></div>
                            <div class="master-field"><label class="master-label">Public Tracking Link</label><label class="master-public-check"><input type="checkbox" name="is_public" value="1" @checked($history->is_public)> Visible</label></div>
                        </div>
                        <div class="master-history-footer">
                            <button class="master-btn master-btn-primary" type="submit">
                                Update History
                            </button>
                    </form>
                
                            <form method="POST"
                                  action="{{ route('shipments.history.destroy', $history) }}"
                                  onsubmit="return confirm('Delete this tracking history entry?')">
                                @csrf
                                @method('DELETE')
                
                                <button class="master-btn master-btn-danger" type="submit">
                                    Delete History
                                </button>
                            </form>
                
                        </div>
                </div>
            @empty
                <div class="master-history-row"><div class="master-history-note">No tracking history found. Add the first tracking stage above.</div></div>
            @endforelse
        </div>
    @endif

</div>

<template id="shipmentItemRowTemplate">
    <tr>
        <td><input class="master-input" name="items[__INDEX__][product_name]" placeholder="Product"></td>
        <td><input class="master-input" name="items[__INDEX__][hs_code]"></td>
        <td><input class="master-input" type="number" step="0.001" min="0" name="items[__INDEX__][quantity]" value="1"></td>
        <td><input class="master-input" name="items[__INDEX__][unit]" value="pcs"></td>
        <td><input class="master-input" type="number" step="0.01" min="0" name="items[__INDEX__][declared_value]"></td>
        <td><select class="master-select" name="items[__INDEX__][currency]">@foreach($currencyOptions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></td>
        <td><button type="button" class="master-remove-row" onclick="removeShipmentItemRow(this)">×</button></td>
    </tr>
</template>

<script>
    let shipmentItemIndex = {{ count($items) }};
    document.getElementById('addShipmentItemRow')?.addEventListener('click', function () {
        const template = document.getElementById('shipmentItemRowTemplate').innerHTML.replaceAll('__INDEX__', shipmentItemIndex++);
        document.querySelector('#shipmentItemsTable tbody').insertAdjacentHTML('beforeend', template);
    });
    function removeShipmentItemRow(button) {
        const tbody = document.querySelector('#shipmentItemsTable tbody');
        if (tbody.children.length <= 1) return;
        button.closest('tr').remove();
    }
    function removeAttachment(id, btn) {

        if (!confirm('Remove this attachment?')) {
            return;
        }
    
        fetch(`/shipments/attachments/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(async response => {
    
            console.log("Status:", response.status);
    
            const text = await response.text();
            console.log(text);
    
            const card = btn.closest('.master-attachment-card');
            console.log(card);
    
            if(card){
                card.remove();
            }
        })
        .catch(err => console.error(err));
    }
    function toggleAttachmentPublic(id, checkbox) {

        fetch(`/shipments/attachments/${id}/toggle-public`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                is_public: checkbox.checked
            })
        })
        .then(res => res.json())
        .then(data => {
    
            if (!data.success) {
                checkbox.checked = !checkbox.checked;
                alert('Unable to update attachment.');
            }
    
        })
        .catch(() => {
            checkbox.checked = !checkbox.checked;
            alert('Something went wrong.');
        });
    
    }
</script>
@endsection
