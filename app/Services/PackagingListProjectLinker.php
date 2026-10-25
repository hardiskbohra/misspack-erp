<?php

namespace App\Services;

use App\Models\PackagingList;
use App\Models\PackagingListItem;
use App\Models\ProjectProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PackagingListProjectLinker
{
    /**
     * Attach unlinked packing rows to a project product only when the identity is unambiguous.
     * Existing explicit links are never changed.
     */
    public function reconcile(PackagingList $list): array
    {
        if (! $list->project_id) return ['linked' => 0, 'unresolved' => 0];

        $products = ProjectProduct::query()
            ->where('project_id', $list->project_id)
            ->where('status', '!=', 'cancelled')
            ->get(['id', 'product_id', 'product_name', 'sku']);
        $list->loadMissing('cartons.items');

        $linked = 0;
        $unresolved = 0;
        foreach ($list->cartons->flatMap->items->whereNull('project_product_id') as $item) {
            $match = $this->unambiguousMatch($item, $products);
            if (! $match) {
                $unresolved++;
                continue;
            }

            $item->update(['project_product_id' => $match->id]);
            $linked++;
        }

        return compact('linked', 'unresolved');
    }

    private function unambiguousMatch(PackagingListItem $item, Collection $products): ?ProjectProduct
    {
        $candidates = collect();
        if ($item->product_id) {
            $candidates = $products->where('product_id', $item->product_id);
            return $candidates->count() === 1 ? $candidates->first() : null;
        }

        $sku = $this->normalise($item->sku);
        if ($sku !== '') {
            $candidates = $products->filter(fn ($product) => $this->normalise($product->sku) === $sku);
            return $candidates->count() === 1 ? $candidates->first() : null;
        }

        $description = $this->normalise($item->item_description);
        if ($description !== '') {
            $candidates = $products->filter(fn ($product) => $this->normalise($product->product_name) === $description);
            if ($candidates->count() === 1) return $candidates->first();
        }

        // A project with one active product is the only safe fallback for a free-text manual row.
        return $products->count() === 1 ? $products->first() : null;
    }

    private function normalise(mixed $value): string
    {
        return Str::of((string) $value)->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish()->toString();
    }
}
