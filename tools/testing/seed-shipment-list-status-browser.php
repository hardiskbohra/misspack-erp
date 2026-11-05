<?php

/** Fictional fixture for shipment list status checks. Never run on a live database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$storage = realpath(storage_path());
$password = getenv('SHIPMENT_LIST_BROWSER_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || ! $work || ! $database
    || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR) || ! $storage || ! str_starts_with($storage, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database and storage under .work with a fictional password.');
}
User::create(['name' => 'Shipping Browser', 'email' => 'shipment-list-manager@example.test', 'password' => $password,
    'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
User::create(['name' => 'Shipping Reader', 'email' => 'shipment-list-reader@example.test', 'password' => $password,
    'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.view']]);
$client = Client::create(['client_number' => 'LIST-BROWSER-CLIENT', 'company_name' => 'Fictional client']);
$first = Shipment::create(['shipment_number' => 'BROWSER-LIST-01', 'identity_name' => 'Fictional carton delivery',
    'shipment_type' => 'domestic', 'shipment_mode' => 'road', 'status' => 'in_transit', 'pickup_date' => '2026-01-10',
    'currency' => 'INR', 'client_id' => $client->id, 'show_client_portal' => true,
    'from_name' => 'Fictional sender', 'to_name' => 'Fictional receiver']);
$second = Shipment::create(['shipment_number' => 'BROWSER-LIST-02', 'identity_name' => 'Fictional bottle delivery',
    'shipment_type' => 'domestic', 'shipment_mode' => 'road', 'status' => 'in_transit', 'pickup_date' => '2026-01-10',
    'currency' => 'INR', 'from_name' => 'Fictional sender', 'to_name' => 'Fictional receiver']);
file_put_contents($work.'/shipment-list-status-fixture.json', json_encode([
    'first_id' => $first->id, 'second_id' => $second->id, 'public_token' => $first->public_token,
], JSON_THROW_ON_ERROR));
echo "Seeded fictional shipments and shipping users.\n";
