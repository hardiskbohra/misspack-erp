<?php

namespace App\Services;

use App\Models\PackagingList;
use Illuminate\Validation\ValidationException;

class PackagingListLabels
{
    // Bound the generated HTML and QR workload. Larger runs can still print per group.
    public const MAX_BATCH_LABELS = 10000;

    /** One entry per group, not an expanded array per physical carton. */
    public function batches(PackagingList $list): array
    {
        $list->loadMissing('cartons.items');
        $batches = [];
        $next = 1;
        foreach ($list->cartons as $carton) {
            $copies = (int) $carton->carton_count;
            if ($copies < 1) {
                throw ValidationException::withMessages(['format' => 'Every carton group must contain at least one carton before printing all labels.']);
            }
            if ($next - 1 + $copies > self::MAX_BATCH_LABELS) {
                throw ValidationException::withMessages(['format' => 'A single label run supports up to 10,000 cartons. Print larger runs by carton group.']);
            }
            $carton->setRelation('packagingList', $list);
            $batches[] = ['carton' => $carton, 'copies' => $copies, 'startCartonNumber' => $next];
            $next += $copies;
        }
        if ($batches === []) {
            throw ValidationException::withMessages(['format' => 'Add cartons before printing labels.']);
        }
        return $batches;
    }
}
