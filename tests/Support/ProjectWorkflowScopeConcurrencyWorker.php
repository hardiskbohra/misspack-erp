<?php

// Spawned only against the disposable strict MySQL *_test database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection();
if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
    throw new LogicException('Workflow scope concurrency worker requires a disposable MySQL *_test database.');
}
$db->beginTransaction();
if (($argv[2] ?? '') === 'sales') {
    $invoice = \App\Models\SalesInvoice::findOrFail((int) $argv[1]);
    fwrite(STDOUT, "READY\n"); fflush(STDOUT);
    app(\App\Services\ProjectProducts::class)->fromSalesInvoice($invoice);
    $db->commit();
    fwrite(STDOUT, "MATERIALIZED\n");
    exit;
}
if (($argv[2] ?? '') === 'purchase') {
    $purchase = \App\Models\PurchaseInvoice::findOrFail((int) $argv[1]);
    // Report an actual product write, not merely an attempted operation.
    \Illuminate\Support\Facades\DB::listen(function ($query) {
        if (preg_match('/^update `?project_products`?\s/i', $query->sql)) {
            fwrite(STDOUT, "PRODUCT_UPDATED\n"); fflush(STDOUT);
        }
    });
    fwrite(STDOUT, "READY\n"); fflush(STDOUT);
    app(\App\Services\ProjectProducts::class)->fromPurchaseInvoice($purchase);
    $db->commit();
    fwrite(STDOUT, "MATERIALIZED\n");
    exit;
}
if (($argv[2] ?? '') === 'start') {
    $project = \App\Models\Project::findOrFail((int) $argv[1]);
    $project->workflow()->count(); // Snapshot before the workflow is created.
    fwrite(STDOUT, "READY\n"); fflush(STDOUT);
    $workflow = app(\App\Services\ProjectWorkflowExecution::class)->start($project);
    $db->commit();
    fwrite(STDOUT, "STARTED {$workflow->id}\n");
    exit;
}
$workflow = \App\Models\ProjectWorkflow::findOrFail((int) $argv[1]);
// Establish a REPEATABLE READ snapshot before the other transaction commits.
$workflow->steps()->count();
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
app(\App\Services\ProjectWorkflowExecution::class)->sync($workflow);
if (($argv[2] ?? '') === 'readiness') {
    app(\App\Services\ProjectOperationalReadiness::class)->evaluateAll($workflow);
} else {
    app(\App\Services\ProjectWorkflowExecution::class)->recalculate($workflow);
}
$db->commit();
fwrite(STDOUT, "SYNCED\n");
