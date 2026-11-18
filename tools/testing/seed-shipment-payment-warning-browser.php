<?php

/** Fictional payment-warning browser fixture. Never use a live database or storage. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\PackagingList;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectProduct;
use App\Models\ProjectQualityInspection;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Support\Facades\DB;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
$storage = realpath(storage_path());
$password = getenv('SHIPMENT_PAYMENT_BROWSER_PASSWORD');
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || ! $work || ! $database
    || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR) || ! $storage || ! str_starts_with($storage, $work.DIRECTORY_SEPARATOR)
    || ! is_string($password) || strlen($password) < 12) {
    throw new LogicException('Use a disposable SQLite database and storage under .work with a fictional password.');
}
$operator = User::create(['name' => 'Payment Warning Browser', 'email' => 'shipment-payment-browser@example.test',
    'password' => $password, 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
$client = Client::create(['client_number' => 'PAYMENT-BROWSER-CLIENT', 'company_name' => 'Fictional buyer']);
$shipments = [];
foreach (['1440', '768', '390', '320', 'nojs'] as $suffix) {
    $number = 'PAYMENT-BROWSER-'.$suffix;
    $project = Project::forceCreate(['project_number' => $number, 'client_id' => $client->id,
        'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
    $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Board', 'quantity' => 5]);
    $invoice = SalesInvoice::create(['invoice_number' => $number, 'invoice_type' => 'proforma', 'status' => 'sent',
        'client_id' => $client->id, 'project_id' => $project->id, 'currency' => 'INR',
        'total_amount' => 100, 'amount_paid' => 25, 'balance_before_dispatch' => true]);
    $workflow = app(ProjectWorkflowExecution::class)->start($project, $operator->id);
    $workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
    $shipment = Shipment::create(['shipment_number' => $number, 'identity_name' => 'Fictional delivery',
        'project_id' => $project->id, 'client_id' => $client->id, 'status' => 'planning',
        'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
    $shipment->items()->create(['project_product_id' => $product->id, 'product_name' => 'Board', 'quantity' => 5, 'unit' => 'pcs']);
    $list = PackagingList::create(['packing_number' => $number, 'packing_date' => now()->toDateString(),
        'project_id' => $project->id, 'shipment_id' => $shipment->id, 'status' => 'confirmed',
        'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
    $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1, 'length_cm' => 30,
        'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
    $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $product->id,
        'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
    ProjectQualityInspection::create(['project_id' => $project->id, 'project_product_id' => $product->id,
        'inspected_quantity' => 1, 'passed_quantity' => 1, 'result' => 'passed', 'checklist' => [],
        'content_hash' => str_repeat('c', 64), 'inspected_at' => now()]);
    ProjectAttachment::create(['project_id' => $project->id, 'category' => 'delivery_challan',
        'file_path' => 'fake/challan.pdf', 'original_name' => 'challan.pdf']);
    $shipments[$suffix] = ['id' => $shipment->id, 'number' => $number];
}
file_put_contents($work.'/shipment-payment-warning-fixture.json', json_encode($shipments, JSON_THROW_ON_ERROR));
echo "Seeded fictional unpaid projects with real dispatch evidence.\n";
