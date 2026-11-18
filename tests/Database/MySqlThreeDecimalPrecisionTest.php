<?php

namespace Tests\Database;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Real MySQL DECIMAL persistence and the HTTP writers, on a disposable schema. */
class MySqlThreeDecimalPrecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'cache.default' => 'array',
            'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Precision tests require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if (! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) {
            throw new \LogicException('Precision tests require strict MySQL SQL mode.');
        }
        $manager = new DatabaseTransactionsManager([$db->getName()]);
        $this->app->instance('db.transactions', $manager);
        $db->setTransactionManager($manager);
        $db->beginTransaction();
        $this->actingAs(User::create(['name' => 'Accounts', 'email' => 'precision-mysql@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
    }

    protected function tearDown(): void
    {
        if (app()->bound('db')) {
            $db = DB::connection();
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
        parent::tearDown();
    }

    public function test_discounted_tax_payload_reconciles_saved_mysql_totals(): void
    {
        $this->post(route('sales-invoices.store'), ['invoice_number' => null, 'invoice_type' => 'tax', 'status' => 'sent',
            'invoice_date' => '2026-10-28', 'currency' => 'INR', 'gst_type' => 'inter_state', 'place_of_supply' => '24',
            'seller_gstin' => '24AATCM8816E1Z5', 'client_gstin' => '24ABCDE1234F1Z6', 'auto_round_total' => '0',
            'discount_type' => 'amount', 'discount_value' => '3.00', 'round_off' => '0',
            'items' => [
                ['product_name' => 'A', 'hsn_sac' => '4819', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '10', 'gst_percent' => 18],
                ['product_name' => 'B', 'hsn_sac' => '4819', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '20', 'gst_percent' => 5],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $invoice = SalesInvoice::latest('id')->firstOrFail();
        $payload = (new \App\Services\EInvoice\EInvoicePayload($invoice))->build();
        $this->assertSame('29.520', DB::table('sales_invoices')->where('id', $invoice->id)->value('total_amount'));
        $this->assertEqualsWithDelta(27, array_sum(array_column($payload['ItemList'], 'AssAmt')), 0.000001);
        $this->assertEqualsWithDelta(2.52, array_sum(array_column($payload['ItemList'], 'IgstAmt')), 0.000001);
        $this->assertEqualsWithDelta(0, $payload['ValDtls']['Discount'], 0.000001);
        $this->assertEqualsWithDelta(29.52, $payload['ValDtls']['TotInvVal'], 0.000001);
    }

    public function test_fractional_cash_conversion_of_a_paise_claim_keeps_the_exact_remainder_on_mysql(): void
    {
        $this->post(route('sales-invoices.store'), ['invoice_number' => null, 'invoice_type' => 'proforma', 'status' => 'sent',
            'invoice_date' => '2026-10-28', 'currency' => 'INR', 'gst_type' => 'export', 'auto_round_total' => '0',
            'round_off' => '0', 'amount_paid' => '0.001',
            'items' => [['product_name' => 'A', 'quantity' => 1, 'unit_price' => '1.000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $pi = SalesInvoice::where('invoice_type', 'proforma')->latest('id')->firstOrFail();
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount',
            'conversion_value' => '0.001', 'transfer_received' => '0.001'])->assertRedirect()->assertSessionHasNoErrors();
        $cash = SalesInvoice::where('source_proforma_id', $pi->id)->where('invoice_type', 'cash')->firstOrFail();
        $this->assertSame('0.001', DB::table('sales_invoices')->where('id', $pi->id)->value('cash_converted_total_amount'));
        $this->assertSame('0.001', DB::table('sales_invoices')->where('id', $cash->id)->value('total_amount'));
        $this->assertEqualsWithDelta(0.999, $pi->fresh()->receivableTotal(), 0.000001);
        $this->post(route('sales-invoices.convert', $pi))->assertRedirect()->assertSessionHas('error');
        $this->assertNull($pi->fresh()->converted_invoice_id);
    }

    public function test_unsafe_partial_tax_conversion_is_atomic_on_native_mysql(): void
    {
        $this->post(route('sales-invoices.store'), ['invoice_number' => null, 'invoice_type' => 'proforma',
            'status' => 'sent', 'currency' => 'INR', 'gst_type' => 'export', 'auto_round_total' => '0', 'round_off' => 0,
            'items' => [['product_name' => 'Cartons', 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => '1000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $pi = SalesInvoice::latest('id')->firstOrFail();
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount',
            'conversion_value' => '33333.33', 'transfer_received' => '0'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(66666.67, $pi->fresh()->receivableTotal(), 0.000001);
        $this->post(route('sales-invoices.convert', $pi))->assertRedirect()->assertSessionHasErrors('invoice');
        $this->assertSame(0, SalesInvoice::where('invoice_type', 'tax')->count());
        $this->assertNull($pi->fresh()->converted_invoice_id);
        $this->assertEqualsWithDelta(66666.67, $pi->fresh()->receivableTotal(), 0.000001);
    }

    public function test_sql_columns_and_http_writers_preserve_three_place_values(): void
    {
        $metadata = DB::selectOne("SELECT numeric_precision AS p, numeric_scale AS s FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'sales_invoice_items' AND column_name = 'unit_price'");
        $this->assertSame(17, (int) $metadata->p);
        $this->assertSame(3, (int) $metadata->s);

        foreach (['proforma', 'tax'] as $type) {
            $this->post(route('sales-invoices.store'), ['invoice_number' => null, 'invoice_type' => $type,
                'status' => 'draft', 'currency' => 'INR', 'gst_type' => 'export', 'auto_round_total' => '0', 'round_off' => 0,
                'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '0.574', 'gst_percent' => 0]],
            ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
            $invoice = SalesInvoice::latest('id')->firstOrFail();
            $this->assertSame('0.574', DB::table('sales_invoice_items')->where('sales_invoice_id', $invoice->id)->value('unit_price'));
            $this->assertSame($type === 'tax' ? '0.570' : '0.574', DB::table('sales_invoices')->where('id', $invoice->id)->value('total_amount'));
        }
        foreach (['order', 'bill'] as $type) {
            $this->post(route('purchase-invoices.store'), ['invoice_number' => 'MYSQL-PRECISION-'.$type, 'invoice_type' => $type,
                'status' => 'draft', 'currency' => 'INR', 'gst_type' => 'export', 'terms_conditions' => null,
                'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '0.574', 'gst_percent' => 0]],
            ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
            $invoice = PurchaseInvoice::latest('id')->firstOrFail();
            $this->assertSame('0.574', DB::table('purchase_invoice_items')->where('purchase_invoice_id', $invoice->id)->value('unit_price'));
            $this->assertSame('0.574', DB::table('purchase_invoices')->where('id', $invoice->id)->value('total_amount'));
        }

        $account = CashflowAccount::create(['account_name' => 'MySQL precision account', 'currency' => 'INR', 'opening_balance' => '10']);
        $this->post(route('cashflows.store'), ['entry_date' => '2026-10-28', 'particular' => 'Precision payment',
            'transaction_type' => 'credit', 'amount' => '0.574', 'currency' => 'INR', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'related_party_type' => 'other', 'related_party_name' => 'Test party'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $entry = CashflowEntry::latest('id')->firstOrFail();
        $this->assertSame('0.574', DB::table('cashflow_entries')->where('id', $entry->id)->value('credit_amount'));
        $this->assertSame('10.574', DB::table('cashflow_accounts')->where('id', $account->id)->value('current_balance'));
    }
}
