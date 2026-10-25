<?php

namespace App\Services;

use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\Shipment;

/** Read-only sender details for a shareable shipment document. */
class ShipmentPrintIssuer
{
    /** @return array{name: string, address: string, mobile: string, email: string, logo_url: ?string} */
    public function forShipment(Shipment $shipment, Organisation $organisation): array
    {
        $name = trim((string) $shipment->from_name);
        // Reuse the packaging documents' authoritative sender-name policy.
        // Unlike a packing list, an unnamed shipment never implies our company.
        $ours = $name !== '' && (new PackagingList(['from_name' => $name]))->issuedByUs($organisation);
        $address = $this->address($shipment, 'from');
        if ($ours && $address === '' && ($organisation->exists || $organisation->relationLoaded('addresses'))) {
            $office = $organisation->defaultAddress('shipping') ?? $organisation->defaultAddress('billing');
            $address = $office?->oneLine() ?? '';
        }

        return [
            'name' => $name,
            'address' => $address,
            'mobile' => trim((string) ($shipment->from_mobile ?: ($ours ? $organisation->mobile : ''))),
            'email' => trim((string) ($shipment->from_email ?: ($ours ? $organisation->email : ''))),
            'logo_url' => $ours ? $organisation->logoUrl('print') : null,
        ];
    }

    /** Keep the shipment snapshot; do not repeat city/PIN already written in its free text. */
    public function address(Shipment $shipment, string $party): string
    {
        $address = trim((string) $shipment->{$party.'_address'});
        $fold = fn (string $value) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value));
        foreach (['city', 'state', 'pincode', 'country'] as $field) {
            $part = trim((string) $shipment->{$party.'_'.$field});
            if ($part !== '' && ! str_contains($fold($address), $fold($part))) {
                $address = $address === '' ? $part : $address.', '.$part;
            }
        }

        return $address;
    }
}
