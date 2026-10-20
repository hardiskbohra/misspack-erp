@php
    $accepted = (float) $item->deliveryReceiptLines->sum('accepted_quantity');
    $returned = (float) $item->deliveryReceiptLines->sum('returned_quantity');
    $netAccepted = max($accepted - $returned, 0);
    $remaining = max((float) $item->quantity - $netAccepted, 0);
    $hasIssues = $remaining > 0
        && $item->deliveryReceiptLines->contains(fn ($line) => $line->hasDiscrepancy());
    $stateClass = $remaining <= 0 ? 'status-completed' : ($hasIssues ? 'status-delayed' : 'status-planning');
    $stateLabel = $remaining <= 0 ? 'Reconciled' : ($hasIssues ? 'Discrepancy open' : 'Awaiting receipt');
@endphp
<tr>
    <td><strong>{{ $item->product_name }}</strong></td>
    <td class="is-num">{{ number_format((float) $item->quantity, 3) }}</td>
    <td class="is-num">{{ number_format($netAccepted, 3) }}</td>
    <td class="is-num">{{ number_format($remaining, 3) }}</td>
    <td><span class="master-badge {{ $stateClass }}">{{ $stateLabel }}</span></td>
</tr>
