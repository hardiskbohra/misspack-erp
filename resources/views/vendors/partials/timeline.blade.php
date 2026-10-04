@php
    /**
     * What has happened lately, in one list: bills, payments, project
     * mappings, shipments, documents and comments. Asking "what changed?"
     * should not mean opening four tabs and comparing dates by eye.
     */
@endphp

@if (! empty($vendorActivity))
    <ol class="vendor-timeline">
        @foreach ($vendorActivity as $event)
            {{-- Each entry is a way back into the tab it came from: a trail you
                 cannot follow is a caption, not a trail. --}}
            <li class="vendor-timeline-row">
                <a class="vendor-timeline-item" href="{{ $event['url'] }}">
                    <span class="vendor-timeline-icon" aria-hidden="true"><i class="{{ $event['icon'] }}"></i></span>
                    <div class="vendor-timeline-copy">
                        <strong>{{ $event['title'] }}</strong>
                        <span class="master-sub">{{ $event['meta'] }}</span>
                    </div>
                    <span class="vendor-timeline-time">{{ $event['at']->diffForHumans(short: true) }}</span>
                </a>
            </li>
        @endforeach
    </ol>
@else
    <p class="vendor-empty-text">Nothing has happened yet. Bills, payments, documents and comments all appear here as they are added.</p>
@endif
