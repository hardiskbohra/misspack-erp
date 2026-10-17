<article class="pl-carton" data-carton>
    <div class="pl-carton-head"><div><span class="pl-carton-number" data-carton-number>Carton group {{ is_numeric($cartonIndex) ? $cartonIndex+1 : '' }}</span><strong data-carton-summary>Enter dimensions and weights</strong></div><button type="button" class="master-icon-btn is-danger" data-remove-carton aria-label="Remove carton group"><i class="fa-solid fa-trash"></i></button></div>
    <div class="pl-carton-fields">
        <div class="master-field"><label class="master-label">Label</label><input class="master-input" name="cartons[{{ $cartonIndex }}][carton_label]" value="{{ $carton['carton_label'] ?? '' }}" placeholder="e.g. 100ml Bottle Set"></div>
        <div class="master-field"><label class="master-label">Carton count <span class="master-required">*</span></label><input class="master-input" type="number" min="1" step="1" name="cartons[{{ $cartonIndex }}][carton_count]" value="{{ $carton['carton_count'] ?? 1 }}" data-carton-count required></div>
        <div class="master-field"><label class="master-label">Length (cm) <span class="master-required">*</span></label><input class="master-input" type="number" min="0" step="0.01" name="cartons[{{ $cartonIndex }}][length_cm]" value="{{ $carton['length_cm'] ?? '' }}" data-length required></div>
        <div class="master-field"><label class="master-label">Width (cm) <span class="master-required">*</span></label><input class="master-input" type="number" min="0" step="0.01" name="cartons[{{ $cartonIndex }}][width_cm]" value="{{ $carton['width_cm'] ?? '' }}" data-width required></div>
        <div class="master-field"><label class="master-label">Height (cm) <span class="master-required">*</span></label><input class="master-input" type="number" min="0" step="0.01" name="cartons[{{ $cartonIndex }}][height_cm]" value="{{ $carton['height_cm'] ?? '' }}" data-height required></div>
        <div class="master-field"><label class="master-label">NW / carton (kg) <span class="master-required">*</span></label><input class="master-input" type="number" min="0" step="0.001" name="cartons[{{ $cartonIndex }}][net_weight_per_carton]" value="{{ $carton['net_weight_per_carton'] ?? '' }}" data-net required></div>
        <div class="master-field"><label class="master-label">GW / carton (kg) <span class="master-required">*</span></label><input class="master-input" type="number" min="0" step="0.001" name="cartons[{{ $cartonIndex }}][gross_weight_per_carton]" value="{{ $carton['gross_weight_per_carton'] ?? '' }}" data-gross required></div>
        <div class="pl-carton-cbm"><span>CBM / carton</span><strong data-cbm>0.0000</strong></div>
    </div>
    <div class="pl-items-head"><strong>Items in this carton group</strong><button type="button" class="master-btn master-btn-light master-btn-sm" data-add-item><i class="fa-solid fa-plus"></i> Add item</button></div>
    <div class="pl-item-list" data-item-list>
        @foreach(($carton['items'] ?? []) as $itemIndex=>$item)
            @include('packaging_lists.partials.item-editor',compact('cartonIndex','itemIndex','item','products'))
        @endforeach
    </div>
</article>
