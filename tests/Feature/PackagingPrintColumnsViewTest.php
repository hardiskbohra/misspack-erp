<?php

namespace Tests\Feature;

use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PackagingPrintColumnsViewTest extends TestCase
{
    public function test_invalid_column_selections_are_rejected_on_save(): void
    {
        $method = new \ReflectionMethod(\App\Http\Controllers\PackagingListController::class, 'validated');
        $method->setAccessible(true);
        foreach (['', [], ['unknown'], ['cbm', 'cbm'], 'cbm'] as $selection) {
            $request = \Illuminate\Http\Request::create('/packaging-lists', 'POST', ['print_columns' => $selection]);
            try {
                $method->invoke(new \App\Http\Controllers\PackagingListController, $request);
                $this->fail('Invalid print columns must not be saved.');
            } catch (\Illuminate\Validation\ValidationException $error) {
                $keys = array_keys($error->errors());
                $this->assertNotEmpty(array_filter($keys, fn ($key) => $key === 'print_columns' || str_starts_with($key, 'print_columns.')));
            }
        }
    }

    public function test_print_headers_cells_spans_and_totals_follow_the_saved_selection(): void
    {
        foreach ([null, array_keys(PackagingList::printColumnOptions()), ['item', 'cartons', 'cbm', 'total_cbm'], ['cbm'], ['item']] as $selection) {
            $carton = new PackagingListCarton([
                'packing_group' => 'Mixed packing', 'carton_count' => 2, 'length_cm' => 50, 'width_cm' => 40, 'height_cm' => 30,
                'net_weight_per_carton' => 10, 'gross_weight_per_carton' => 12,
            ]);
            $carton->setRelation('items', new Collection([
                new PackagingListItem(['item_description' => 'Bottle', 'quantity_per_carton' => 10, 'total_quantity' => 20, 'unit' => 'pcs']),
                new PackagingListItem(['item_description' => 'Cap', 'quantity_per_carton' => 10, 'total_quantity' => 20, 'unit' => 'pcs']),
            ]));
            $list = new PackagingList([
                'packing_number' => 'MP/PL/26-27/001', 'status' => 'draft', 'transport_mode' => 'air',
                'print_columns' => $selection, 'total_cartons' => 2, 'total_cbm' => 0.12,
                'total_net_weight' => 20, 'total_gross_weight' => 24,
            ]);
            $list->id = 1;
            $list->setRelation('cartons', new Collection([$carton]));
            $list->setRelation('shipment', null);
            $list->setRelation('project', null);
            $html = view('packaging_lists.print', [
                'list' => $list, 'sections' => $list->sections(), 'onLetterhead' => false, 'errors' => new \Illuminate\Support\ViewErrorBag,
                'organisation' => null, 'assetVer' => fn ($path) => asset($path),
            ])->render();

            preg_match('/<table class="pl-items">(.*?)<\/table>/s', $html, $table);
            preg_match('/<thead>(.*?)<\/thead>/s', $table[1], $head);
            $columns = $list->selectedPrintColumns();
            $this->assertSame(count($columns), preg_match_all('/<th\b/', $head[1]));
            $this->assertSame(count($columns), preg_match_all('/<col\s/', $table[1]));
            $this->assertStringContainsString('colspan="'.count($columns).'"', $table[1]);
            foreach (PackagingList::printColumnOptions() as $key => $label) {
                if (in_array($key, $columns, true)) {
                    $this->assertStringContainsString(e($label), $head[1]);
                } else {
                    $this->assertStringNotContainsString(e($label), $head[1]);
                }
            }
            if (in_array('cbm', $columns, true)) {
                $this->assertStringContainsString('rowspan="2">0.0600</td>', $table[1]);
            }
            if (in_array('total_cbm', $columns, true)) {
                $this->assertStringContainsString('rowspan="2">0.1200</td>', $table[1]);
            }
            if (in_array('item', $columns, true)) {
                $this->assertStringContainsString('Bottle', $table[1]);
                $this->assertStringContainsString('Cap', $table[1]);
            } else {
                $this->assertStringNotContainsString('Bottle', $table[1]);
                $this->assertStringNotContainsString('Cap', $table[1]);
            }
        }
    }
}
