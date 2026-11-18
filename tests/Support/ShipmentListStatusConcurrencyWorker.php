<?php

/** Competing list update against a guarded, disposable MySQL test database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ShipmentController;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$db = DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
    throw new LogicException('The shipment status worker requires a disposable MySQL *_test database.');
}
if ($argc !== 3 || ! ctype_digit($argv[1]) || ! ctype_digit($argv[2])) throw new InvalidArgumentException('Expected a shipment and user id.');
$shipment = Shipment::findOrFail((int) $argv[1]);
$user = User::findOrFail((int) $argv[2]);
Auth::setUser($user);
$request = Request::create('/shipments/'.$shipment->id.'/history', 'POST', [
    '_list_status_modal' => '1', '_expected_status' => 'in_transit', 'status' => 'delayed',
    'location' => 'Fictional hub', 'remarks' => 'Competing carrier update', 'is_public' => '0', 'notify_client' => '0',
]);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
try {
    app(ShipmentController::class)->storeHistory($request, $shipment);
    fwrite(STDOUT, "UNEXPECTED SUCCESS\n");
    exit(1);
} catch (ValidationException $exception) {
    fwrite(STDOUT, 'REJECTED '.implode(' ', $exception->errors()['status'] ?? [])."\n");
}
