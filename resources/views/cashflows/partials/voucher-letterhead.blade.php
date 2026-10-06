@php
    $org = $org ?? \App\Models\Organisation::current();
    $bill = $org->defaultAddress('billing');
    $contact = $org->relationLoaded('contacts')
        ? $org->contacts->firstWhere('is_primary', true) ?: $org->contacts->first()
        : null;
    $location = collect([$bill?->city, $bill?->state, $bill?->pincode])->filter()->implode(' · ');
    $phones = collect([$org->mobile, $contact?->mobile])->filter()->unique()->implode(' · ');
    $mail = $org->email ?: $contact?->email;
@endphp
<div class="voucher-letterhead">
    <img class="voucher-logo" src="{{ $org->logoUrl('print') }}" alt="{{ $org->legal_name }}">
    <div class="voucher-letterhead-copy">
        <p class="voucher-brand">{{ $org->legal_name }}</p>
        @if ($org->tagline)
            <p class="voucher-sub">{{ $org->tagline }}</p>
        @endif
        @if ($bill)
            <p class="voucher-legal">{{ $bill->oneLine() }}</p>
        @elseif ($location)
            <p class="voucher-legal">{{ $location }}</p>
        @endif
        <p class="voucher-legal">
            @if ($org->gstin) GSTIN {{ $org->gstin }} @endif
            @if ($org->pan) · PAN {{ $org->pan }} @endif
        </p>
        <p class="voucher-legal">
            {{ collect([$mail, $phones, $org->website])->filter()->implode(' · ') }}
        </p>
    </div>
</div>
