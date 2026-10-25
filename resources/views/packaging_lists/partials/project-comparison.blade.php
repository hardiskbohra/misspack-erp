@if($list->project_id && $comparison)
<section class="master-card master-card--flat master-section pl-product-comparison" id="projectProductComparison">
    <div class="master-section-head">
        <div>
            <h2 class="master-section-title">Project product comparison</h2>
            <p class="master-sub">Actual packing lines alongside their linked project products. Different descriptions are highlighted for review, not treated as missing links.</p>
        </div>
        <span class="pl-verification-status is-{{ $comparison['unresolved'] ? 'failed' : ($comparison['review'] ? 'warning' : 'passed') }}">
            {{ $comparison['unresolved'] ? $comparison['unresolved'].' need mapping' : ($comparison['review'] ? $comparison['review'].' to review' : 'All linked') }}
        </span>
    </div>
    @if($comparison['unresolved'])
        <p class="master-sub">Review the highlighted rows below and choose the correct Project product in Edit. You can keep saving drafts; confirmation requires valid links.</p>
    @endif
    <p class="master-sub pl-comparison-scroll-hint">Scroll the comparison sideways to see the check and next action.</p>
    <div class="master-table-wrap">
        <table class="master-table pl-comparison-table">
            <thead><tr><th scope="col">Packing list product</th><th scope="col">Linked project product</th><th scope="col">Check / next action</th></tr></thead>
            <tbody>
                @foreach($comparison['rows'] as $row)
                    <tr class="pl-comparison-row is-{{ $row['status'] }}">
                        <td>
                            <span class="master-sub">{{ $row['carton_label'] }} · Line {{ $row['item_index'] + 1 }}</span>
                            <strong>{{ $row['packing_description'] }}</strong>
                            <span>Catalog: {{ $row['packing_product'] ?: 'Custom item' }} · SKU: {{ $row['packing_sku'] ?: 'Not set' }}</span>
                            <span>{{ number_format($row['packing_quantity'], 3) }} {{ $row['packing_unit'] }} packed</span>
                        </td>
                        <td>
                            <strong>{{ $row['project_product'] ?: 'No project product linked' }}</strong>
                            @if($row['project_product_id'])<span>Project product #{{ $row['project_product_id'] }} · SKU: {{ $row['project_sku'] ?: 'Not set' }}</span>@endif
                            @if($row['project_quantity'] !== null)<span>{{ number_format($row['project_quantity'], 3) }} {{ $row['project_unit'] }} ordered</span>@endif
                        </td>
                        <td>
                            <strong class="pl-comparison-status is-{{ $row['status'] }}">{{ $row['label'] }}</strong>
                            <span>{{ $row['message'] }}</span>
                            @if(auth()->user()?->hasPermission('operations.manage'))
                                <a href="{{ route('packaging-lists.edit', $list) }}#plProjectProduct{{ $row['carton_index'] }}x{{ $row['item_index'] }}">{{ $row['status'] === 'pass' ? 'Review mapping' : 'Review / correct mapping' }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <details class="pl-project-products-details" @if($comparison['unresolved']) open @endif>
        <summary>Actual products in this project ({{ count($comparison['products']) }})</summary>
        <div class="pl-project-products">
            @forelse($comparison['products'] as $product)
                <div>
                    <strong>{{ $product['product_name'] }} · #{{ $product['id'] }}</strong>
                    <span>SKU: {{ $product['sku'] ?: 'Not set' }} · {{ number_format((float)$product['quantity'], 3) }} {{ $product['unit'] }}</span>
                    @if($product['status'] === 'cancelled')<span class="pl-comparison-status is-fail">Cancelled — not available for packing</span>@endif
                </div>
            @empty
                <p class="master-sub">This project has no products yet. Add its products before mapping the packing lines.</p>
            @endforelse
        </div>
    </details>
</section>
@endif
