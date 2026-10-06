@php
    $total = array_sum(array_map('intval', $items ?: []));
@endphp
@if($total > 0)
    @foreach($items as $label => $count)
        @php($percent = $total > 0 ? round(((int) $count / $total) * 100, 1) : 0)
        <div class="dash-status-row">
            <strong>{{ $label }}</strong>
            <span>{{ $count }} · {{ $percent }}%</span>
            <div class="dash-status-bar"><i style="width: {{ $percent }}%"></i></div>
        </div>
    @endforeach
@else
    <div class="dash-empty small">No status data available.</div>
@endif
