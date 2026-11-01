<?php

/** Fictional fixtures for the cashflow date-group browser check; SQLite under .work only. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$password = getenv('CASHFLOW_GROUPS_TEST_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database under .work and a test-only password.');
}
User::create(['name' => 'Cashflow Browser', 'email' => 'cashflow-groups@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'full']);
$account = CashflowAccount::create(['account_name' => 'Browser account', 'account_type' => 'current', 'currency' => 'INR']);
// The browser server's test clock is fixed at 29 Oct 2026 in the office timezone.
foreach ([
    ['2026-11-15', 'Future far', 'credit'], ['2026-10-30', 'Future near', 'credit'],
    ['2026-10-29', 'Today entry', 'credit'], ['2026-10-28', 'Yesterday entry', 'debit'],
    ['2026-10-27', 'Two days entry', 'debit'], ['2026-10-26', 'This week entry', 'credit'],
    ['2026-10-25', 'Last week recent', 'debit'], ['2026-10-19', 'Last week start', 'credit'],
    ['2026-10-18', 'This month recent', 'debit'], ['2026-10-01', 'This month start', 'credit'],
    ['2026-09-30', 'Last month recent', 'credit'], ['2026-09-01', 'Last month start', 'credit'],
    ['2026-08-31', 'Older recent', 'debit'], ['2025-12-31', 'Older past', 'credit'],
] as [$date, $name, $type]) {
    CashflowEntry::create(['entry_date' => $date, 'particular' => $name, 'transaction_type' => $type,
        'credit_amount' => $type === 'credit' ? 1 : 0, 'debit_amount' => $type === 'debit' ? 1 : 0,
        'currency' => 'INR', 'account_id' => $account->id, 'accounting_status' => 'booked']);
}
echo "Seeded 14 fictional cashflow entries.\n";
