<?php

namespace Tests\Database;

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
use App\Services\ShipmentDispatchAuthorization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** One ordered payment/dispatch interleaving on guarded native InnoDB. */
class MySqlShipmentPaymentWarningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Shipment payment checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Shipment payment checks require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'cache.default' => 'array',
            'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
    }

    public function test_waiting_dispatch_observes_payment_committed_under_the_project_lock(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $actor = User::create(['name' => 'Native shipping operator', 'email' => "payment-{$suffix}@example.test",
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $client = Client::create(['client_number' => 'PAYMENT-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'PAYMENT-'.$suffix, 'client_id' => $client->id,
            'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Board', 'quantity' => 5]);
        $invoice = SalesInvoice::create(['invoice_number' => 'PAYMENT-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'sent',
            'client_id' => $client->id, 'project_id' => $project->id, 'currency' => 'INR',
            'total_amount' => 100, 'amount_paid' => 25, 'balance_before_dispatch' => true]);
        $workflow = app(ProjectWorkflowExecution::class)->start($project, $actor->id);
        $workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
        $shipment = Shipment::create(['shipment_number' => 'PAYMENT-'.$suffix, 'identity_name' => 'Fictional shipment',
            'project_id' => $project->id, 'client_id' => $client->id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
        $shipment->items()->create(['project_product_id' => $product->id, 'product_name' => 'Board', 'quantity' => 5, 'unit' => 'pcs']);
        $list = PackagingList::create(['packing_number' => 'PAYMENT-'.$suffix, 'packing_date' => now()->toDateString(),
            'project_id' => $project->id, 'shipment_id' => $shipment->id, 'status' => 'confirmed',
            'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
        $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1, 'length_cm' => 30,
            'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $product->id,
            'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
        ProjectQualityInspection::create(['project_id' => $project->id, 'project_product_id' => $product->id,
            'inspected_quantity' => 1, 'passed_quantity' => 1, 'result' => 'passed', 'checklist' => [],
            'content_hash' => str_repeat('b', 64), 'inspected_at' => now()]);
        ProjectAttachment::create(['project_id' => $project->id, 'category' => 'delivery_challan',
            'file_path' => 'fake/challan.pdf', 'original_name' => 'challan.pdf']);
        $db = DB::connection(); $process = null; $pipes = [];
        try {
            $authorization = app(ShipmentDispatchAuthorization::class);
            $this->assertSame([], $authorization->inspect($shipment, $workflow, $authorization->rowsFromShipment($shipment))['issues']);
            $db->beginTransaction();
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the payment concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ShipmentPaymentWarningConcurrencyWorker.php'),
                (string) $shipment->id, (string) $actor->id], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the payment concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $ready = fgets($pipes[1]);
            if ($ready !== "READY\n") {
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $this->fail('The payment worker did not reach its barrier: '.trim((string) $ready.stream_get_contents($pipes[1]).stream_get_contents($pipes[2])));
            }
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'Dispatch must wait for the project lock.');
            $invoice->update(['amount_paid' => 100]);
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('NO PAYMENT WARNING', $output);
            $this->assertSame('picked_up', $shipment->fresh()->status);
            $this->assertSame(1, $shipment->histories()->count());
            $this->assertSame('passed', $workflow->evaluations()->where('gate_key', 'dispatch_balance')->latest('id')->firstOrFail()->result);
            $this->assertEquals(100, $invoice->fresh()->amount_paid);
        } finally {
            if ($db->transactionLevel() > 0) $db->rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('packaging_lists')->where('id', $list->id)->delete();
            DB::table('shipments')->where('id', $shipment->id)->delete();
            DB::table('sales_invoices')->where('id', $invoice->id)->delete();
            DB::table('projects')->where('id', $project->id)->delete();
            DB::table('clients')->where('id', $client->id)->delete();
            DB::table('users')->where('id', $actor->id)->delete();
        }
    }
}
