@php
    /*
     | Public tracking stepper — Booked → Picked up → In transit → Customs
     | clearance → Out for delivery → Delivered.
     |
     | Used by the admin detail page, the client portal and the public tracker
     | so a client and a coordinator describe the same journey with the same
     | words. Expects: $shipment (with histories loaded for "delayed" to place
     | itself correctly).
     */
    $trackStage = $shipment->trackingStage();
    $trackStages = \App\Models\Shipment::trackingStages();
    $showDelayReason = $showDelayReason ?? true;
@endphp

@if ($trackStage === null)
    <div class="ship-track-cancelled">
        This shipment was cancelled{{ $showDelayReason && $shipment->delay_reason ? ' — '.$shipment->delay_reason : '' }}.
    </div>
@else
    <ol class="ship-track">
        @foreach ($trackStages as $status => $label)
            @php($trackIndex = $loop->index)
            <li class="{{ $trackIndex < $trackStage ? 'is-done' : ($trackIndex === $trackStage ? 'is-current' : '') }}">
                {{ $label }}
                @if ($trackIndex === $trackStage && $shipment->status === \App\Models\Shipment::STATUS_DELAYED)
                    <span class="ship-track-note">Delayed{{ $showDelayReason && $shipment->delay_reason ? ' · '.$shipment->delay_reason : '' }}</span>
                @endif
            </li>
        @endforeach
    </ol>
@endif
