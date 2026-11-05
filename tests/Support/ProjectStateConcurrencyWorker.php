<?php

// Spawned only by the native MySQL test against a disposable *_test database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
    throw new LogicException('Project concurrency worker requires a disposable MySQL *_test database.');
}
$workflow = \App\Models\ProjectWorkflow::findOrFail((int) $argv[1]);
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
app(\App\Services\ProjectWorkflowExecution::class)->recalculate($workflow);
fwrite(STDOUT, "RECALCULATED\n");
