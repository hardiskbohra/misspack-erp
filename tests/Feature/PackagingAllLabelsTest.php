<?php

namespace Tests\Feature;

use App\Http\Controllers\PackagingListController;
use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use App\Services\PackagingListLabels;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PackagingAllLabelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Brand fixture: rendering must not depend on an installed organisation database.
        view()->share('labelQrUrl', '');
        view()->share('organisation', new \App\Models\Organisation([
            'legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack',
        ]));
    }

    public function test_all_formats_render_each_physical_carton_with_correct_data_and_audit_counts(): void
    {
        foreach (['a4', 'a5', 'thermal', 'thermal150'] as $format) {
            $list = new PackagingList(['packing_number' => 'MP/PL/26-27/013', 'total_cartons' => 99, 'transport_mode' => 'air']);
            $list->id = 13;
            $cartons = [];
            foreach ([2, 1] as $index => $count) {
                $carton = \Mockery::mock(PackagingListCarton::class)->makePartial();
                $carton->fill(['carton_count' => $count, 'carton_type' => 'box', 'barcode' => 'BAR-'.$index,
                    'packing_group' => 'Group '.$index, 'length_cm' => 40, 'width_cm' => 30, 'height_cm' => 20]);
                $carton->id = $index + 1;
                $carton->setRelation('items', new Collection([
                    new PackagingListItem(['item_description' => 'Product '.$index, 'quantity_per_carton' => 10, 'unit' => 'pcs']),
                ]));
                $relation = \Mockery::mock();
                $relation->shouldReceive('create')->once()->with(\Mockery::on(fn ($row) =>
                    $row['copies'] === $count && $row['label_format'] === $format && $row['printed_by'] === 42 && $row['reason'] === 'Warehouse run'
                ));
                $carton->shouldReceive('labelPrints')->once()->andReturn($relation);
                $cartons[] = $carton;
            }
            $list->setRelation('cartons', new Collection($cartons));
            Auth::shouldReceive('id')->andReturn(42);
            DB::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback());
            $view = (new PackagingListController)->labels($list, Request::create('/', 'POST', [
                'format' => $format, 'reason' => 'Warehouse run', 'copies' => 1,
            ]), new PackagingListLabels);
            $html = $view->render();
            $this->assertSame(3, substr_count($html, '<main class="carton-label">'));
            foreach ([1, 2, 3] as $number) $this->assertStringContainsString('<strong>'.$number.'/3</strong>', $html);
            $this->assertSame(2, substr_count($html, '<strong>Product 0</strong>'));
            $this->assertSame(1, substr_count($html, '<strong>Product 1</strong>'));
            $this->assertStringContainsString('format-'.$format, $html);
            $this->assertStringContainsString('data-auto-print="true"', $html);
            $this->assertStringContainsString('Save as PDF', $html);
            $paper = ['a4' => 'A4 landscape', 'a5' => 'A5 landscape', 'thermal' => '100mm 140mm', 'thermal150' => '100mm 150mm'][$format];
            $this->assertStringContainsString('@page{size:'.$paper, $html);
            $this->assertSame(3, substr_count($html, 'data-ship-qr="'));
        }
    }

    public function test_single_group_accepts_and_audits_the_additional_150_mm_format(): void
    {
        $list = new PackagingList(['packing_number' => 'PL-150', 'total_cartons' => 2, 'from_name' => 'MissPack']);
        $list->id = 13;
        $carton = \Mockery::mock(PackagingListCarton::class)->makePartial();
        $carton->fill(['carton_count' => 2, 'carton_type' => 'box', 'barcode' => 'BAR-150']);
        $carton->id = 7;
        $carton->setRelation('packagingList', $list);
        $carton->setRelation('items', new Collection([
            new PackagingListItem(['item_description' => 'Bottle', 'quantity_per_carton' => 10, 'unit' => 'pcs']),
        ]));
        $list->setRelation('cartons', new Collection([$carton]));
        $carton->shouldReceive('load')->once()->andReturnSelf();
        $prints = \Mockery::mock();
        $prints->shouldReceive('create')->once()->with(\Mockery::on(fn ($row) =>
            $row['label_format'] === 'thermal150' && $row['copies'] === 2 && $row['printed_by'] === 42
        ));
        $carton->shouldReceive('labelPrints')->once()->andReturn($prints);
        Auth::shouldReceive('id')->andReturn(42);

        $view = (new PackagingListController)->label($carton, Request::create('/', 'POST', [
            'format' => 'thermal150', 'copies' => 2,
        ]));
        $html = $view->render();
        $this->assertStringContainsString('@page{size:100mm 150mm;margin:0}', $html);
        $this->assertStringContainsString('100 × 150 mm', $html);
        $this->assertSame(2, substr_count($html, 'data-ship-qr="'));
        $this->assertStringContainsString('data-auto-print="false"', $html);
    }

    public function test_an_unknown_format_is_rejected_before_loading_or_auditing_any_cartons(): void
    {
        DB::shouldReceive('transaction')->never();
        $this->expectException(ValidationException::class);
        (new PackagingListController)->labels(new PackagingList, Request::create('/', 'POST', ['format' => 'a3']), new PackagingListLabels);
    }
}
