<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\ProjectProduct;
use Illuminate\Support\Str;

/** Read-only evidence: show actual identities; never infer or replace a user's link. */
class PackagingListProjectComparison
{
    public function inspect(PackagingList $list): array
    {
        if (! $list->project_id) return ['rows' => [], 'products' => [], 'unresolved' => 0, 'review' => 0];
        $list->loadMissing('cartons.items.product');
        $items = $list->cartons->flatMap->items;
        $products = ProjectProduct::where(function ($query) use ($list, $items) {
            $query->where('project_id', $list->project_id)->orWhereIn('id', $items->pluck('project_product_id')->filter());
        })->orderBy('sort_order')->orderBy('id')->get(['id', 'project_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit', 'status']);
        $byId = $products->keyBy('id');
        $rows = [];
        foreach ($list->cartons->values() as $cartonIndex => $carton) {
            foreach ($carton->items->values() as $itemIndex => $item) {
                $linked = $byId->get($item->project_product_id);
                $state = 'pass'; $label = 'Matched'; $message = 'Linked to an active product in this project.';
                if (! $linked) {
                    $state = 'fail'; $label = 'Not linked';
                    $message = 'Choose the project product this packing line belongs to.';
                } elseif ($linked->project_id != $list->project_id) {
                    $state = 'fail'; $label = 'Wrong project';
                    $message = 'This product belongs to another project. Select a product from this list’s project.';
                } elseif ($linked->status === 'cancelled') {
                    $state = 'fail'; $label = 'Cancelled product';
                    $message = 'The linked project product is cancelled. Review the project or select an active product.';
                } elseif (($item->product_id && $linked->product_id && $item->product_id != $linked->product_id)
                    || $this->normalise($item->item_description) !== $this->normalise($linked->product_name)
                    || ($item->sku && $linked->sku && $this->normalise($item->sku) !== $this->normalise($linked->sku))
                    || $this->normalise($item->unit) !== $this->normalise($linked->unit)) {
                    $state = 'warning'; $label = 'Review difference';
                    $message = 'Description, catalog product, SKU or unit differs. The explicit project link is valid; review that the difference is intentional.';
                }
                $rows[] = [
                    'item_id' => $item->id, 'carton_index' => $cartonIndex, 'item_index' => $itemIndex,
                    'carton_label' => 'Carton group '.($cartonIndex + 1).($carton->carton_label ? ' · '.$carton->carton_label : ''),
                    'packing_description' => $item->item_description, 'packing_product' => $item->product?->name,
                    'packing_sku' => $item->sku, 'packing_quantity' => (float) $item->total_quantity, 'packing_unit' => $item->unit,
                    'project_product_id' => $item->project_product_id, 'project_product' => $linked?->product_name,
                    'project_sku' => $linked?->sku, 'project_quantity' => $linked ? (float) $linked->quantity : null,
                    'project_unit' => $linked?->unit, 'status' => $state, 'label' => $label, 'message' => $message,
                ];
            }
        }
        return [
            'rows' => $rows,
            'products' => $products->where('project_id', $list->project_id)->map(fn ($product) => $product->only([
                'id', 'product_name', 'sku', 'quantity', 'unit', 'status',
            ]))->values()->all(),
            'unresolved' => collect($rows)->where('status', 'fail')->count(),
            'review' => collect($rows)->where('status', 'warning')->count(),
        ];
    }

    private function normalise(?string $value): string
    {
        return Str::of($value ?? '')->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish()->toString();
    }
}
