<?php

namespace Tests\Integration;

use App\Models\Shipment;
use App\Models\ShipmentDeliveryReceipt;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class DeliveryReceiptPrintTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_06_09_010000_create_shipments_table',
            '2026_06_09_010100_create_shipment_items_table',
        ]);
        // These unrelated nullable FK targets are sufficient for the scoped receipt fixture.
        Schema::create('project_products', fn (Blueprint $table) => $table->id());
        (require database_path('migrations/2026_10_18_175000_create_shipment_delivery_receipts.php'))->up();
        (require database_path('migrations/2026_10_20_150000_make_delivery_receipt_evidence_optional.php'))->up();
    }

    private function office(array $permissions = ['operations.view']): User
    {
        $user = User::create(['name' => 'Internal recorder', 'email' => 'receipt@example.test',
            'password' => 'password', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
        $this->actingAs($user);
        return $user;
    }

    private function receipt(string $shipper = 'MissPack India Pvt Ltd', bool $evidence = true): ShipmentDeliveryReceipt
    {
        $shipment = Shipment::create(['shipment_number' => 'SHIP-PRINT-001', 'identity_name' => 'Bottle delivery',
            'from_name' => $shipper, 'from_address' => '229 Block F, Shahibaug, Ahmedabad 380004',
            'to_name' => 'Receiver Limited', 'to_address' => 'Receiver warehouse', 'status' => 'delivered']);
        $item = ShipmentItem::create(['shipment_id' => $shipment->id, 'product_name' => '100ml bottle', 'quantity' => 100, 'unit' => 'pcs']);
        $receipt = ShipmentDeliveryReceipt::create([
            'shipment_id' => $shipment->id, 'recipient_name' => 'Warehouse receiver', 'received_at' => '2026-10-25 14:30:00',
            'receipt_reference' => 'POD-001', 'recorded_by' => auth()->id(), 'content_hash' => str_repeat('c', 64),
            'evidence_hash' => $evidence ? str_repeat('e', 64) : null,
            'evidence_path' => $evidence ? 'shipments/delivery-receipts/internal.pdf' : null,
            'evidence_original_name' => $evidence ? 'internal-receiver-copy.pdf' : null,
        ]);
        $receipt->lines()->create(['shipment_item_id' => $item->id, 'received_quantity' => 100, 'accepted_quantity' => 99,
            'damaged_quantity' => 1, 'discrepancy_reason' => 'One bottle damaged']);
        return $receipt;
    }

    public function test_authenticated_print_is_receiver_ready_without_mutating_stored_hashes_or_evidence(): void
    {
        $this->office();
        $receipt = $this->receipt();
        $before = $receipt->fresh()->getAttributes();
        $lines = DB::table('shipment_delivery_receipt_lines')->get()->toArray();
        $shipment = $receipt->shipment->getAttributes();
        $this->get(route('shipments.delivery-receipts.print', [$receipt->shipment_id, $receipt]))
            ->assertOk()->assertSee('Company stamp')->assertSee('Receiver signature')->assertSee('229 Block F')
            ->assertSee('images/logo-dark.png', false)->assertSee('One bottle damaged')
            ->assertDontSee('Record integrity')->assertDontSee('SHA-256')
            ->assertDontSee(str_repeat('c', 64))->assertDontSee(substr(str_repeat('e', 64), 0, 24))
            ->assertDontSee('Internal recorder')->assertDontSee('internal-receiver-copy.pdf');
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertEquals($lines, DB::table('shipment_delivery_receipt_lines')->get()->toArray());
        $this->assertSame($shipment, $receipt->shipment->fresh()->getAttributes());
    }

    public function test_optional_evidence_and_vendor_shipper_remain_printable_without_our_logo(): void
    {
        $this->office();
        $receipt = $this->receipt('Solo Beauty Packaging Co., Ltd', false);
        $this->get(route('shipments.delivery-receipts.print', [$receipt->shipment_id, $receipt]))
            ->assertOk()->assertSee('Solo Beauty Packaging Co., Ltd')->assertSee('Company stamp')
            ->assertDontSee('images/logo-dark.png', false)->assertDontSee('Evidence document')->assertDontSee('SHA-256');
    }

    public function test_receipt_cannot_be_printed_under_another_shipment(): void
    {
        $this->office();
        $receipt = $this->receipt();
        $other = Shipment::create(['shipment_number' => 'SHIP-OTHER', 'identity_name' => 'Other shipment']);
        $this->get(route('shipments.delivery-receipts.print', [$other, $receipt]))->assertNotFound();
    }

    public function test_print_retains_login_and_operations_permission_boundaries(): void
    {
        $receipt = $this->receipt();
        $url = route('shipments.delivery-receipts.print', [$receipt->shipment_id, $receipt]);
        $this->get($url)->assertRedirect(route('login'));
        $this->office(['finance.view']);
        $this->get($url)->assertForbidden();
    }
}
