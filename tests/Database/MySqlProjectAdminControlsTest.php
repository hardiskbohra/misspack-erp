<?php

namespace Tests\Database;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\ProjectAdminState;
use App\Services\ProjectFinancialClosure;
use App\Services\ProjectWorkflowBypass;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Native InnoDB locking, JSON and decimal behaviour; only on a disposable database. */
class MySqlProjectAdminControlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Project controls checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES'))
            || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Project controls checks require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        config(['cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
    }

    public function test_native_manual_state_bypass_and_closure_preserve_financial_snapshot(): void
    {
        $db = DB::connection();
        $manager = new DatabaseTransactionsManager([$db->getName()]);
        $this->app->instance('db.transactions', $manager);
        $db->setTransactionManager($manager);
        $db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(4));
            $actor = User::create(['name' => 'Project administrator', 'email' => 'project-mysql-'.$suffix.'@example.test',
                'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
            $client = Client::create(['client_number' => 'PROJECT-MYSQL-'.$suffix, 'company_name' => 'Fictional client']);
            $project = Project::forceCreate(['project_number' => 'PROJECT-MYSQL-'.$suffix, 'client_id' => $client->id,
                'name' => 'Native project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
            ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Carton', 'quantity' => 10]);
            $workflow = app(ProjectWorkflowExecution::class)->start($project, $actor->id);
            $invoice = SalesInvoice::create(['invoice_number' => 'PROJECT-MYSQL-INV-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'sent',
                'client_id' => $client->id, 'project_id' => $project->id, 'client_company_name' => 'Fictional client',
                'invoice_date' => '2026-11-01', 'currency' => 'INR', 'total_amount' => '125.574']);
            app(ProjectAdminState::class)->update($project, $actor, 'on_hold', 73, 'The administrator reviewed this project state.');
            $step = $workflow->steps()->where('step_key', 'project_setup')->firstOrFail();
            app(ProjectWorkflowBypass::class)->bypass($project, $step, $actor, 'The administrator accepted this particular risk.');
            $closure = app(ProjectFinancialClosure::class)->manualClose($project, $actor, 'The unfinished work and balances remain with the accounts team.');
            $this->assertSame('manually_closed', $project->fresh()->status);
            $this->assertSame(73, $project->fresh()->progress_percent);
            $this->assertSame('skipped', $step->fresh()->status);
            $this->assertTrue($closure->financial_snapshot['has_outstanding_financials']);
            $this->assertNotEmpty($closure->financial_snapshot['open_operational_steps']);
            $this->assertEqualsWithDelta(125.574, $closure->financial_snapshot['totals']['receivables']['INR'], 0.000001);
            $this->assertSame('125.574', $invoice->fresh()->getRawOriginal('total_amount'));
            $this->assertSame($actor->id, $closure->approved_by);
        } finally {
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
    }

    public function test_workflow_recalculation_waits_for_a_project_terminal_decision_and_does_not_overwrite_it(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $actor = User::create(['name' => 'Project race administrator', 'email' => 'project-race-'.$suffix.'@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $client = Client::create(['client_number' => 'PROJECT-RACE-'.$suffix, 'company_name' => 'Race client']);
        $project = Project::forceCreate(['project_number' => 'PROJECT-RACE-'.$suffix, 'client_id' => $client->id,
            'name' => 'Race project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $process = null;
        $pipes = [];
        try {
            $workflow = app(ProjectWorkflowExecution::class)->start($project, $actor->id);
            $workflow->steps()->update(['status' => 'completed']);
            $this->assertSame('in_progress', $project->fresh()->status);
            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the native project concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectStateConcurrencyWorker.php'), (string) $workflow->id],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the project concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'The calculation must wait for the project decision lock.');
            // This is the committed terminal state produced by manual closure.
            DB::table('projects')->where('id', $project->id)->update(['status' => 'manually_closed', 'stage' => 'closure', 'completed_at' => now()]);
            DB::commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('RECALCULATED', $output);
            $this->assertSame('manually_closed', $project->fresh()->status);
            $this->assertSame('closure', $project->fresh()->stage);
            $this->assertSame(100, $workflow->fresh()->calculated_progress);
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
            $actor->delete();
        }
    }
    public function test_scope_sync_uses_current_linked_sales_rows_after_waiting_on_a_stale_snapshot(): void
    {
        $this->assertScopeSyncUsesCurrentRows(false);
    }

    public function test_scope_sync_uses_current_legacy_sales_rows_after_waiting_on_a_stale_snapshot(): void
    {
        $this->assertScopeSyncUsesCurrentRows(true);
    }

    private function assertScopeSyncUsesCurrentRows(bool $legacy): void
    {
        $suffix = bin2hex(random_bytes(4));
        $client = Client::create(['client_number' => 'SCOPE-RACE-'.$suffix, 'company_name' => 'Fictional race client']);
        $project = Project::forceCreate(['project_number' => 'SCOPE-RACE-'.$suffix, 'client_id' => $client->id,
            'name' => 'Sales scope race', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $process = null;
        $pipes = [];
        try {
            $workflow = app(ProjectWorkflowExecution::class)->start($project);
            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Racing carton', 'quantity' => 2]);
            $invoice = SalesInvoice::create(['invoice_number' => 'SCOPE-RACE-INV-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'draft',
                'project_id' => $project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional race client', 'currency' => 'INR']);
            $invoice->items()->create(['project_product_id' => $legacy ? null : $product->id, 'product_name' => 'Racing carton', 'quantity' => 2]);
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the workflow scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $workflow->id],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the scope concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'Sync must wait for the project lock.');
            app(ProjectWorkflowExecution::class)->sync($workflow);
            DB::commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('SYNCED', $output);
            $steps = $workflow->fresh()->steps()->where('project_product_id', $product->id)->get();
            $this->assertCount(8, $steps);
            $this->assertSame(8, $steps->pluck('step_key')->unique()->count());
            foreach ($steps as $step) {
                $this->assertTrue(data_get($step->metadata, 'sales_workflow_scope.active'), 'A stale snapshot marked a current sales item out of scope.');
            }
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
        }
    }

    public function test_scope_sync_does_not_reactivate_a_cancelled_source_from_a_stale_snapshot(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $client = Client::create(['client_number' => 'SCOPE-CANCEL-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'SCOPE-CANCEL-'.$suffix, 'client_id' => $client->id,
            'name' => 'Cancellation race', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $process = null; $pipes = [];
        try {
            $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Racing carton', 'quantity' => 2]);
            $invoice = SalesInvoice::create(['invoice_number' => 'SCOPE-CANCEL-INV-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'draft',
                'project_id' => $project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional client', 'currency' => 'INR']);
            $invoice->items()->create(['project_product_id' => $product->id, 'product_name' => 'Racing carton', 'quantity' => 2]);
            $workflow = app(ProjectWorkflowExecution::class)->start($project);
            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $invoice->update(['status' => 'cancelled']);
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the workflow scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $workflow->id],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the scope concurrency worker.');
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running']);
            app(ProjectWorkflowExecution::class)->sync($workflow);
            DB::commit();
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('SYNCED', $output);
            $this->assertSame(0, $workflow->fresh()->steps()->where('project_product_id', $product->id)->count());
            $historical = $workflow->allSteps()->where('project_product_id', $product->id)->get();
            $this->assertCount(8, $historical);
            foreach ($historical as $step) $this->assertFalse(data_get($step->metadata, 'sales_workflow_scope.active'));
            $this->assertSame(0, $project->logs()->where('event_type', 'workflow_sales_scope_reentered')->count());
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
        }
    }

    public function test_stale_readiness_cannot_reapply_a_bypass_retired_during_sales_reentry(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $actor = User::create(['name' => 'Race administrator', 'email' => 'scope-reentry-'.$suffix.'@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $client = Client::create(['client_number' => 'SCOPE-REENTRY-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'SCOPE-REENTRY-'.$suffix, 'client_id' => $client->id,
            'name' => 'Reentry race', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $process = null; $pipes = [];
        try {
            $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Racing carton', 'quantity' => 2]);
            $invoice = SalesInvoice::create(['invoice_number' => 'SCOPE-REENTRY-INV-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'draft',
                'project_id' => $project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional client', 'currency' => 'INR']);
            $invoice->items()->create(['project_product_id' => $product->id, 'product_name' => 'Racing carton', 'quantity' => 2]);
            $workflow = app(ProjectWorkflowExecution::class)->start($project);
            app(\App\Services\ProjectOperationalReadiness::class)->evaluateAll($workflow, $actor->id);
            $step = $workflow->steps()->where('project_product_id', $product->id)->where('step_key', 'production_complete')->firstOrFail();
            app(ProjectWorkflowBypass::class)->bypass($project, $step, $actor, 'Administrator accepted the original production risk.');
            $override = $workflow->evaluations()->where('project_product_id', $product->id)->where('gate_key', 'production_complete')->latest('id')->firstOrFail()->activeOverride;
            $this->assertSame('skipped', $step->fresh()->status);
            $this->assertNotNull($override);
            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $workflow->id, 'readiness'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the scope concurrency worker.');
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1])); usleep(300000);
            $this->assertTrue(proc_get_status($process)['running']);
            $invoice->update(['status' => 'cancelled']);
            app(ProjectWorkflowExecution::class)->sync($workflow);
            $invoice->update(['status' => 'draft']);
            app(ProjectWorkflowExecution::class)->sync($workflow);
            DB::commit();
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('SYNCED', $output);
            $this->assertNotNull($override->fresh()->revoked_at);
            $this->assertSame('blocked', $step->fresh()->status, 'The retired bypass must not be applied again from an old snapshot.');
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete(); $actor->delete();
        }
    }

    public function test_sales_materialization_does_not_pick_a_legacy_name_that_became_ambiguous_while_waiting(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $client = Client::create(['client_number' => 'AMBIGUOUS-RACE-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'AMBIGUOUS-RACE-'.$suffix, 'client_id' => $client->id,
            'name' => 'Concurrent legacy item', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $invoice = null; $process = null; $pipes = [];
        try {
            $first = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Legacy carton', 'quantity' => 2]);
            $invoice = SalesInvoice::create(['invoice_number' => 'AMBIGUOUS-RACE-SALE-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'draft',
                'project_id' => $project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional client', 'currency' => 'INR']);
            $line = $invoice->items()->create(['product_name' => 'Legacy carton', 'quantity' => 8]);
            $workflow = app(ProjectWorkflowExecution::class)->start($project);
            $this->assertSame(8, $workflow->steps()->where('project_product_id', $first->id)->count());
            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $second = ProjectProduct::create(['project_id' => $project->id, 'product_name' => ' LEGACY CARTON ', 'quantity' => 3]);
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $invoice->id, 'sales'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the sales concurrency worker.');
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $read = [$pipes[1]]; $write = null; $except = null;
            $this->assertSame(0, stream_select($read, $write, $except, 1));
            app(ProjectWorkflowExecution::class)->sync($workflow);
            DB::commit();
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('MATERIALIZED', $output);
            $this->assertNull($line->fresh()->project_product_id);
            $this->assertEquals(2, $first->fresh()->quantity);
            $this->assertEquals(3, $second->fresh()->quantity);
            $this->assertSame(0, $workflow->fresh()->steps()->whereIn('project_product_id', [$first->id, $second->id])->count());
            $this->assertContains($line->id, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($project->id)->pluck('id')->all());
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            if ($invoice) DB::table('sales_invoices')->where('id', $invoice->id)->delete();
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
        }
    }

    public function test_purchase_product_materialization_waits_for_the_workflow_project_lock_before_writing(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $client = Client::create(['client_number' => 'PURCHASE-LOCK-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'PURCHASE-LOCK-'.$suffix, 'client_id' => $client->id,
            'name' => 'Concurrent purchase', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $purchase = null; $invoice = null; $process = null; $pipes = [];
        try {
            $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Shared carton', 'quantity' => 2]);
            $invoice = SalesInvoice::create(['invoice_number' => 'PURCHASE-LOCK-SALE-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'draft',
                'project_id' => $project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional client', 'currency' => 'INR']);
            $invoice->items()->create(['project_product_id' => $product->id, 'product_name' => 'Shared carton', 'quantity' => 2]);
            $purchase = \App\Models\PurchaseInvoice::create(['invoice_number' => 'PURCHASE-LOCK-PO-'.$suffix, 'invoice_type' => 'order', 'status' => 'approved',
                'public_token' => 'purchase-lock-'.$suffix, 'project_id' => $project->id, 'vendor_company_name' => 'Fictional vendor',
                'vendor_bill_number' => 'SUPPLIER-'.$suffix, 'currency' => 'INR']);
            $purchase->items()->create(['project_product_id' => $product->id, 'product_name' => 'Shared carton', 'quantity' => 2]);
            $workflow = app(ProjectWorkflowExecution::class)->start($project);

            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $purchase->id, 'purchase'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the purchase concurrency worker.');
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $read = [$pipes[1]]; $write = null; $except = null;
            $this->assertSame(0, stream_select($read, $write, $except, 1),
                'Purchase materialization must wait on the project lock, not lock a product before requesting it.');
            $this->assertTrue(proc_get_status($process)['running']);
            app(ProjectWorkflowExecution::class)->sync($workflow);
            DB::commit();
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('PRODUCT_UPDATED', $output);
            $this->assertStringContainsString('MATERIALIZED', $output);
            $this->assertSame('SUPPLIER-'.$suffix, $product->fresh()->vendor_invoice_number);
            $this->assertSame(8, $workflow->fresh()->steps()->where('project_product_id', $product->id)->count());
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            if ($purchase) DB::table('purchase_invoices')->where('id', $purchase->id)->delete();
            if ($invoice) DB::table('sales_invoices')->where('id', $invoice->id)->delete();
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
        }
    }

    public function test_purchase_refresh_finds_a_workflow_started_while_its_snapshot_waited(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $client = Client::create(['client_number' => 'PURCHASE-START-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'PURCHASE-START-'.$suffix, 'client_id' => $client->id,
            'name' => 'Purchase and workflow start', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $purchase = null; $invoice = null; $process = null; $pipes = [];
        try {
            $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Shared board', 'quantity' => 2]);
            $invoice = SalesInvoice::create(['invoice_number' => 'PURCHASE-START-SALE-'.$suffix, 'invoice_type' => 'proforma', 'status' => 'draft',
                'project_id' => $project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional client', 'currency' => 'INR']);
            $invoice->items()->create(['project_product_id' => $product->id, 'product_name' => 'Shared board', 'quantity' => 2]);
            $purchase = \App\Models\PurchaseInvoice::create(['invoice_number' => 'PURCHASE-START-PO-'.$suffix, 'invoice_type' => 'order', 'status' => 'approved',
                'public_token' => 'purchase-start-'.$suffix, 'project_id' => $project->id, 'vendor_company_name' => 'Fictional vendor',
                'vendor_bill_number' => 'SUPPLIER-'.$suffix, 'currency' => 'INR']);
            $purchase->items()->create(['project_product_id' => $product->id, 'product_name' => 'Shared board', 'quantity' => 2]);

            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $purchase->id, 'purchase'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the purchase concurrency worker.');
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            $read = [$pipes[1]]; $write = null; $except = null;
            $this->assertSame(0, stream_select($read, $write, $except, 1));
            $workflow = app(ProjectWorkflowExecution::class)->start($project);
            $this->assertSame(0, $workflow->evaluations()->count(), 'The purchase refresh must be the operation that evaluates readiness.');
            DB::commit();
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('MATERIALIZED', $output);
            $this->assertSame('SUPPLIER-'.$suffix, $product->fresh()->vendor_invoice_number);
            $this->assertGreaterThan(0, $workflow->fresh()->evaluations()->where('project_product_id', $product->id)->count(),
                'The new workflow must be refreshed even though the purchase transaction began with an older snapshot.');
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            if ($purchase) DB::table('purchase_invoices')->where('id', $purchase->id)->delete();
            if ($invoice) DB::table('sales_invoices')->where('id', $invoice->id)->delete();
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
        }
    }

    public function test_start_uses_the_workflow_created_while_a_stale_transaction_waited(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $client = Client::create(['client_number' => 'START-RACE-'.$suffix, 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'START-RACE-'.$suffix, 'client_id' => $client->id,
            'name' => 'Workflow start race', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $process = null; $pipes = [];
        try {
            DB::beginTransaction();
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the scope concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ProjectWorkflowScopeConcurrencyWorker.php'), (string) $project->id, 'start'],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the scope concurrency worker.');
            fclose($pipes[0]); stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1])); usleep(300000);
            $this->assertTrue(proc_get_status($process)['running']);
            $workflow = app(ProjectWorkflowExecution::class)->start($project);
            DB::commit();
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('STARTED '.$workflow->id, $output);
            $this->assertSame(1, $project->workflow()->count());
            $this->assertSame(8, $workflow->steps()->count());
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('projects')->where('id', $project->id)->delete();
            $client->delete();
        }
    }

}
