<?php

namespace Tests\Unit;

use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The sheet is read in sections, and a carton can hold two products.
 *
 * Two rules the printed document is built on, and the two that are easy to get
 * wrong:
 *
 *   - **a section is a heading, not a product.** "50ml Empty Spray Bottle" sits
 *     over a row of bottles *and* a row of caps, because that is what was packed
 *     together. The heading is typed on the carton block; blocks that name the
 *     same heading print under one band, in the order they were entered.
 *   - **a carton can hold more than one item.** A 60 × 40 × 40 box carrying 600
 *     bottles and 5,000 hooks is *one* carton with *one* weight, and its 14.40 kg
 *     is not divided between them. That is why the sheet prints the carton's
 *     columns once and spans them across its item lines.
 *
 * The arithmetic is over rows already stored, so it is proved here without a
 * database.
 */
class PackagingSectionsTest extends TestCase
{
    private int $cartonId = 0;
    public function test_blocks_sharing_a_heading_form_one_section_in_entry_order(): void
    {
        $list = $this->list([
            $this->carton(15, ['50ml Spray Bottle' => 336], '50ml Empty Spray Bottle'),
            $this->carton(13, ['50ml Spray Cap' => 392], '50ml Empty Spray Bottle'),
            $this->carton(1, ['Silicone Ring' => 5000], 'Silicone Ring Case', length: 42, width: 40, height: 40, net: 13.10, gross: 13.33),
        ]);

        $sections = $list->sections();

        $this->assertCount(2, $sections);
        $this->assertSame('50ml Empty Spray Bottle', $sections[0]['label']);
        $this->assertCount(2, $sections[0]['cartons'], 'the bottle and the cap print under one heading');
        $this->assertSame(28, $sections[0]['cartons_count'], '15 + 13 cartons');
        $this->assertSame('Silicone Ring Case', $sections[1]['label']);
        $this->assertSame(1, $sections[1]['cartons_count']);
    }

    public function test_a_block_with_no_heading_is_titled_by_its_label_then_its_first_item(): void
    {
        $list = $this->list([
            $this->carton(2, ['30ml Bottle' => 2200], null, length: 61, width: 49, height: 51),
            $this->carton(1, ['30ml Cap' => 5000], null, cartonLabel: 'Caps only'),
        ]);

        $sections = $list->sections();

        $this->assertCount(2, $sections, 'unheaded blocks never merge into one another');
        $this->assertSame('30ml Bottle', $sections[0]['label']);
        $this->assertSame('Caps only', $sections[1]['label']);
    }

    public function test_a_carton_holding_two_products_is_one_block_with_two_lines(): void
    {
        /* The 30ml Bottle / 30ml Hook block: one 60 × 40 × 40 carton, 14.40 kg,
           carrying 600 bottles and 5,000 hooks. */
        $block = $this->carton(1, ['30ml Bottle' => 600, '30ml Hook' => 5000], '30ml Empty Squeeze Bottle', length: 60, width: 40, height: 40, net: 14.40, gross: 15.00);

        $list = $this->list([$block]);
        $section = $list->sections()->first();

        $this->assertSame(1, $section['cartons_count'], 'the carton is counted once, not once per item');
        $this->assertCount(1, $section['cartons']);
        $this->assertCount(2, $section['cartons'][0]->items, 'two lines share the carton');
        $this->assertEqualsWithDelta(5600.0, $section['quantity'], 0.001, 'both products are in the carton');
        $this->assertEqualsWithDelta(14.40, $section['net'], 0.001, 'the carton weighs what it weighs');
        $this->assertEqualsWithDelta(15.00, $section['gross'], 0.001);
        $this->assertEqualsWithDelta(0.096, $section['cbm'], 0.0001);
    }

    public function test_the_sections_add_up_to_the_document_totals(): void
    {
        /* The sheet's own foot: 33 cartons, 236.15 kg net, 261.63 kg gross,
           2.42 CBM. */
        $list = $this->list([
            $this->carton(15, ['50ml Spray Bottle' => 336], '50ml Empty Spray Bottle', net: 6.90, gross: 7.36),
            $this->carton(13, ['50ml Spray Cap' => 392], '50ml Empty Spray Bottle', net: 5.20, gross: 6.30),
            $this->carton(1, ['Silicone Ring' => 5000], 'Silicone Ring Case', length: 42, width: 40, height: 40, net: 13.10, gross: 13.33),
            $this->carton(2, ['30ml Bottle' => 2200], '30ml Empty Squeeze Bottle', length: 61, width: 49, height: 51, net: 11.50, gross: 13.00),
            $this->carton(1, ['30ml Cap' => 5000], '30ml Empty Squeeze Bottle', length: 60, width: 40, height: 40, net: 14.55, gross: 15.00),
            $this->carton(1, ['30ml Bottle' => 600, '30ml Hook' => 5000], '30ml Empty Squeeze Bottle', length: 60, width: 40, height: 40, net: 14.40, gross: 15.00),
        ]);

        $sections = $list->sections();

        $this->assertSame(33, $sections->sum('cartons_count'));
        $this->assertEqualsWithDelta(236.15, $sections->sum('net'), 0.001);
        $this->assertEqualsWithDelta(261.63, $sections->sum('gross'), 0.001);
        $this->assertEqualsWithDelta(2.42, $sections->sum('cbm'), 0.01);

        $this->assertSame(3, $sections->count(), 'three headings on the sheet');
        $this->assertEqualsWithDelta(30136.0, $list->totalQuantity(), 0.001, 'every packed unit, both products in the shared carton');
    }

    public function test_a_section_can_carry_more_than_one_carton_size(): void
    {
        /* The first sheet this was built from: one bottle, three carton sizes,
           all under one heading. */
        $list = $this->list([
            $this->carton(10, ['50ml Spray Bottle' => 336], '50ml Spray Bottle'),
            $this->carton(2, ['50ml Spray Bottle' => 340], '50ml Spray Bottle'),
            $this->carton(3, ['50ml Spray Bottle' => 300], '50ml Spray Bottle'),
        ]);

        $section = $list->sections()->first();

        $this->assertCount(1, $list->sections());
        $this->assertCount(3, $section['cartons'], 'each carton size keeps its own block');
        $this->assertSame(15, $section['cartons_count']);
        $this->assertEqualsWithDelta(4940.0, $section['quantity'], 0.001);
    }

    private function list(array $cartons): PackagingList
    {
        $list = new PackagingList(['packing_number' => 'MP/PL/26-27/001']);
        $list->setRelation('cartons', new Collection($cartons));

        return $list;
    }

    /** One carton block: a count, its items, and the carton's own figures. */
    private function carton(
        int $count,
        array $items,
        ?string $group = null,
        ?string $cartonLabel = null,
        float $length = 50,
        float $width = 34,
        float $height = 39,
        float $net = 6.90,
        float $gross = 7.36
    ): PackagingListCarton {
        $carton = new PackagingListCarton([
            'carton_count' => $count,
            'carton_number' => '1',
            'packing_group' => $group,
            'carton_label' => $cartonLabel,
            'length_cm' => $length,
            'width_cm' => $width,
            'height_cm' => $height,
            'net_weight_per_carton' => $net,
            'gross_weight_per_carton' => $gross,
        ]);

        $lines = [];
        foreach ($items as $description => $perCarton) {
            $lines[] = new PackagingListItem([
                'item_description' => $description,
                'quantity_per_carton' => $perCarton,
                'total_quantity' => $perCarton * $count,
                'unit' => 'pcs',
                'previously_packed_quantity' => 0,
            ]);
        }

        // Real persisted carton blocks have distinct identities even without a heading.
        $carton->id = ++$this->cartonId;
        $carton->setRelation('items', new Collection($lines));

        return $carton;
    }
}
