<?php

namespace Tests\Integration;

use App\Events\ShipmentStatusChanged;
use App\Http\Controllers\ShipmentController;
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
use App\Services\ShipmentDispatchAuthorization;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** IT-SHIP-016: shipment tracking saves while named readiness checks remain open. */
class ShipmentDispatchReadinessWarningTest extends TestCase
{
    use IsolatedDatabase;

    private User $admin;
    private User $operator;
    private Project $project;
    private ProjectProduct $product;
    private ProjectWorkflow $workflow;
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
        $this->admin = User::create(['name' => 'Readiness administrator', 'email' => 'readiness-admin@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $this->operator = User::create(['name' => 'Readiness operator', 'email' => 'readiness-operator@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $client = Client::create(['client_number' => 'READINESS-CLIENT', 'company_name' => 'Fictional buyer']);
        $this->project = Project::forceCreate(['project_number' => 'READINESS-PROJECT', 'client_id' => $client->id,
            'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $this->product = ProjectProduct::create(['project_id' => $this->project->id, 'product_name' => 'Board', 'quantity' => 5]);
        $this->invoice = SalesInvoice::create(['invoice_number' => 'READINESS-PI', 'invoice_type' => 'proforma', 'status' => 'sent',
            'client_id' => $client->id, 'project_id' => $this->project->id, 'currency' => 'INR',
            'total_amount' => 100, 'amount_paid' => 25, 'balance_before_dispatch' => true]);
        $this->workflow = app(ProjectWorkflowExecution::class)->start($this->project, $this->admin->id);
        $this->workflow->update(['commercial_sales_invoice_id' => $this->invoice->id]);
        $this->shipment = Shipment::create(['shipment_number' => 'READINESS-SHIP', 'identity_name' => 'Fictional delivery',
            'project_id' => $this->project->id, 'client_id' => $client->id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR', 'cost_borne_by' => 'misspack']);
        $this->shipment->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5, 'unit' => 'pcs']);
        $this->actingAs($this->operator);
    }

    private function listPayload(array $overrides = []): array
    {
        return array_replace(['_list_status_modal' => '1', '_list_shipment_id' => (string) $this->shipment->id,
            '_expected_status' => 'planning', 'status' => 'picked_up', 'remarks' => 'Fictional update',
            'is_public' => '0', 'notify_client' => '0'], $overrides);
    }

    private function warningContains($warning, array $phrases): bool
    {
        if (! is_array($warning)) return false;
        $text = ($warning['message'] ?? '').' '.implode(' ', $warning['issues'] ?? []);
        foreach ($phrases as $phrase) if (! str_contains($text, $phrase)) return false;
        return true;
    }

    private function prepareReadyShipment(): void
    {
        $list = PackagingList::create(['packing_number' => 'READINESS-PL', 'packing_date' => now()->toDateString(),
            'project_id' => $this->project->id, 'shipment_id' => $this->shipment->id, 'status' => 'confirmed',
            'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
        $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1,
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $this->product->id,
            'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
        ProjectQualityInspection::create(['project_id' => $this->project->id, 'project_product_id' => $this->product->id,
            'inspected_quantity' => 1, 'passed_quantity' => 1, 'result' => 'passed', 'checklist' => [],
            'content_hash' => str_repeat('d', 64), 'inspected_at' => now()]);
        ProjectAttachment::create(['project_id' => $this->project->id, 'category' => 'delivery_challan',
            'file_path' => 'fake/challan.pdf', 'original_name' => 'challan.pdf']);
        $authorization = app(ShipmentDispatchAuthorization::class);
        $this->assertSame([], $authorization->inspect($this->shipment, $this->workflow, $authorization->rowsFromShipment($this->shipment))['issues']);
    }

    public function test_list_delivered_saves_with_each_screenshot_warning_and_preserves_actual_readiness(): void
    {
        Event::fake([ShipmentStatusChanged::class]);
        // Sales scope creates pending Artwork and PPS controls, as in the updated screenshot.
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $list = route('shipments.index', ['status' => 'planning', 'search' => 'READINESS-SHIP']);
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $this->listPayload(['status' => 'delivered']))
            ->assertRedirect($list)->assertSessionHasNoErrors()->assertSessionHas('success')
            ->assertSessionHas('shipment_readiness_warning', fn ($warning) => $this->warningContains($warning,
                ['READINESS-SHIP', 'Packaging List', 'QC', 'Artwork and PPS approvals', 'Delivery Challan', 'Proof of Delivery']))
            ->assertSessionHas('shipment_balance_warning');
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $this->assertSame(Shipment::businessToday(), $this->shipment->fresh()->drop_date->toDateString());
        $this->assertSame('delivered', $this->shipment->histories()->firstOrFail()->status);
        $this->assertFalse($this->shipment->histories()->firstOrFail()->is_public);
        Event::assertDispatched(ShipmentStatusChanged::class, fn ($event) => $event->shipment->id === $this->shipment->id && $event->from === 'planning');
        $this->assertSame(0, $this->shipment->deliveryReceipts()->count());
        $this->assertSame(0, $this->project->qualityInspections()->count());
        $this->assertSame(0, $this->project->attachments()->count());
        $this->assertSame(0, $this->project->approvalRequests()->count());
        foreach (['artwork_approved', 'pps_approved'] as $key) {
            $step = $this->workflow->allSteps()->where('project_product_id', $this->product->id)->where('step_key', $key)->firstOrFail();
            $this->assertNotContains($step->status, ['completed', 'skipped']);
        }
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('blocked', $evaluation->result);
        $this->assertNull($evaluation->activeOverride);
        $this->assertStringContainsString('Artwork and PPS approvals', implode(' ', $evaluation->details['issues']));
        $this->assertStringContainsString('Proof of Delivery', implode(' ', $evaluation->details['issues']));
        $this->assertEquals(25, $this->invoice->fresh()->amount_paid);
        $this->get($list)->assertOk()->assertSee('Shipment readiness warning')->assertSee('role="status"', false)
            ->assertSee('READINESS-SHIP')->assertSee('Packaging List')->assertSee('QC')->assertSee('Artwork and PPS approvals')
            ->assertSee('Delivery Challan')->assertSee('Proof of Delivery')->assertSee('Proforma balance warning');
    }

    public function test_later_delivered_status_warns_about_current_pod_and_readiness_without_new_blockers(): void
    {
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $prior = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertNotContains('Proof of Delivery (POD)', $prior->details['paperwork']['missing']);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment),
            $this->listPayload(['_expected_status' => 'picked_up', 'status' => 'delivered']))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
                fn ($warning) => $this->warningContains($warning, ['Packaging List', 'QC', 'Artwork and PPS approvals', 'Proof of Delivery']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));
        $this->assertContains('Proof of Delivery (POD)', $current->details['paperwork']['missing']);
        $this->assertSame(0, $this->shipment->deliveryReceipts()->count());
        $this->assertDatabaseCount('project_gate_overrides', 0);
    }

    public function test_later_delivery_from_shipment_edit_warns_about_pod(): void
    {
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->from(route('shipments.edit', $this->shipment))->put(route('shipments.update', $this->shipment), [
            'shipment_number' => $this->shipment->shipment_number, 'identity_name' => $this->shipment->identity_name,
            'shipment_type' => 'domestic', 'status' => 'delivered', 'project_id' => $this->project->id,
            'client_id' => $this->project->client_id, 'currency' => 'INR', 'cost_borne_by' => 'misspack', 'notify_client' => '0',
        ])->assertRedirect(route('shipments.show', $this->shipment))->assertSessionHasNoErrors()
            ->assertSessionHas('shipment_readiness_warning', fn ($warning) => $this->warningContains($warning, ['Proof of Delivery']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->deliveryReceipts()->count());
    }

    public function test_later_delivery_derived_from_history_warns_about_pod(): void
    {
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $history = $this->shipment->histories()->firstOrFail();
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $this->from($detail)->put(route('shipments.history.update', $history), [
            'status' => 'delivered', 'remarks' => 'Delivered', 'event_time' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
            fn ($warning) => $this->warningContains($warning, ['Proof of Delivery']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertContains('Proof of Delivery (POD)', $current->details['paperwork']['missing']);
        $this->assertSame(0, $this->shipment->deliveryReceipts()->count());
    }

    public function test_detail_and_shipment_edit_save_with_readiness_and_payment_warnings(): void
    {
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $this->from($detail)->post(route('shipments.history.store', $this->shipment), [
            'status' => 'picked_up', 'remarks' => 'Picked up', 'notify_client' => '0',
        ])->assertRedirect($detail)->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning')
            ->assertSessionHas('shipment_balance_warning');
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->from(route('shipments.edit', $this->shipment))->put(route('shipments.update', $this->shipment), [
            'shipment_number' => $this->shipment->shipment_number, 'identity_name' => $this->shipment->identity_name,
            'shipment_type' => 'domestic', 'status' => 'picked_up', 'project_id' => $this->project->id,
            'client_id' => $this->project->client_id, 'currency' => 'INR', 'cost_borne_by' => 'misspack', 'notify_client' => '0',
        ])->assertRedirect(route('shipments.show', $this->shipment))->assertSessionHasNoErrors()
            ->assertSessionHas('shipment_readiness_warning')->assertSessionHas('shipment_balance_warning');
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame(1, $this->shipment->items()->count());
        $this->assertSame(2, $this->shipment->histories()->count());
    }

    public function test_history_edit_and_delete_show_readiness_warnings_for_derived_dispatch(): void
    {
        $older = $this->shipment->histories()->create(['status' => 'picked_up', 'remarks' => 'Pickup', 'event_time' => '2026-01-01 10:00:00']);
        $newer = $this->shipment->histories()->create(['status' => 'planning', 'remarks' => 'Planning', 'event_time' => '2026-01-02 10:00:00']);
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $this->from($detail)->put(route('shipments.history.update', $newer), ['status' => 'picked_up', 'remarks' => 'Pickup', 'event_time' => '2026-01-02 10:00:00'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning')->assertSessionHas('shipment_balance_warning');
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->from($detail)->delete(route('shipments.history.destroy', $newer))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning')->assertSessionHas('shipment_balance_warning');
        $this->assertNull($newer->fresh());
        $this->assertSame('picked_up', $older->fresh()->status);
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
    }

    public function test_pending_sales_approvals_warn_without_approving_or_overriding_them(): void
    {
        $this->prepareReadyShipment();
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
                fn ($warning) => $this->warningContains($warning, ['Artwork and PPS approvals']));
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame(1, $this->shipment->histories()->count());
        $this->assertSame(0, $this->project->approvalRequests()->count());
        $this->assertDatabaseCount('project_gate_overrides', 0);
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('blocked', $evaluation->result);
        $this->assertFalse(data_get($evaluation->details, 'products.0.approvals_ready'));
    }

    public function test_invalid_project_identity_and_quantity_still_block_tracking(): void
    {
        $item = $this->shipment->items()->firstOrFail();
        $item->update(['quantity' => 0]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasErrors('status')->assertSessionMissing('shipment_readiness_warning');
        $this->assertStringContainsString('positive shipment quantity', session('errors')->first('status'));
        $item->update(['quantity' => 6]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertStringContainsString('project product quantity', session('errors')->first('status'));
        $other = Project::forceCreate(['project_number' => 'READINESS-OTHER', 'client_id' => $this->project->client_id, 'name' => 'Other project',
            'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $foreign = ProjectProduct::create(['project_id' => $other->id, 'product_name' => 'Other board', 'quantity' => 5]);
        $item->update(['project_product_id' => $foreign->id, 'quantity' => 5]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertStringContainsString('does not belong to this project', session('errors')->first('status'));
        $item->update(['project_product_id' => $this->product->id]);
        $this->product->update(['status' => 'cancelled']);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertStringContainsString('cancelled project product', session('errors')->first('status'));
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_rejected_dispatch_edit_keeps_a_reviewable_gate_without_saving_shipment_changes(): void
    {
        $item = $this->shipment->items()->firstOrFail();
        $item->update(['quantity' => 6]);
        $payload = [
            'shipment_number' => $this->shipment->shipment_number, 'identity_name' => 'Unsaved edit',
            'shipment_type' => 'domestic', 'status' => 'delivered', 'project_id' => $this->project->id,
            'currency' => 'INR', 'cost_borne_by' => 'misspack', 'notify_client' => '0',
        ];
        $this->from(route('shipments.edit', $this->shipment))->put(route('shipments.update', $this->shipment), $payload)
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertSame('Fictional delivery', $this->shipment->fresh()->identity_name);
        $this->assertEquals(6, $item->fresh()->quantity);
        $this->assertSame(0, $this->shipment->histories()->count());
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('blocked', $evaluation->result);
        $this->assertEquals(6, data_get($evaluation->details, 'lines.0.shipment_quantity'));
        $this->assertContains('Proof of Delivery (POD)', data_get($evaluation->details, 'paperwork.missing'));
        $this->actingAs($this->admin)->post(route('projects.workflow.gates.override', [$this->project, $evaluation]),
            ['reason' => 'Administrator accepts this specific quantity risk.'])->assertSessionHasNoErrors();
        $this->actingAs($this->operator)->from(route('shipments.edit', $this->shipment))
            ->put(route('shipments.update', $this->shipment), $payload)->assertSessionHasNoErrors()
            ->assertSessionHas('shipment_readiness_warning', fn ($warning) => $this->warningContains($warning, ['project product quantity', 'Proof of Delivery']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $this->assertSame('Unsaved edit', $this->shipment->fresh()->identity_name);
        $this->assertSame(1, $this->shipment->histories()->count());
    }

    public function test_rejected_dispatch_does_not_apply_unsaved_line_edits_or_approve_them_as_current(): void
    {
        $item = $this->shipment->items()->firstOrFail();
        $payload = [
            'shipment_number' => $this->shipment->shipment_number, 'identity_name' => $this->shipment->identity_name,
            'shipment_type' => 'domestic', 'status' => 'picked_up', 'project_id' => $this->project->id,
            'currency' => 'INR', 'cost_borne_by' => 'misspack',
            'items' => [['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 6, 'unit' => 'pcs']],
        ];
        $this->from(route('shipments.edit', $this->shipment))->put(route('shipments.update', $this->shipment), $payload)
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertEquals(5, $item->fresh()->quantity);
        $this->assertSame($item->id, $this->shipment->items()->firstOrFail()->id);
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertEquals(6, data_get($evaluation->details, 'lines.0.shipment_quantity'));
        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)
            ->post(route('projects.workflow.gates.override', [$this->project, $evaluation]),
                ['reason' => 'Administrator accepts this specific quantity risk.'])->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('project_gate_overrides', 0);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_stale_unlinked_shipment_cannot_skip_a_new_project_on_history_or_edit(): void
    {
        $stale = $this->shipment->fresh();
        $stale->project_id = null; // Simulate route binding before another writer linked the project.
        $stale->unsetRelation('project');
        $historyRequest = Request::create('/shipments/'.$stale->id.'/history', 'POST', [
            'status' => 'picked_up', 'remarks' => 'Picked up', 'notify_client' => '0',
        ]);
        try {
            app(ShipmentController::class)->storeHistory($historyRequest, $stale);
            $this->fail('A stale unlinked shipment must be reloaded before a tracking update.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $editRequest = Request::create('/shipments/'.$stale->id, 'PUT', [
            'shipment_number' => $stale->shipment_number, 'identity_name' => $stale->identity_name,
            'shipment_type' => 'domestic', 'status' => 'picked_up', 'currency' => 'INR', 'cost_borne_by' => 'misspack',
        ]);
        try {
            app(ShipmentController::class)->update($editRequest, $stale);
            $this->fail('A stale unlinked shipment must be reloaded before an edit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertSame($this->project->id, $this->shipment->fresh()->project_id);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_stale_project_binding_does_not_record_a_gate_for_the_wrong_project(): void
    {
        $stale = $this->shipment->fresh()->load('project.workflow');
        $other = Project::forceCreate(['project_number' => 'READINESS-REASSIGNED', 'client_id' => $this->project->client_id,
            'name' => 'Other project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $otherProduct = ProjectProduct::create(['project_id' => $other->id, 'product_name' => 'Other board', 'quantity' => 5]);
        $this->shipment->update(['project_id' => $other->id]);
        $this->shipment->items()->firstOrFail()->update(['project_product_id' => $otherProduct->id, 'product_name' => 'Other board']);
        $history = Request::create('/shipments/'.$stale->id.'/history', 'POST', [
            'status' => 'picked_up', 'remarks' => 'Picked up', 'notify_client' => '0',
        ]);
        try {
            app(ShipmentController::class)->storeHistory($history, $stale);
            $this->fail('A stale project binding must not evaluate the old project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $edit = Request::create('/shipments/'.$stale->id, 'PUT', [
            'shipment_number' => $stale->shipment_number, 'identity_name' => $stale->identity_name,
            'shipment_type' => 'domestic', 'status' => 'picked_up', 'currency' => 'INR', 'cost_borne_by' => 'misspack',
        ]);
        try {
            app(ShipmentController::class)->update($edit, $stale);
            $this->fail('A stale edit must not evaluate the old project.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame(0, $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->count());
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertSame($other->id, $this->shipment->fresh()->project_id);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_stale_missing_workflow_cannot_skip_a_concurrently_started_workflow(): void
    {
        $stale = $this->shipment->fresh();
        $oldProject = $this->project->fresh();
        $oldProject->setRelation('workflow', null); // Simulate a lookup before workflow creation.
        $stale->setRelation('project', $oldProject);
        $request = Request::create('/shipments/'.$stale->id.'/history', 'POST', [
            'status' => 'picked_up', 'remarks' => 'Picked up', 'notify_client' => '0',
        ]);
        try {
            app(ShipmentController::class)->storeHistory($request, $stale);
            $this->fail('A newly started workflow must be considered before dispatch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $edit = Request::create('/shipments/'.$stale->id, 'PUT', [
            'shipment_number' => $stale->shipment_number, 'identity_name' => $stale->identity_name,
            'shipment_type' => 'domestic', 'status' => 'picked_up', 'currency' => 'INR', 'cost_borne_by' => 'misspack',
        ]);
        try {
            app(ShipmentController::class)->update($edit, $stale);
            $this->fail('A newly started workflow must be considered before an edit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_quantity_override_for_direct_delivery_rechecks_the_attempted_pod_state(): void
    {
        $this->shipment->items()->firstOrFail()->update(['quantity' => 6]);
        $list = route('shipments.index');
        $delivery = $this->listPayload(['status' => 'delivered']);
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $delivery)
            ->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertContains('Proof of Delivery (POD)', $evaluation->details['paperwork']['missing']);
        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)
            ->post(route('projects.workflow.gates.override', [$this->project, $evaluation]), ['reason' => 'Administrator accepts this specific quantity risk.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $override = $evaluation->fresh()->activeOverride;
        $this->patch(route('projects.workflow.overrides.revoke', [$this->project, $override]), ['revocation_reason' => 'Recheck before proceeding.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $step = $this->workflow->allSteps()->whereNull('project_product_id')->where('step_key', ShipmentDispatchAuthorization::GATE_KEY)->firstOrFail();
        $this->post(route('projects.workflow.steps.bypass', [$this->project, $step]), ['reason' => 'Administrator accepts this specific quantity risk.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->operator)->from($list)->post(route('shipments.history.store', $this->shipment), $delivery)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
                fn ($warning) => $this->warningContains($warning, ['project product quantity', 'Proof of Delivery']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->deliveryReceipts()->count());
        $this->assertSame(0, $this->project->qualityInspections()->count());
    }

    public function test_quantity_override_rechecks_direct_delivery_when_existing_pod_was_removed(): void
    {
        $pod = ProjectAttachment::create(['project_id' => $this->project->id, 'category' => 'pod',
            'file_path' => 'fake/pod.pdf', 'original_name' => 'pod.pdf']);
        $this->shipment->items()->firstOrFail()->update(['quantity' => 6]);
        $delivery = $this->listPayload(['status' => 'delivered']);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $delivery)
            ->assertRedirect()->assertSessionHasErrors('status');
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertTrue(data_get($evaluation->details, 'paperwork.delivery_checklist'));
        $this->assertNotContains('Proof of Delivery (POD)', $evaluation->details['paperwork']['missing']);
        $pod->delete();

        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)
            ->post(route('projects.workflow.gates.override', [$this->project, $evaluation]), ['reason' => 'Administrator accepts this specific quantity risk.'])
            ->assertRedirect()->assertSessionHasErrors('reason');
        $this->assertDatabaseCount('project_gate_overrides', 0);
        $this->assertSame('planning', $this->shipment->fresh()->status);
        $this->assertNotContains('Proof of Delivery (POD)', $evaluation->fresh()->details['paperwork']['missing']);

        // A fresh failed attempt exposes the missing POD before a new decision.
        $this->actingAs($this->operator)->from(route('shipments.index'))
            ->post(route('shipments.history.store', $this->shipment), $delivery)->assertSessionHasErrors('status');
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertNotSame($evaluation->id, $current->id);
        $this->assertContains('Proof of Delivery (POD)', $current->details['paperwork']['missing']);
        $this->actingAs($this->admin)->post(route('projects.workflow.gates.override', [$this->project, $current]),
            ['reason' => 'Administrator accepts this specific quantity risk.'])->assertSessionHasNoErrors();
        $this->actingAs($this->operator)->from(route('shipments.index'))
            ->post(route('shipments.history.store', $this->shipment), $delivery)->assertSessionHasNoErrors()
            ->assertSessionHas('shipment_readiness_warning', fn ($warning) => $this->warningContains($warning, ['Proof of Delivery']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
    }

    public function test_history_edit_and_delete_warn_about_pending_sales_approvals(): void
    {
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $older = $this->shipment->histories()->create(['status' => 'picked_up', 'remarks' => 'Pickup', 'event_time' => '2026-01-01 10:00:00']);
        $newer = $this->shipment->histories()->create(['status' => 'planning', 'remarks' => 'Planning', 'event_time' => '2026-01-02 10:00:00']);
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $hasApprovalWarning = fn ($warning) => $this->warningContains($warning, ['Artwork and PPS approvals']);
        $this->from($detail)->put(route('shipments.history.update', $newer), ['status' => 'picked_up', 'remarks' => 'Pickup', 'event_time' => '2026-01-02 10:00:00'])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning', $hasApprovalWarning);
        $this->assertSame('picked_up', $newer->fresh()->status);
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->shipment->refresh()->update(['status' => 'planning']);
        $this->from($detail)->delete(route('shipments.history.destroy', $newer))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning', $hasApprovalWarning);
        $this->assertNull($newer->fresh());
        $this->assertSame('picked_up', $older->fresh()->status);
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->project->approvalRequests()->count());
        $this->assertDatabaseCount('project_gate_overrides', 0);
    }

    public function test_short_confirmed_packing_is_an_explicit_warning_not_a_status_blocker(): void
    {
        $this->prepareReadyShipment();
        $list = PackagingList::where('shipment_id', $this->shipment->id)->firstOrFail();
        $list->cartons()->firstOrFail()->items()->firstOrFail()->update(['quantity_per_carton' => 3, 'total_quantity' => 3]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
                fn ($warning) => $this->warningContains($warning, ['Board', 'confirmed packed']));
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame('blocked', $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail()->result);
    }

    public function test_existing_matching_override_does_not_hide_outstanding_readiness_warnings(): void
    {
        $authorization = app(ShipmentDispatchAuthorization::class);
        $evaluation = $authorization->evaluate($this->shipment, $this->workflow, $authorization->rowsFromShipment($this->shipment), $this->admin->id);
        app(ProjectWorkflowBypass::class)->overrideEvaluation($this->project, $evaluation, $this->admin, 'Administrator accepted this specific outstanding readiness risk.');
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
                fn ($warning) => $this->warningContains($warning, ['Packaging List', 'QC', 'dispatch documents']));
        $this->assertSame('overridden', $evaluation->fresh()->effectiveResult());
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertDatabaseCount('project_gate_overrides', 1);
    }

    public function test_matching_legacy_override_is_preserved_without_rewriting_its_snapshot(): void
    {
        $authorization = app(ShipmentDispatchAuthorization::class);
        $legacyDetails = $authorization->inspect($this->shipment, $this->workflow, $authorization->rowsFromShipment($this->shipment));
        unset($legacyDetails['paperwork']['delivery_checklist']);
        $evaluation = $this->workflow->evaluations()->create(['project_product_id' => null,
            'gate_key' => ShipmentDispatchAuthorization::GATE_KEY, 'result' => 'blocked',
            'content_hash' => hash('sha256', json_encode($legacyDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'summary' => 'Previously reviewed shipment readiness.', 'details' => $legacyDetails,
            'evaluated_by' => $this->admin->id, 'evaluated_at' => now()]);
        $evaluation->overrides()->create(['reason' => 'Administrator accepted this historical readiness risk.',
            'risk_snapshot' => $legacyDetails, 'approved_by' => $this->admin->id, 'approved_at' => now()]);
        $storedLegacyDetails = $evaluation->fresh()->details;
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning');
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($evaluation->id, $current->id);
        $this->assertSame('overridden', $current->effectiveResult());
        $this->assertSame($storedLegacyDetails, $current->details);
        $this->assertDatabaseCount('project_gate_overrides', 1);
    }

    public function test_matching_override_applies_to_delivery_when_pod_was_already_present(): void
    {
        ProjectAttachment::create(['project_id' => $this->project->id, 'category' => 'pod',
            'file_path' => 'fake/pod.pdf', 'original_name' => 'pod.pdf']);
        $this->shipment->items()->firstOrFail()->update(['quantity' => 6]);
        $authorization = app(ShipmentDispatchAuthorization::class);
        $evaluation = $authorization->evaluate($this->shipment, $this->workflow, $authorization->rowsFromShipment($this->shipment), $this->admin->id);
        app(ProjectWorkflowBypass::class)->overrideEvaluation($this->project, $evaluation, $this->admin, 'Administrator accepts this specific quantity risk.');
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload(['status' => 'delivered']))
            ->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning',
                fn ($warning) => $this->warningContains($warning, ['project product quantity']));
        $this->assertSame('delivered', $this->shipment->fresh()->status);
        $this->assertSame($evaluation->id, $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->value('id'));
        $this->assertSame('overridden', $evaluation->fresh()->effectiveResult());
        $this->assertDatabaseCount('project_gate_overrides', 1);
    }

    public function test_post_dispatch_gate_overrides_remain_specific_to_the_current_partial_shipment(): void
    {
        $this->shipment->items()->firstOrFail()->update(['quantity' => 2]);
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $other = Shipment::create(['shipment_number' => 'READINESS-BYPASS-OTHER', 'identity_name' => 'Other partial',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR', 'cost_borne_by' => 'misspack']);
        $other->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 3, 'unit' => 'pcs']);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning');
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($this->shipment->id, data_get($evaluation->details, 'shipment_id'));
        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)
            ->post(route('projects.workflow.gates.override', [$this->project, $evaluation]), ['reason' => 'Administrator accepts this particular shipment readiness risk.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('overridden', $evaluation->fresh()->effectiveResult());
        $override = $evaluation->fresh()->activeOverride;
        $this->patch(route('projects.workflow.overrides.revoke', [$this->project, $override]), ['revocation_reason' => 'Review the specific risk again.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $step = $this->workflow->allSteps()->whereNull('project_product_id')->where('step_key', ShipmentDispatchAuthorization::GATE_KEY)->firstOrFail();
        $this->post(route('projects.workflow.steps.bypass', [$this->project, $step]), ['reason' => 'Administrator accepts this particular shipment readiness risk.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($evaluation->id, $current->id);
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));
        $this->assertSame('overridden', $current->effectiveResult());
        $this->assertSame('planning', $other->fresh()->status);
        $this->assertSame(0, $this->project->approvalRequests()->count());
    }

    public function test_later_documents_qc_and_packing_refresh_the_current_dispatched_readiness(): void
    {
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning');
        $original = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $originalDetails = $original->details;
        $this->assertSame('blocked', $original->result);
        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)->post(route('projects.attachments.store', $this->project), [
            'category' => 'delivery_challan', 'title' => 'Challan', 'attachments' => [UploadedFile::fake()->create('challan.pdf', 1, 'application/pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $afterDocument = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertNotSame($original->id, $afterDocument->id);
        $this->assertNotContains('Delivery Challan', $afterDocument->details['paperwork']['missing']);
        $this->assertStringContainsString('QC', implode(' ', $afterDocument->details['issues']));

        $this->from('/projects/'.$this->project->id)->post(route('projects.quality-inspections.store', [$this->project, $this->product]), [
            'inspected_quantity' => 1, 'passed_quantity' => 1, 'rejected_quantity' => 0, 'rework_quantity' => 0,
            'result' => 'passed', 'checklist' => array_fill_keys(array_keys(ProjectQualityInspection::checks()), 1),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $afterQc = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertNotSame($afterDocument->id, $afterQc->id);
        $this->assertStringNotContainsString('QC sample', implode(' ', $afterQc->details['issues']));

        $list = PackagingList::create(['packing_number' => 'READINESS-LATER-PL', 'packing_date' => now()->toDateString(),
            'project_id' => $this->project->id, 'shipment_id' => $this->shipment->id, 'status' => 'draft',
            'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
        $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1,
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $this->product->id,
            'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
        $this->from(route('packaging-lists.show', $list))->patch(route('packaging-lists.status', $list), ['status' => 'confirmed'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame('passed', $current->result);
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));
        $this->assertSame($originalDetails, $original->fresh()->details);
        $this->assertSame('blocked', $original->fresh()->result);
        $this->assertDatabaseCount('project_gate_overrides', 0);
    }

    public function test_later_delivery_receipt_refreshes_pod_readiness_for_the_current_shipment(): void
    {
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload(['status' => 'delivered']))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('shipment_readiness_warning');
        $original = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertContains('Proof of Delivery (POD)', $original->details['paperwork']['missing']);
        $item = $this->shipment->items()->firstOrFail();
        $this->from(route('shipments.show', $this->shipment))->post(route('shipments.delivery-receipts.store', $this->shipment), [
            'recipient_name' => 'Fictional recipient', 'received_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'lines' => [['shipment_item_id' => $item->id, 'received_quantity' => 5, 'accepted_quantity' => 5,
                'short_quantity' => 0, 'damaged_quantity' => 0, 'rejected_quantity' => 0, 'returned_quantity' => 0, 'replacement_quantity' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertNotSame($original->id, $current->id);
        $this->assertNotContains('Proof of Delivery (POD)', $current->details['paperwork']['missing']);
        $this->assertContains('Proof of Delivery (POD)', $original->fresh()->details['paperwork']['missing']);
    }

    public function test_manual_workflow_refresh_rechecks_pending_approval_state_on_the_current_shipment(): void
    {
        $this->invoice->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 5]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $original = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertFalse(data_get($original->details, 'products.0.approvals_ready'));
        $this->workflow->allSteps()->where('project_product_id', $this->product->id)
            ->whereIn('step_key', ['artwork_approved', 'pps_approved'])->update(['status' => 'completed']);
        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)
            ->post(route('projects.workflow.evaluate', $this->project))->assertRedirect()->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));
        $this->assertTrue(data_get($current->details, 'products.0.approvals_ready'));
        $this->assertFalse(data_get($original->fresh()->details, 'products.0.approvals_ready'));
        $this->assertSame('blocked', $current->result); // Packing, QC and documents remain outstanding.
    }

    public function test_refreshing_other_partial_shipment_evidence_does_not_replace_the_current_gate(): void
    {
        $this->shipment->items()->firstOrFail()->update(['quantity' => 2]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors();
        $other = Shipment::create(['shipment_number' => 'READINESS-OTHER-SHIP', 'identity_name' => 'Other partial',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'shipment_mode' => 'courier', 'currency' => 'INR', 'cost_borne_by' => 'misspack']);
        $otherItem = $other->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 3, 'unit' => 'pcs']);
        $step = $this->workflow->allSteps()->whereNull('project_product_id')->where('step_key', ShipmentDispatchAuthorization::GATE_KEY)->firstOrFail();
        $guidance = app(\App\Services\ProjectWorkflowGuidance::class)->forStep($step);
        $this->assertSame($this->shipment->id, $guidance['shipment_id']);
        $this->assertStringContainsString('READINESS-SHIP', $guidance['title']);
        $list = PackagingList::create(['packing_number' => 'READINESS-OTHER-PL', 'packing_date' => now()->toDateString(),
            'project_id' => $this->project->id, 'shipment_id' => $other->id, 'status' => 'draft',
            'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
        $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1,
            'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $this->product->id,
            'quantity_per_carton' => 3, 'total_quantity' => 3, 'unit' => 'pcs']);
        $this->actingAs($this->admin)->from(route('packaging-lists.show', $list))
            ->patch(route('packaging-lists.status', $list), ['status' => 'confirmed'])->assertRedirect()->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));

        $other->update(['status' => 'delivered']);
        $this->from(route('shipments.show', $other))->post(route('shipments.delivery-receipts.store', $other), [
            'recipient_name' => 'Fictional recipient', 'received_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'lines' => [['shipment_item_id' => $otherItem->id, 'received_quantity' => 3, 'accepted_quantity' => 3,
                'short_quantity' => 0, 'damaged_quantity' => 0, 'rejected_quantity' => 0, 'returned_quantity' => 0, 'replacement_quantity' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));
        $this->assertSame(1, $other->deliveryReceipts()->count());
    }

    public function test_a_stale_shipment_gate_is_not_reassigned_to_an_unrelated_partial(): void
    {
        $this->shipment->items()->firstOrFail()->update(['quantity' => 2]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertSessionHasNoErrors();
        $evaluation = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $other = Shipment::create(['shipment_number' => 'READINESS-STALE-OTHER', 'identity_name' => 'Other partial',
            'project_id' => $this->project->id, 'client_id' => $this->project->client_id, 'status' => 'planning',
            'shipment_type' => 'domestic', 'currency' => 'INR']);
        $other->items()->create(['project_product_id' => $this->product->id, 'product_name' => 'Board', 'quantity' => 3, 'unit' => 'pcs']);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment),
            $this->listPayload(['_expected_status' => 'picked_up', 'status' => 'cancelled']))->assertSessionHasNoErrors();
        $step = $this->workflow->allSteps()->whereNull('project_product_id')->where('step_key', ShipmentDispatchAuthorization::GATE_KEY)->firstOrFail();
        $guide = app(\App\Services\ProjectWorkflowGuidance::class)->forStep($step);
        $this->assertArrayNotHasKey('shipment_id', $guide);
        $this->assertStringContainsString('previous dispatch check', $guide['title']);
        $this->actingAs($this->admin)->from('/projects/'.$this->project->id)
            ->post(route('projects.workflow.gates.override', [$this->project, $evaluation]), ['reason' => 'Administrator accepts this particular risk.'])
            ->assertSessionHasErrors('reason');
        $this->post(route('projects.workflow.steps.bypass', [$this->project, $step]), ['reason' => 'Administrator accepts this particular risk.'])
            ->assertSessionHasErrors('reason');
        $this->post(route('projects.attachments.store', $this->project), ['category' => 'delivery_challan', 'title' => 'Challan',
            'attachments' => [UploadedFile::fake()->create('challan.pdf', 1, 'application/pdf')]])->assertSessionHasNoErrors();
        $current = $this->workflow->evaluations()->where('gate_key', ShipmentDispatchAuthorization::GATE_KEY)->latest('id')->firstOrFail();
        $this->assertSame($evaluation->id, $current->id);
        $this->assertSame($this->shipment->id, data_get($current->details, 'shipment_id'));
        $this->assertSame('planning', $other->fresh()->status);
        $this->assertDatabaseCount('project_gate_overrides', 0);
    }

    public function test_complete_readiness_has_no_readiness_warning(): void
    {
        $this->prepareReadyShipment();
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->listPayload())
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('shipment_readiness_warning')
            ->assertSessionHas('shipment_balance_warning');
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
    }

    public function test_revoked_operations_access_hides_flashed_readiness_details(): void
    {
        $this->operator->update(['permissions' => ['dashboard.view']]);
        $this->actingAs($this->operator->fresh())->withSession(['shipment_readiness_warning' => [
            'message' => 'Private shipment readiness detail.', 'issues' => ['Private packing detail.'],
        ]])->get(route('dashboards.index'))->assertOk()->assertDontSee('Private shipment readiness detail.')
            ->assertDontSee('Private packing detail.');
    }

    public function test_warning_is_escaped_server_rendered_and_can_coexist_with_other_alerts(): void
    {
        $this->withSession(['shipment_readiness_warning' => ['message' => 'Shipment <unsafe> saved.',
            'issues' => ['Check <script>packing</script>.']], 'shipment_balance_warning' => 'Accounts needs review.',
            'warning' => 'Paperwork needs review.'])->get(route('shipments.index'))->assertOk()
            ->assertSee('Shipment &lt;unsafe&gt; saved.', false)->assertSee('Check &lt;script&gt;packing&lt;/script&gt;.', false)
            ->assertDontSee('<script>packing</script>', false)->assertSee('Shipment readiness warning')
            ->assertSee('Accounts needs review.')->assertSee('Paperwork needs review.');
    }
}
