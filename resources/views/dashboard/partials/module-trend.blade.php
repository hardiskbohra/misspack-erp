@php($trendMaximum = max(1, collect($chart['series'])->max(fn ($point) => max((float) $point['value'], (float) ($point['secondary'] ?? 0)))))
<section class="module-dashboard-card module-trend-card" aria-labelledby="{{ $chartId }}">
            <header class="module-card-heading">
                <div><span>Selected period</span><h2 id="{{ $chartId }}">{{ $chart['title'] }}</h2></div>
                <span class="module-card-chip">{{ $period['label'] }}</span>
            </header>
            <div class="module-trend-legend">
                <span><i class="primary"></i>{{ $chart['primary_label'] }}</span>
                @if($chart['secondary_label'])<span><i class="secondary"></i>{{ $chart['secondary_label'] }}</span>@endif
            </div>
            <div class="module-trend-bars">
                @foreach($chart['series'] as $point)
                    <div class="module-trend-item">
                        <div class="module-trend-values">
                            <progress max="{{ $trendMaximum }}" value="{{ $point['value'] }}" aria-label="{{ $point['label'] }} {{ $chart['primary_label'] }}"></progress>
                            @if(array_key_exists('secondary', $point))
                                <progress class="secondary" max="{{ $trendMaximum }}" value="{{ $point['secondary'] }}" aria-label="{{ $point['label'] }} outward"></progress>
                            @endif
                        </div>
                        <span>{{ $point['label'] }}</span>
                        <strong>{{ $formatMetric($point['value'], $chart['format']) }}@if(array_key_exists('secondary', $point)) <small>/ {{ $formatMetric($point['secondary'], $chart['format']) }}</small>@endif</strong>
                    </div>
                @endforeach
            </div>
        </section>
