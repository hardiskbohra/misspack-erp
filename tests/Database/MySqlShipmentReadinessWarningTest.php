<?php

namespace Tests\Database;

use App\Models\Client;
use App\Models\PackagingList;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectProduct;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ProjectWorkflowBypass;
use App\Services\ProjectWorkflowExecution;
use App\Services\ShipmentDispatchAuthorization;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Current-read designs on guarded MySQL; these do not establish deadlock freedom. */
class MySqlShipmentReadinessWarningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Shipment readiness checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Shipment readiness checks require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        config(['cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        config(['database.connections.readiness_competitor' => config('database.connections.'.$db->getName())]);
        DB::purge('readiness_competitor');
    }

    private function fixture(float $quantity): array
    {
        $suffix = bin2hex(random_bytes(5));
        $admin = User::create(['name' => 'Readiness administrator', 'email' => 'readiness-native-'.$suffix.'@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $client = Client::create(['client_number' => 'READINESS-NATIVE-'.$suffix, 'company_name' => 'Fictional buyer']);
        $project = Project::forceCreate(['project_number' => 'READINESS-NATIVE-'.$suffix, 'client_id' => $client->id,
            'name' => 'Fictional project', 'status' => 'planned', 'stage' => 'setup', 'currency' => 'INR']);
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Board', 'quantity' => 5]);
        $workflow = app(ProjectWorkflowExecution::class)->start($project, $admin->id);
        $shipment = Shipment::create(['shipment_number' => 'READINESS-NATIVE-'.$suffix, 'identity_name' => 'Fictional shipment',
            'project_id' => $project->id, 'status' => 'planning', 'shipment_type' => 'domestic', 'currency' => 'INR']);
        $shipment->items()->create(['project_product_id' => $product->id, 'product_name' => 'Board', 'quantity' => $quantity, 'unit' => 'pcs']);
        return compact('admin', 'client', 'project', 'product', 'workflow', 'shipment', 'suffix');
    }

    private function cleanup(array $fixture): void
    {
        while (DB::connection()->transactionLevel() > 0) DB::connection()->rollBack();
        $other = DB::connection('readiness_competitor');
        while ($other->transactionLevel() > 0) $other->rollBack();
        DB::purge('readiness_competitor');
        DB::table('packaging_lists')->where('project_id', $fixture['project']->id)->delete();
        DB::table('shipments')->where('project_id', $fixture['project']->id)->delete();
        DB::table('projects')->where('id', $fixture['project']->id)->delete();
        DB::table('clients')->where('id', $fixture['client']->id)->delete();
        DB::table('users')->where('id', $fixture['admin']->id)->delete();
    }

    public function test_current_reservation_read_observes_a_shipment_reactivated_after_the_snapshot(): void
    {
        $f = $this->fixture(2);
        try {
            $otherShipment = Shipment::create(['shipment_number' => 'READINESS-OTHER-'.$f['suffix'], 'identity_name' => 'Other partial',
                'project_id' => $f['project']->id, 'status' => 'cancelled', 'shipment_type' => 'domestic', 'currency' => 'INR']);
            $otherShipment->items()->create(['project_product_id' => $f['product']->id, 'product_name' => 'Board', 'quantity' => 4, 'unit' => 'pcs']);
            $db = DB::connection();
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            $this->assertSame('cancelled', DB::table('shipments')->where('id', $otherShipment->id)->value('status'));
            $other = DB::connection('readiness_competitor');
            $other->transaction(function () use ($other, $f, $otherShipment) {
                $other->table('projects')->where('id', $f['project']->id)->lockForUpdate()->first();
                $other->table('shipments')->where('id', $otherShipment->id)->update(['status' => 'planning']);
            });
            Project::query()->whereKey($f['project']->id)->lockForUpdate()->firstOrFail();
            $authorization = app(ShipmentDispatchAuthorization::class);
            $assessment = $authorization->inspectForTracking($f['shipment'], $f['workflow'], $authorization->rowsFromShipment($f['shipment'], true), true);
            $this->assertEquals(4, data_get($assessment, 'details.products.0.reserved_elsewhere'));
            $this->assertStringContainsString('project product quantity', implode(' ', $assessment['blocking_issues']));
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_current_document_read_observes_a_challan_removed_after_the_snapshot(): void
    {
        $f = $this->fixture(5);
        try {
            $document = ProjectAttachment::create(['project_id' => $f['project']->id, 'category' => 'delivery_challan',
                'file_path' => 'fake/challan.pdf', 'original_name' => 'challan.pdf']);
            $db = DB::connection();
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            $this->assertSame(1, DB::table('project_attachments')->where('id', $document->id)->count());
            $other = DB::connection('readiness_competitor');
            $other->transaction(function () use ($other, $f, $document) {
                $other->table('projects')->where('id', $f['project']->id)->lockForUpdate()->first();
                $other->table('project_attachments')->where('id', $document->id)->delete();
            });
            Project::query()->whereKey($f['project']->id)->lockForUpdate()->firstOrFail();
            $authorization = app(ShipmentDispatchAuthorization::class);
            $assessment = $authorization->inspectForTracking($f['shipment'], $f['workflow'], $authorization->rowsFromShipment($f['shipment'], true), true);
            $this->assertContains('Delivery Challan', data_get($assessment, 'details.paperwork.missing'));
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_current_packing_lines_observe_quantity_changed_after_the_snapshot(): void
    {
        $f = $this->fixture(5);
        try {
            $list = PackagingList::create(['packing_number' => 'READINESS-NATIVE-'.$f['suffix'], 'packing_date' => now()->toDateString(),
                'project_id' => $f['project']->id, 'shipment_id' => $f['shipment']->id, 'status' => 'confirmed',
                'from_name' => 'Fictional issuer', 'bill_to_name' => 'Fictional buyer', 'ship_to_name' => 'Fictional buyer']);
            $carton = $list->cartons()->create(['carton_label' => 'Board', 'carton_count' => 1, 'length_cm' => 30,
                'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
            $item = $carton->items()->create(['item_description' => 'Board', 'project_product_id' => $f['product']->id,
                'quantity_per_carton' => 5, 'total_quantity' => 5, 'unit' => 'pcs']);
            $db = DB::connection();
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            $this->assertEquals(5, DB::table('packaging_list_items')->where('id', $item->id)->value('total_quantity'));
            $other = DB::connection('readiness_competitor');
            $other->transaction(function () use ($other, $f, $item) {
                $other->table('projects')->where('id', $f['project']->id)->lockForUpdate()->first();
                $other->table('packaging_list_items')->where('id', $item->id)->update(['quantity_per_carton' => 3, 'total_quantity' => 3]);
            });
            Project::query()->whereKey($f['project']->id)->lockForUpdate()->firstOrFail();
            $authorization = app(ShipmentDispatchAuthorization::class);
            $assessment = $authorization->inspectForTracking($f['shipment'], $f['workflow'], $authorization->rowsFromShipment($f['shipment'], true), true);
            $this->assertEquals(3, data_get($assessment, 'details.lines.0.linked_packed_quantity'));
            $this->assertStringContainsString('confirmed packed', implode(' ', $assessment['warnings']));
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_gate_application_preserves_step_start_recorded_after_the_snapshot(): void
    {
        $f = $this->fixture(5);
        try {
            $step = $f['workflow']->allSteps()->whereNull('project_product_id')->where('step_key', ShipmentDispatchAuthorization::GATE_KEY)->firstOrFail();
            $db = DB::connection();
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            $this->assertNull(DB::table('project_workflow_steps')->where('id', $step->id)->value('actual_started_at'));
            $other = DB::connection('readiness_competitor');
            $other->transaction(function () use ($other, $f, $step) {
                $other->table('projects')->where('id', $f['project']->id)->lockForUpdate()->first();
                $other->table('project_workflow_steps')->where('id', $step->id)->update(['actual_started_at' => '2026-01-02 10:00:00']);
            });
            $authorization = app(ShipmentDispatchAuthorization::class);
            $authorization->evaluateForTracking($f['shipment'], $f['workflow'], $authorization->rowsFromShipment($f['shipment'], true), $f['admin']->id, true);
            $this->assertSame('2026-01-02 10:00:00', DB::table('project_workflow_steps')->where('id', $step->id)->value('actual_started_at'));
        } finally {
            $this->cleanup($f);
        }
    }

    public function test_current_override_read_does_not_reuse_a_bypass_revoked_after_the_snapshot(): void
    {
        $f = $this->fixture(6);
        try {
            $authorization = app(ShipmentDispatchAuthorization::class);
            $evaluation = $authorization->evaluate($f['shipment'], $f['workflow'], $authorization->rowsFromShipment($f['shipment']), $f['admin']->id);
            app(ProjectWorkflowBypass::class)->overrideEvaluation($f['project'], $evaluation, $f['admin'], 'Administrator accepts this specific quantity risk.');
            $override = $evaluation->fresh()->activeOverride;
            $this->assertNotNull($override);
            $db = DB::connection();
            $db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $db->beginTransaction();
            $this->assertNull(DB::table('project_gate_overrides')->where('id', $override->id)->value('revoked_at'));
            $other = DB::connection('readiness_competitor');
            $other->transaction(function () use ($other, $f, $override) {
                $other->table('projects')->where('id', $f['project']->id)->lockForUpdate()->first();
                $other->table('project_gate_overrides')->where('id', $override->id)->update([
                    'revoked_at' => now(), 'revoked_by' => $f['admin']->id, 'revocation_reason' => 'The accepted risk was withdrawn.',
                ]);
            });
            $candidate = clone $f['shipment'];
            $candidate->status = Shipment::STATUS_PICKED_UP;
            $assessment = $authorization->evaluateForTracking($candidate, $f['workflow'], $authorization->rowsFromShipment($f['shipment'], true), $f['admin']->id, true);
            $this->assertSame($evaluation->id, $assessment['evaluation']->id);
            $this->assertSame('blocked', $assessment['evaluation']->effectiveResult());
            $this->assertStringContainsString('project product quantity', implode(' ', $assessment['blocking_issues']));
        } finally {
            $this->cleanup($f);
        }
    }
}
