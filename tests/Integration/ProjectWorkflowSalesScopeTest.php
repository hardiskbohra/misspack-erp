<?php

namespace Tests\Integration;

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\ProjectWorkflowStep;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectFinancialClosure;
use App\Services\ProjectOperationalReadiness;
use App\Services\ProjectWorkflowExecution;
use App\Services\ShipmentDispatchAuthorization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Sales workflow scope on a disposable full schema, with purchase obligations intact. */
class ProjectWorkflowSalesScopeTest extends TestCase
{
    use IsolatedDatabase;

    private User $admin;
    private Project $project;
    private ProjectProduct $sold;
    private ProjectProduct $bought;
    private SalesInvoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        Storage::fake('local');
        Storage::fake('public');
        $this->admin = User::create(['name' => 'Sales scope admin', 'email' => 'sales-scope@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $client = Client::create(['client_number' => 'SCOPE-CLIENT', 'company_name' => 'Fictional client']);
        $this->project = Project::forceCreate(['project_number' => 'SCOPE-PROJECT', 'client_id' => $client->id,
            'name' => 'Fictional scope project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $this->sold = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Sold carton', 'quantity' => 5]);
        $this->bought = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Purchased board', 'quantity' => 5]);
        $this->invoice = SalesInvoice::create(['invoice_number' => 'SCOPE-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'project_id' => $this->project->id, 'client_id' => $client->id, 'client_company_name' => 'Fictional client',
            'currency' => 'INR', 'total_amount' => 0]);
        $this->invoice->items()->create(['project_product_id' => $this->sold->id, 'product_name' => 'Sold carton', 'quantity' => 5]);
        $purchase = PurchaseInvoice::create(['invoice_number' => 'SCOPE-PO', 'invoice_type' => 'order', 'status' => 'approved',
            'public_token' => 'scope-purchase-token', 'project_id' => $this->project->id, 'vendor_company_name' => 'Fictional vendor', 'currency' => 'INR']);
        $purchase->items()->create(['project_product_id' => $this->bought->id, 'product_name' => 'Purchased board', 'quantity' => 5]);
        $this->actingAs($this->admin);
    }

    public function test_only_sales_items_create_active_product_rules_and_purchase_history_remains_intact(): void
    {
        $execution = app(ProjectWorkflowExecution::class);
        $workflow = $execution->start($this->project, $this->admin->id);
        $this->assertSame(16, $workflow->steps()->count());
        $this->assertSame(8, $workflow->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertSame(0, $workflow->steps()->where('project_product_id', $this->bought->id)->count());
        app(ProjectOperationalReadiness::class)->evaluateAll($workflow, $this->admin->id);
        $this->assertSame(0, $workflow->evaluations()->where('project_product_id', $this->bought->id)->count());

        // Simulate a historical purchase-only row created by the earlier workflow.
        $source = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'artwork_approved')->firstOrFail();
        $historical = $source->replicate();
        $historical->project_product_id = $this->bought->id;
        $historical->notes = 'Existing purchase-only workflow history.';
        $historical->save();
        $this->assertSame(16, $workflow->fresh()->steps()->count());
        $this->assertSame('Existing purchase-only workflow history.', $historical->fresh()->notes);
        $this->from('/projects/'.$this->project->id.'?tab=workflow')
            ->post(route('projects.workflow.steps.bypass', [$this->project, $historical]), [
                'reason' => 'This historical purchase-only step must not be bypassed.', 'approval_source' => 0,
            ])->assertSessionHasErrors();
        $snapshot = app(ProjectFinancialClosure::class)->snapshot($this->project->fresh());
        $this->assertCount(1, collect($snapshot['open_operational_steps'])->where('step_key', 'artwork_approved'), 'Only the sales item contributes its artwork check.');
        $this->assertSame(1, $this->project->purchaseOrders()->count());
        $this->assertSame(2, $this->project->products()->count());

        $response = $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);
        $workflowText = $xpath->query('//*[@id="project-panel-workflow"]')->item(0)->textContent;
        $this->assertStringContainsString('Sold carton', $workflowText);
        $this->assertStringNotContainsString('Purchased board', $workflowText);
        $this->get('/projects/'.$this->project->id.'?tab=products')->assertOk()->assertSee('Purchased board');
    }

    public function test_unlinked_legacy_sales_items_match_only_an_unambiguous_same_project_product(): void
    {
        $legacy = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Legacy sales box', 'quantity' => 3]);
        $legacyLine = $this->invoice->items()->create(['product_name' => 'Legacy sales box', 'quantity' => 3]);
        $execution = app(ProjectWorkflowExecution::class);
        $workflow = $execution->start($this->project, $this->admin->id);
        $this->assertSame(8, $workflow->steps()->where('project_product_id', $legacy->id)->count());
        $this->assertNull($legacyLine->fresh()->project_product_id, 'Workflow matching must not rewrite a saved sales invoice line.');
        $sameName = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Legacy sales box', 'quantity' => 3]);
        $execution->sync($workflow, $this->project->fresh());
        $this->assertSame(0, $workflow->fresh()->steps()->whereIn('project_product_id', [$legacy->id, $sameName->id])->count());
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertSee('Unmatched sales invoice items need review');
    }

    public function test_saving_an_invoice_does_not_assign_ambiguous_sales_lines_to_arbitrary_products(): void
    {
        $first = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Legacy box', 'quantity' => 2]);
        $second = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => '  LEGACY BOX  ', 'quantity' => 3]);
        $catalogue = \App\Models\Product::create(['product_number' => 'SCOPE-AMBIGUOUS', 'name' => 'Catalogue label']);
        $third = ProjectProduct::create(['project_id' => $this->project->id, 'product_id' => $catalogue->id, 'product_name' => 'First catalogue match', 'quantity' => 4]);
        $fourth = ProjectProduct::create(['project_id' => $this->project->id, 'product_id' => $catalogue->id, 'product_name' => 'Second catalogue match', 'quantity' => 5]);
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['items' => [
            ['product_name' => 'Legacy box', 'quantity' => 8, 'unit' => 'pcs', 'unit_price' => 1],
            ['product_id' => $catalogue->id, 'product_name' => 'Catalogue label', 'quantity' => 9, 'unit' => 'pcs', 'unit_price' => 1],
        ]]))->assertRedirect()->assertSessionHasNoErrors();
        $lines = $this->invoice->fresh()->items()->orderBy('sort_order')->get();
        $this->assertCount(2, $lines);
        foreach ($lines as $line) $this->assertNull($line->project_product_id, 'Ambiguous sales items must remain unresolved.');
        $this->assertEquals(2, $first->fresh()->quantity);
        $this->assertEquals(3, $second->fresh()->quantity);
        $this->assertEquals(4, $third->fresh()->quantity);
        $this->assertEquals(5, $fourth->fresh()->quantity);
        $this->assertSame(0, $workflow->steps()->whereIn('project_product_id', [$first->id, $second->id, $third->id, $fourth->id])->count());
        $this->assertCount(2, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($this->project->id));
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertSee('Unmatched sales invoice items need review');
    }

    public function test_saving_an_unchanged_project_preserves_an_unresolved_foreign_product_link(): void
    {
        $other = Project::forceCreate(['project_number' => 'SCOPE-EDIT-FOREIGN', 'client_id' => $this->project->client_id,
            'name' => 'Foreign project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $foreign = ProjectProduct::create(['project_id' => $other->id, 'product_name' => 'Shared name', 'quantity' => 1]);
        $local = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Shared name', 'quantity' => 2]);
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['items' => [
            ['project_product_id' => $foreign->id, 'product_name' => 'Shared name', 'quantity' => 7, 'unit' => 'pcs', 'unit_price' => 1],
        ]]))->assertRedirect()->assertSessionHasNoErrors();
        $line = $this->invoice->fresh()->items()->firstOrFail();
        $this->assertSame($foreign->id, $line->project_product_id);
        $this->assertEquals(2, $local->fresh()->quantity);
        $this->assertSame(0, $workflow->steps()->where('project_product_id', $local->id)->count());
        $this->assertContains($line->id, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($this->project->id)->pluck('id')->all());
    }

    public function test_reassigning_an_invoice_requires_an_unambiguous_target_product(): void
    {
        $oldWorkflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $other = Project::forceCreate(['project_number' => 'SCOPE-MOVE-AMBIGUOUS', 'client_id' => $this->project->client_id,
            'name' => 'Target project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $first = ProjectProduct::create(['project_id' => $other->id, 'product_name' => 'Sold carton', 'quantity' => 2]);
        $second = ProjectProduct::create(['project_id' => $other->id, 'product_name' => 'Sold carton', 'quantity' => 3]);
        $newWorkflow = app(ProjectWorkflowExecution::class)->start($other, $this->admin->id);
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['project_id' => $other->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $line = $this->invoice->fresh()->items()->firstOrFail();
        $this->assertNull($line->project_product_id);
        $this->assertSame(0, $oldWorkflow->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertSame(0, $newWorkflow->steps()->whereIn('project_product_id', [$first->id, $second->id])->count());
        $this->assertContains($line->id, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($other->id)->pluck('id')->all());
        $this->assertEquals(2, $first->fresh()->quantity);
        $this->assertEquals(3, $second->fresh()->quantity);
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['project_id' => $other->id,
            'items' => [['project_product_id' => $second->id, 'product_name' => 'Sold carton', 'quantity' => 5, 'unit' => 'pcs', 'unit_price' => 1]]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($second->id, $this->invoice->fresh()->items()->firstOrFail()->project_product_id);
        $this->assertSame(8, $newWorkflow->steps()->where('project_product_id', $second->id)->count());
        $this->assertSame(0, $newWorkflow->steps()->where('project_product_id', $first->id)->count());
    }

    public function test_sales_source_removal_excludes_checks_and_reentry_requires_a_fresh_bypass(): void
    {
        $execution = app(ProjectWorkflowExecution::class);
        $workflow = $execution->start($this->project, $this->admin->id);
        $step = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'artwork_approved')->firstOrFail();
        $this->post(route('projects.workflow.steps.bypass', [$this->project, $step]), [
            'reason' => 'Administrator accepted the original sales item risk.', 'approval_source' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $override = $workflow->evaluations()->where('project_product_id', $this->sold->id)->where('gate_key', 'artwork_approved')->firstOrFail()->activeOverride;
        $this->assertNotNull($override);
        $this->invoice->update(['status' => 'cancelled']);
        $execution->sync($workflow, $this->project->fresh());
        $execution->recalculate($workflow);
        $this->assertSame(0, $workflow->fresh()->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertNotNull($step->fresh(), 'Historical step is not deleted.');
        $this->invoice->update(['status' => 'sent']);
        $execution->sync($workflow, $this->project->fresh());
        $execution->recalculate($workflow);
        $this->assertSame(8, $workflow->fresh()->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertNotSame('skipped', $step->fresh()->status);
        $this->assertNotNull($override->fresh()->revoked_at);
    }

    public function test_purchase_only_shipment_keeps_physical_safeguards_but_not_sales_approval_rules(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $shipment = Shipment::create(['shipment_number' => 'SCOPE-SHIP', 'identity_name' => 'Fictional shipment',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
        $authorization = app(ShipmentDispatchAuthorization::class);
        $purchase = $authorization->inspect($shipment, $workflow, [['project_product_id' => $this->bought->id, 'product_name' => 'Purchased board', 'quantity' => 5]]);
        $text = implode(' ', $purchase['issues']);
        $this->assertStringContainsString('Packaging List', $text);
        $this->assertStringContainsString('QC', $text);
        $this->assertStringContainsString('dispatch documents', $text);
        $this->assertStringNotContainsString('Artwork and PPS approvals', $text);
        $sales = $authorization->inspect($shipment, $workflow, [['project_product_id' => $this->sold->id, 'product_name' => 'Sold carton', 'quantity' => 5]]);
        $this->assertStringContainsString('Artwork and PPS approvals', implode(' ', $sales['issues']));
    }
    private function invoicePayload(array $overrides = []): array
    {
        return array_replace([
            'invoice_number' => $this->invoice->invoice_number, 'invoice_type' => 'proforma', 'status' => 'draft',
            'client_id' => $this->project->client_id, 'project_id' => $this->project->id, 'currency' => 'INR', 'gst_type' => 'export',
            'client_company_name' => 'Fictional client',
            'items' => [['project_product_id' => $this->sold->id, 'product_name' => 'Sold carton', 'quantity' => 5, 'unit' => 'pcs', 'unit_price' => 1]],
        ], $overrides);
    }

    public function test_edit_removal_cancellation_reassignment_and_deletion_refresh_old_and_new_projects(): void
    {
        $execution = app(ProjectWorkflowExecution::class);
        $oldWorkflow = $execution->start($this->project, $this->admin->id);
        $second = SalesInvoice::create(['invoice_number' => 'SCOPE-SECOND', 'invoice_type' => 'tax', 'status' => 'draft',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'currency' => 'INR']);
        $second->items()->create(['project_product_id' => $this->sold->id, 'product_name' => 'Sold carton', 'quantity' => 5]);
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['items' => []]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(8, $oldWorkflow->steps()->where('project_product_id', $this->sold->id)->count(), 'The other active invoice keeps this lane.');
        $this->delete(route('sales-invoices.destroy', $second))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $oldWorkflow->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertSame(8, $oldWorkflow->allSteps()->where('project_product_id', $this->sold->id)->count());
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(8, $oldWorkflow->steps()->where('project_product_id', $this->sold->id)->count());
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['status' => 'cancelled']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $oldWorkflow->steps()->where('project_product_id', $this->sold->id)->count());

        $other = Project::forceCreate(['project_number' => 'SCOPE-OTHER', 'client_id' => $this->project->client_id,
            'name' => 'Another fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $newWorkflow = $execution->start($other, $this->admin->id);
        $this->put(route('sales-invoices.update', $this->invoice), $this->invoicePayload(['project_id' => $other->id]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $newProductId = $this->invoice->fresh()->items()->firstOrFail()->project_product_id;
        $this->assertNotSame($this->sold->id, $newProductId);
        $this->assertSame(0, $oldWorkflow->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertSame(8, $newWorkflow->steps()->where('project_product_id', $newProductId)->count());
        $this->delete(route('sales-invoices.destroy', $this->invoice))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $newWorkflow->steps()->where('project_product_id', $newProductId)->count());
        $this->assertSame(8, $newWorkflow->allSteps()->where('project_product_id', $newProductId)->count());
    }

    public function test_purchase_only_direct_actions_are_guarded_and_production_is_not_blocked_by_sales_advance(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $source = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'production_complete')->firstOrFail();
        $historical = $source->replicate(); $historical->project_product_id = $this->bought->id; $historical->save();
        $this->from('/projects/'.$this->project->id.'?tab=workflow')
            ->patch(route('projects.workflow.steps.update', [$this->project, $historical]), ['status' => 'completed'])->assertStatus(422);
        try {
            app(ProjectWorkflowExecution::class)->updateStep($historical, 'completed', $this->admin->id);
            $this->fail('A direct service call cannot alter a purchase-only control.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('not_started', $historical->fresh()->status);
        $this->post(route('projects.approvals.store', $this->project), ['project_product_id' => $this->bought->id,
            'approval_type' => 'artwork', 'title' => 'Purchase-only artwork',
            'approval_file' => \Illuminate\Http\UploadedFile::fake()->create('artwork.pdf', 1, 'application/pdf')])->assertStatus(422);
        $this->assertSame(0, $workflow->approvals()->count());
        $this->put(route('projects.products.update', $this->bought), ['status' => 'in_production', 'production_completed_quantity' => 0])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('in_production', $this->bought->fresh()->status);
        $this->from('/projects/'.$this->project->id)->put(route('projects.products.update', $this->sold), ['status' => 'in_production', 'production_completed_quantity' => 0])
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('planned', $this->sold->fresh()->status);
    }

    public function test_unmatched_sales_lines_block_delivery_reconciliation_but_purchase_only_products_do_not(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->invoice->update(['status' => 'cancelled']);
        $evaluations = app(ProjectOperationalReadiness::class)->evaluateAll($workflow);
        $delivery = collect($evaluations)->firstWhere('gate_key', 'delivery_reconciled');
        $this->assertSame('passed', $delivery->result);
        $this->assertSame(0, $delivery->details['product_count']);
        $invoice = SalesInvoice::create(['invoice_number' => 'UNMATCHED-PI', 'invoice_type' => 'proforma', 'status' => 'draft',
            'client_id' => $this->project->client_id, 'project_id' => $this->project->id, 'currency' => 'INR']);
        $line = $invoice->items()->create(['product_name' => 'Unmatched sales item', 'quantity' => 1]);
        $delivery = collect(app(ProjectOperationalReadiness::class)->evaluateAll($workflow))->firstWhere('gate_key', 'delivery_reconciled');
        $this->assertSame('blocked', $delivery->result);
        $this->assertContains($line->id, $delivery->details['unmatched_sales_line_ids']);
        $snapshot = app(ProjectFinancialClosure::class)->snapshot($this->project->fresh());
        $this->assertFalse($snapshot['operationally_ready']);
        $this->assertTrue(collect($snapshot['open_operational_steps'])->contains('step_key', 'unmatched_sales_items'));
    }

    public function test_converted_cash_amount_lines_are_not_mistaken_for_unmatched_products(): void
    {
        $cash = SalesInvoice::create(['invoice_number' => 'SCOPE-CASH', 'invoice_type' => 'cash', 'status' => 'draft',
            'source_proforma_id' => $this->invoice->id, 'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'currency' => 'INR']);
        $cash->items()->create(['product_name' => 'Amount against SCOPE-PI', 'quantity' => 1]);
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->assertSame(16, $workflow->steps()->count());
        $this->assertCount(0, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($this->project->id));
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertDontSee('Unmatched sales invoice items need review');
    }

    public function test_legacy_cross_project_link_is_never_matched_by_name_and_is_reported(): void
    {
        $other = Project::forceCreate(['project_number' => 'SCOPE-FOREIGN', 'client_id' => $this->project->client_id,
            'name' => 'Foreign project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $foreign = ProjectProduct::create(['project_id' => $other->id, 'product_name' => 'Foreign carton', 'quantity' => 1]);
        $local = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Foreign carton', 'quantity' => 1]);
        $line = $this->invoice->items()->create(['project_product_id' => $foreign->id, 'product_name' => 'Foreign carton', 'quantity' => 1]);
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->assertSame(0, $workflow->steps()->where('project_product_id', $local->id)->count());
        $this->assertSame(0, $workflow->steps()->where('project_product_id', $foreign->id)->count());
        $this->assertContains($line->id, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($this->project->id)->pluck('id')->all());
        $this->assertSame($foreign->id, $line->fresh()->project_product_id);
    }

    public function test_first_sales_link_records_old_unmarked_purchase_scope_and_retires_its_bypass(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $soldStep = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'artwork_approved')->firstOrFail();
        $this->post(route('projects.workflow.steps.bypass', [$this->project, $soldStep]), [
            'reason' => 'Administrator accepted the original artwork risk.', 'approval_source' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $soldEvaluation = $workflow->evaluations()->where('project_product_id', $this->sold->id)->where('gate_key', 'artwork_approved')->firstOrFail();
        $soldOverride = $soldEvaluation->activeOverride;
        // An old system generated this same bypass on a purchase-only row, before scope markers existed.
        $legacyStep = $soldStep->fresh()->replicate(); $legacyStep->project_product_id = $this->bought->id;
        $legacyStep->metadata = null; $legacyStep->save();
        $legacyEvaluation = $soldEvaluation->replicate(); $legacyEvaluation->project_product_id = $this->bought->id; $legacyEvaluation->save();
        $legacyOverride = $soldOverride->replicate(); $legacyOverride->project_gate_evaluation_id = $legacyEvaluation->id; $legacyOverride->save();

        $this->post(route('sales-invoices.store'), $this->invoicePayload(['invoice_number' => 'SCOPE-PURCHASE-NOW-SOLD',
            'items' => [['project_product_id' => $this->bought->id, 'product_name' => 'Purchased board', 'quantity' => 5, 'unit' => 'pcs', 'unit_price' => 1]]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($legacyOverride->fresh()->revoked_at);
        $this->assertNull($soldOverride->fresh()->revoked_at, 'An unrelated sales-product decision stays intact.');
        $this->assertNotSame('skipped', $legacyStep->fresh()->status);
        $this->assertTrue(data_get($legacyStep->fresh()->metadata, 'sales_workflow_scope.active'));
        $this->assertSame('skipped', data_get($legacyStep->fresh()->metadata, 'sales_workflow_scope.previous_steps.0.status'));
        $this->assertSame(8, $workflow->steps()->where('project_product_id', $this->bought->id)->count());
    }

    public function test_reentry_revokes_approval_override_and_restores_real_client_decision(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $attachment = \App\Models\ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->sold->id,
            'category' => 'artwork', 'file_path' => 'fake/artwork.pdf', 'original_name' => 'artwork.pdf']);
        $approvals = app(\App\Services\ProjectApprovals::class);
        $approval = $approvals->create($workflow, $this->sold->id, 'artwork', 'Artwork', null, null, $attachment, str_repeat('a', 64), $this->admin->id);
        $approvals->override($approval, 'Administrator accepted the original artwork risk.', $this->admin->id, 1);
        $override = $approval->fresh()->activeOverrideRecord();
        $step = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'artwork_approved')->firstOrFail();
        $this->assertSame('skipped', $step->fresh()->status);
        $this->invoice->update(['status' => 'cancelled']);
        app(ProjectWorkflowExecution::class)->sync($workflow);
        $this->invoice->update(['status' => 'sent']);
        app(ProjectWorkflowExecution::class)->sync($workflow);
        $this->assertNotNull($override->fresh()->revoked_at);
        $this->assertSame('waiting_client', $step->fresh()->status);
        $this->assertSame('open', $approval->fresh()->status);
        // A real client decision on the exact file remains historical evidence and is restored.
        $approval->update(['status' => 'approved', 'resolved_at' => now()]);
        $approval->versions()->where('version_number', 1)->update(['status' => 'approved', 'decided_at' => now()]);
        $this->invoice->update(['status' => 'cancelled']);
        app(ProjectWorkflowExecution::class)->sync($workflow);
        $this->invoice->update(['status' => 'sent']);
        app(ProjectWorkflowExecution::class)->sync($workflow);
        $this->assertSame('completed', $step->fresh()->status);
        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertCount(2, data_get($step->fresh()->metadata, 'sales_workflow_scope.previous_steps'));
    }

    public function test_purchase_only_shipment_can_pass_only_with_real_packing_quantity_qc_and_documents(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $shipment = Shipment::create(['shipment_number' => 'SCOPE-COMPLETE-SHIP', 'identity_name' => 'Fictional shipment',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
        $shipment->items()->create(['project_product_id' => $this->bought->id, 'product_name' => 'Purchased board', 'quantity' => 5, 'unit' => 'pcs']);
        $list = \App\Models\PackagingList::create(['packing_number' => 'SCOPE-PL', 'packing_date' => now()->toDateString(),
            'project_id' => $this->project->id, 'shipment_id' => $shipment->id, 'status' => 'confirmed',
            'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
        $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1,
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Purchased board', 'project_product_id' => $this->bought->id,
            'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
        \App\Models\ProjectQualityInspection::create(['project_id' => $this->project->id, 'project_product_id' => $this->bought->id,
            'inspected_quantity' => 1, 'passed_quantity' => 1, 'result' => 'passed', 'checklist' => [],
            'content_hash' => str_repeat('a', 64), 'inspected_at' => now()]);
        $doc = \App\Models\ProjectAttachment::create(['project_id' => $this->project->id, 'category' => 'delivery_challan',
            'file_path' => 'fake/challan.pdf', 'original_name' => 'challan.pdf']);
        $authorization = app(ShipmentDispatchAuthorization::class);
        $rows = $authorization->rowsFromShipment($shipment);
        $ready = $authorization->inspect($shipment, $workflow, $rows);
        $this->assertSame([], $ready['issues']);
        $this->assertFalse($ready['products'][0]['workflow_sales_item']);
        $this->assertNull($ready['products'][0]['approvals_ready']);
        $excess = $authorization->inspect($shipment, $workflow, [['project_product_id' => $this->bought->id, 'product_name' => 'Purchased board', 'quantity' => 6]]);
        $this->assertStringContainsString('confirmed packed', implode(' ', $excess['issues']));
        $this->assertStringContainsString('QC', implode(' ', $excess['issues']));
        $doc->delete();
        $missing = $authorization->inspect($shipment, $workflow, $rows);
        $this->assertStringContainsString('dispatch documents', implode(' ', $missing['issues']));
        $this->assertStringNotContainsString('Artwork and PPS approvals', implode(' ', $missing['issues']));

        // Exercise the actual dispatch transition too: project-level commercial
        // controls remain in force, while purchase-only approval checks do not.
        $workflow->update(['commercial_sales_invoice_id' => $this->invoice->id]);
        $history = route('shipments.history.store', $shipment);
        $pickup = ['status' => 'picked_up', 'remarks' => 'Fictional pickup', 'notify_client' => false];
        $this->from('/shipments/'.$shipment->id)->post($history, $pickup)->assertSessionHasErrors('status');
        $this->assertSame('planning', $shipment->fresh()->status);
        \App\Models\ProjectAttachment::create(['project_id' => $this->project->id, 'category' => 'delivery_challan',
            'file_path' => 'fake/challan-restored.pdf', 'original_name' => 'challan-restored.pdf']);
        $this->post($history, $pickup)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('picked_up', $shipment->fresh()->status);
        $this->assertSame(0, $workflow->fresh()->steps()->where('project_product_id', $this->bought->id)->count());
    }

    public function test_purchase_only_override_is_not_an_active_exception_or_scheduled_notification(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $step = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'artwork_approved')->firstOrFail();
        $this->post(route('projects.workflow.steps.bypass', [$this->project, $step]), [
            'reason' => 'Administrator accepted the original artwork risk.', 'approval_source' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $evaluation = $workflow->evaluations()->where('project_product_id', $this->sold->id)->where('gate_key', 'artwork_approved')->firstOrFail();
        $old = $evaluation->replicate(); $old->project_product_id = $this->bought->id; $old->save();
        $override = $evaluation->activeOverride->replicate(); $override->project_gate_evaluation_id = $old->id;
        $override->expected_resolution_date = now()->subDay(); $override->save();
        $exception = \App\Models\ProjectException::create(['project_id' => $this->project->id, 'project_workflow_id' => $workflow->id,
            'project_product_id' => $this->bought->id, 'source_type' => 'gate_override', 'source_key' => 'gate_override:'.$override->id,
            'source_fingerprint' => str_repeat('a', 64), 'category' => 'manager_override', 'severity' => 'warning', 'status' => 'open',
            'title' => 'Historic purchase override', 'summary' => 'Historic evidence', 'source_snapshot' => [], 'detected_at' => now()]);
        app(\App\Services\ProjectExceptionRegistry::class)->synchronize();
        $this->assertSame('resolved', $exception->fresh()->status);
        $this->assertSame('auto_resolved', $exception->events()->firstOrFail()->event_type);
        $this->assertNull($override->fresh()->revoked_at, 'The historical decision is retained, not erased.');
        app(\App\Services\ProjectWorkflowNotifications::class)->run();
        $this->assertSame(0, \App\Models\OfficeAlert::where('fingerprint', 'like', 'workflow.override_%:'.$override->id.':%')->count());
        $response = $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk();
        $this->assertStringNotContainsString('Historic purchase override', $response->getContent());
    }

    public function test_a_duplicate_completed_approval_does_not_hide_an_unfinished_sales_check(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $artwork = $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'artwork_approved')->firstOrFail();
        $duplicate = $artwork->replicate(); $duplicate->status = 'completed'; $duplicate->save();
        $workflow->steps()->where('project_product_id', $this->sold->id)->where('step_key', 'pps_approved')->update(['status' => 'completed']);
        $shipment = Shipment::create(['shipment_number' => 'SCOPE-DUPLICATE-SHIP', 'identity_name' => 'Fictional shipment',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR']);
        $details = app(ShipmentDispatchAuthorization::class)->inspect($shipment, $workflow,
            [['project_product_id' => $this->sold->id, 'product_name' => 'Sold carton', 'quantity' => 5]]);
        $this->assertStringContainsString('Artwork and PPS approvals', implode(' ', $details['issues']));
        $this->assertFalse($details['products'][0]['approvals_ready']);
        $this->assertSame('not_started', $artwork->fresh()->status);
        $this->assertSame('completed', $duplicate->fresh()->status);
    }

    public function test_bulk_draft_deletion_removes_only_the_deleted_sales_scope(): void
    {
        $this->invoice->update(['status' => 'draft']);
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->post(route('sales-invoices.bulk'), ['action' => 'delete_drafts', 'ids' => [$this->invoice->id]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($this->invoice->fresh());
        $this->assertSame(0, $workflow->steps()->where('project_product_id', $this->sold->id)->count());
        $this->assertSame(8, $workflow->allSteps()->where('project_product_id', $this->sold->id)->count());
        $this->assertNotNull($this->bought->fresh());
    }

    public function test_legacy_catalogue_id_must_match_one_same_project_product(): void
    {
        $catalogue = \App\Models\Product::create(['product_number' => 'SCOPE-CATALOGUE', 'name' => 'Catalogue item']);
        $local = ProjectProduct::create(['project_id' => $this->project->id, 'product_id' => $catalogue->id,
            'product_name' => 'Renamed item', 'quantity' => 2]);
        $line = $this->invoice->items()->create(['product_id' => $catalogue->id, 'product_name' => 'Old invoice label', 'quantity' => 2]);
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->assertSame(8, $workflow->steps()->where('project_product_id', $local->id)->count());
        $this->assertNull($line->fresh()->project_product_id);
        $duplicate = ProjectProduct::create(['project_id' => $this->project->id, 'product_id' => $catalogue->id,
            'product_name' => 'Different product with same catalogue ID', 'quantity' => 2]);
        app(ProjectWorkflowExecution::class)->sync($workflow);
        $this->assertSame(0, $workflow->steps()->whereIn('project_product_id', [$local->id, $duplicate->id])->count());
        $this->assertContains($line->id, app(\App\Services\ProjectWorkflowSalesScope::class)->unmatched($this->project->id)->pluck('id')->all());
    }

    public function test_out_of_scope_historical_client_approval_cannot_be_revised_overridden_or_decided(): void
    {
        $workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $attachment = \App\Models\ProjectAttachment::create(['project_id' => $this->project->id, 'project_product_id' => $this->sold->id,
            'category' => 'artwork', 'file_path' => 'fake/scope.pdf', 'original_name' => 'scope.pdf']);
        $service = app(\App\Services\ProjectApprovals::class);
        $approval = $service->create($workflow, $this->sold->id, 'artwork', 'Sales artwork', null, null, $attachment, str_repeat('b', 64), $this->admin->id);
        $this->invoice->update(['status' => 'cancelled']);
        app(ProjectWorkflowExecution::class)->sync($workflow);
        $portalUser = \App\Models\ClientPortalUser::create(['client_id' => $this->project->client_id, 'name' => 'Fictional client',
            'username' => 'scope-client', 'password' => 'fixture', 'portal_enabled' => true, 'is_active' => true]);
        $actions = [
            fn () => $service->addVersion($approval, $attachment, str_repeat('c', 64), null, null, $this->admin->id),
            fn () => $service->override($approval, 'Administrator must not override a historical item.', $this->admin->id, 1),
            fn () => $service->decide($approval, 'approved', null, $portalUser),
        ];
        foreach ($actions as $action) {
            try { $action(); $this->fail('Historical purchase-only or removed sales approvals must not accept new decisions.'); }
            catch (\Illuminate\Validation\ValidationException $exception) { $this->assertArrayHasKey('project_product_id', $exception->errors()); }
        }
        $this->assertSame('open', $approval->fresh()->status);
        $this->assertSame(1, $approval->versions()->count());
        $this->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()->assertDontSee('Sales artwork');
    }

    public function test_calculated_progress_excludes_purchase_only_history_and_reincludes_sales_items(): void
    {
        $execution = app(ProjectWorkflowExecution::class);
        $workflow = $execution->start($this->project, $this->admin->id);
        $workflow->steps()->whereNull('project_product_id')->update(['status' => 'completed']);
        $execution->recalculate($workflow);
        $this->assertLessThan(100, $workflow->fresh()->calculated_progress);
        $this->invoice->update(['status' => 'cancelled']);
        $execution->sync($workflow);
        $execution->recalculate($workflow);
        $this->assertSame(100, $workflow->fresh()->calculated_progress);
        $this->assertSame(8, $workflow->allSteps()->where('project_product_id', $this->sold->id)->count());
        $this->assertSame(1, $this->project->purchaseOrders()->count());
        $this->invoice->update(['status' => 'sent']);
        $execution->sync($workflow);
        $execution->recalculate($workflow);
        $this->assertLessThan(100, $workflow->fresh()->calculated_progress);
    }

}
