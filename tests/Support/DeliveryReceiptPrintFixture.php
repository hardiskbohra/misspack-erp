<?php

namespace Tests\Support;

use App\Models\Organisation;
use App\Models\OrganisationAddress;
use App\Models\Shipment;
use App\Models\ShipmentDeliveryReceipt;
use App\Models\ShipmentDeliveryReceiptLine;
use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** In-memory print fixtures only: no database or real shipment data. */
class DeliveryReceiptPrintFixture
{
    public static function data(string $sender = 'MissPack India Pvt Ltd', bool $discrepancy = false): array
    {
        $shipment = new Shipment([
            'shipment_number' => 'SHIP-RECEIPT-001', 'identity_name' => 'Bottle delivery',
            'from_name' => $sender, 'from_address' => '229 Block F, Shahibaug',
            'from_city' => 'Ahmedabad', 'from_state' => 'Gujarat', 'from_pincode' => '380004',
            'from_country' => 'India', 'to_name' => 'MissPack receiving warehouse',
            'to_address' => 'Receiver warehouse, Bengaluru',
        ]);
        $shipment->id = 4;
        $shipment->setRelation('client', null)->setRelation('project', null);
        $receipt = new ShipmentDeliveryReceipt([
            'shipment_id' => 4, 'recipient_name' => 'Receiver <script>alert(1)</script>',
            'receipt_reference' => 'POD-003', 'received_at' => '2026-10-25 14:30:00',
            'content_hash' => str_repeat('a', 64), 'evidence_hash' => str_repeat('b', 64),
            'evidence_original_name' => 'internal-evidence.pdf', 'notes' => 'Unpack <carefully>',
        ]);
        $receipt->id = 3;
        $receipt->setRelation('recorder', new User(['name' => 'Internal audit operator']));
        $line = new ShipmentDeliveryReceiptLine([
            'received_quantity' => 100, 'accepted_quantity' => $discrepancy ? 98 : 100,
            'damaged_quantity' => $discrepancy ? 2 : 0,
            'discrepancy_reason' => $discrepancy ? 'Two damaged <bottles>' : null,
        ]);
        $line->setRelation('shipmentItem', new ShipmentItem(['product_name' => 'Shampoo bottle', 'unit' => 'pcs']));
        $receipt->setRelation('lines', new Collection([$line]));
        $organisation = new Organisation(['legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack']);
        $organisation->setRelation('addresses', new Collection([
            new OrganisationAddress(['kind' => 'shipping', 'is_default' => true, 'line1' => 'Current organisation address']),
        ]));

        return compact('shipment', 'receipt', 'organisation');
    }
}
