<?php

namespace App\Services;

use App\Models\PackagingList;
use Illuminate\Support\Carbon;

class PackagingListVerifier
{
    public const MANUAL_CHECKS = [
        'contents_match' => 'Contents match the packing instructions and source document',
        'quantities_counted' => 'Item quantities were physically counted',
        'cartons_intact' => 'Cartons are sealed, intact and suitable for dispatch',
        'weights_verified' => 'Net and gross weights were checked',
        'labels_applied' => 'Carton labels and handling marks are applied and readable',
    ];

    public function inspect(PackagingList $list): array
    {
        $list->loadMissing('cartons.items');
        $cartons = $list->cartons;
        $items = $cartons->flatMap->items;
        $checks = [];

        $checks[] = $this->check(
            'structure',
            'Cartons and items',
            $cartons->isNotEmpty() && $items->isNotEmpty() ? 'pass' : 'fail',
            $cartons->isNotEmpty() && $items->isNotEmpty()
                ? $cartons->count().' carton groups and '.$items->count().' item lines are ready.'
                : 'Add at least one carton group with one item.'
        );

        $invalidCartons = $cartons->filter(fn ($carton) =>
            (int) $carton->carton_count < 1
            || (float) $carton->length_cm <= 0
            || (float) $carton->width_cm <= 0
            || (float) $carton->height_cm <= 0
            || (float) $carton->net_weight_per_carton < 0
            || (float) $carton->gross_weight_per_carton < (float) $carton->net_weight_per_carton
        );
        $checks[] = $this->check(
            'carton_specs',
            'Carton dimensions and weights',
            $invalidCartons->isEmpty() ? 'pass' : 'fail',
            $invalidCartons->isEmpty()
                ? 'All carton groups have valid dimensions and gross weight is not below net weight.'
                : $invalidCartons->count().' carton group(s) have invalid dimensions, counts or weights.'
        );

        $invalidItems = $items->filter(fn ($item) =>
            trim((string) $item->item_description) === ''
            || (float) $item->quantity_per_carton <= 0
            || (float) $item->total_quantity <= 0
        );
        $checks[] = $this->check(
            'item_quantities',
            'Item descriptions and quantities',
            $invalidItems->isEmpty() ? 'pass' : 'fail',
            $invalidItems->isEmpty()
                ? 'Every item line has a description and positive quantity.'
                : $invalidItems->count().' item line(s) need a description or positive quantity.'
        );

        $serials = [];
        $duplicateSerials = [];
        $serialMismatches = 0;
        foreach ($items as $item) {
            $itemSerials = collect($item->serial_numbers ?? [])->map(fn ($value) => strtoupper(trim((string) $value)))->filter()->values();
            foreach ($itemSerials as $serial) {
                if (isset($serials[$serial])) $duplicateSerials[$serial] = true;
                $serials[$serial] = true;
            }
            if ($itemSerials->isNotEmpty() && abs((float) $item->total_quantity - $itemSerials->count()) > 0.0001) $serialMismatches++;
        }
        $serialStatus = $duplicateSerials ? 'fail' : ($serialMismatches > 0 ? 'warning' : 'pass');
        $serialMessage = $duplicateSerials
            ? count($duplicateSerials).' duplicate serial number(s) were found.'
            : ($serialMismatches > 0
                ? $serialMismatches.' serialized item line(s) do not have one serial per packed unit.'
                : ($serials ? count($serials).' serial numbers are unique and quantity-aligned.' : 'No serialized item lines require reconciliation.'));
        $checks[] = $this->check('serials', 'Serial-number reconciliation', $serialStatus, $serialMessage);

        $invalidDates = $items->filter(fn ($item) =>
            $item->manufactured_at && $item->expires_at && $item->expires_at->lt($item->manufactured_at)
        );
        $expiredItems = $items->filter(fn ($item) => $item->expires_at && $item->expires_at->lt(Carbon::today()));
        $traceStatus = $invalidDates->isNotEmpty() ? 'fail' : ($expiredItems->isNotEmpty() ? 'warning' : 'pass');
        $traceMessage = $invalidDates->isNotEmpty()
            ? $invalidDates->count().' item line(s) expire before their manufacturing date.'
            : ($expiredItems->isNotEmpty()
                ? $expiredItems->count().' packed item line(s) are past their expiry date.'
                : 'Recorded manufacturing and expiry dates are consistent.');
        $checks[] = $this->check('traceability', 'Batch and expiry consistency', $traceStatus, $traceMessage);

        $warnings = collect($list->packing_warnings ?? []);
        $checks[] = $this->check(
            'source_allocation',
            'Source quantity allocation',
            $warnings->isEmpty() ? 'pass' : 'warning',
            $warnings->isEmpty()
                ? 'Packed quantities do not exceed their linked source quantities.'
                : $warnings->count().' source line(s) are overpacked and require acknowledgement.'
        );

        $missingLabels = $cartons->filter(fn ($carton) => ! $carton->barcode || ! $carton->public_token);
        $checks[] = $this->check(
            'label_identity',
            'Carton label identity',
            $missingLabels->isEmpty() ? 'pass' : 'fail',
            $missingLabels->isEmpty()
                ? 'Every carton group has a barcode and secure label identity.'
                : $missingLabels->count().' carton group(s) are missing label identity.'
        );

        return [
            'checks' => $checks,
            'failed' => collect($checks)->where('status', 'fail')->count(),
            'warnings' => collect($checks)->where('status', 'warning')->count(),
            'passed' => collect($checks)->where('status', 'pass')->count(),
        ];
    }

    public function fingerprint(PackagingList $list): string
    {
        $list->loadMissing('cartons.items');
        $payload = [
            'list' => $list->only(['shipment_id', 'sales_invoice_id', 'purchase_invoice_id', 'total_cartons', 'total_net_weight', 'total_gross_weight', 'total_cbm', 'packing_warnings']),
            'cartons' => $list->cartons->map(fn ($carton) => [
                'attributes' => $carton->only(['carton_label', 'packing_mode', 'carton_number', 'carton_type', 'carton_count', 'length_cm', 'width_cm', 'height_cm', 'net_weight_per_carton', 'gross_weight_per_carton', 'barcode', 'public_token', 'shipping_mark', 'pallet_number', 'seal_number', 'is_fragile', 'keep_dry', 'this_side_up', 'is_hazardous', 'handling_instructions']),
                'items' => $carton->items->map(fn ($item) => $item->only(['item_description', 'sku', 'customer_item_code', 'hs_code', 'quantity_per_carton', 'total_quantity', 'serial_numbers', 'batch_number', 'manufactured_at', 'expires_at', 'country_of_origin', 'unit_net_weight', 'unit_gross_weight', 'packing_instructions']))->values()->all(),
            ])->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function check(string $key, string $label, string $status, string $message): array
    {
        return compact('key', 'label', 'status', 'message');
    }
}
