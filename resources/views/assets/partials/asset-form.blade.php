@php
    /*
     * The register's own columns, in the order the office's spreadsheet had them
     * and the order the export prints them: what it is, what it cost, where it
     * lives, and the recipe it depreciates by.
     *
     * One partial, two dialogs. The list's dialog registers a new asset (nothing
     * prefilled but the next code); the record's dialog changes one, and its
     * fields come back filled — including after a validation failure, because
     * `old()` is read first everywhere and an empty `old()` falls through to the
     * asset.
     *
     * The three depreciation fields are blank by default **on purpose**: blank
     * means "follow the class", which is the answer for almost every asset. A
     * field that invented a life of its own would be a second, quieter recipe.
     */
    $dateValue = fn ($field) => old($field, $asset?->{$field}?->format('Y-m-d'));
    $textValue = fn ($field, $default = '') => old($field, $asset?->{$field} ?? $default);
@endphp

<div class="master-modal-grid">
    <div class="master-field">
        <label class="master-label" for="assetCode{{ $suffix }}">Asset ID
            <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="assetCode{{ $suffix }}" name="asset_code" required maxlength="40"
            value="{{ $textValue('asset_code', $asset ? '' : ($nextCode ?? '')) }}">
        <span class="master-help">The company's own number for it — the one an auditor will read off the sticker.</span>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetName{{ $suffix }}">Asset name
            <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="assetName{{ $suffix }}" name="name" required maxlength="160"
            value="{{ $textValue('name') }}" placeholder="e.g. CNC plasma cutting table">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetCategory{{ $suffix }}">Class</label>
        <select class="master-select" id="assetCategory{{ $suffix }}" name="category_id">
            <option value="">Unclassified</option>
            @foreach ($categoryOptions as $option)
                <option value="{{ $option->id }}" @selected((string) old('category_id', $asset?->category_id ?? '') === (string) $option->id)>
                    {{ $option->name }} — {{ $option->lifeLabel() }}
                </option>
            @endforeach
        </select>
        <span class="master-help">The class carries the life, method and residual value below.</span>
    </div>

    <div class="master-field full">
        <label class="master-label" for="assetDescription{{ $suffix }}">Description / specifications</label>
        <textarea class="master-input" id="assetDescription{{ $suffix }}" name="description" rows="2"
            maxlength="2000" placeholder="Capacity, size, what it does — the line an auditor reads first">{{ $textValue('description') }}</textarea>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetMake{{ $suffix }}">Make / brand</label>
        <input class="master-input" id="assetMake{{ $suffix }}" name="make" maxlength="120" value="{{ $textValue('make') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetModel{{ $suffix }}">Model</label>
        <input class="master-input" id="assetModel{{ $suffix }}" name="model" maxlength="120" value="{{ $textValue('model') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetSerial{{ $suffix }}">Serial / IMEI / identification no.</label>
        <input class="master-input" id="assetSerial{{ $suffix }}" name="serial_no" maxlength="120"
            value="{{ $textValue('serial_no') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetPurchasedOn{{ $suffix }}">Purchase date
            <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="assetPurchasedOn{{ $suffix }}" type="date" name="purchase_date" required
            value="{{ $dateValue('purchase_date') }}">
        <span class="master-help">Depreciation starts here — the date the asset was put to use.</span>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetVendor{{ $suffix }}">Supplier / vendor</label>
        <select class="master-select" id="assetVendor{{ $suffix }}" name="vendor_id">
            <option value="">Not a vendor on file</option>
            @foreach ($vendorOptions as $option)
                <option value="{{ $option->id }}" @selected((string) old('vendor_id', $asset?->vendor_id ?? '') === (string) $option->id)>
                    {{ $option->vendor_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetSupplier{{ $suffix }}">Supplier name (if not on file)</label>
        <input class="master-input" id="assetSupplier{{ $suffix }}" name="supplier_name" maxlength="160"
            value="{{ $textValue('supplier_name') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetInvoiceNo{{ $suffix }}">Invoice no.</label>
        <input class="master-input" id="assetInvoiceNo{{ $suffix }}" name="invoice_no" maxlength="80"
            value="{{ $textValue('invoice_no') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetInvoiceDate{{ $suffix }}">Invoice date</label>
        <input class="master-input" id="assetInvoiceDate{{ $suffix }}" type="date" name="invoice_date"
            value="{{ $dateValue('invoice_date') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetCost{{ $suffix }}">Purchase cost
            <span class="master-required" aria-hidden="true">*</span></label>
        <input class="master-input" id="assetCost{{ $suffix }}" type="number" step="0.01" min="0" name="cost"
            required value="{{ old('cost', $asset?->cost) }}" placeholder="0.00">
        <span class="master-help">The line on the invoice, before GST.</span>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetGst{{ $suffix }}">GST paid</label>
        <input class="master-input" id="assetGst{{ $suffix }}" type="number" step="0.01" min="0" name="gst_amount"
            value="{{ old('gst_amount', $asset?->gst_amount) }}" placeholder="0.00">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetGstTreatment{{ $suffix }}">GST treatment</label>
        @php
            $gstMode = old('depreciate_on_total', $asset?->depreciate_on_total ?? false) ? '1' : '0';
        @endphp
        <select class="master-select" id="assetGstTreatment{{ $suffix }}" name="depreciate_on_total">
            <option value="0" @selected($gstMode === '0')>Claim input credit — depreciate the cost alone</option>
            <option value="1" @selected($gstMode === '1')>Capitalise it — depreciate cost + GST</option>
        </select>
        <span class="master-help">Decides the basis every figure on the asset is computed from.</span>
    </div>
</div>

<div class="ast-form-divider" role="presentation"><span>Where it is</span></div>

<div class="master-modal-grid">
    <div class="master-field">
        <label class="master-label" for="assetLocation{{ $suffix }}">Location</label>
        <input class="master-input" id="assetLocation{{ $suffix }}" name="location" maxlength="120" list="faLocationList"
            value="{{ $textValue('location') }}" placeholder="e.g. Unit 2 — Vadodara">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetDepartment{{ $suffix }}">Department</label>
        <input class="master-input" id="assetDepartment{{ $suffix }}" name="department" maxlength="80"
            value="{{ $textValue('department') }}" placeholder="e.g. Fabrication">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetCustodian{{ $suffix }}">Custodian / employee</label>
        <select class="master-select" id="assetCustodian{{ $suffix }}" name="custodian_id">
            <option value="">Nobody in particular</option>
            @foreach ($peopleOptions as $person)
                <option value="{{ $person->id }}" @selected((string) old('custodian_id', $asset?->custodian_id ?? '') === (string) $person->id)>
                    {{ $person->name }}
                </option>
            @endforeach
        </select>
        <span class="master-help">Handing it over from the record keeps this in step — it is the same field.</span>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetStatus{{ $suffix }}">Status</label>
        <select class="master-select" id="assetStatus{{ $suffix }}" name="status" required>
            @foreach ($statusOptions as $key => $label)
                <option value="{{ $key }}" @selected(old('status', $asset?->status ?? 'in_use') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetWarranty{{ $suffix }}">Warranty end date</label>
        <input class="master-input" id="assetWarranty{{ $suffix }}" type="date" name="warranty_end_date"
            value="{{ $dateValue('warranty_end_date') }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetInsurance{{ $suffix }}">Insurance expiry</label>
        <input class="master-input" id="assetInsurance{{ $suffix }}" type="date" name="insurance_expiry"
            value="{{ $dateValue('insurance_expiry') }}">
    </div>

    <div class="master-field full">
        <label class="master-label" for="assetInsuranceDetails{{ $suffix }}">Insurance details</label>
        <textarea class="master-input" id="assetInsuranceDetails{{ $suffix }}" name="insurance_details" rows="2"
            maxlength="1000" placeholder="Policy number, insurer, sum insured, what it covers">{{ $textValue('insurance_details') }}</textarea>
    </div>
</div>

<div class="ast-form-divider" role="presentation"><span>How it depreciates</span></div>

<div class="master-modal-grid">
    <div class="master-field">
        <label class="master-label" for="assetLife{{ $suffix }}">Useful life (years)</label>
        <input class="master-input" id="assetLife{{ $suffix }}" type="number" min="1" max="100" name="useful_life_years"
            value="{{ old('useful_life_years', $asset?->useful_life_years) }}"
            placeholder="{{ $asset?->category?->useful_life_years ?? 'from the class' }}">
    </div>

    <div class="master-field">
        <label class="master-label" for="assetMethod{{ $suffix }}">Depreciation method</label>
        <select class="master-select" id="assetMethod{{ $suffix }}" name="depreciation_method">
            <option value="">From the class</option>
            @foreach ($methodOptions as $key => $label)
                <option value="{{ $key }}" @selected(old('depreciation_method', $asset?->depreciation_method) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="master-field">
        <label class="master-label" for="assetResidual{{ $suffix }}">Residual value (%)</label>
        <input class="master-input" id="assetResidual{{ $suffix }}" type="number" step="0.01" min="0" max="100"
            name="residual_percent" value="{{ old('residual_percent', $asset?->residual_percent) }}"
            placeholder="{{ $asset?->category?->residual_percent ?? \App\Services\AssetVocabulary::DEFAULT_RESIDUAL_PERCENT }}">
        <span class="master-help">The value left at the end of the life — Schedule II's 5% unless the class says otherwise.</span>
    </div>

    <div class="master-field full">
        <label class="master-label" for="assetRemarks{{ $suffix }}">Remarks</label>
        <textarea class="master-input" id="assetRemarks{{ $suffix }}" name="remarks" rows="2" maxlength="2000"
            placeholder="Anything the next person to pick this register up should know">{{ $textValue('remarks') }}</textarea>
    </div>
</div>

{{-- The office's own words for places, offered as suggestions rather than a
     fixed list: a location is typed once and then reused, and a locked select
     would fight the first asset ever registered in a new unit. --}}
<datalist id="faLocationList">
    @foreach ($locations ?? [] as $option)
        <option value="{{ $option }}"></option>
    @endforeach
</datalist>
