<?php

namespace Tests\Unit;

use App\Models\Organisation;
use App\Models\OrganisationAddress;
use App\Models\Shipment;
use App\Services\ShipmentPrintIssuer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShipmentPrintIssuerTest extends TestCase
{
    private function organisation(): Organisation
    {
        $organisation = new Organisation([
            'legal_name' => 'MissPack India Pvt Ltd', 'trade_name' => 'MissPack',
            'email' => 'office@example.test', 'mobile' => '7041110823',
        ]);
        $organisation->setRelation('addresses', new Collection([
            new OrganisationAddress(['kind' => 'shipping', 'line1' => 'Not the default']),
            new OrganisationAddress(['kind' => 'shipping', 'is_default' => true, 'line1' => 'Default dispatch address', 'city' => 'Ahmedabad']),
            new OrganisationAddress(['kind' => 'billing', 'is_default' => true, 'line1' => 'Billing address']),
        ]));
        return $organisation;
    }

    public function test_letterhead_follows_normalised_shipper_identity_not_receiver_or_cost_payer(): void
    {
        $resolver = new ShipmentPrintIssuer;
        foreach (['MissPack', ' MISSPACK INDIA PVT. LTD. ', 'MissPack India Private Limited'] as $name) {
            $issuer = $resolver->forShipment(new Shipment(['from_name' => $name, 'to_name' => 'Client', 'cost_borne_by' => 'receiver']), $this->organisation());
            $this->assertStringContainsString('images/logo-dark.png', $issuer['logo_url']);
            $this->assertSame(trim($name), $issuer['name']);
        }
        foreach (['Solo Beauty Packaging Co., Ltd', '', 'Other MissPackaging Supplier'] as $name) {
            $issuer = $resolver->forShipment(new Shipment(['from_name' => $name, 'to_name' => 'MissPack', 'cost_borne_by' => 'misspack']), $this->organisation());
            $this->assertNull($issuer['logo_url']);
            $this->assertSame('', $issuer['address']);
            $this->assertSame('', $issuer['email']);
            $this->assertSame('', $issuer['mobile']);
        }
    }

    public function test_stored_origin_and_contacts_take_precedence_over_current_organisation_settings(): void
    {
        $issuer = (new ShipmentPrintIssuer)->forShipment(new Shipment([
            'from_name' => 'MissPack', 'from_address' => 'Old dispatch office, Ahmedabad, Gujarat 380004, India',
            'from_city' => 'Ahmedabad', 'from_state' => 'Gujarat', 'from_pincode' => '380004', 'from_country' => 'India',
            'from_mobile' => '1234567890', 'from_email' => 'dispatch@example.test',
        ]), $this->organisation());
        $this->assertSame('Old dispatch office, Ahmedabad, Gujarat 380004, India', $issuer['address']);
        $this->assertSame('1234567890', $issuer['mobile']);
        $this->assertSame('dispatch@example.test', $issuer['email']);
    }

    public function test_missing_own_origin_falls_back_to_default_shipping_then_billing_only(): void
    {
        $organisation = $this->organisation();
        $shipment = new Shipment(['from_name' => 'MissPack']);
        $resolver = new ShipmentPrintIssuer;
        $this->assertSame('Default dispatch address, Ahmedabad', $resolver->forShipment($shipment, $organisation)['address']);
        $organisation->setRelation('addresses', $organisation->addresses->where('kind', 'billing')->values());
        $this->assertSame('Billing address', $resolver->forShipment($shipment, $organisation)['address']);
        $organisation->setRelation('addresses', new Collection);
        $this->assertSame('', $resolver->forShipment($shipment, $organisation)['address']);
    }

    public function test_partial_shipment_address_is_not_silently_replaced_with_current_office(): void
    {
        $issuer = (new ShipmentPrintIssuer)->forShipment(new Shipment(['from_name' => 'MissPack', 'from_city' => 'Surat']), $this->organisation());
        $this->assertSame('Surat', $issuer['address']);
    }

    public function test_configured_print_logo_is_used_without_screen_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/receipt-print.png', 'test-print-logo');
        $organisation = $this->organisation();
        $organisation->logo_print_path = 'logos/receipt-print.png';
        $organisation->logo_path = 'logos/screen.png';
        $issuer = (new ShipmentPrintIssuer)->forShipment(new Shipment(['from_name' => 'MissPack']), $organisation);
        $this->assertStringContainsString('storage/logos/receipt-print.png', $issuer['logo_url']);
        $this->assertStringNotContainsString('screen.png', $issuer['logo_url']);
    }

    public function test_address_preserves_non_latin_city_state_and_country(): void
    {
        $address = (new ShipmentPrintIssuer)->address(new Shipment([
            'from_address' => 'Warehouse A', 'from_city' => 'અમદાવાદ', 'from_state' => 'ગુજરાત',
            'from_pincode' => '380004', 'from_country' => 'ભારત',
        ]), 'from');
        $this->assertSame('Warehouse A, અમદાવાદ, ગુજરાત, 380004, ભારત', $address);
    }
}
