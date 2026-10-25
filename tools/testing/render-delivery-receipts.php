<?php

// Render the real Blade template with in-memory fixtures for optional browser/PDF checks.
// APP_ENV=testing php tools/testing/render-delivery-receipts.php .work/receipts
if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "Print fixtures require APP_ENV=testing.\n");
    exit(1);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$output = $argv[1] ?? base_path('.work/receipts');
if (! is_dir($output)) mkdir($output, 0777, true);
config(['app.name' => 'LARAVEL']);
foreach (['own', 'vendor', 'discrepancy', 'long'] as $case) {
    $data = \Tests\Support\DeliveryReceiptPrintFixture::data(
        $case === 'vendor' ? 'Solo Beauty Packaging Co., Ltd' : 'MissPack India Pvt Ltd',
        in_array($case, ['discrepancy', 'long'], true)
    );
    if ($case === 'long') {
        $line = $data['receipt']->lines->first();
        $data['receipt']->setRelation('lines', new \Illuminate\Database\Eloquent\Collection(
            array_map(fn () => clone $line, range(1, 60))
        ));
    }
    file_put_contents($output.'/receipt-'.$case.'.html', view('shipments.print.delivery-receipt', $data)->render());
}
echo "Rendered four in-memory receipt fixtures in {$output}.\n";
