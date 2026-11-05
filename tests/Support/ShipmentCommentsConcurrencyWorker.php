<?php

/** Spawned only by the disposable native MySQL shipment locking tests. */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\ShipmentCommentController;
use App\Http\Controllers\ShipmentController;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$db = DB::connection();
$work = realpath(base_path('.work'));
$root = realpath($argv[4] ?? '');
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')
    || ! $work || ! $root || ! str_starts_with($root, $work.DIRECTORY_SEPARATOR)) {
    throw new LogicException('Shipment concurrency worker requires a disposable MySQL database and private storage under .work.');
}
config(['filesystems.disks.local.root' => $root]);
Storage::purge('local');
$shipment = Shipment::findOrFail((int) $argv[2]);
$actor = User::findOrFail((int) $argv[3]);
Auth::setUser($actor);
$temp = null;
try {
    if ($argv[1] === 'store') {
        $temp = tempnam($work, 'race-upload-');
        file_put_contents($temp, "%PDF-1.4\n%%EOF\n");
        $upload = new UploadedFile($temp, 'race.pdf', 'application/pdf', null, true);
        $request = Request::create('/shipments/'.$shipment->id.'/comments', 'POST', ['body' => 'Racing private upload'], [], ['attachments' => [$upload]]);
        $request->setUserResolver(fn () => $actor);
        fwrite(STDOUT, "READY\n"); fflush(STDOUT);
        try {
            app(ShipmentCommentController::class)->store($request, $shipment);
            fwrite(STDOUT, "STORED\n");
        } catch (ModelNotFoundException) {
            fwrite(STDOUT, "MISSING_PARENT\n");
        }
    } elseif ($argv[1] === 'destroy') {
        fwrite(STDOUT, "READY\n"); fflush(STDOUT);
        app(ShipmentController::class)->destroy($shipment);
        fwrite(STDOUT, "DELETED\n");
    } else {
        throw new LogicException('Unknown shipment concurrency operation.');
    }
} finally {
    if ($temp && is_file($temp)) unlink($temp);
}
