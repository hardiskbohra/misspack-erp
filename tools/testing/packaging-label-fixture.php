<?php

/* Render the real Blade label with fake in-memory models for the optional
 * Chromium print check. No database, network delivery or customer data. */

use App\Models\Organisation;
use App\Models\PackagingList;
use App\Models\PackagingListCarton;
use App\Models\PackagingListItem;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$format = $argv[1] ?? 'thermal';
$scenario = $argv[2] ?? 'single';
if (! in_array($format, ['a4', 'a5', 'thermal', 'thermal150'], true)
    || ! in_array($scenario, ['single', 'batch', 'vendor', 'overflow'], true)) {
    fwrite(STDERR, "Unsupported label fixture\n");
    exit(1);
}

$vendor = $scenario === 'vendor';
$list = new PackagingList([
    'packing_number' => 'MP/PL/26-27/024', 'total_cartons' => 3,
    'packing_date' => '2026-10-22', 'transport_mode' => 'road',
    'from_name' => $vendor ? 'Example Packaging Co.' : 'MissPack India Pvt Ltd',
    'from_address' => '229 Block F, Shahibaug, Ahmedabad, Gujarat 380004',
    'ship_to_name' => 'Example Trading Company',
    'ship_to_address' => 'Sri Enterprises, Kempegowda, Bengaluru, Karnataka 560074',
    'vendor_id' => $vendor ? 3 : null,
]);
$list->id = 24;

$makeCarton = static function (int $id, string $description) use ($list, $scenario): PackagingListCarton {
    $carton = new PackagingListCarton([
        'carton_type' => 'box', 'carton_count' => 2, 'barcode' => 'CTN-TEST-00'.$id,
        'packing_group' => '300ml Shampoo Bottle',
        'length_cm' => 60, 'width_cm' => 40, 'height_cm' => 42,
        'net_weight_per_carton' => 6.6, 'gross_weight_per_carton' => 7.2,
        'is_fragile' => true, 'keep_dry' => true, 'this_side_up' => true, 'is_hazardous' => true,
        'handling_instructions' => 'Keep away from heat',
    ]);
    $carton->id = $id;
    $carton->setRelation('packagingList', $list);
    $items = [];
    $itemCount = $scenario === 'overflow' ? 30 : 1;
    for ($i = 0; $i < $itemCount; $i++) {
        $items[] = new PackagingListItem([
            'item_description' => $description.($i ? ' '.$i : ''),
            'sku' => 'SKU-300', 'batch_number' => 'BATCH-24',
            'serial_numbers' => ['SER-001', 'SER-002'], 'quantity_per_carton' => 174,
            'unit' => 'pcs', 'country_of_origin' => 'India', 'hs_code' => '392330',
        ]);
    }
    $carton->setRelation('items', new Collection($items));

    return $carton;
};

$first = $makeCarton(1, '300ml Shampoo Bottle');
$batches = [['carton' => $first, 'copies' => $scenario === 'batch' ? 2 : 1, 'startCartonNumber' => 1]];
if ($scenario === 'batch') {
    $batches[] = ['carton' => $makeCarton(2, 'Fine Mist Pump'), 'copies' => 1, 'startCartonNumber' => 3];
}

echo view('packaging_lists.label', [
    'carton' => $first, 'labelBatches' => $batches, 'format' => $format,
    'copies' => $scenario === 'batch' ? 3 : 1, 'startCartonNumber' => 1,
    'autoPrint' => $scenario === 'batch', 'labelQrUrl' => 'https://example.test/label/24',
    'organisation' => new Organisation(['legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack']),
])->render();
