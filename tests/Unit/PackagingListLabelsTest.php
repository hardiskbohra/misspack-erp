<?php

namespace Tests\Unit;

use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use App\Services\PackagingListLabels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackagingListLabelsTest extends TestCase
{
    private function listWithCounts(array $counts): PackagingList
    {
        $list = new PackagingList(['packing_number' => 'MP/PL/26-27/013']);
        $cartons = [];
        foreach ($counts as $index => $count) {
            $carton = new PackagingListCarton(['carton_count' => $count, 'sort_order' => $index]);
            $carton->id = $index + 1;
            $carton->setRelation('items', new Collection([new PackagingListItem(['item_description' => 'Item '.$index])]));
            $cartons[] = $carton;
        }
        $list->setRelation('cartons', new Collection($cartons));
        return $list;
    }

    public function test_all_groups_are_numbered_continuously_without_expanding_the_batch_array(): void
    {
        $list = $this->listWithCounts([3, 1, 2]);
        $batches = (new PackagingListLabels)->batches($list);
        $this->assertCount(3, $batches);
        $this->assertSame([1, 4, 5], array_column($batches, 'startCartonNumber'));
        $this->assertSame([3, 1, 2], array_column($batches, 'copies'));
        $numbers = [];
        foreach ($batches as $batch) {
            $this->assertSame($list, $batch['carton']->packagingList);
            $this->assertTrue($batch['carton']->relationLoaded('items'));
            for ($copy = 0; $copy < $batch['copies']; $copy++) {
                $numbers[] = $batch['startCartonNumber'] + $copy;
            }
        }
        $this->assertSame([1, 2, 3, 4, 5, 6], $numbers);
    }

    public function test_empty_invalid_or_oversized_runs_are_rejected_before_a_partial_batch_can_print(): void
    {
        foreach ([[], [0], [-1], [1, PackagingListLabels::MAX_BATCH_LABELS]] as $counts) {
            try {
                (new PackagingListLabels)->batches($this->listWithCounts($counts));
                $this->fail('Invalid label batch must be refused.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('format', $exception->errors());
            }
        }
    }

    public function test_the_batch_limit_itself_is_inclusive(): void
    {
        $batches = (new PackagingListLabels)->batches($this->listWithCounts([PackagingListLabels::MAX_BATCH_LABELS]));
        $this->assertCount(1, $batches);
        $this->assertSame(PackagingListLabels::MAX_BATCH_LABELS, $batches[0]['copies']);
    }
}
