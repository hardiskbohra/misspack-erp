<?php

namespace Tests\Integration;

use App\Events\ShipmentStatusChanged;
use App\Models\Client;
use App\Models\Project;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectWorkflowExecution;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Real shipment routes on a disposable full-schema SQLite database. */
class ShipmentListStatusTest extends TestCase
{
    use IsolatedDatabase;

    private User $manager;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]), Artisan::output());
        $this->manager = $this->user('manager', ['operations.manage']);
        $this->actingAs($this->manager);
        $this->shipment = $this->shipment('LIST-001');
    }

    private function user(string $name, array $permissions): User
    {
        return User::create(['name' => ucfirst($name), 'email' => $name.'@list-status.example.test', 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
    }

    private function shipment(string $number, array $overrides = []): Shipment
    {
        return Shipment::create(array_merge(['shipment_number' => $number, 'identity_name' => 'Fictional carton delivery',
            'shipment_type' => 'domestic', 'shipment_mode' => 'road', 'status' => 'in_transit',
            'pickup_date' => '2026-01-01', 'currency' => 'INR'], $overrides));
    }

    private function payload(Shipment $shipment, array $overrides = []): array
    {
        return array_replace(['_list_status_modal' => '1', '_list_shipment_id' => (string) $shipment->id,
            '_expected_status' => $shipment->status, 'status' => 'out_for_delivery',
            'location' => 'Fictional hub', 'remarks' => 'On the local route', 'is_public' => '0', 'notify_client' => '0'], $overrides);
    }

    public function test_listing_shows_a_status_action_for_managers_but_not_readers(): void
    {
        $second = $this->shipment('LIST-002', ['status' => 'out_for_delivery']);
        $response = $this->get(route('shipments.index'))->assertOk();
        $response->assertSee('id="shipmentStatusModal"', false)->assertSee('data-shipment-status-open', false)
            ->assertSee('Update status')->assertSee(route('shipments.history.store', $this->shipment), false)
            ->assertSee(route('shipments.history.store', $second), false)
            ->assertSee(route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']), false);

        $reader = $this->user('reader', ['operations.view']);
        $this->actingAs($reader)->get(route('shipments.index'))->assertOk()->assertSee('In transit')
            ->assertDontSee('data-shipment-status-open', false)->assertDontSee('id="shipmentStatusModal"', false);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->payload($this->shipment))
            ->assertForbidden();
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_modal_updates_the_selected_shipment_through_existing_history_and_preserves_list_filters(): void
    {
        $other = $this->shipment('LIST-OTHER');
        $client = Client::create(['client_number' => 'LIST-CLIENT', 'company_name' => 'Fictional receiver']);
        $this->shipment->update(['client_id' => $client->id, 'show_client_portal' => true]);
        Event::fake([ShipmentStatusChanged::class]);
        $list = route('shipments.index', ['status' => 'in_transit', 'search' => 'LIST-001', 'page' => 1]);
        $payload = $this->payload($this->shipment, ['status' => 'delayed', 'remarks' => 'Private carrier update',
            'event_time' => '2026-06-01T11:30']);
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $payload)
            ->assertRedirect($list)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('delayed', $this->shipment->fresh()->status);
        $this->assertSame('in_transit', $other->fresh()->status);
        $history = $this->shipment->histories()->firstOrFail();
        $this->assertSame('delayed', $history->status);
        $this->assertSame('Fictional hub', $history->location);
        $this->assertSame('Private carrier update', $history->remarks);
        $this->assertSame('2026-06-01 11:30', $history->event_time->format('Y-m-d H:i'));
        $this->assertFalse($history->is_public);
        $this->assertSame($this->manager->id, $history->created_by);
        $this->assertSame(0, $other->histories()->count());
        $this->assertSame(0, DB::table('client_portal_notifications')->where('related_id', $this->shipment->id)->count());
        Event::assertDispatched(ShipmentStatusChanged::class, fn ($event) => $event->shipment->id === $this->shipment->id && $event->from === 'in_transit');
        $this->get(route('shipments.publicTrack', $this->shipment->public_token))->assertOk()->assertDontSee('Private carrier update');
    }

    public function test_public_history_and_requested_client_notification_are_preserved(): void
    {
        $client = Client::create(['client_number' => 'LIST-NOTIFY', 'company_name' => 'Fictional recipient']);
        $this->shipment->update(['client_id' => $client->id, 'show_client_portal' => true]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment),
            $this->payload($this->shipment, ['remarks' => 'Public delivery update', 'is_public' => '1', 'notify_client' => '1']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($this->shipment->histories()->firstOrFail()->is_public);
        $this->assertDatabaseHas('client_portal_notifications', ['client_id' => $client->id, 'related_type' => 'shipment',
            'related_id' => $this->shipment->id]);
        $this->get(route('shipments.publicTrack', $this->shipment->public_token))->assertOk()->assertSee('Public delivery update');
    }

    public function test_a_tracking_note_without_a_status_change_does_not_emit_a_status_change(): void
    {
        $client = Client::create(['client_number' => 'LIST-UNCHANGED', 'company_name' => 'Fictional recipient']);
        $this->shipment->update(['client_id' => $client->id, 'show_client_portal' => true]);
        Event::fake([ShipmentStatusChanged::class]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment),
            $this->payload($this->shipment, ['status' => 'in_transit', 'notify_client' => '1']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $this->shipment->histories()->count());
        $this->assertSame(0, DB::table('client_portal_notifications')->where('related_id', $this->shipment->id)->count());
        Event::assertNotDispatched(ShipmentStatusChanged::class);
    }

    public function test_delivery_defaults_keep_existing_dates_and_reject_dates_before_pickup(): void
    {
        $list = route('shipments.index');
        $this->from($list)->post(route('shipments.history.store', $this->shipment),
            $this->payload($this->shipment, ['status' => 'delivered', 'drop_date' => '2025-12-31']))
            ->assertRedirect($list)->assertSessionHasErrors('drop_date');
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());

        $this->from($list)->post(route('shipments.history.store', $this->shipment),
            $this->payload($this->shipment, ['status' => 'delivered', 'drop_date' => '']))
            ->assertRedirect($list)->assertSessionHasNoErrors();
        $this->assertSame(Shipment::businessToday(), $this->shipment->fresh()->drop_date->toDateString());

        $existing = $this->shipment('LIST-DATE', ['drop_date' => '2026-02-01']);
        $this->from($list)->post(route('shipments.history.store', $existing),
            $this->payload($existing, ['status' => 'delivered', 'drop_date' => '']))
            ->assertRedirect($list)->assertSessionHasNoErrors();
        $this->assertSame('2026-02-01', $existing->fresh()->drop_date->toDateString());
    }

    public function test_failed_update_reopens_the_right_shipment_with_input_even_when_filtered_off_the_page(): void
    {
        $other = $this->shipment('LIST-OTHER');
        $list = route('shipments.index', ['search' => 'no-matching-row', 'page' => 3]);
        $payload = $this->payload($this->shipment, ['_list_shipment_id' => (string) $other->id,
            'status' => 'delayed', 'remarks' => '', 'location' => 'Retain this location']);
        // Follow the redirect before inspecting the JSON-serialized session:
        // starting Laravel's in-process test session between requests can
        // remarshal its error bag and lose the errors on the next GET.
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $payload)->assertRedirect($list);
        $this->get($list)->assertOk()->assertSee('data-open-on-load="true"', false)
            ->assertSee('Shipment status was not updated')->assertSee('Add a remark explaining why the shipment is on hold or delayed.')
            ->assertSee('Retain this location')->assertSee('<input type="hidden" name="_list_shipment_id" value="'.$this->shipment->id.'">', false)
            ->assertSee('<strong data-shipment-status-number>LIST-001</strong>', false)
            ->assertSee(route('shipments.history.store', $this->shipment), false)->assertDontSee(route('shipments.history.store', $other), false);
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_array_valued_old_input_cannot_break_validation_recovery(): void
    {
        $list = route('shipments.index', ['search' => 'LIST-001']);
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $this->payload($this->shipment, [
            'status' => ['delayed'], 'location' => ['bad'], 'remarks' => ['bad'], 'event_time' => ['bad'],
            'drop_date' => ['bad'], 'is_public' => ['1'], 'notify_client' => ['1'],
        ]))->assertRedirect($list);
        $this->get($list)->assertOk()->assertSee('Shipment status was not updated')
            ->assertSee('data-open-on-load="true"', false)->assertSee('LIST-001');
        $this->assertSame('in_transit', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_stale_status_is_refused_and_the_operator_can_review_the_latest_value(): void
    {
        $payload = $this->payload($this->shipment, ['status' => 'delayed', 'remarks' => 'Retain this note']);
        $this->shipment->update(['status' => 'picked_up']);
        $list = route('shipments.index', ['search' => 'LIST-001']);
        $this->from($list)->post(route('shipments.history.store', $this->shipment), $payload)->assertRedirect($list);
        $this->get($list)->assertOk()->assertSee('data-open-on-load="true"', false)
            ->assertSee('Retain this note')->assertSee('Picked up')->assertSee('changed since');
        $this->assertSame('picked_up', $this->shipment->fresh()->status);
        $this->assertSame(0, $this->shipment->histories()->count());
    }

    public function test_project_dispatch_gates_still_block_a_list_status_change(): void
    {
        $client = Client::create(['client_number' => 'LIST-GATE-CLIENT', 'company_name' => 'Fictional client']);
        $project = Project::forceCreate(['project_number' => 'LIST-GATE', 'client_id' => $client->id,
            'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        app(ProjectWorkflowExecution::class)->start($project, $this->manager->id);
        $shipment = $this->shipment('LIST-GATE-SHIP', ['status' => 'planning', 'project_id' => $project->id, 'client_id' => $client->id]);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $shipment),
            $this->payload($shipment, ['status' => 'picked_up']))->assertRedirect()->assertSessionHasErrors('status');
        $this->assertSame('planning', $shipment->fresh()->status);
        $this->assertSame(0, $shipment->histories()->count());
    }

    public function test_legacy_current_status_can_be_replaced_from_the_list(): void
    {
        $this->shipment->update(['status' => 'legacy_status']);
        $this->from(route('shipments.index'))->post(route('shipments.history.store', $this->shipment), $this->payload($this->shipment))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('out_for_delivery', $this->shipment->fresh()->status);
        $this->assertSame(1, $this->shipment->histories()->count());
    }

    public function test_existing_tracking_form_does_not_require_the_new_list_marker_or_status_token(): void
    {
        $detail = route('shipments.show', ['shipment' => $this->shipment, 'tab' => 'tracking']);
        $this->from($detail)->post(route('shipments.history.store', $this->shipment), [
            'status' => 'out_for_delivery', 'remarks' => 'Detail page update', 'is_public' => '1', 'notify_client' => '0',
        ])->assertRedirect($detail)->assertSessionHasNoErrors();
        $this->assertSame('out_for_delivery', $this->shipment->fresh()->status);
        $this->assertSame(1, $this->shipment->histories()->count());
    }
}
