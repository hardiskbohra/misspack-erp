<?php

use App\Models\StockItem;
use App\Models\User;
use App\Services\StockRegistry;
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
$recordRevision = (int) ($argv[3] ?? -1);
$ledgerVersion = (int) ($argv[4] ?? -1);
echo "READY\n";
flush();
try {
    app(StockRegistry::class)->archive($item, ['reason' => 'Fictional concurrent archive', 'record_revision' => $recordRevision,
        'ledger_version' => $ledgerVersion, 'acknowledge_nonzero' => '1'], $actor);
    echo "ARCHIVED\n";
} catch (ValidationException $exception) {
    echo 'REJECTED '.implode(' ', collect($exception->errors())->flatten()->all())."\n";
}
