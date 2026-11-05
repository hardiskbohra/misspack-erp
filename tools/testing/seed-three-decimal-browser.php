<?php

/** Fictional fixtures for three-decimal-browser.cjs. Never targets a real database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CashflowAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$password = getenv('PRECISION_TEST_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use only a disposable SQLite database under .work and a test-only password.');
}

User::firstOrCreate(['email' => 'precision-browser@example.test'], ['name' => 'Browser Test Admin',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'full']);
$account = CashflowAccount::firstOrCreate(['account_name' => 'Precision browser account'],
    ['currency' => 'INR', 'opening_balance' => '10.000', 'current_balance' => '10.000']);
echo json_encode(['account' => $account->id], JSON_THROW_ON_ERROR)."\n";
