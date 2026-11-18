<?php

/** Fictional data only, for the project administrator controls browser check. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\ClientPortalUser;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$work = realpath(base_path('.work'));
$database = realpath((string) DB::connection()->getDatabaseName());
if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)) {
    throw new LogicException('Use a disposable SQLite database under .work.');
}
$email = getenv('PROJECT_ADMIN_BROWSER_EMAIL');
$password = getenv('PROJECT_ADMIN_BROWSER_PASSWORD');
if (! $email || ! $password) throw new LogicException('Set fictional browser fixture credentials.');
$admin = User::create(['name' => 'Browser Administrator', 'email' => $email, 'password' => Hash::make($password),
    'role' => 'admin', 'access_profile' => 'full']);
User::create(['name' => 'Browser Project Operator', 'email' => 'limited-project-browser@example.test', 'password' => Hash::make($password),
    'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['projects.view', 'projects.manage']]);
$client = Client::create(['client_number' => 'BROWSER-PROJECT-CLIENT', 'company_name' => 'Fictional Project Client']);
$client->forceFill(['portal_enabled' => true])->save();
ClientPortalUser::create(['client_id' => $client->id, 'name' => 'Browser Client', 'username' => 'project-browser-client',
    'password' => Hash::make($password), 'portal_enabled' => true, 'is_active' => true, 'must_change_password' => false]);
$project = Project::forceCreate(['project_number' => 'BROWSER-PROJECT-CONTROL', 'client_id' => $client->id,
    'name' => 'Cartons for a fictional client', 'status' => 'planned', 'stage' => 'setup',
    'currency' => 'INR', 'show_client_portal' => true]);
$product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Fictional carton', 'quantity' => 10]);
$invoice = SalesInvoice::create(['invoice_number' => 'BROWSER-PROJECT-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
    'client_id' => $client->id, 'project_id' => $project->id, 'invoice_date' => '2026-10-29',
    'client_company_name' => $client->company_name, 'currency' => 'INR', 'total_amount' => '125.574']);
$invoice->items()->create(['project_product_id' => $product->id, 'product_name' => 'Fictional carton', 'quantity' => 10]);
$salesIds = [$product->id];
$productNames = [$product->id => $product->product_name];
for ($i = 2; $i <= 12; $i++) {
    $name = $i === 2 ? 'Fictional carton' : ($i === 12 ? 'Fictional long product '.str_repeat('W', 70) : 'Fictional item '.$i);
    $sales = ProjectProduct::create(['project_id' => $project->id, 'product_name' => $name, 'quantity' => 10]);
    $salesIds[] = $sales->id;
    $productNames[$sales->id] = $sales->product_name;
    $invoice->items()->create(['project_product_id' => $sales->id, 'product_name' => $sales->product_name, 'quantity' => 10]);
}
$purchaseOnly = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Purchase-only fictional board', 'quantity' => 10]);
$purchase = PurchaseInvoice::create(['invoice_number' => 'BROWSER-PURCHASE-PO', 'invoice_type' => 'order', 'status' => 'approved',
    'public_token' => 'browser-purchase-token', 'project_id' => $project->id, 'vendor_company_name' => 'Fictional supplier', 'currency' => 'INR']);
$purchase->items()->create(['project_product_id' => $purchaseOnly->id, 'product_name' => $purchaseOnly->product_name, 'quantity' => 10]);
app(ProjectWorkflowExecution::class)->start($project, $admin->id);
file_put_contents($work.'/project-admin-controls-fixture.json', json_encode(['project_id' => $project->id, 'sales_product_ids' => $salesIds, 'product_names' => $productNames, 'purchase_product_id' => $purchaseOnly->id], JSON_THROW_ON_ERROR));
echo "Seeded a fictional project with open work and a receivable.\n";
