<?php

namespace Tests\Feature;

use Tests\Support\DeliveryReceiptPrintFixture;
use Tests\TestCase;

class DeliveryReceiptPrintViewTest extends TestCase
{
    private function renderReceipt(string $sender = 'MissPack India Pvt Ltd', bool $discrepancy = false): string
    {
        config(['app.name' => 'LARAVEL']);
        return view('shipments.print.delivery-receipt', DeliveryReceiptPrintFixture::data($sender, $discrepancy))->render();
    }

    public function test_shared_print_has_no_internal_integrity_or_evidence_metadata(): void
    {
        $html = $this->renderReceipt();
        foreach (['Record integrity', 'SHA-256', str_repeat('a', 64), substr(str_repeat('b', 64), 0, 24),
            'Internal audit operator', 'Recorded by', 'internal-evidence.pdf', 'Evidence document', 'LARAVEL'] as $internal) {
            $this->assertStringNotContainsString($internal, $html);
        }
        foreach (['SHIP-RECEIPT-001', 'POD-003', '25 Oct 2026', 'Shampoo bottle', '100.000'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
    }

    public function test_own_shipper_has_print_logo_and_origin_address_above_document_title(): void
    {
        $html = $this->renderReceipt();
        foreach (['class="receipt-logo"', 'images/logo-dark.png', 'MissPack India Pvt Ltd', '229 Block F, Shahibaug',
            'Ahmedabad', 'Gujarat', '380004', 'India'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        $this->assertLessThan(strpos($html, '<h1>Delivery Receipt</h1>'), strpos($html, 'class="receipt-logo"'));
        $this->assertLessThan(strpos($html, '<h1>Delivery Receipt</h1>'), strpos($html, '229 Block F, Shahibaug'));
        $this->assertStringNotContainsString('Current organisation address', $html);
    }

    public function test_third_party_or_empty_shipper_does_not_get_our_letterhead_even_when_receiver_is_misspack(): void
    {
        foreach (['Solo Beauty Packaging Co., Ltd', ''] as $sender) {
            $html = $this->renderReceipt($sender);
            $this->assertStringNotContainsString('class="receipt-logo"', $html);
            $this->assertStringNotContainsString('images/logo-dark.png', $html);
            $this->assertStringNotContainsString('Current organisation address', $html);
            if ($sender !== '') $this->assertStringContainsString($sender, $html);
        }
    }

    public function test_receiver_can_sign_date_and_stamp_without_accepting_undisclosed_damage(): void
    {
        $html = $this->renderReceipt(discrepancy: true);
        foreach (['Receiver acknowledgement', 'Receiver signature', 'Name / designation', 'Date / time',
            'Company stamp', 'receipt-signature-space', 'receipt-stamp-space', 'Received with discrepancies',
            'Two damaged &lt;bottles&gt;', '98.000', '2.000'] as $detail) {
            $this->assertStringContainsString($detail, $html);
        }
        $this->assertStringNotContainsString('Received in accepted condition with no discrepancy.', $html);
        $this->assertStringNotContainsString('Received in good condition', $html);
    }

    public function test_shared_print_escapes_recipient_notes_and_reasons(): void
    {
        $html = $this->renderReceipt(discrepancy: true);
        $this->assertStringContainsString('Receiver &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('Unpack &lt;carefully&gt;', $html);
        $this->assertStringContainsString('Two damaged &lt;bottles&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_party_names_and_addresses_remain_plain_text(): void
    {
        $data = DeliveryReceiptPrintFixture::data('Vendor <script>alert(2)</script>');
        $data['shipment']->from_address = '<img src=x onerror=alert(3)>';
        $data['shipment']->to_address = '<b>Receiver warehouse</b>';
        $html = view('shipments.print.delivery-receipt', $data)->render();
        $this->assertStringContainsString('Vendor &lt;script&gt;alert(2)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(3)&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Receiver warehouse&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
