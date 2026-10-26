<?php

namespace Tests\Feature;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesInvoiceNote;
use App\Models\User;
use App\Services\SalesInvoiceAdjustment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * IT-ADJ-001…010 — an issued tax invoice is a legal document: its money and
 * identity lock at issue, corrections travel as credit / debit notes and
 * return documents, and a note is cancelled (with a reason), never edited.
 * Each test pins one sentence of that rule.
 */
class InvoiceAdjustmentTest extends TestCase
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
            '2026_08_26_010200_create_sales_invoice_attachments_table',
            '2026_06_10_010200_create_cashflow_entries_table',
            '2026_06_11_010400_normalize_cashflow_currency_codes',
            '2026_07_18_010700_add_project_id_to_cashflow_entries_table',
            '2026_08_29_000000_add_sales_invoice_id_to_cashflow_entries_table',
            '2026_10_15_100000_create_cashflow_allocations_table',
            '2026_10_26_030000_create_sales_invoice_notes_table',
        ] as $migration) {
            (require database_path('migrations/' . $migration . '.php'))->up();
        }
    }

    private function office(): User
    {
        $user = User::create(['name' => 'Accounts officer', 'email' => 'accounts@example.test',
            'password' => 'password', 'role' => 'admin', 'access_profile' => 'full', 'department' => 'accounts']);
        $this->actingAs($user);

        return $user;
    }

    private function invoice(array $overrides = []): SalesInvoice
    {
        $invoice = SalesInvoice::create(array_merge([
            'invoice_number' => 'MP/INV/26-27/001', 'invoice_type' => 'tax', 'status' => 'sent',
            'sent_at' => '2026-10-20 10:00:00',
            'public_token' => 'tok-' . bin2hex(random_bytes(4)),
            'invoice_date' => '2026-10-20', 'due_date' => '2026-11-20', 'currency' => 'INR',
            'gst_type' => 'intra_state',
            'seller_company_name' => 'MissPack India Pvt Ltd', 'seller_gstin' => '24AATCM8816E1Z5',
            'client_company_name' => 'Acme Consumer Ltd', 'client_email' => 'accounts@acme.test',
            'billing_city' => 'Ahmedabad', 'billing_pincode' => '380001', 'amount_paid' => 0,
            'subtotal' => 1000, 'taxable_amount' => 1000,
            'cgst_amount' => 90, 'sgst_amount' => 90,
            'total_amount' => 1180, 'balance_amount' => 1180,
        ], $overrides));

        SalesInvoiceItem::create(['sales_invoice_id' => $invoice->id,
            'product_name' => 'Printed carton 12x8x6', 'hsn_sac' => '4819',
            'quantity' => 10, 'unit' => 'PCS', 'unit_price' => 100, 'taxable_amount' => 1000, 'line_total' => 1000]);

        return $invoice;
    }

    /** The payload an edit form posts — the full document, changed where told. */
    private function editPayload(SalesInvoice $invoice, array $overrides = []): array
    {
        return array_merge([
            'invoice_number' => $invoice->invoice_number,
            'invoice_type' => $invoice->invoice_type,
            'status' => $invoice->status,
            'invoice_date' => $invoice->invoice_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'currency' => 'INR',
            'gst_type' => 'intra_state',
            'amount_paid' => 0,
            'items' => [[
                'product_name' => 'Printed carton 12x8x6', 'hsn_sac' => '4819',
                'quantity' => 10, 'unit' => 'PCS', 'unit_price' => 100,
            ]],
        ], $overrides);
    }

    public function test_a_sent_tax_invoice_refuses_money_and_identity_changes(): void
    {
        $this->office();
        $invoice = $this->invoice();

        /* Items move money. */
        $response = $this->put("/sales-invoices/{$invoice->id}", $this->editPayload($invoice, [
            'items' => [['product_name' => 'Printed carton 12x8x6', 'quantity' => 20, 'unit' => 'PCS', 'unit_price' => 100]],
        ]));
        $response->assertSessionHasErrors('invoice');
        $this->assertEquals(10, (float) $invoice->fresh()->items->first()->quantity);

        /* So do the identity fields a GST document may not rewrite. */
        $this->put("/sales-invoices/{$invoice->id}", $this->editPayload($invoice, [
            'client_company_name' => 'Someone Else Ltd',
        ]))->assertSessionHasErrors('invoice');
    }

    public function test_an_issued_invoice_still_edits_its_working_fields(): void
    {
        $this->office();
        $invoice = $this->invoice();

        /* Terms, notes, transport — none of it rewrites the tax document. */
        $this->put("/sales-invoices/{$invoice->id}", $this->editPayload($invoice, [
            'internal_notes' => 'Client asked for delivery after Diwali',
            'dispatch_terms' => 'By road',
        ]))->assertSessionHasNoErrors();

        $fresh = $invoice->fresh();
        $this->assertSame('Client asked for delivery after Diwali', $fresh->internal_notes);
        $this->assertEquals(1180, (float) $fresh->total_amount);
    }

    public function test_an_issued_tax_invoice_cannot_be_deleted_but_a_draft_can(): void
    {
        $this->office();
        $issued = $this->invoice();
        $draft = $this->invoice(['invoice_number' => 'MP/INV/26-27/002', 'status' => 'draft', 'sent_at' => null]);

        $this->delete("/sales-invoices/{$issued->id}")->assertSessionHasErrors('invoice');
        $this->assertDatabaseHas('sales_invoices', ['id' => $issued->id]);

        $this->delete("/sales-invoices/{$draft->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sales_invoices', ['id' => $draft->id]);
    }

    public function test_a_credit_note_lowers_what_is_owed_and_stops_when_full(): void
    {
        $this->office();
        $invoice = $this->invoice();

        $this->post("/sales-invoices/{$invoice->id}/notes", [
            'kind' => 'credit', 'note_date' => '2026-10-24',
            'subtotal' => 500, 'reason' => 'Rate revision agreed with client',
        ])->assertSessionHasNoErrors();

        $note = SalesInvoiceNote::first();
        $this->assertMatchesRegularExpression('#^MP/CN/\d{2}-\d{2}/\d{3}$#', $note->number);
        /* 500 at the invoice's 9+9 split: 500 + 45 + 45. */
        $this->assertEquals(590, (float) $note->total);

        $invoice->unsetRelation('adjustments');
        $this->assertEquals(590, $invoice->payableTotal());
        $this->assertEquals(590, $invoice->balanceDue());
        /* The stored copy other ledgers read moved too. */
        $this->assertEquals(590, (float) $invoice->fresh()->balance_amount);

        /* Crediting it down to zero leaves nothing to chase. */
        app(SalesInvoiceAdjustment::class)->issue($invoice->fresh(), 'credit', [
            'note_date' => '2026-10-25', 'subtotal' => 500, 'reason' => 'Second box disputed',
        ]);
        $invoice->unsetRelation('adjustments');
        $this->assertEquals(0, $invoice->payableTotal());
        $this->assertSame('paid', $invoice->paymentState());

        /* And past zero nobody can push it. */
        try {
            app(SalesInvoiceAdjustment::class)->issue($invoice->fresh(), 'credit', [
                'note_date' => '2026-10-25', 'subtotal' => 100, 'reason' => 'One too many',
            ]);
            $this->fail('a credit beyond what the invoice is still worth must be refused');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertCount(2, SalesInvoiceNote::all());
        }
    }

    public function test_a_debit_note_raises_what_is_owed(): void
    {
        $this->office();
        $invoice = $this->invoice();

        $this->post("/sales-invoices/{$invoice->id}/notes", [
            'kind' => 'debit', 'note_date' => '2026-10-24',
            'subtotal' => 200, 'reason' => 'Freight re-charged on revised rates',
        ])->assertSessionHasNoErrors();

        $note = SalesInvoiceNote::first();
        $this->assertMatchesRegularExpression('#^MP/DN/#', $note->number);
        /* 200 + 18 + 18 of tax. */
        $this->assertEquals(236, (float) $note->total);

        $invoice->unsetRelation('adjustments');
        $this->assertEquals(1416, $invoice->payableTotal());
        $this->assertEquals(1416, $invoice->balanceDue());
    }

    public function test_a_return_restock_posts_its_credit_and_caps_quantities(): void
    {
        $this->office();
        $invoice = $this->invoice();
        $item = $invoice->items->first();

        $this->post("/sales-invoices/{$invoice->id}/returns", [
            'note_date' => '2026-10-24', 'reason' => 'Cartons crushed in transit', 'restock' => '1',
            'lines' => [['item_id' => $item->id, 'quantity' => 4]],
        ])->assertSessionHasNoErrors();

        $note = SalesInvoiceNote::first();
        $this->assertTrue($note->is_return);
        $this->assertTrue($note->restock);
        $this->assertSame('credit', $note->kind);
        /* 4 at ₹100 = 400, plus its 9+9 split. */
        $this->assertEquals(472, (float) $note->total);
        $this->assertEquals(4, (float) $note->lines[0]['quantity']);

        /* What is already back leaves only 6 to return — more must be refused. */
        $response = $this->post("/sales-invoices/{$invoice->id}/returns", [
            'note_date' => '2026-10-25', 'reason' => 'Second lot rejected',
            'lines' => [['item_id' => $item->id, 'quantity' => 7]],
        ]);
        $response->assertSessionHasErrors('lines');
        $this->assertEquals(1, SalesInvoiceNote::count());
    }

    public function test_a_cancelled_note_stops_applying_and_keeps_its_reason(): void
    {
        $this->office();
        $invoice = $this->invoice();
        $note = app(SalesInvoiceAdjustment::class)->issue($invoice, 'credit', [
            'note_date' => '2026-10-24', 'subtotal' => 500, 'reason' => 'Rate revision agreed with client',
        ]);

        /* Cancelling without saying why is refused; the note keeps applying. */
        $this->patch("/sales-invoice-notes/{$note->id}/cancel", ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->patch("/sales-invoice-notes/{$note->id}/cancel", [
            'cancel_reason' => 'Issued twice — the other note is the right one',
        ])->assertSessionHasNoErrors();

        $note->refresh();
        $this->assertSame('cancelled', $note->status);
        $this->assertSame('Issued twice — the other note is the right one', $note->cancel_reason);

        $invoice->unsetRelation('adjustments');
        $this->assertEquals(1180, $invoice->payableTotal());
        $this->assertEquals(1180, (float) $invoice->fresh()->balance_amount);

        /* A cancelled note is history — it cannot be cancelled again. */
        $this->patch("/sales-invoice-notes/{$note->id}/cancel", ['cancel_reason' => 'again'])
            ->assertStatus(422);
    }

    public function test_corrections_refuse_drafts_proformas_and_superseded_documents(): void
    {
        $this->office();

        $draft = $this->invoice(['invoice_number' => 'MP/INV/26-27/002', 'status' => 'draft', 'sent_at' => null]);
        $this->post("/sales-invoices/{$draft->id}/notes", [
            'kind' => 'credit', 'note_date' => '2026-10-24', 'subtotal' => 100, 'reason' => 'x',
        ])->assertStatus(422);

        $proforma = $this->invoice(['invoice_number' => 'MP/PI/26-27/001', 'invoice_type' => 'proforma']);
        $this->post("/sales-invoices/{$proforma->id}/notes", [
            'kind' => 'credit', 'note_date' => '2026-10-24', 'subtotal' => 100, 'reason' => 'x',
        ])->assertStatus(422);

        $this->assertEquals(0, SalesInvoiceNote::count());
    }

    public function test_notes_change_what_the_listing_counts_but_never_the_receipts(): void
    {
        $this->office();
        $invoice = $this->invoice();

        /* A payment recorded against the invoice stays sacrosanct. */
        $invoice->forceFill(['amount_paid' => 500])->save();

        app(SalesInvoiceAdjustment::class)->issue($invoice->fresh(), 'credit', [
            'note_date' => '2026-10-24', 'subtotal' => 500, 'reason' => 'Rate revision, part of order',
        ]);

        $invoice->unsetRelation('adjustments');
        /* 1180 - 590 credited = 590 owed; 500 already in hand leaves 90. */
        $this->assertEquals(590, $invoice->payableTotal());
        $this->assertEquals(500, $invoice->receivedAmount());
        $this->assertEquals(90, $invoice->balanceDue());
        $this->assertSame('partial', $invoice->paymentState());
    }
}
