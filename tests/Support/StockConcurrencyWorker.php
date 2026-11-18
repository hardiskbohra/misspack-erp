<?php

use App\Models\StockItem;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
    throw new LogicException('This worker requires a disposable MySQL *_test database.');
}
$item = StockItem::findOrFail((int) ($argv[1] ?? 0));
$actor = User::findOrFail((int) ($argv[2] ?? 0));
echo "READY\n";
flush();
try {
    app(StockLedger::class)->manualMovement($item, ['type' => 'issue', 'quantity' => '6.000', 'reason' => 'Concurrent issue',
        'reference' => null, 'record_revision' => $item->record_revision, 'request_key' => '5cabba89-7a92-41bd-a214-032f44d67bcd'], $actor);
    echo "POSTED\n";
} catch (ValidationException $exception) {
    echo "REJECTED\n";
}
