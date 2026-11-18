<?php

use App\Models\StockCount;
use App\Models\User;
use App\Services\StockReconciliation;
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
$count = StockCount::findOrFail((int) ($argv[1] ?? 0));
$reviewer = User::findOrFail((int) ($argv[2] ?? 0));
$revision = (int) ($argv[3] ?? -1);
echo "READY\n";
flush();
try {
    app(StockReconciliation::class)->approve($count, $reviewer, 'Simultaneous approval', $revision);
    echo "POSTED\n";
} catch (ValidationException $exception) {
    echo "REJECTED\n";
}
