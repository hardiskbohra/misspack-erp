<?php

namespace App\Services;

use App\Models\PackagingList;
use Illuminate\Support\Carbon;

class PackagingListVerifier
{
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

        $comparison = app(PackagingListProjectComparison::class)->inspect($list);
        $unmatched = collect($comparison['rows'])->where('status', 'fail');
        $checks[] = $this->check(
            'project_identity', 'Project product identity',
            $comparison['unresolved'] > 0 ? 'fail' : ($comparison['review'] > 0 ? 'warning' : 'pass'),
            $comparison['unresolved'] > 0
                ? $comparison['unresolved'].' packing line(s) need attention: '.$unmatched->take(6)->pluck('packing_description')->implode(', ').'. Compare Packing list product and Linked project product in the Project product comparison, then correct the mapping in Edit. You can save a draft while reviewing.'
                : ($comparison['review'] > 0
                    ? $comparison['review'].' packing label(s) differ from the linked project product. Review the side-by-side comparison; valid explicit links do not block confirmation.'
                    : ($list->project_id ? 'Every item line is matched to an active product in this project.' : 'This list is not marked for a project.'))
        );
        $checks[array_key_last($checks)]['unmatched_item_ids'] = $unmatched->pluck('item_id')->all();

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
        $financialReference = $list->source_type === 'purchase_invoice' && $list->purchase_invoice_id
            && $list->purchaseInvoice?->isPartialConversion();
        $allocationMessage = $warnings->isEmpty() ? '' : $warnings->count().' source line(s) are overpacked and require acknowledgement. ';
        if ($financialReference) {
            $allocationMessage .= 'Linked purchase bill quantities are financial references, not a physical source balance. Compare actual physical quantities against receiving and packing records.';
        } elseif ($allocationMessage === '') {
            $allocationMessage = 'Packed quantities do not exceed their linked source quantities.';
        }
        $checks[] = $this->check(
            'source_allocation', 'Source quantity allocation',
            $warnings->isEmpty() && ! $financialReference ? 'pass' : 'warning',
            $allocationMessage
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
            'project_comparison' => $comparison,
            'failed' => collect($checks)->where('status', 'fail')->count(),
            'warnings' => collect($checks)->where('status', 'warning')->count(),
            'passed' => collect($checks)->where('status', 'pass')->count(),
        ];
    }

    public function fingerprint(PackagingList $list): string
    {
        $list->loadMissing('cartons.items');
        $payload = [
            'list' => $list->only(['project_id', 'shipment_id', 'sales_invoice_id', 'purchase_invoice_id', 'total_cartons', 'total_net_weight', 'total_gross_weight', 'total_cbm', 'packing_warnings']),
            'cartons' => $list->cartons->map(fn ($carton) => [
                'attributes' => $carton->only(['carton_label', 'packing_group', 'packing_mode', 'carton_number', 'carton_type', 'carton_count', 'length_cm', 'width_cm', 'height_cm', 'net_weight_per_carton', 'gross_weight_per_carton', 'barcode', 'public_token', 'shipping_mark', 'pallet_number', 'seal_number', 'is_fragile', 'keep_dry', 'this_side_up', 'is_hazardous', 'handling_instructions']),
                'items' => $carton->items->map(fn ($item) => $item->only(['project_product_id', 'item_description', 'sku', 'customer_item_code', 'hs_code', 'quantity_per_carton', 'total_quantity', 'serial_numbers', 'batch_number', 'manufactured_at', 'expires_at', 'country_of_origin', 'unit_net_weight', 'unit_gross_weight', 'packing_instructions']))->values()->all(),
            ])->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function check(string $key, string $label, string $status, string $message): array
    {
        return compact('key', 'label', 'status', 'message');
    }
}
