<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use Illuminate\Support\Collection;

/**
 * The paperwork side of a shipment.
 *
 * Indian export/import consignments are held up by missing paper, not by
 * missing trucks: a shipping bill without a packing list, an import without a
 * bill of entry. This service knows which document types a shipment should
 * carry (by type and status), which ones are on file, and which are missing —
 * so the checklist on the shipment screen and the "docs pending" filter on the
 * list always agree.
 */
class ShipmentDocuments
{
    public const TYPES = [
        'packing_list' => 'Packing List',
        'commercial_invoice' => 'Commercial Invoice',
        'shipping_bill' => 'Shipping Bill',
        'bill_of_entry' => 'Bill of Entry',
        'bill_of_lading' => 'Bill of Lading',
        'awb' => 'Air Waybill (AWB)',
        'certificate_of_origin' => 'Certificate of Origin',
        'insurance' => 'Insurance Certificate',
        'eway_bill' => 'e-Way Bill',
        'cha_checklist' => 'CHA Checklist',
        'delivery_challan' => 'Delivery Challan',
        'pod' => 'Proof of Delivery (POD)',
        'other' => 'Other Document',
    ];

    public function available(): bool
    {
        return class_exists(ShipmentAttachment::class);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::TYPES;
    }

    public static function label(?string $type): ?string
    {
        return $type ? (self::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type))) : null;
    }

    /**
     * Document types this shipment is expected to carry.
     *
     * @return array<int, string>
     */
    public function requiredFor(Shipment $shipment): array
    {
        $required = match ($shipment->shipment_type) {
            Shipment::TYPE_EXPORT => ['packing_list', 'commercial_invoice', 'shipping_bill'],
            Shipment::TYPE_IMPORT => ['packing_list', 'commercial_invoice', 'bill_of_entry'],
            default => ['delivery_challan'],
        };

        // Proof of delivery is only expected once the shipment says delivered.
        if ($shipment->status === Shipment::STATUS_DELIVERED) {
            $required[] = 'pod';
        }

        return $required;
    }

    /**
     * Per-type rows for the checklist card.
     *
     * @return Collection<int, array{key: string, label: string, required: bool, done: bool, attachments: Collection}>
     */
    public function checklist(Shipment $shipment): Collection
    {
        $attachments = $shipment->relationLoaded('attachments')
            ? $shipment->attachments
            : $shipment->attachments()->get();

        $required = $this->requiredFor($shipment);

        $grouped = $attachments
            ->filter(fn (ShipmentAttachment $attachment) => (string) $attachment->document_type !== '')
            ->groupBy('document_type');

        return collect(self::TYPES)->map(function (string $label, string $key) use ($grouped, $required) {
            $files = $grouped->get($key, collect());

            return [
                'key' => $key,
                'label' => $label,
                'required' => in_array($key, $required, true),
                'done' => $files->isNotEmpty(),
                'attachments' => $files->values(),
            ];
        })->values();
    }

    /**
     * Required document labels that have nothing on file.
     *
     * @return array<int, string>
     */
    public function missing(Shipment $shipment): array
    {
        return $this->checklist($shipment)
            ->filter(fn (array $row) => $row['required'] && ! $row['done'])
            ->pluck('label')
            ->values()
            ->all();
    }

    /**
     * Compact state for lists and print headers.
     *
     * @return array{required: int, done: int, missing: array<int, string>, complete: bool}
     */
    public function summary(Shipment $shipment): array
    {
        $checklist = $this->checklist($shipment);
        $required = $checklist->where('required', true);
        $done = $required->where('done', true);

        return [
            'required' => $required->count(),
            'done' => $done->count(),
            'missing' => $required->where('done', false)->pluck('label')->values()->all(),
            'complete' => $required->where('done', false)->isEmpty(),
        ];
    }
}
