<?php

namespace Tests\Unit;

use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * A product packed in several carton sizes is one product.
 *
 * A packaging list stores carton groups, because a carton is what has a size
 * and a weight. But the office receives 10 cartons of 336, 2 of 340 and 3 of 300
 * of the same bottle, and reads that as 15 cartons and 4,940 pieces — one line
 * per product, with one weight and one CBM. Read carton by carton, the product
 * appears three times and nothing adds it up.
 *
 * `productGroups()` is that sum. These cases are built in memory: the grouping
 * is arithmetic over the rows already stored, so it can be proved without a
 * database.
 */
class PackagingProductGroupsTest extends TestCase
{
    public function test_one_product_in_three_carton_sizes_is_one_group(): void
    {
        $list = $this->list([
            $this->carton(10, 336),
            $this->carton(2, 340),
            $this->carton(3, 300),
        ]);

        $groups = $list->productGroups();

        $this->assertCount(1, $groups, 'the three carton sizes are the same product');

        $group = $groups->first();

        $this->assertSame('50ml Spray Bottle', $group['label']);
        $this->assertCount(3, $group['rows'], 'each carton size keeps its own row');
        $this->assertSame(15, $group['cartons']);
        $this->assertEqualsWithDelta(4940.0, $group['quantity'], 0.001);
        $this->assertEqualsWithDelta(103.5, $group['net'], 0.001);
        $this->assertEqualsWithDelta(110.4, $group['gross'], 0.001);
        $this->assertEqualsWithDelta(0.9945, $group['cbm'], 0.0001);
    }

    public function test_each_row_carries_its_own_carton_figures(): void
    {
        $list = $this->list([$this->carton(10, 336), $this->carton(2, 340)]);
        $rows = $list->productGroups()->first()['rows'];

        $this->assertSame(10, $rows[0]['cartons']);
        $this->assertEqualsWithDelta(69.0, $rows[0]['net'], 0.001);
        $this->assertSame(2, $rows[1]['cartons']);
        $this->assertEqualsWithDelta(13.8, $rows[1]['net'], 0.001);
    }

    public function test_different_products_stay_apart(): void
    {
        $list = $this->list([
            $this->carton(10, 336, '50ml Spray Bottle'),
            $this->carton(3, 1000, 'Silicone Ring'),
        ]);

        $groups = $list->productGroups();

        $this->assertCount(2, $groups);
        $this->assertSame(['50ml Spray Bottle', 'Silicone Ring'], $groups->pluck('label')->all());
        $this->assertSame([10, 3], $groups->pluck('cartons')->all());
    }

    public function test_a_product_linked_to_the_same_record_groups_even_when_the_description_is_retyped(): void
    {
        /* The same product typed twice with different casing and spacing is
           one product because the link says so — a list generated from an
           invoice carries that link, and the office should not have to type the
           name identically in every carton size. */
        $list = $this->list([
            $this->carton(4, 250, '30ml  Spray Bottle', productId: 91),
            $this->carton(6, 250, '30ml spray bottle', productId: 91),
        ]);

        $groups = $list->productGroups();

        $this->assertCount(1, $groups);
        $this->assertSame(10, $groups->first()['cartons']);
    }

    public function test_a_carton_shared_by_two_products_counts_its_weight_once(): void
    {
        /* A mixed carton is listed under both products so nothing packed in it
           is invisible, but its weight and CBM belong to the carton, not to
           either product — so neither group claims them, and the list totals
           (which the screen prints) still carry them once. */
        $shared = $this->carton(5, 100, 'Mixed carton', null, [
            $this->item('50ml Spray Bottle', 100, 5),
            $this->item('30ml Spray Bottle', 100, 5),
        ]);

        $list = $this->list([$shared]);

        $groups = $list->productGroups();

        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            $this->assertSame(0, $group['cartons'], 'a mixed carton is not one product\'s carton');
            $this->assertSame(1, $group['shared_cartons']);
            $this->assertTrue($group['rows'][0]['shared']);
            $this->assertEqualsWithDelta(0.0, $group['net'], 0.001);
            $this->assertEqualsWithDelta(500.0, $group['quantity'], 0.001, 'the pieces are still the product\'s');
        }
    }

    public function test_the_list_still_carries_every_packed_unit(): void
    {
        $list = $this->list([$this->carton(10, 336), $this->carton(2, 340), $this->carton(3, 300)]);

        $this->assertEqualsWithDelta(4940.0, $list->totalQuantity(), 0.001);
    }

    private function list(array $cartons): PackagingList
    {
        $list = new PackagingList(['packing_number' => 'MP/PL/26-27/001']);
        $list->setRelation('cartons', new Collection($cartons));

        return $list;
    }

    /** One carton configuration: 50 × 34 × 39 cm, 6.90 kg net, 7.36 kg gross. */
    private function carton(int $count, float $perCarton, string $description = '50ml Spray Bottle', ?int $productId = null, ?array $items = null): PackagingListCarton
    {
        $carton = new PackagingListCarton([
            'carton_count' => $count,
            'carton_number' => '1',
            'length_cm' => 50,
            'width_cm' => 34,
            'height_cm' => 39,
            'net_weight_per_carton' => 6.90,
            'gross_weight_per_carton' => 7.36,
        ]);

        $carton->setRelation('items', new Collection(
            $items ?? [$this->item($description, $perCarton, $count, $productId)]
        ));

        return $carton;
    }

    private function item(string $description, float $perCarton, int $cartons = 1, ?int $productId = null): PackagingListItem
    {
        return new PackagingListItem([
            'item_description' => $description,
            'product_id' => $productId,
            'quantity_per_carton' => $perCarton,
            'total_quantity' => $perCarton * $cartons,
            'unit' => 'pcs',
            'previously_packed_quantity' => 0,
        ]);
    }
}
