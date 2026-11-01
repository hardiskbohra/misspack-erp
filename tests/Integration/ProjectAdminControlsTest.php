<?php

namespace Tests\Integration;

use App\Models\Client;
use App\Models\ClientPortalUser;
use App\Models\FeedbackRequest;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectFinancialClosure;
use App\Models\ProjectGateEvaluation;
use App\Models\ProjectGateOverride;
use App\Models\ProjectWorkflowStep;
use App\Models\Shipment;
use App\Models\ProjectProduct;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\ProjectApprovals;
use App\Services\ProjectFeedbackClosure;
use App\Services\ProjectWorkflowExecution;
use App\Services\ProjectWorkflowGates;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** End-to-end project override scenarios on an entirely disposable schema. */
class ProjectAdminControlsTest extends TestCase
{
    use IsolatedDatabase;

    private User $admin;
    private Project $project;
    private ProjectProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        $this->admin = User::create(['name' => 'Administrator', 'email' => 'project-admin@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $client = Client::create(['client_number' => 'ADMIN-CLIENT', 'company_name' => 'Fictional client']);
        $this->project = Project::forceCreate(['project_number' => 'ADMIN-PROJECT', 'client_id' => $client->id,
            'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $this->product = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Carton', 'quantity' => 10]);
        app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->actingAs($this->admin);
    }

    private function state(array $values = []): \Illuminate\Testing\TestResponse
    {
        return $this->from('/projects/'.$this->project->id)
            ->patch('/projects/'.$this->project->id.'/admin-state', array_replace([
                'manual_status' => 'on_hold', 'manual_progress_percent' => '73',
                'reason' => 'Administrator reviewed the current execution position.',
            ], $values));
    }

    private function step(string $key): \App\Models\ProjectWorkflowStep
    {
        return $this->project->workflow->steps()->where('step_key', $key)->firstOrFail();
    }

    private function bypass(\App\Models\ProjectWorkflowStep $step, array $values = []): \Illuminate\Testing\TestResponse
    {
        if (in_array($step->step_key, ['artwork_approved', 'pps_approved'], true)) {
            $values = array_replace(['approval_source' => 0], $values);
        }
        return $this->from('/projects/'.$this->project->id.'?tab=workflow')
            ->post('/projects/'.$this->project->id.'/workflow/steps/'.$step->id.'/bypass', array_replace([
                'reason' => 'Administrator accepts this specific outstanding risk.',
            ], $values));
    }

    private function manualClose(array $values = []): \Illuminate\Testing\TestResponse
    {
        return $this->from('/projects/'.$this->project->id.'?tab=workflow')
            ->post('/projects/'.$this->project->id.'/workflow/financial-closure/manual-close', array_replace([
                'reason' => 'Work and outstanding balances will be followed up by the accounts team.',
                'risk_acknowledgement' => '1',
            ], $values));
    }

    public function test_manual_state_persists_through_recalculation_and_can_be_independently_cleared(): void
    {
        $workflow = $this->project->workflow;
        $calculatedBefore = $workflow->calculated_progress;
        $this->state()->assertRedirect()->assertSessionHasNoErrors();
        $project = $this->project->fresh();
        $this->assertSame('on_hold', $project->status);
        $this->assertSame('on_hold', $project->manual_status);
        $this->assertSame(73, $project->progress_percent);
        $this->assertSame(73, $project->manual_progress_percent);
        $this->assertSame($calculatedBefore, $workflow->fresh()->calculated_progress);

        app(ProjectWorkflowExecution::class)->recalculate($workflow->fresh());
        $this->assertSame('on_hold', $project->fresh()->status);
        $this->assertSame(73, $project->fresh()->progress_percent);
        $this->assertSame($calculatedBefore, $workflow->fresh()->calculated_progress);
        $this->get('/projects/'.$project->id)->assertOk()->assertSee('73%')->assertSee('Administrator-set')
            ->assertSee('Calculated workflow');
        $log = $project->logs()->where('event_type', 'admin_state_changed')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringContainsString('reviewed', $log->description);
        $this->assertSame(73, $log->new_values['manual_progress_percent']);
        $this->assertFalse($log->is_public);

        // Clearing only progress does not clear status; zero is an explicit value.
        $this->state(['manual_status' => 'on_hold', 'manual_progress_percent' => '0'])->assertSessionHasNoErrors();
        $this->assertSame(0, $project->fresh()->progress_percent);
        $this->state(['manual_status' => 'on_hold', 'manual_progress_percent' => ''])->assertSessionHasNoErrors();
        $this->assertNull($project->fresh()->manual_progress_percent);
        $this->assertSame($workflow->fresh()->calculated_progress, $project->fresh()->progress_percent);
        $this->assertSame('on_hold', $project->fresh()->status);
        $this->state(['manual_status' => '', 'manual_progress_percent' => ''])->assertSessionHasNoErrors();
        $this->assertNull($project->fresh()->manual_status);
        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_manual_state_validation_does_not_accept_closed_labels_bad_percentages_or_missing_reason(): void
    {
        $before = $this->project->fresh()->only(['status', 'stage', 'progress_percent']);
        $this->state(['manual_status' => 'closed'])->assertSessionHasErrors('manual_status');
        $this->state(['manual_status' => 'closed_with_outstanding_financials'])->assertSessionHasErrors('manual_status');
        $this->state(['manual_progress_percent' => '-1'])->assertSessionHasErrors('manual_progress_percent');
        $this->state(['manual_progress_percent' => '101'])->assertSessionHasErrors('manual_progress_percent');
        $this->state(['manual_progress_percent' => '12.5'])->assertSessionHasErrors('manual_progress_percent');
        $this->state(['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->assertSame($before, $this->project->fresh()->only(['status', 'stage', 'progress_percent']));
        $this->assertSame(0, $this->project->logs()->where('event_type', 'admin_state_changed')->count());
        $this->state(['manual_status' => 'completed', 'manual_progress_percent' => '100'])->assertSessionHasNoErrors();
        $this->assertSame(100, $this->project->fresh()->progress_percent);
        $this->assertSame('completed', $this->project->fresh()->status);
    }

    public function test_state_closure_and_step_bypass_require_an_admin_with_the_sensitive_permission(): void
    {
        $limited = User::create(['name' => 'Project operator', 'email' => 'project-limited@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom',
            'permissions' => ['projects.view', 'projects.manage']]);
        $this->actingAs($limited);
        $this->state()->assertForbidden();
        $this->manualClose()->assertForbidden();
        $this->bypass($this->step('project_setup'))->assertForbidden();
        $this->get('/projects/'.$this->project->id)->assertOk()->assertDontSee('Administrator controls');

        // isAdmin() historically means "not employee"; a made-up role must not be a bypass authority.
        $other = User::create(['name' => 'Other role', 'email' => 'project-other@example.test',
            'password' => 'fixture', 'role' => 'other', 'access_profile' => 'full']);
        $this->actingAs($other);
        $this->state()->assertForbidden();
        $this->manualClose()->assertForbidden();
        $this->bypass($this->step('project_setup'))->assertForbidden();
        $this->assertSame('in_progress', $this->project->fresh()->status);
        $this->assertSame(0, $this->project->fresh()->progress_percent);
        $this->assertDatabaseCount('project_gate_overrides', 0);
        $this->assertDatabaseCount('project_financial_closures', 0);
    }

    public function test_manual_closure_preserves_open_work_outstanding_money_and_reopening_history(): void
    {
        $invoice = SalesInvoice::create(['invoice_number' => 'ADMIN-PI', 'invoice_type' => 'proforma',
            'status' => 'sent', 'public_token' => 'admin-fixture-invoice', 'client_id' => $this->project->client_id,
            'project_id' => $this->project->id, 'currency' => 'INR', 'total_amount' => '125.574']);
        $beforeInvoice = $invoice->fresh()->only(['status', 'total_amount', 'amount_paid']);
        $this->state(['manual_status' => '', 'manual_progress_percent' => '42'])->assertSessionHasNoErrors();
        $this->manualClose(['risk_acknowledgement' => ''])->assertSessionHasErrors('risk_acknowledgement');
        $this->assertDatabaseCount('project_financial_closures', 0);
        $this->manualClose()->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('manually_closed', $this->project->fresh()->status);
        $this->assertSame(42, $this->project->fresh()->progress_percent);
        $this->assertNotNull($this->project->fresh()->completed_at);
        $closure = ProjectFinancialClosure::query()->firstOrFail();
        $this->assertSame('manually_closed', $closure->closure_type);
        $this->assertSame($this->admin->id, $closure->approved_by);
        $this->assertTrue($closure->financial_snapshot['has_outstanding_financials']);
        $this->assertNotEmpty($closure->financial_snapshot['open_operational_steps']);
        $this->assertEqualsWithDelta(125.574, $closure->financial_snapshot['totals']['receivables']['INR'], 0.000001);
        $this->assertSame($beforeInvoice, $invoice->fresh()->only(['status', 'total_amount', 'amount_paid']));
        $this->assertSame(0, FeedbackRequest::where('project_id', $this->project->id)->count());
        app(ProjectWorkflowExecution::class)->recalculate($this->project->workflow->fresh());
        $this->assertSame('manually_closed', $this->project->fresh()->status);
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertSee('Manually closed')
            ->assertSee('remain active');
        $this->manualClose()->assertSessionHasErrors('closure');
        $this->post('/projects/'.$this->project->id.'/workflow/financial-closure/reopen', [
            'reason' => 'Reopen for ongoing delivery and accounting review.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $this->project->fresh()->status);
        $this->assertSame(42, $this->project->fresh()->progress_percent);
        $this->assertDatabaseCount('project_financial_closures', 2);
        $this->assertSame('reopened', ProjectFinancialClosure::latest('id')->firstOrFail()->closure_type);
        $this->assertSame($beforeInvoice, $invoice->fresh()->only(['status', 'total_amount', 'amount_paid']));
    }

    public function test_each_project_step_can_be_individually_bypassed_and_source_data_is_not_falsified(): void
    {
        $keys = ['project_setup', 'commercial_baseline', 'advance_received', 'procurement_ready',
            'artwork_approved', 'pps_approved', 'production_complete', 'quality_passed', 'packing_verified',
            'dispatch_balance', 'dispatch_approved', 'shipment_dispatched', 'delivery_reconciled',
            'delivery_complete', 'feedback_closure'];
        foreach ($keys as $key) {
            $step = $this->step($key);
            $this->bypass($step)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('skipped', $step->fresh()->status, $key);
            $this->assertStringContainsString('override', strtolower((string) $step->fresh()->notes), $key);
        }
        $this->assertSame('planned', $this->product->fresh()->status);
        $this->assertEqualsWithDelta(0, (float) $this->product->fresh()->production_completed_quantity, 0.000001);
        $this->assertDatabaseCount('project_approval_requests', 0);
        $this->assertDatabaseCount('project_financial_closures', 0);
        $this->assertSame(0, FeedbackRequest::where('project_id', $this->project->id)->count());
        $this->assertGreaterThanOrEqual(count($keys), ProjectGateOverride::count());
        $feedback = $this->step('feedback_closure');
        app(ProjectFeedbackClosure::class)->sync($this->project->workflow->fresh(), $this->admin->id, false);
        $this->assertSame('skipped', $feedback->fresh()->status);
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertSee('Administrator bypass');
    }

    public function test_missing_approval_bypass_expires_when_a_request_is_created_and_new_version_is_distinct(): void
    {
        $step = $this->step('artwork_approved');
        $this->bypass($step)->assertSessionHasNoErrors();
        $this->assertSame('skipped', $step->fresh()->status);
        $missing = ProjectGateOverride::latest('id')->firstOrFail();
        $attachment = ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'category' => 'artwork', 'file_path' => 'fixture/artwork.pdf', 'original_name' => 'artwork.pdf']);
        $approval = app(ProjectApprovals::class)->create($this->project->workflow->fresh(), $this->product->id,
            'artwork', 'Artwork', null, null, $attachment, str_repeat('a', 64), $this->admin->id);
        $this->assertNotNull($missing->fresh()->revoked_at);
        $this->assertSame('waiting_client', $step->fresh()->status);
        $this->bypass($step, ['approval_source' => $approval->id, 'approval_version' => 1])->assertSessionHasNoErrors();
        $this->assertSame('skipped', $step->fresh()->status);
        $this->assertTrue($approval->fresh()->hasActiveOverride());
        $this->assertSame('open', $approval->fresh()->status); // the client has not approved anything
        app(ProjectApprovals::class)->addVersion($approval, $attachment, str_repeat('b', 64), null, null, $this->admin->id);
        $this->assertFalse($approval->fresh()->hasActiveOverride());
        $this->assertSame('waiting_client', $step->fresh()->status);
    }

    public function test_changed_gate_facts_expire_a_bypass_and_revoking_stale_evidence_does_not_change_the_new_step(): void
    {
        $step = $this->step('advance_received');
        $this->bypass($step)->assertRedirect()->assertSessionHasNoErrors();
        $oldOverride = ProjectGateOverride::latest('id')->firstOrFail();
        $this->assertSame('skipped', $step->fresh()->status);
        $invoice = SalesInvoice::create(['invoice_number' => 'NEW-BASELINE', 'invoice_type' => 'proforma',
            'status' => 'sent', 'public_token' => 'new-baseline-fixture', 'client_id' => $this->project->client_id,
            'project_id' => $this->project->id, 'currency' => 'INR', 'total_amount' => 100,
            'advance_required' => true, 'advance_percentage' => 50]);
        $this->project->workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
        app(ProjectWorkflowGates::class)->evaluateAll($this->project->workflow->fresh(), $this->admin->id);
        $this->assertSame('blocked', $step->fresh()->status);
        $this->patch('/projects/'.$this->project->id.'/workflow/overrides/'.$oldOverride->id.'/revoke', [
            'revocation_reason' => 'Old facts no longer apply.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($oldOverride->fresh()->revoked_at);
        $this->assertSame('blocked', $step->fresh()->status);
    }

    public function test_cross_project_bypass_and_invalid_reasons_are_rejected_without_any_audit_or_mutation(): void
    {
        $other = Project::forceCreate(['project_number' => 'OTHER-PROJECT', 'client_id' => $this->project->client_id,
            'name' => 'Other project', 'status' => 'planned', 'stage' => 'setup']);
        $otherWorkflow = app(ProjectWorkflowExecution::class)->start($other, $this->admin->id);
        $foreign = $otherWorkflow->steps->firstWhere('step_key', 'project_setup');
        $this->bypass($foreign)->assertNotFound();
        $this->bypass($this->step('project_setup'), ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('project_gate_overrides', 0);
        $this->assertSame('not_started', $foreign->fresh()->status);
        $this->assertSame('not_started', $this->step('project_setup')->fresh()->status);
    }
    public function test_a_legacy_project_restores_its_prior_state_and_can_be_manually_closed_without_a_workflow(): void
    {
        $legacy = Project::forceCreate(['project_number' => 'ADMIN-LEGACY', 'client_id' => $this->project->client_id,
            'name' => 'Legacy project', 'status' => 'on_hold', 'progress_percent' => 45, 'stage' => 'setup', 'currency' => 'INR']);
        $this->get('/projects/'.$legacy->id)->assertOk()->assertSee('standard workflow will be started');
        $endpoint = '/projects/'.$legacy->id.'/admin-state';
        $this->patch($endpoint, ['manual_status' => 'completed', 'manual_progress_percent' => 100,
            'reason' => 'Legacy execution was reviewed by the administrator.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $legacy->fresh()->status);
        $this->assertSame(100, $legacy->fresh()->progress_percent);
        $this->patch($endpoint, ['manual_status' => '', 'manual_progress_percent' => '',
            'reason' => 'Return the legacy project to its recorded state.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('on_hold', $legacy->fresh()->status);
        $this->assertSame(45, $legacy->fresh()->progress_percent);
        $this->assertNull($legacy->fresh()->workflow);

        $this->post('/projects/'.$legacy->id.'/workflow/financial-closure/manual-close', [
            'reason' => 'The legacy work requires follow up after this manual closure.', 'risk_acknowledgement' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('manually_closed', $legacy->fresh()->status);
        $this->assertNotNull($legacy->fresh()->workflow);
        $this->assertNotEmpty($legacy->financialClosures()->firstOrFail()->financial_snapshot['open_operational_steps']);
    }

    public function test_schema_downgrade_refuses_to_erase_active_or_soft_deleted_decisions(): void
    {
        $this->state()->assertSessionHasNoErrors();
        $this->project->delete();
        $migration = require database_path('migrations/2026_11_01_100000_add_project_manual_state_overrides.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot remove project override columns');
        $migration->down();
    }

    public function test_legacy_override_endpoints_and_foreign_ids_do_not_bypass_the_admin_or_ownership_checks(): void
    {
        $workflow = $this->project->workflow;
        app(ProjectWorkflowGates::class)->evaluateAll($workflow, $this->admin->id);
        $evaluation = $workflow->evaluations()->where('gate_key', 'advance_received')->latest('id')->firstOrFail();
        $attachment = ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'category' => 'artwork', 'title' => 'Approval file', 'file_path' => 'fixture.pdf', 'original_name' => 'fixture.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 10, 'extension' => 'pdf', 'uploaded_by' => $this->admin->id]);
        $approval = app(ProjectApprovals::class)->create($workflow, $this->product->id, 'artwork', 'Artwork', null, null, $attachment, hash('sha256', 'a'), $this->admin->id);
        $other = User::create(['name' => 'Other role', 'email' => 'old-endpoints-other@example.test',
            'password' => 'fixture', 'role' => 'other', 'access_profile' => 'full']);
        $this->actingAs($other);
        $this->post(route('projects.workflow.gates.override', [$this->project, $evaluation]), ['reason' => 'Not authorised to accept this risk.'])->assertForbidden();
        $this->post(route('projects.approvals.override', [$this->project, $approval]), ['override_reason' => 'Not authorised to accept this risk.'])->assertForbidden();
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertDontSee('Administrator bypass');
        $this->assertFalse($approval->fresh()->hasActiveOverride());
        $this->assertNull($evaluation->fresh()->activeOverride);

        $this->actingAs($this->admin);
        $this->post(route('projects.workflow.gates.override', [$this->project, $evaluation]), ['reason' => 'Administrator accepts this gate risk.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('projects.approvals.override', [$this->project, $approval]), ['override_reason' => 'Administrator accepts this approval risk.', 'approval_version' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $override = $evaluation->fresh()->activeOverride;
        $foreign = Project::forceCreate(['project_number' => 'ADMIN-FOREIGN', 'client_id' => $this->project->client_id, 'name' => 'Another project', 'currency' => 'INR']);
        $foreignWorkflow = app(ProjectWorkflowExecution::class)->start($foreign, $this->admin->id);
        $foreignStep = $foreignWorkflow->steps()->where('step_key', 'project_setup')->firstOrFail();
        $this->bypass($foreignStep)->assertNotFound();
        $this->patch(route('projects.workflow.overrides.revoke', [$foreign, $override]), ['revocation_reason' => 'Foreign project'])->assertNotFound();
        $this->patch(route('projects.approvals.override.revoke', [$foreign, $approval]), ['override_revocation_reason' => 'Foreign project'])->assertNotFound();
        $this->actingAs($other);
        $this->patch(route('projects.workflow.overrides.revoke', [$this->project, $override]), ['revocation_reason' => 'Not authorised'])->assertForbidden();
        $this->patch(route('projects.approvals.override.revoke', [$this->project, $approval]), ['override_revocation_reason' => 'Not authorised'])->assertForbidden();
        $this->assertNull($override->fresh()->revoked_at);
        $this->assertTrue($approval->fresh()->hasActiveOverride());
    }

    public function test_an_unrecalculated_source_change_cannot_be_approved_by_posting_an_old_evaluation(): void
    {
        $workflow = $this->project->workflow;
        app(ProjectWorkflowGates::class)->evaluateAll($workflow, $this->admin->id);
        $old = $workflow->evaluations()->where('gate_key', 'advance_received')->latest('id')->firstOrFail();
        $invoice = SalesInvoice::create(['invoice_number' => 'ADMIN-STALE-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'client_id' => $this->project->client_id, 'project_id' => $this->project->id, 'client_company_name' => 'Fictional client',
            'invoice_date' => '2026-11-01', 'currency' => 'INR', 'total_amount' => 1000, 'advance_required' => true, 'advance_percentage' => 50]);
        $workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
        // No screen or scheduled job has refreshed the old gate yet.
        $this->post(route('projects.workflow.gates.override', [$this->project, $old]), ['reason' => 'Attempt to approve the old missing-baseline facts.'])
            ->assertRedirect()->assertSessionHasErrors('reason');
        $this->assertNull($old->fresh()->activeOverride);
        app(ProjectWorkflowGates::class)->evaluateAll($workflow->fresh(), $this->admin->id);
        $this->assertNotSame($old->id, $workflow->evaluations()->where('gate_key', 'advance_received')->latest('id')->value('id'));
    }

    public function test_bypass_creation_and_revocation_are_atomic_when_a_later_write_fails(): void
    {
        $step = $this->step('project_setup');
        $this->withoutExceptionHandling();
        ProjectGateOverride::creating(function () { throw new \RuntimeException('Simulated audit write failure'); });
        try {
            $this->bypass($step);
            $this->fail('The simulated audit failure must escape.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit write failure', $exception->getMessage());
        } finally {
            ProjectGateOverride::flushEventListeners();
        }
        $this->assertFalse(ProjectGateEvaluation::query()->where('project_workflow_id', $step->project_workflow_id)
            ->where('gate_key', 'project_setup')->exists(), 'The missing-source evaluation must roll back with its failed audit.');
        $this->assertSame('not_started', $step->fresh()->status);

        $this->bypass($step)->assertRedirect()->assertSessionHasNoErrors();
        $override = ProjectGateOverride::query()->whereHas('evaluation', fn ($q) => $q->where('gate_key', 'project_setup'))->firstOrFail();
        ProjectWorkflowStep::updating(function ($updated) use ($step) {
            if ($updated->id === $step->id) throw new \RuntimeException('Simulated step write failure');
        });
        try {
            $this->patch(route('projects.workflow.overrides.revoke', [$this->project, $override]), ['revocation_reason' => 'Evidence is now available.']);
            $this->fail('The simulated step failure must escape.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated step write failure', $exception->getMessage());
        } finally {
            ProjectWorkflowStep::flushEventListeners();
        }
        $this->assertNull($override->fresh()->revoked_at, 'The revocation must roll back with its failed step update.');
        $this->assertSame('skipped', $step->fresh()->status);
    }

    public function test_advance_bypass_enables_the_linked_production_action_only_until_source_facts_change(): void
    {
        $url = route('projects.products.update', $this->product);
        $data = ['product_name' => 'Carton', 'quantity' => 10, 'status' => 'in_production', 'production_completed_quantity' => 0];
        $this->from('/projects/'.$this->project->id)->put($url, $data)->assertSessionHasErrors('status');
        $this->bypass($this->step('advance_received'))->assertRedirect()->assertSessionHasNoErrors();
        $this->put($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('in_production', $this->product->fresh()->status);
        $this->put($url, array_replace($data, ['status' => 'planned']))->assertRedirect()->assertSessionHasNoErrors();
        $invoice = SalesInvoice::create(['invoice_number' => 'ADMIN-ACTION-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'client_id' => $this->project->client_id, 'project_id' => $this->project->id, 'client_company_name' => 'Fictional client',
            'invoice_date' => '2026-11-01', 'currency' => 'INR', 'total_amount' => 1000, 'advance_required' => true, 'advance_percentage' => 50]);
        $this->project->workflow->update(['commercial_sales_invoice_id' => $invoice->id]);
        $this->put($url, $data)->assertSessionHasErrors('status');
        $this->assertSame('planned', $this->product->fresh()->status);
    }

    public function test_individual_dispatch_bypasses_enable_only_the_linked_shipment_with_matching_evidence(): void
    {
        $shipment = Shipment::create(['shipment_number' => 'ADMIN-SHIPMENT', 'identity_name' => 'Project cartons', 'project_id' => $this->project->id,
            'client_id' => $this->project->client_id, 'status' => 'planning', 'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
        $item = $shipment->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Carton', 'quantity' => 2, 'unit' => 'pcs']);
        $history = route('shipments.history.store', $shipment);
        $data = ['status' => 'picked_up', 'remarks' => 'Pickup for the project', 'notify_client' => false];
        $this->from('/shipments/'.$shipment->id)->post($history, $data)->assertSessionHasErrors('status');
        $this->bypass($this->step('dispatch_balance'))->assertRedirect()->assertSessionHasNoErrors();
        $this->post($history, $data)->assertSessionHasErrors('status');
        $this->bypass($this->step('dispatch_approved'))->assertRedirect()->assertSessionHasNoErrors();
        $item->update(['quantity' => 3]);
        $this->post($history, $data)->assertSessionHasErrors('status');
        $this->assertSame('planning', $shipment->fresh()->status);
        $this->bypass($this->step('dispatch_approved'))->assertRedirect()->assertSessionHasNoErrors();
        $this->post($history, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('picked_up', $shipment->fresh()->status);
        $this->assertSame(0, $this->project->qualityInspections()->count());
        $this->assertSame(0, $this->project->approvalRequests()->count());
    }

    public function test_revoking_a_live_feedback_override_restores_the_waiting_for_client_state(): void
    {
        $workflow = $this->project->workflow;
        $this->step('commercial_closure')->update(['status' => 'completed']);
        $evaluation = app(ProjectFeedbackClosure::class)->sync($workflow->fresh(), $this->admin->id, true);
        $this->assertSame('blocked', $evaluation->result);
        $this->assertSame('waiting_client', $this->step('feedback_closure')->fresh()->status);
        $this->bypass($this->step('feedback_closure'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('skipped', $this->step('feedback_closure')->fresh()->status);
        $override = $evaluation->fresh()->activeOverride;
        $this->patch(route('projects.workflow.overrides.revoke', [$this->project, $override]), ['revocation_reason' => 'The client will respond.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('waiting_client', $this->step('feedback_closure')->fresh()->status);
    }

    public function test_direct_versioned_approval_override_and_revocation_roll_back_when_step_updates_fail(): void
    {
        $workflow = $this->project->workflow;
        $attachment = ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'category' => 'artwork', 'title' => 'Approval file', 'file_path' => 'fixture.pdf', 'original_name' => 'fixture.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 10, 'extension' => 'pdf', 'uploaded_by' => $this->admin->id]);
        $approval = app(ProjectApprovals::class)->create($workflow, $this->product->id, 'artwork', 'Artwork', null, null, $attachment, hash('sha256', 'version one'), $this->admin->id);
        $step = $this->step('artwork_approved');
        $this->withoutExceptionHandling();
        $failUpdate = static function ($updated) use ($step) {
            if ($updated->id === $step->id) throw new \RuntimeException('Simulated approval-step failure');
        };
        ProjectWorkflowStep::updating($failUpdate);
        try {
            $this->post(route('projects.approvals.override', [$this->project, $approval]), ['override_reason' => 'Administrator accepts this specific version risk.', 'approval_version' => 1]);
            $this->fail('The simulated approval-step failure must escape.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated approval-step failure', $exception->getMessage());
        } finally {
            ProjectWorkflowStep::flushEventListeners();
        }
        $this->assertFalse($approval->fresh()->hasActiveOverride());
        $this->assertSame(0, $approval->overrides()->count());
        $this->assertSame('waiting_client', $step->fresh()->status);

        $this->post(route('projects.approvals.override', [$this->project, $approval]), ['override_reason' => 'Administrator accepts this specific version risk.', 'approval_version' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $override = $approval->fresh()->activeOverrideRecord();
        ProjectWorkflowStep::updating($failUpdate);
        try {
            $this->patch(route('projects.approvals.override.revoke', [$this->project, $approval]), ['override_revocation_reason' => 'The accepted risk was withdrawn.', 'override_id' => $override->id]);
            $this->fail('The simulated approval-step failure must escape.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated approval-step failure', $exception->getMessage());
        } finally {
            ProjectWorkflowStep::flushEventListeners();
        }
        $this->assertNull($override->fresh()->revoked_at);
        $this->assertSame('skipped', $step->fresh()->status);
    }

    public function test_a_stale_missing_request_or_approval_version_cannot_authorize_or_revoke_a_newer_client_file(): void
    {
        $workflow = $this->project->workflow;
        $step = $this->step('artwork_approved');
        $attachment = ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'category' => 'artwork', 'title' => 'Approval file', 'file_path' => 'fixture.pdf', 'original_name' => 'fixture.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 10, 'extension' => 'pdf', 'uploaded_by' => $this->admin->id]);
        $approvals = app(ProjectApprovals::class);
        $approval = $approvals->create($workflow, $this->product->id, 'artwork', 'Artwork', null, null, $attachment, hash('sha256', 'one'), $this->admin->id);
        // The administrator's open form described a missing request, but one was created before submission.
        $this->bypass($step, ['approval_source' => 0])->assertRedirect()->assertSessionHasErrors('reason');
        $this->assertFalse($approval->fresh()->hasActiveOverride());
        $approvals->addVersion($approval, $attachment, hash('sha256', 'two'), 'New file', null, $this->admin->id);
        $this->post(route('projects.approvals.override', [$this->project, $approval]), [
            'approval_version' => 1, 'override_reason' => 'This reason relates only to the old file.',
        ])->assertRedirect()->assertSessionHasErrors('override_reason');
        $this->bypass($step, ['approval_source' => $approval->id, 'approval_version' => 1])->assertRedirect()->assertSessionHasErrors('reason');
        $this->assertFalse($approval->fresh()->hasActiveOverride());
        $this->bypass($step, ['approval_source' => $approval->id, 'approval_version' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $oldOverride = $approval->fresh()->activeOverrideRecord();
        $this->assertSame(2, $oldOverride->version_number);
        $approvals->addVersion($approval->fresh(), $attachment, hash('sha256', 'three'), 'Newer file', null, $this->admin->id);
        $this->bypass($step, ['approval_source' => $approval->id, 'approval_version' => 3])->assertRedirect()->assertSessionHasNoErrors();
        $newOverride = $approval->fresh()->activeOverrideRecord();
        $this->patch(route('projects.approvals.override.revoke', [$this->project, $approval]), [
            'override_id' => $oldOverride->id, 'override_revocation_reason' => 'This decision related to the old file.',
        ])->assertRedirect()->assertSessionHasErrors('override_revocation_reason');
        $this->assertSame($newOverride->id, $approval->fresh()->activeOverrideRecord()->id);
        $this->assertNull($newOverride->fresh()->revoked_at);
    }

    public function test_a_manual_step_service_update_cannot_overwrite_an_active_bypass(): void
    {
        $step = $this->step('project_setup');
        $this->bypass($step)->assertRedirect()->assertSessionHasNoErrors();
        try {
            app(ProjectWorkflowExecution::class)->updateStep($step->fresh(), 'completed', $this->admin->id);
            $this->fail('The active bypass must be revoked before editing its step.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('skipped', $step->fresh()->status);
    }

    public function test_manual_state_service_rejects_invalid_values_even_when_called_without_the_http_validator(): void
    {
        $service = app(\App\Services\ProjectAdminState::class);
        foreach ([['closed', 20, 'A clear and substantive reason.'], ['on_hold', 101, 'A clear and substantive reason.'],
            ['on_hold', -1, 'A clear and substantive reason.'], ['on_hold', 20, '            ']] as [$status, $progress, $reason]) {
            try {
                $service->update($this->project, $this->admin, $status, $progress, $reason);
                $this->fail('The authoritative service must reject invalid state or a blank reason.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame('in_progress', $this->project->fresh()->status);
        $this->assertSame(0, $this->project->fresh()->progress_percent);
        $this->assertSame(0, $this->project->logs()->where('event_type', 'admin_state_changed')->count());
    }

    public function test_client_portal_shows_published_progress_without_revealing_private_reasons_or_requiring_a_catalogue_product(): void
    {
        $this->state()->assertRedirect()->assertSessionHasNoErrors();
        $this->project->forceFill(['show_client_portal' => true])->save();
        $client = $this->project->client;
        $client->forceFill(['portal_enabled' => true])->save();
        $portalUser = ClientPortalUser::create(['client_id' => $client->id, 'username' => 'project-portal-fixture',
            'password' => bcrypt('fixture'), 'portal_enabled' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->withSession(['client_portal_user_id' => $portalUser->id]);
        $this->get('/client-portal/projects')->assertOk()->assertSee('73%')->assertSee('Set by office');
        $this->get('/client-portal/projects/'.$this->project->id)->assertOk()->assertSee('73%')
            ->assertSee('Carton')->assertDontSee('Administrator reviewed the current execution position.');
        $this->assertNull($this->product->product_id);
    }

    public function test_manual_state_is_preserved_across_closure_and_reopening_until_explicitly_cleared(): void
    {
        $this->state()->assertRedirect()->assertSessionHasNoErrors();
        $this->manualClose()->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('manually_closed', $this->project->fresh()->status);
        $this->assertSame('on_hold', $this->project->fresh()->manual_status);
        $this->assertSame(73, $this->project->fresh()->manual_progress_percent);
        $this->get('/projects/'.$this->project->id)->assertOk()->assertSee('resumes after reopening');
        // Updating progress while closed must not inadvertently clear the status override.
        $this->state(['manual_status' => 'on_hold', 'manual_progress_percent' => 64])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('manually_closed', $this->project->fresh()->status);
        $this->assertSame('on_hold', $this->project->fresh()->manual_status);
        $this->post(route('projects.workflow.financial-closure.reopen', $this->project), ['reason' => 'The project needs further work.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('on_hold', $this->project->fresh()->status);
        $this->assertSame(64, $this->project->fresh()->progress_percent);
        $this->state(['manual_status' => '', 'manual_progress_percent' => ''])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($this->project->fresh()->manual_status);
        $this->assertNull($this->project->fresh()->manual_progress_percent);
        $this->assertSame('in_progress', $this->project->fresh()->status);
    }

    public function test_direct_approval_override_service_requires_admin_permission_for_recording_and_revoking(): void
    {
        $attachment = ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'category' => 'artwork', 'title' => 'Approval file', 'file_path' => 'fixture.pdf', 'original_name' => 'fixture.pdf',
            'mime_type' => 'application/pdf', 'file_size' => 10, 'extension' => 'pdf', 'uploaded_by' => $this->admin->id]);
        $service = app(ProjectApprovals::class);
        $approval = $service->create($this->project->workflow, $this->product->id, 'artwork', 'Artwork', null, null,
            $attachment, hash('sha256', 'direct-auth'), $this->admin->id);
        $other = User::create(['name' => 'Operator', 'email' => 'direct-approval-operator@example.test',
            'password' => 'fixture', 'role' => 'employee', 'access_profile' => 'full']);
        try {
            $service->override($approval, 'An operator cannot approve this risk.', $other->id, 1);
            $this->fail('Non-admin approval bypass must be forbidden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertFalse($approval->fresh()->hasActiveOverride());
        $service->override($approval, 'Administrator reviewed this approval risk.', $this->admin->id, 1);
        $override = $approval->fresh()->activeOverrideRecord();
        try {
            $service->revokeOverride($approval, 'Operator cannot revoke this decision.', $other->id, $override->id);
            $this->fail('Non-admin approval bypass revocation must be forbidden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertNull($override->fresh()->revoked_at);
    }

}
