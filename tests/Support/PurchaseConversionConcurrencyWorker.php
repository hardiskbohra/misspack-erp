<?php

// Started by MySqlPurchasePartialConversionTest, never against a live database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
    throw new LogicException('Concurrency worker requires a disposable MySQL *_test database.');
}
\Illuminate\Support\Facades\Auth::loginUsingId((int) $argv[2]);
$order = \App\Models\PurchaseInvoice::findOrFail((int) $argv[1]);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
try {
    $attempt = 0;
    $collidingNumber = $argv[3] ?? null;
    app(\App\Services\PurchasePartialConversion::class)->convert($order,
        ['kind' => 'cash', 'mode' => 'amount', 'value' => '6000'],
        function () use (&$attempt, $collidingNumber) {
            if (! $collidingNumber) return 'RACE-WORKER-'.bin2hex(random_bytes(6));
            return $attempt++ === 0 ? $collidingNumber : $collidingNumber.'-NEXT';
        });
    fwrite(STDOUT, "CREATED\n");
} catch (\Illuminate\Validation\ValidationException $exception) {
    fwrite(STDOUT, "REJECTED\n");
}
