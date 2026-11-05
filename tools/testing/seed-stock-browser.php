<?php

/** Fictional fixture for the stock browser check. Never run on a live database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$storage = realpath(storage_path());
$password = getenv('STOCK_BROWSER_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || ! $work || ! $database
    || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR) || ! $storage || ! str_starts_with($storage, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database and storage under .work with a fictional password.');
}
foreach ([
    ['Stock Counter', 'stock-counter-browser@example.test', ['operations.manage']],
    ['Stock Reviewer', 'stock-reviewer-browser@example.test', ['operations.manage']],
    ['Stock Reader', 'stock-reader-browser@example.test', ['operations.view']],
] as [$name, $email, $permissions]) {
    User::create(['name' => $name, 'email' => $email, 'password' => $password,
        'role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
}
$product = Product::create(['product_number' => 'BROWSER-STOCK-P1', 'name' => 'Fictional stock bottle',
    'sku' => 'BROWSER-BOTTLE', 'status' => 'active']);
file_put_contents($work.'/stock-browser-fixture.json', json_encode(['product_id' => $product->id], JSON_THROW_ON_ERROR));
echo "Seeded fictional stock users and product.\n";
