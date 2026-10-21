<?php

namespace Tests\Unit;

use App\Models\PackagingList;
use PHPUnit\Framework\TestCase;

class PackagingPrintColumnsTest extends TestCase
{
    public function test_legacy_and_new_lists_keep_the_eleven_column_default(): void
    {
        $list = new PackagingList;
        $this->assertCount(12, PackagingList::printColumnOptions());
        $this->assertCount(11, $list->selectedPrintColumns());
        $this->assertNotContains('cbm', $list->selectedPrintColumns());
        $this->assertEqualsWithDelta(100, array_sum($list->printColumnWidths()), 0.000001);
    }

    public function test_columns_are_list_specific_canonical_deduplicated_and_cast_to_json(): void
    {
        $first = new PackagingList(['print_columns' => ['total_cbm', 'item', 'cbm', 'item', 'unknown']]);
        $second = new PackagingList(['print_columns' => ['size']]);
        $this->assertSame(['item', 'cbm', 'total_cbm'], $first->selectedPrintColumns());
        $this->assertSame(['size'], $second->selectedPrintColumns());
        $this->assertEqualsWithDelta(100, $second->printColumnWidths()['size'], 0.000001);
        $copy = new PackagingList;
        $copy->setRawAttributes($first->getAttributes());
        $this->assertSame($first->selectedPrintColumns(), $copy->selectedPrintColumns());
    }

    public function test_every_nonempty_subset_has_matching_widths_totaling_one_hundred_percent(): void
    {
        $keys = array_keys(PackagingList::printColumnOptions());
        for ($mask = 1; $mask < (1 << count($keys)); $mask++) {
            $selected = array_values(array_filter($keys, fn ($key, $index) => (bool) ($mask & (1 << $index)), ARRAY_FILTER_USE_BOTH));
            $list = new PackagingList(['print_columns' => $selected]);
            $this->assertSame($selected, $list->selectedPrintColumns());
            $this->assertSame($selected, array_keys($list->printColumnWidths()));
            $this->assertEqualsWithDelta(100, array_sum($list->printColumnWidths()), 0.000001);
        }
    }

    public function test_empty_or_obsolete_stored_settings_fall_back_safely(): void
    {
        foreach ([null, [], ['obsolete']] as $stored) {
            $list = new PackagingList(['print_columns' => $stored]);
            $this->assertSame(PackagingList::defaultPrintColumns(), $list->selectedPrintColumns());
        }
    }
}
