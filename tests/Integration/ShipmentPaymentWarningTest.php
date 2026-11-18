<?php

namespace Tests\Integration;

use App\Events\ShipmentStatusChanged;
use App\Models\Client;
use App\Models\PackagingList;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectProduct;
use App\Models\ProjectQualityInspection;
use App\Models\ProjectWorkflow;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectWorkflowBypass;
use App\Services\ProjectWorkflowExecution;
use App\Services\ProjectWorkflowGates;
use App\Services\ShipmentDispatchAuthorization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** IT-SHIP-015: full-schema payment advice at the shipment HTTP boundaries. */
class ShipmentPaymentWarningTest extends TestCase
{
    use IsolatedDatabase;

    private User $admin;
    private User $operator;
    private Project $project;
    private ProjectWorkflow $workflow;
    private ProjectProduct $product;
    private SalesInvoice $invoice;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        $this->admin = User::create(['name' => 'Administrator', 'email' => 'payment-warning-admin@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $this->operator = User::create(['name' => 'Operator', 'email' => 'payment-warning-operator@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $client = Client::create(['client_number' => 'WARN-CLIENT', 'company_name' => 'Fictional buyer']);
        $this->project = Project::forceCreate(['project_number' => 'WARN-PROJECT', 'client_id' => $client->id,
            'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $this->product = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Board', 'quantity' => 5]);
        $this->invoice = SalesInvoice::create(['invoice_number' => 'WARN-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'public_token' => 'payment-warning-invoice', 'client_id' => $client->id, 'project_id' => $this->project->id,
            'currency' => 'INR', 'total_amount' => 100, 'amount_paid' => 25, 'balance_before_dispatch' => true]);
        $this->workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->workflow->update(['commercial_sales_invoice_id' => $this->invoice->id]);
        $this->shipment = Shipment::create(['shipment_number' => 'WARN-SHIP', 'identity_name' => 'Fictional board delivery',
            'project_id' => $this->project->id, 'client_id' => $client->id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR', 'cost_borne_by' => 'misspack']);
        $this->shipment->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5, 'unit' => 'pcs']);
        $this->actingAs($this->operator);
    }

    /** Real packing, QC and paperwork: these positive tests do not bypass dispatch authorization. */
    private function prepareAuthorizedShipment(): void
    {
        $list = PackagingList::create(['packing_number' => 'WARN-PL', 'packing_date' => now()->toDateString(),
            'project_id' => $this->project->id, 'shipment_id' => $this->shipment->id, 'status' => 'confirmed',
            'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
        $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1,
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $this->product->id,
            'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
        ProjectQualityInspection::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'inspected_quantity' => 1, 'passed_quantity' => 1, 'result' => 'passed', 'checklist' => [],
            'content_hash' => str_repeat('a', 64), 'inspected_at' => now()]);
        ProjectAttachment::create(['project_id' => $this->project->id, 'category' => 'delivery_challan',
            'file_path' => 'fake/challan.pdf', 'original_name' => 'challan.pdf']);
        $authorization = app(ShipmentDispatchAuthorization::class);
        $this->assertSame([], $authorization->inspect($this->shipment, $this->workflow, $authorization->rowsFromShipment($this->shipment))['issues']);
    }

    private function listPayload(array $overrides = []): array
    {
        return array_replace(['_list_status_modal' => '1', '_list_shipment_id' => (string) $this->shipment->id,
            '_expected_status' => 'planning', 'status' => 'picked_up', 'remarks' => 'Fictional pickup',
            'location' => 'Warehouse', 'is_public' => '0', 'notify_client' => '0'], $overrides);
    }

    private function editPayload(array $overrides = []): array
    {
        return array_replace(['shipment_number' => $this->shipment->shipment_number,
            'identity_name' => $this->shipment->identity_name, 'shipment_type' => 'domestic', 'status' => 'picked_up',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id,
            'currency' => 'INR', 'cost_borne_by' => 'misspack', 'notify_client' => '0'], $overrides);
    }

    private function balanceEvaluation()
    {
        return $this->workflow->evaluations()->where('gate_key', ProjectWorkflowGates::GATE_DISPATCH_BALANCE)->latest('id')->firstOrFail();
    }

    private function assertOutstandingWarning($warning): bool
    {
        return is_string($warning) && str_contains($warning, 'WARN-SHIP') && str_contains($warning, 'Proforma')
            && str_contains($warning, 'outstanding') && ! str_contains($warning, '75.00');
    }

    public function test_list_pickup_is_saved_with_an_accessible_warning_and_preserves_the_unpaid_evaluation(): void
    {
        $this->prepareAuthorizedShipment();
        Event::fake([ShipmentStatusChanged::class]);
        $list = route('shipments.index', ['status' => 'planning', 'search' => 'WARN-SHIP']);
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect($list)->assertSessionHasNoErrors()->assertSessionHas('success')
            ->assertSessionHas('shipment_balance_warning', fn ($warning) => $this->assertOutstandingWarning($warning));
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame('picked_up', $this->shipment->histories()->firstOrFail()->status);
        $this->assertFalse($this->shipment->histories()->firstOrFail()->is_public);
        Event::assertDispatched(ShipmentStatusChanged::class, fn ($event) => $event->shipment->id === $this->shipment->id && $event->from === 'planning');
        $this->assertSame('blocked', $this->balanceEvaluation()->result);
        $this->assertEquals(75, $this->balanceEvaluation()->details['shortfall']);
        $this->assertNull($this->balanceEvaluation()->activeOverride);
        $this->assertEquals(25, $this->invoice->fresh()->amount_paid);
        $this->assertEquals(75, $this->invoice->fresh()->clientPortalBalanceDue());
        $this->get($list)->assertOk()->assertSee('role="status"', false)->assertSee('Proforma balance')
            ->assertSee('WARN-SHIP')->assertSee('Accounts')->assertDontSee('Shipment pickup is blocked until');
    }

    public function test_tracking_detail_and_shipment_edit_both_save_with_a_warning(): void
    {
        $this->prepareAuthorizedShipment();
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $this->from($detail)->post(route('shipments.history.store', $this->shipment), [
            'status' => 'picked_up', 'remarks' => 'Picked up', 'notify_client' => '0',
        ])->assertRedirect($detail)->assertSessionHasNoErrors()
            ->assertSessionHas('shipment_balance_warning', fn ($warning) => $this->assertOutstandingWarning($warning));
        $this->get($detail)->assertOk()->assertSee('Proforma balance')->assertSee('Accounts');

        // Return to planning to exercise the independent edit route using the same real evidence.
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->from(route('shipments.edit', $this->shipment))->put(route('shipments.update', $this->shipment), $this->editPayload())
            ->assertRedirect(route('shipments.show', $this->shipment))->assertSessionHasNoErrors()
            ->assertSessionHas('shipment_balance_warning', fn ($warning) => $this->assertOutstandingWarning($warning));
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame(2, $this->shipment->histories()->count());
        $this->get(route('shipments.show', $this->shipment))->assertOk()->assertSee('Proforma balance')->assertSee('Accounts');
    }

    public function test_paid_balance_or_no_required_balance_does_not_produce_a_warning(): void
    {
        $this->prepareAuthorizedShipment();
        $this->invoice->update(['amount_paid' => 100]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('shipment_balance_warning');
        $this->assertSame('passed', $this->balanceEvaluation()->result);
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->invoice->update(['amount_paid' => 25, 'balance_before_dispatch' => false]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('shipment_balance_warning');
        $this->assertEquals(75, $this->invoice->fresh()->clientPortalBalanceDue());
    }

    public function test_missing_or_cancelled_baseline_warns_that_balance_cannot_be_verified(): void
    {
        $this->prepareAuthorizedShipment();
        $this->workflow->update(['commercial_sales_invoice_id' => null]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning',
                fn ($warning) => str_contains($warning, 'cannot be verified') && str_contains($warning, 'Proforma'));
        $this->assertFalse($this->balanceEvaluation()->details['invoice_usable']);
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->invoice->update(['status' => 'cancelled']);
        $this->workflow->update(['commercial_sales_invoice_id' => $this->invoice->id]);
        $this->actingAs($this->admin)->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning',
                fn ($warning) => str_contains($warning, 'cannot be verified'))
            ->assertSessionHas('shipment_balance_warning_detail', fn ($detail) => str_contains($detail, 'cancelled'));
        $this->assertSame('cancelled', $this->invoice->fresh()->status);
    }

    public function test_existing_financial_override_is_preserved_but_does_not_hide_the_unpaid_warning(): void
    {
        $this->prepareAuthorizedShipment();
        $step = $this->workflow->steps()->where('step_key', 'dispatch_balance')->firstOrFail();
        app(ProjectWorkflowBypass::class)->bypass($this->project, $step, $this->admin, 'Accounts will follow up on the outstanding balance.');
        $override = $this->balanceEvaluation()->activeOverride;
        $this->assertNotNull($override);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning',
                fn ($warning) => $this->assertOutstandingWarning($warning));
        $this->assertSame($override->id, $this->balanceEvaluation()->activeOverride->id);
        $this->assertNull($override->fresh()->revoked_at);
        $this->assertEquals(75, $this->invoice->fresh()->clientPortalBalanceDue());
    }

    public function test_legacy_evaluation_text_stays_immutable_but_current_workflow_no_longer_claims_dispatch_is_blocked(): void
    {
        $this->prepareAuthorizedShipment();
        app(ProjectWorkflowGates::class)->evaluateAll($this->workflow, $this->admin->id);
        $evaluation = $this->balanceEvaluation();
        $legacySummary = 'Dispatch is blocked by an outstanding balance of 75.00.';
        $evaluation->update(['summary' => $legacySummary]); // A snapshot created under the prior policy.
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning');
        $this->assertSame($evaluation->id, $this->balanceEvaluation()->id);
        $this->assertSame($legacySummary, $evaluation->fresh()->summary);
        $step = $this->workflow->steps()->where('step_key', 'dispatch_balance')->firstOrFail();
        $this->assertSame('blocked', $step->status);
        $this->assertStringContainsString('Shipment tracking may continue', $step->blocked_reason);
        $this->actingAs($this->admin)->get('/projects/'.$this->project->id.'?tab=workflow')->assertOk()
            ->assertSee('Shipment tracking may continue');
    }

    public function test_unpaid_balance_and_pending_sales_approvals_warn_separately_without_changing_facts(): void
    {
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning')
            ->assertSessionHas('shipment_readiness_warning', fn ($warning) => str_contains(implode(' ', $warning['issues']), 'Artwork and PPS approvals'));
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame(1, $this->shipment->histories()->count());
        $this->assertEquals(25, $this->invoice->fresh()->amount_paid);
        $this->assertSame(0, $this->project->approvalRequests()->count());
        $this->assertDatabaseCount('project_gate_overrides', 0);
    }

    public function test_history_edit_and_delete_apply_dispatch_safeguards_and_advisory_payment(): void
    {
        $older = $this->shipment->histories()->create(['status' => 'picked_up', 'remarks' => 'Pickup', 'event_time' => '2026-01-01 10:00:00']);
        $newer = $this->shipment->histories()->create(['status' => 'planning', 'remarks' => 'Planning', 'event_time' => '2026-01-02 10:00:00']);
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $this->from($detail)->put(route('shipments.history.update', $newer), ['status' => 'picked_up', 'remarks' => 'Pickup', 'event_time' => '2026-01-02 10:00:00'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning')
            ->assertSessionHas('shipment_balance_warning', fn ($warning) => $this->assertOutstandingWarning($warning));
        $this->assertSame('picked_up', $newer->fresh()->status);
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->from($detail)->delete(route('shipments.history.destroy', $newer))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning')
            ->assertSessionHas('shipment_balance_warning', fn ($warning) => $this->assertOutstandingWarning($warning));
        $this->assertNull($newer->fresh());
        $this->assertSame('picked_up', $older->fresh()->status);
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
    }

    public function test_reassigning_a_planning_shipment_while_dispatching_uses_the_target_project_warnings(): void
    {
        // Release the fixture shipment's planned quantity before assigning it to another shipment.
        $this->shipment->update(['status' => 'cancelled']);
        $unlinked = Shipment::create(['shipment_number' => 'WARN-UNLINKED', 'identity_name' => 'Unlinked delivery',
            'status' => 'planning', 'shipment_type' => 'domestic', 'currency' => 'INR', 'cost_borne_by' => 'misspack']);
        $payload = $this->editPayload(['shipment_number' => $unlinked->shipment_number, 'identity_name' => $unlinked->identity_name,
            'items' => [['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5, 'unit' => 'pcs', 'currency' => 'INR']]]);
        $this->from(route('shipments.edit', $unlinked))->put(route('shipments.update', $unlinked), $payload)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning')
            ->assertSessionHas('shipment_balance_warning');
        $this->assertSame($this->project->id, $unlinked->fresh()->project_id);
        $this->assertSame('picked_up', $unlinked->fresh()->status);
    }

    public function test_warning_uses_invoice_currency_and_escapes_html_without_hiding_other_warnings(): void
    {
        $this->prepareAuthorizedShipment();
        $this->invoice->update(['currency' => 'USD']);
        $this->actingAs($this->admin)->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning')
            ->assertSessionHas('shipment_balance_warning_detail', fn ($detail) => str_contains($detail, 'USD') && str_contains($detail, '75.00'));
        $this->withSession(['shipment_balance_warning' => 'Proforma balance for <unsafe>', 'warning' => 'Paperwork is incomplete.'])
            ->get(route('shipments.index'))->assertOk()->assertSee('Proforma balance for &lt;unsafe&gt;', false)
            ->assertDontSee('Proforma balance for <unsafe>', false)->assertSee('Paperwork is incomplete.');
    }

    public function test_monetary_warning_details_respect_sales_or_project_view_access(): void
    {
        $this->prepareAuthorizedShipment();
        $salesUser = User::create(['name' => 'Sales operator', 'email' => 'payment-warning-sales@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage', 'sales.view']]);
        $projectUser = User::create(['name' => 'Project operator', 'email' => 'payment-warning-project@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage', 'projects.view']]);
        foreach ([$salesUser, $projectUser] as $user) {
            $this->shipment->refresh()->update(['status' => 'planning']);
            $this->actingAs($user)->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
                ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning')
                ->assertSessionHas('shipment_balance_warning_detail', fn ($detail) => str_contains($detail, 'INR') && str_contains($detail, '75.00'));
            $this->get(route('shipments.index'))->assertOk()->assertSee('Recorded shortfall: INR 75.00.');
        }
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->actingAs($this->operator)->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning',
                fn ($warning) => $this->assertOutstandingWarning($warning))->assertSessionMissing('shipment_balance_warning_detail');
    }

    public function test_revoked_financial_view_access_is_rechecked_before_rendering_the_warning(): void
    {
        $this->prepareAuthorizedShipment();
        $viewer = User::create(['name' => 'Temporary sales viewer', 'email' => 'payment-revoked@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage', 'sales.view']]);
        $this->actingAs($viewer)->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_balance_warning_detail');
        $viewer->update(['permissions' => ['operations.manage']]);
        $this->actingAs($viewer->fresh())->get(route('shipments.index'))->assertOk()
            ->assertSee('Proforma balance warning')->assertDontSee('Recorded shortfall: INR 75.00.');
    }

    public function test_regular_validation_permissions_and_non_transition_behaviour_are_unchanged(): void
    {
        $this->prepareAuthorizedShipment();
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload(['status' => 'delayed', 'remarks' => '']))
            ->assertRedirect()->assertSessionHasErrors('remarks')->assertSessionMissing('shipment_balance_warning');
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload(['_expected_status' => 'in_transit']))
            ->assertRedirect()->assertSessionHasErrors('status')->assertSessionMissing('shipment_balance_warning');
        $reader = User::create(['name' => 'Reader', 'email' => 'payment-warning-reader@example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.view']]);
        $this->actingAs($reader)->post(route('shipments.history.store', $this->shipment), $this->listPayload())->assertForbidden();
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
        $this->actingAs($this->operator)->from(route('shipments.index'))
            ->post(route('shipments.history.store', $this->shipment), $this->listPayload(['status' => 'planning']))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('shipment_balance_warning');
    }
}
