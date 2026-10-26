<?php

namespace Tests\Feature;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceEInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * IT-TAX-001…006 — the e-invoice lifecycle: pre-flight refusal, payload
 * generation in portal schema shape, the manual acknowledgement flow,
 * the portal's 24-hour cancellation window, and the HTTP gateway's contract.
 *
 * Fixtures pin *valid-but-fictitious* GSTINs so the checksum gate cannot be
 * bypassed by accident: `24AATCM8816E1Z5` (the seeded organisation), buyer
 * `24ABCDE1234F1Z6`.
 */
class EInvoiceFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:' . base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_08_26_010100_create_sales_invoice_items_table',
            '2026_10_26_010100_create_sales_invoice_e_invoices_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }

    private function office(): User
    {
        $user = User::create(['name' => 'Accounts officer', 'email' => 'accounts@example.test',
            'password' => 'password', 'role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    private function invoice(array $overrides = [], array $items = []): SalesInvoice
    {
        $invoice = SalesInvoice::create(array_merge([
            'invoice_number' => 'INV-TEST-0001', 'invoice_type' => 'tax', 'status' => 'issued',
            'public_token' => 'tok-' . bin2hex(random_bytes(4)),
            'invoice_date' => '2026-10-20', 'due_date' => '2026-11-20', 'currency' => 'INR',
            'gst_type' => 'intra_state', 'place_of_supply' => '24',
            'seller_company_name' => 'MissPack India Pvt Ltd', 'seller_gstin' => '24AATCM8816E1Z5',
            'seller_address' => 'Ring Road, Ahmedabad', 'seller_city' => 'Ahmedabad', 'seller_pincode' => '380015',
            'client_id' => null, 'client_company_name' => 'Acme Consumer Ltd',
            'client_gstin' => '24ABCDE1234F1Z6', 'billing_address' => '1 MG Road',
            'billing_city' => 'Ahmedabad', 'billing_pincode' => '380001',
            'subtotal' => 1000, 'taxable_amount' => 1000,
            'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0,
            'total_amount' => 1180, 'balance_amount' => 1180,
        ], $overrides));

        foreach ($items ?: [[
            'product_name' => 'Printed carton 12x8x6', 'hsn_sac' => '4819',
            'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 10,
            'gross_amount' => 1000, 'taxable_amount' => 1000, 'gst_percent' => 18,
            'cgst_amount' => 90, 'sgst_amount' => 90, 'igst_amount' => 0, 'line_total' => 1180,
        ]] as $item) {
            SalesInvoiceItem::create($item + ['sales_invoice_id' => $invoice->id]);
        }

        return $invoice->fresh();
    }

    public function test_a_proforma_is_refused_before_any_record_exists(): void
    {
        $this->office();
        $invoice = $this->invoice(['invoice_type' => 'proforma']);

        $this->post(route('sales-invoices.einvoice.generate', $invoice))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNull($invoice->fresh()->eInvoice);
    }

    public function test_a_line_without_hsn_is_named_in_the_refusal(): void
    {
        $this->office();
        $invoice = $this->invoice([], [[
            'product_name' => 'Mystery item', 'hsn_sac' => null,
            'quantity' => 1, 'unit_price' => 1000, 'gross_amount' => 1000,
            'taxable_amount' => 1000, 'gst_percent' => 18,
            'cgst_amount' => 90, 'sgst_amount' => 90, 'line_total' => 1180,
        ]]);

        $this->post(route('sales-invoices.einvoice.generate', $invoice))
            ->assertRedirect();
        $this->assertStringContainsString('HSN', (string) session('error'));
        $this->assertNull($invoice->fresh()->eInvoice);
    }

    public function test_generate_builds_the_portal_schema_and_parks_it_pending(): void
    {
        $this->office();
        $invoice = $this->invoice();

        $this->post(route('sales-invoices.einvoice.generate', $invoice))->assertRedirect();

        $record = $invoice->fresh()->eInvoice;
        $this->assertNotNull($record);
        $this->assertSame(SalesInvoiceEInvoice::STATUS_PENDING, $record->status);

        $payload = $record->request_payload;
        $this->assertSame('GST', $payload['TranDtls']['TaxSch']);
        $this->assertSame('B2B', $payload['TranDtls']['SupTyp']);
        $this->assertSame('INV', $payload['DocDtls']['Typ']);
        $this->assertSame('20/10/2026', $payload['DocDtls']['Dt']);
        $this->assertSame('24AATCM8816E1Z5', $payload['SellerDtls']['Gstin']);
        $this->assertSame('24ABCDE1234F1Z6', $payload['BuyerDtls']['Gstin']);
        $this->assertSame('24', $payload['BuyerDtls']['Pos']);
        $this->assertSame('4819', $payload['ItemList'][0]['HsnCd']);
        $this->assertEqualsWithDelta(18.0, (float) $payload['ItemList'][0]['GstRt'], 0.0001);
        $this->assertEqualsWithDelta(1000.0, (float) $payload['ValDtls']['AssVal'], 0.0001);
        $this->assertEqualsWithDelta(1180.0, (float) $payload['ValDtls']['TotInvVal'], 0.0001);
    }

    public function test_the_acknowledgement_accepts_only_a_real_irn_and_marks_registration(): void
    {
        $office = $this->office();
        $invoice = $this->invoice();
        $this->post(route('sales-invoices.einvoice.generate', $invoice));
        $record = $invoice->fresh()->eInvoice;

        $this->post(route('sales-invoices.einvoice.record', $invoice), ['irn' => 'NOT-AN-IRN'])
            ->assertSessionHasErrors(['irn']);

        /* The domain check itself: full-length but not hexadecimal. */
        $this->post(route('sales-invoices.einvoice.record', $invoice),
            ['irn' => str_repeat('Z', 64)])
            ->assertRedirect();
        $this->assertStringContainsString('64 hexadecimal', (string) session('error'));
        $this->assertSame(SalesInvoiceEInvoice::STATUS_PENDING, $record->fresh()->status);

        $irn = strtoupper(hash('sha256', 'INV-TEST-0001'));
        $this->post(route('sales-invoices.einvoice.record', $invoice), [
            'irn' => $irn, 'ack_no' => '162510000123456', 'ack_date' => now()->format('Y-m-d\TH:i'),
            'signed_qr_code' => 'eyJhbGciOiJSUzI1NiJ9.payload.signature',
        ])->assertRedirect();

        $record = $record->fresh();
        $this->assertTrue($record->isRegistered());
        $this->assertSame($irn, $record->irn);
        $this->assertSame('162510000123456', $record->ack_no);
        $this->assertSame($office->id, $record->registered_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sales_invoice.einvoice.registered']);
    }

    public function test_the_cancel_window_is_the_portals_twenty_four_hours_not_ours(): void
    {
        $this->office();
        $invoice = $this->invoice();
        $this->post(route('sales-invoices.einvoice.generate', $invoice));
        $record = $invoice->fresh()->eInvoice;
        $record->update([
            'status' => SalesInvoiceEInvoice::STATUS_REGISTERED,
            'irn' => strtoupper(hash('sha256', 'late-irn')),
            'ack_date' => now()->subHours(30), 'registered_by' => 1,
        ]);

        $this->post(route('sales-invoices.einvoice.cancel', $invoice), ['cancel_reason' => '2'])
            ->assertRedirect();
        $this->assertStringContainsString('24 hours', (string) session('error'));
        $this->assertNull($record->fresh()->cancelled_at);

        // Inside the window the manual gateway accepts the portal-side cancel.
        $record->update(['ack_date' => now()->subHours(2)]);
        $this->post(route('sales-invoices.einvoice.cancel', $invoice), ['cancel_reason' => '2', 'cancel_remark' => 'Typo'])
            ->assertRedirect();
        $this->assertSame(SalesInvoiceEInvoice::STATUS_CANCELLED, $record->fresh()->status);
    }

    public function test_the_http_gateway_registers_from_the_endpoints_answer(): void
    {
        config([
            'services.einvoice.provider' => 'http',
            'services.einvoice.endpoint' => 'https://gsp.example.test/einvoice',
            'services.einvoice.token' => 'token-123',
        ]);
        Http::fake([
            'gsp.example.test/*' => Http::response([
                'Irn' => strtoupper(hash('sha256', 'gsp-irn')),
                'AckNo' => '1625', 'AckDt' => '26/10/2026 11:20:00',
                'SignedInvoice' => 'c2lnbmVkLWludm9pY2U=',
                'SignedQRCode' => 'eyJhbGciOiJSUzI1NiJ9.qr.signature',
            ]),
        ]);

        $this->office();
        $invoice = $this->invoice();
        $this->post(route('sales-invoices.einvoice.generate', $invoice))->assertRedirect();

        $record = $invoice->fresh()->eInvoice;
        $this->assertTrue($record->isRegistered());
        $this->assertSame('http', $record->provider);
        $this->assertSame(strtoupper(hash('sha256', 'gsp-irn')), $record->irn);
        $this->assertSame('eyJhbGciOiJSUzI1NiJ9.qr.signature', $record->signed_qr_code);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer token-123')
                && $request['Version'] === '1.1'
                && $request['TranDtls']['SupTyp'] === 'B2B';
        });
    }
}
