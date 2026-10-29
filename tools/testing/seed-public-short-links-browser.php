<?php

/** Fictional fixtures for public-short-links-browser.cjs. Never targets a real database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\ClientPortalUser;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$password = getenv('SHORT_LINK_TEST_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use only a disposable SQLite database under .work and a test-only password.');
}

User::create(['name' => 'Browser Test Admin', 'email' => 'short-browser@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'full']);
$client = Client::create(['client_number' => 'BROWSER-SHORT-CLIENT', 'company_name' => 'Browser Short Client',
    'preferred_currency' => 'INR', 'billing_address' => '1 Test Street', 'billing_city' => 'Ahmedabad',
    'billing_state' => 'Gujarat', 'billing_country' => 'India', 'billing_pincode' => '380001']);
$client->forceFill(['portal_enabled' => true])->save();
ClientPortalUser::create(['client_id' => $client->id, 'name' => 'Browser Client User', 'username' => 'short-client',
    'password' => Hash::make($password), 'portal_enabled' => true, 'is_active' => true, 'must_change_password' => false]);
$vendor = Vendor::create(['vendor_number' => 'BROWSER-SHORT-VENDOR', 'vendor_name' => 'Browser Short Vendor', 'status' => 'active']);
$vendor->forceFill(['kyc_public_token' => Str::random(48)])->save();
$shipment = Shipment::create(['shipment_number' => 'BROWSER-SHORT-SHIP', 'identity_name' => 'Browser Short Shipment',
    'shipment_type' => 'domestic', 'status' => 'planning', 'from_name' => 'MissPack', 'to_name' => 'Browser Short Client',
    'public_token' => Str::random(48), 'client_id' => $client->id, 'show_client_portal' => true]);

echo json_encode(['client' => $client->id, 'vendor' => $vendor->id, 'shipment' => $shipment->id], JSON_THROW_ON_ERROR)."\n";
