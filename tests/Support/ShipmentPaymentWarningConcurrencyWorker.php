<?php

/** Competing dispatch against a guarded, disposable MySQL test database. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\ShipmentStatusChanged;
use App\Http\Controllers\ShipmentController;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

$db = DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
    throw new LogicException('The shipment payment worker requires a disposable MySQL *_test database.');
}
if ($argc !== 3 || ! ctype_digit($argv[1]) || ! ctype_digit($argv[2])) throw new InvalidArgumentException('Expected a shipment and user id.');
$shipment = Shipment::findOrFail((int) $argv[1]);
Auth::setUser(User::findOrFail((int) $argv[2]));
Event::fake([ShipmentStatusChanged::class]);
$session = $app->make('session')->driver();
$session->start();
$request = Request::create('/shipments/'.$shipment->id.'/history', 'POST', [
    '_list_status_modal' => '1', '_expected_status' => 'planning', 'status' => 'picked_up',
    'remarks' => 'Fictional pickup', 'is_public' => '0', 'notify_client' => '0',
]);
$request->setLaravelSession($session);
$app->instance('request', $request);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
app(ShipmentController::class)->storeHistory($request, $shipment);
fwrite(STDOUT, $session->has('shipment_balance_warning') ? "WARNING\n" : "NO PAYMENT WARNING\n");
