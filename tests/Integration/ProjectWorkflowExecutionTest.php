<?php

namespace Tests\Integration;

use App\Models\Project;
use App\Models\ProjectProduct;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class ProjectWorkflowExecutionTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase(['0001_01_01_000000_create_users_table', '2026_07_18_010000_create_projects_table',
            '2026_07_18_010100_create_project_products_table', '2026_10_19_120000_add_soft_deletes_to_projects']);
        // Only the referenced identity is needed: these tests do not calculate invoice money.
        Schema::create('sales_invoices', fn (Blueprint $table) => $table->id());
        (require database_path('migrations/2026_10_18_170000_create_project_execution_workflow_tables.php'))->up();
    }

    private function project(): Project
    {
        return Project::forceCreate(['project_number' => 'TEST-PROJECT-1', 'public_token' => 'test-workflow-token',
            'client_id' => 1, 'name' => 'Two product project', 'status' => 'planned', 'stage' => 'setup']);
    }

    private function product(Project $project, string $status = 'planned'): ProjectProduct
    {
        return ProjectProduct::forceCreate(['project_id' => $project->id, 'product_name' => 'Carton', 'quantity' => 100, 'status' => $status]);
    }

    public function test_start_is_idempotent_and_cancelled_products_do_not_get_new_lanes(): void
    {
        $project = $this->project();
        $product = $this->product($project);
        $cancelled = $this->product($project, 'cancelled');
        $service = app(ProjectWorkflowExecution::class);
        $workflow = $service->start($project);
        $this->assertSame('standard_execution', $workflow->template_key);
        $this->assertCount(16, $workflow->steps);
        $this->assertCount(8, $workflow->steps->where('project_product_id', $product->id));
        $this->assertCount(0, $workflow->steps->where('project_product_id', $cancelled->id));
        $again = $service->start($project->fresh());
        $this->assertSame($workflow->id, $again->id);
        $this->assertDatabaseCount('project_workflows', 1);
        $this->assertDatabaseCount('project_workflow_steps', 16);
    }

    public function test_product_lanes_are_independent_and_repeated_sync_preserves_completed_work(): void
    {
        $project = $this->project();
        $first = $this->product($project);
        $second = $this->product($project);
        $service = app(ProjectWorkflowExecution::class);
        $workflow = $service->start($project);
        $step = $workflow->steps->where('project_product_id', $first->id)->firstWhere('step_key', 'production_complete');
        $service->updateStep($step, 'completed', null, 'First product finished');
        $this->assertSame('not_started', $workflow->steps->where('project_product_id', $second->id)->firstWhere('step_key', 'production_complete')->fresh()->status);
        $again = $service->start($project->fresh());
        $completed = $again->steps->firstWhere('id', $step->id);
        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame('First product finished', $completed->notes);
        $this->assertGreaterThan(0, $project->fresh()->progress_percent);
        $this->assertLessThan(100, $project->fresh()->progress_percent);
    }

    public function test_financial_terminal_decision_is_not_overwritten_by_workflow_recalculation(): void
    {
        $project = $this->project();
        $workflow = app(ProjectWorkflowExecution::class)->start($project);
        $project->forceFill(['status' => 'cancelled', 'stage' => 'closure'])->save();
        app(ProjectWorkflowExecution::class)->recalculate($workflow);
        $this->assertSame('cancelled', $project->fresh()->status);
        $this->assertSame('closure', $project->fresh()->stage);
    }

    public function test_regular_mass_assignment_cannot_change_workflow_owned_progress_fields(): void
    {
        $project = $this->project();
        $project->fill(['status' => 'completed', 'stage' => 'completed', 'progress_percent' => 100])->save();
        $this->assertSame('planned', $project->fresh()->status);
        $this->assertSame('setup', $project->fresh()->stage);
        $this->assertSame(0, $project->fresh()->progress_percent);
    }
}
