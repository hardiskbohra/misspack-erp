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
}
