<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PackagingCartonLabelViewTest extends TestCase
{
    private function renderLabel(string $sender, string $format = 'thermal', ?int $vendorId = null, ?Organisation $organisation = null): string
    {
        $list = new PackagingList([
            'packing_number' => 'MP/PL/26-27/024', 'total_cartons' => 36,
            'packing_date' => '2026-10-22', 'transport_mode' => 'road',
            'from_name' => $sender, 'from_address' => '229 Block F, Shahibaug, Ahmedabad, Gujarat 380004',
            'ship_to_name' => 'GARGIE VARUN VIJ',
            'ship_to_address' => 'Sri Enterprises, Kempegowda, Bengaluru, Karnataka 560074',
            'vendor_id' => $vendorId,
        ]);
        $list->id = 24;
        $carton = new PackagingListCarton([
            'carton_type' => 'box', 'carton_count' => 36, 'barcode' => 'CTN-TEST-024',
            'packing_group' => '300ml Shampoo Bottle', 'shipping_mark' => 'CUSTOMER SHIPPING MARK',
            'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 42,
            'net_weight_per_carton' => 6.6, 'gross_weight_per_carton' => 6.6,
        ]);
        $carton->id = 1;
        $carton->setRelation('packagingList', $list);
        $carton->setRelation('items', new Collection([
            new PackagingListItem(['item_description' => '300ml Shampoo Bottle', 'quantity_per_carton' => 174,
                'unit' => 'pcs', 'hs_code' => '392330']),
        ]));

        return view('packaging_lists.label', [
            'labelQrUrl' => '', 'carton' => $carton, 'format' => $format, 'copies' => 1, 'startCartonNumber' => 1,
            'organisation' => $organisation ?? new Organisation(['legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack']),
        ])->render();
    }

    public function test_thermal_label_uses_100_by_140_mm_stock(): void
    {
        $html = $this->renderLabel('MissPack India Pvt Ltd');
        $this->assertStringContainsString('@page{size:100mm 140mm;margin:0}', $html);
        $this->assertStringContainsString('100 × 140 mm', $html);
        $this->assertStringNotContainsString('150mm', $html);
    }

    public function test_misspack_sender_is_the_heading_and_has_our_print_logo_in_every_format(): void
    {
        foreach (['a4', 'a5', 'thermal'] as $format) {
            $html = $this->renderLabel('MissPack India Pvt Ltd', $format);
            $this->assertStringContainsString('<h1>MissPack India Pvt Ltd</h1>', $html);
            $this->assertSame(1, substr_count($html, 'class="label-brand-logo"'));
            $this->assertStringContainsString('images/logo-dark.png', $html);
            $this->assertLessThan(strpos($html, '<h1>'), strpos($html, 'class="label-brand-logo"'));
            $this->assertStringNotContainsString('<h1>GARGIE VARUN VIJ</h1>', $html);
            $this->assertStringNotContainsString('CUSTOMER SHIPPING MARK</h1>', $html);
            $this->assertStringContainsString('<strong>GARGIE VARUN VIJ</strong>', $html);
            $this->assertStringContainsString('<strong>1/36</strong>', $html);
        }
    }

    public function test_vendor_sender_has_its_own_heading_and_never_our_logo(): void
    {
        foreach (['a4', 'a5', 'thermal'] as $format) {
            $html = $this->renderLabel('Solo Beauty Packaging Co., Ltd', $format, 3);
            $this->assertStringContainsString('<h1>Solo Beauty Packaging Co., Ltd</h1>', $html);
            $this->assertStringNotContainsString('class="label-brand-logo"', $html);
            $this->assertStringNotContainsString('images/logo-dark.png', $html);
        }
    }

    public function test_a_configured_print_logo_is_used_instead_of_the_screen_logo(): void
    {
        $organisation = new Organisation([
            'legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack',
            'logo_print_path' => 'logos/carton-print.png', 'logo_path' => 'logos/screen.png',
        ]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with('logos/carton-print.png')->andReturnTrue();
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $html = $this->renderLabel('MissPack India Pvt Ltd', 'thermal', null, $organisation);
        $this->assertStringContainsString('storage/logos/carton-print.png', $html);
        $this->assertStringNotContainsString('logos/screen.png', $html);
    }

    public function test_branding_follows_sender_not_the_presence_of_a_vendor_link(): void
    {
        $html = $this->renderLabel('MissPack India Pvt Ltd', 'thermal', 3);
        $this->assertStringContainsString('class="label-brand-logo"', $html);
        $html = $this->renderLabel('Solo Beauty Packaging Co., Ltd');
        $this->assertStringNotContainsString('class="label-brand-logo"', $html);
    }

    public function test_a4_and_a5_paper_sizes_are_unchanged(): void
    {
        foreach (['a4' => 'A4 landscape', 'a5' => 'A5 landscape'] as $format => $size) {
            $this->assertStringContainsString('@page{size:'.$size.';margin:0}', $this->renderLabel('MissPack', $format));
        }
    }

    public function test_sender_text_is_escaped_and_an_unknown_vendor_does_not_become_the_receiver(): void
    {
        $html = $this->renderLabel('<script>alert(1)</script>', 'thermal', 3);
        $this->assertStringContainsString('<h1>&lt;script&gt;alert(1)&lt;/script&gt;</h1>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $html = $this->renderLabel('', 'thermal', 3);
        $this->assertStringContainsString('<h1>Sender not specified</h1>', $html);
        $this->assertStringNotContainsString('class="label-brand-logo"', $html);
    }
}
