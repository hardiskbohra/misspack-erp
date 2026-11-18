<?php

/** Fictional four-issue shipment fixture; never use a live database or storage. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$storage = realpath(storage_path());
$password = getenv('SHIPMENT_READINESS_BROWSER_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || ! $work || ! $database
    || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR) || ! $storage || ! str_starts_with($storage, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database and storage under .work with a fictional password.');
}
$operator = User::create(['name' => 'Readiness Warning Browser', 'email' => 'shipment-readiness-browser@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
$client = Client::create(['client_number' => 'READINESS-BROWSER-CLIENT', 'company_name' => 'Fictional buyer']);
$shipments = [];
foreach (['1440', '768', '390', '320', 'nojs'] as $suffix) {
    $number = 'READINESS-BROWSER-'.$suffix;
    $project = Project::forceCreate(['project_number' => $number, 'client_id' => $client->id,
        'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
    $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Board', 'quantity' => 5]);
    $invoice = SalesInvoice::create(['invoice_number' => $number, 'invoice_type' => 'proforma', 'status' => 'sent',
        'client_id' => $client->id, 'project_id' => $project->id, 'currency' => 'INR',
        'total_amount' => 100, 'amount_paid' => 25, 'balance_before_dispatch' => true]);
    $invoice->items()->create(['project_product_id' => $product->id, 'product_name' => 'Board', 'quantity' => 5]);
    $workflow = app(ProjectWorkflowExecution::class)->start($project, $operator->id);
    $workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
    $shipment = Shipment::create(['shipment_number' => $number, 'identity_name' => 'Fictional delivery',
        'project_id' => $project->id, 'client_id' => $client->id, 'status' => 'planning',
        'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
    $shipment->items()->create(['project_product_id' => $product->id, 'product_name' => 'Board', 'quantity' => 5, 'unit' => 'pcs']);
    $shipments[$suffix] = ['id' => $shipment->id, 'number' => $number, 'project_id' => $project->id];
}
file_put_contents($work.'/shipment-readiness-warning-fixture.json', json_encode($shipments, JSON_THROW_ON_ERROR));
echo "Seeded fictional shipments with missing packing, QC, approvals and documents.\n";
