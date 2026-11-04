<?php

/** Fictional fixture for the shipment record browser check. Never run on a live database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$storage = realpath(storage_path());
$password = getenv('SHIPMENT_TABS_BROWSER_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || ! $work || ! $database
    || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR) || ! $storage || ! str_starts_with($storage, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database and storage under .work with a fictional password.');
}
User::create(['name' => 'Shipping Browser', 'email' => 'shipment-tabs-browser@example.test', 'password' => $password,
    'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
User::create(['name' => 'Shipping Reader', 'email' => 'shipment-reader-browser@example.test', 'password' => $password,
    'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.view']]);
$shipment = Shipment::create(['shipment_number' => 'BROWSER-SHIP-01', 'identity_name' => 'Fictional shipping record',
    'shipment_type' => 'domestic', 'shipment_mode' => 'road', 'status' => 'in_transit', 'currency' => 'INR',
    'from_name' => 'Fictional origin', 'from_city' => 'Ahmedabad', 'to_name' => 'Fictional receiver',
    'to_city' => 'Mumbai', 'tracking_number' => 'TRACK-FICTIONAL', 'logistic_partner' => 'Example carrier']);
$shipment->items()->create(['product_name' => 'Fictional carton', 'quantity' => 10, 'unit' => 'pcs', 'currency' => 'INR']);
$shipment->histories()->create(['status' => 'in_transit', 'event_time' => now(), 'location' => 'Fictional hub',
    'remarks' => 'The consignment left the fictional hub.', 'is_public' => true]);
file_put_contents($work.'/shipment-tabs-fixture.json', json_encode(['shipment_id' => $shipment->id], JSON_THROW_ON_ERROR));
echo "Seeded fictional shipment, item and tracking history.\n";
