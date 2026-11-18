<?php

namespace Tests\Database;

use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePaymentSplit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\CashflowVendorPaymentSync;
use App\Services\PartyStatement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Real DECIMAL and ONLY_FULL_GROUP_BY coverage; no live or shared database. */
class MySqlPurchaseConvertedVisibilityTest extends TestCase
{
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'cache.default' => 'array',
            'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Purchase visibility tests require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES'))
            || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Purchase visibility tests require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        $manager = new DatabaseTransactionsManager([$db->getName()]);
        $this->app->instance('db.transactions', $manager);
        $db->setTransactionManager($manager);
        $db->beginTransaction();
        Carbon::setTestNow('2026-11-01 12:00:00');
        $this->actingAs(User::create(['name' => 'MySQL Accounts', 'email' => 'visibility-mysql@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
        $this->vendor = Vendor::create(['vendor_number' => 'MYSQL-VISIBILITY', 'vendor_name' => 'MySQL supplier', 'preferred_currency' => 'INR']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        if (app()->bound('db')) {
            $db = DB::connection();
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
        parent::tearDown();
    }

    private function document(string $number, string $type, array $attributes = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_replace([
            'invoice_number' => $number, 'invoice_type' => $type, 'status' => $type === 'order' ? 'approved' : 'received',
            'public_token' => Str::random(48), 'vendor_id' => $this->vendor->id, 'vendor_company_name' => $this->vendor->vendor_name,
            'invoice_date' => '2026-10-05', 'currency' => 'INR', 'total_amount' => '0',
        ], $attributes));
    }

    private function entry(PurchaseInvoice $doc, string $type, string $category, string $amount, string $date = '2026-10-05'): VendorPaymentEntry
    {
        return VendorPaymentEntry::create(['vendor_id' => $this->vendor->id, 'purchase_invoice_id' => $doc->id,
            'transaction_date' => $date, 'invoice_number' => $doc->invoice_number, 'foreign_currency' => 'INR',
            'foreign_amount' => $amount, 'transaction_type' => $type, 'entry_category' => $category,
            'particular' => $category.' '.$doc->invoice_number, 'status' => $type === 'debit' ? 'paid' : 'booked',
            'amount_in_inr' => $amount]);
    }

    public function test_listing_filters_and_statement_use_native_decimal_values(): void
    {
        $legacyBill = $this->document('MYSQL-B-LEGACY', 'bill', ['total_amount' => '200.001']);
        $legacy = $this->document('MYSQL-PO-LEGACY', 'order', ['total_amount' => '200.001', 'converted_invoice_id' => $legacyBill->id]);
        $full = $this->document('MYSQL-PO-DONE', 'order', ['total_amount' => '0', 'converted_amount' => '0.333']);
        $fullBill = $this->document('MYSQL-B-DONE', 'bill', ['total_amount' => '0.333', 'purchase_order_id' => $full->id]);
        $partial = $this->document('MYSQL-PO-PARTIAL', 'order', ['total_amount' => '0.001', 'converted_amount' => '0.999']);
        $zero = $this->document('MYSQL-PO-ZERO', 'order');
        $staleLegacy = $this->entry($legacy, 'credit', 'order', '200.001', '2026-09-01');
        $staleFull = $this->entry($full, 'credit', 'order', '0.333', '2026-09-01');
        $this->entry($legacyBill, 'credit', 'bill', '200.001');
        $this->entry($fullBill, 'credit', 'bill', '0.333');
        $this->entry($partial, 'credit', 'order', '0.001');
        $payment = $this->entry($full, 'debit', 'payment', '0.100', '2026-09-20');

        $default = $this->get(route('purchase-invoices.index'))->assertOk();
        $names = $default->viewData('invoices')->pluck('invoice_number')->sort()->values()->all();
        $this->assertSame(['MYSQL-B-DONE', 'MYSQL-B-LEGACY', 'MYSQL-PO-PARTIAL', 'MYSQL-PO-ZERO'], $names);
        $this->assertSame(2, $default->viewData('chipCounts')['order']);
        $this->assertEqualsWithDelta(200.334, $default->viewData('pageTotals')['byCurrency']['INR']['billed'], 0.0000001);
        $this->assertEqualsWithDelta(0.001, $default->viewData('pageTotals')['byCurrency']['INR']['open'], 0.0000001);
        $include = $this->get(route('purchase-invoices.index', ['converted' => 'include']))->assertOk();
        $this->assertSame(6, $include->viewData('invoices')->total());
        $this->assertSame($default->viewData('pageTotals')['byCurrency']['INR'], $include->viewData('pageTotals')['byCurrency']['INR']);
        $only = $this->get(route('purchase-invoices.index', ['converted' => 'only']))->assertOk();
        $this->assertSame(['MYSQL-PO-DONE', 'MYSQL-PO-LEGACY'], $only->viewData('invoices')->pluck('invoice_number')->sort()->values()->all());

        // SQL classification and model classification agree at the thousandth.
        $this->assertSame([$legacy->id, $full->id], PurchaseInvoice::fullyConverted()->orderBy('id')->pluck('id')->all());
        foreach ([$legacy, $full] as $doc) $this->assertTrue($doc->fresh()->isFullyConverted());
        foreach ([$partial, $zero, $legacyBill, $fullBill] as $doc) $this->assertFalse($doc->fresh()->isFullyConverted());
        $statement = app(PartyStatement::class)->build('vendor', $this->vendor->id, '2026-10-01', '2026-10-31', ['currency' => 'INR']);
        $this->assertEqualsWithDelta(-0.1, $statement['opening'], 0.0000001);
        $this->assertEqualsWithDelta(200.335, $statement['totals']['credit'], 0.0000001);
        $this->assertEqualsWithDelta(200.235, $statement['totals']['closing'], 0.0000001);
        $this->assertSame(3, $statement['totals']['count']);
        $this->assertNotContains('MYSQL-PO-LEGACY', array_column($statement['ageing']['rows'], 'reference'));
        $this->assertContains('MYSQL-PO-PARTIAL', array_column($statement['ageing']['rows'], 'reference'));
        foreach ([$staleLegacy, $staleFull, $payment] as $row) $this->assertNotNull($row->fresh());
    }

    public function test_native_legacy_deletion_reposts_the_order_without_overwriting_manual_credit(): void
    {
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'MYSQL-LEGACY-RESTORE', 'invoice_type' => 'order',
            'status' => 'approved', 'vendor_id' => $this->vendor->id, 'currency' => 'INR', 'gst_type' => 'export',
            'terms_conditions' => null, 'items' => [['product_name' => 'Carton', 'quantity' => 1, 'unit_price' => '1000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::where('invoice_number', 'MYSQL-LEGACY-RESTORE')->firstOrFail();
        $this->post(route('purchase-invoices.convert', $order))->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->fresh()->convertedInvoice;
        $adjustment = $this->entry($order, 'credit', 'adjustment', '0.125');
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertNull($order->fresh()->converted_invoice_id);
        $this->assertSame('adjustment', $adjustment->fresh()->entry_category);
        $this->assertSame('0.1250', $adjustment->fresh()->foreign_amount);
        $statement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertEqualsWithDelta(1000.125, $statement['totals']['credit'], 0.0000001);
        $this->assertSame(2, $statement['totals']['count']);
        $this->assertTrue(PurchaseInvoice::notFullyConverted()->whereKey($order->id)->exists());
    }

    public function test_native_opening_advances_are_not_discarded_on_legacy_or_bulk_deletion(): void
    {
        $bill = $this->document('MYSQL-B-ADVANCE', 'bill', ['total_amount' => '1', 'amount_paid' => '0.001']);
        $order = $this->document('MYSQL-PO-ADVANCE', 'order', ['status' => 'billed', 'total_amount' => '1', 'converted_invoice_id' => $bill->id]);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($bill->fresh());
        $this->assertSame('0.001', DB::table('purchase_invoices')->where('id', $bill->id)->value('amount_paid'));
        $this->assertSame($bill->id, (int) $order->fresh()->converted_invoice_id);

        $draft = $this->document('MYSQL-B-DRAFT-ADVANCE', 'bill', ['status' => 'draft', 'total_amount' => '1', 'amount_paid' => '0.001']);
        $this->post(route('purchase-invoices.bulk'), ['action' => 'delete_drafts', 'ids' => [$draft->id]])->assertRedirect();
        $this->assertNotNull($draft->fresh());
        $this->assertSame('0.001', DB::table('purchase_invoices')->where('id', $draft->id)->value('amount_paid'));
    }

    public function test_native_vendor_statement_batches_owned_and_archived_project_links_without_changing_amounts(): void
    {
        $client = Client::create(['client_number' => 'MYSQL-STMT-CLIENT', 'company_name' => 'MySQL client', 'preferred_currency' => 'INR']);
        $first = Project::create(['project_number' => 'MYSQL-STMT-1', 'client_id' => $client->id, 'name' => 'Native archived project']);
        $second = Project::create(['project_number' => 'MYSQL-STMT-2', 'client_id' => $client->id, 'name' => 'Native second project']);
        $otherProject = Project::create(['project_number' => 'MYSQL-STMT-3', 'client_id' => $client->id, 'name' => 'Other vendor private project']);
        $bill = $this->document('MYSQL-PRJ-BILL', 'bill', ['project_id' => $first->id, 'total_amount' => '100.001']);
        $this->entry($bill, 'credit', 'bill', '100.001'); // legacy ledger row: no direct project_id
        $otherVendor = Vendor::create(['vendor_number' => 'MYSQL-STMT-OTHER', 'vendor_name' => 'MySQL other vendor', 'preferred_currency' => 'INR']);
        $wrongBill = $this->document('MYSQL-OTHER-BILL', 'bill', ['vendor_id' => $otherVendor->id, 'project_id' => $otherProject->id]);
        $this->entry($wrongBill, 'credit', 'bill', '0.100'); // stale cross-vendor link, still a real entry
        $payment = $this->entry($bill, 'debit', 'payment', '0.125');
        $payment->forceFill(['purchase_invoice_id' => null, 'invoice_number' => 'MYSQL-PAY-SLICE'])->save();
        PurchasePaymentSplit::create(['vendor_payment_entry_id' => $payment->id, 'purchase_invoice_id' => $bill->id, 'amount' => '0.100']);
        PurchasePaymentSplit::create(['vendor_payment_entry_id' => $payment->id, 'purchase_invoice_id' => $wrongBill->id, 'amount' => '0.025']);
        $account = CashflowAccount::create(['account_name' => 'MySQL Statement Bank', 'account_type' => 'current', 'currency' => 'INR']);
        $cashflow = CashflowEntry::create(['entry_date' => '2026-10-06', 'particular' => 'Split payment', 'invoice_bill_number' => 'MYSQL-SPLIT',
            'transaction_type' => 'debit', 'debit_amount' => '50.001', 'credit_amount' => 0, 'currency' => 'INR',
            'vendor_id' => $this->vendor->id, 'account_id' => $account->id, 'accounting_status' => 'booked']);
        foreach ([[$first->id, '20'], [$second->id, '30.001']] as [$projectId, $amount]) {
            CashflowAllocation::create(['cashflow_entry_id' => $cashflow->id, 'project_id' => $projectId, 'amount' => $amount, 'currency' => 'INR']);
        }
        app(CashflowVendorPaymentSync::class)->sync($cashflow);
        $first->delete();

        $statement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $rows = collect($statement['rows'])->keyBy('reference');
        $this->assertSame(['Native archived project'], $rows['MYSQL-PRJ-BILL']['project_names']);
        $this->assertSame(['Native archived project', 'Native second project'], $rows['MYSQL-SPLIT']['project_names']);
        $this->assertSame(['Native archived project'], $rows['MYSQL-PAY-SLICE']['project_names']);
        $this->assertSame([], $rows['MYSQL-OTHER-BILL']['project_names']);
        $this->assertEqualsWithDelta(50.126, $statement['totals']['debit'], 0.0000001);
        $this->assertEqualsWithDelta(100.101, $statement['totals']['credit'], 0.0000001);
        $this->assertEqualsWithDelta(49.975, $statement['totals']['closing'], 0.0000001);
        $this->assertSame(4, $statement['totals']['count']);
        $this->get(route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $this->vendor->id,
            'period' => 'all', 'currency' => 'INR']))->assertOk()->assertSee('Native archived project')
            ->assertSee('Native second project')->assertDontSee('Other vendor private project');
    }

    public function test_native_conversion_and_reversal_reopen_the_order_at_one_thousandth(): void
    {
        $this->post(route('purchase-invoices.store'), [
            'invoice_number' => 'MYSQL-PO-LIFECYCLE', 'invoice_type' => 'order', 'status' => 'approved',
            'vendor_id' => $this->vendor->id, 'currency' => 'INR', 'gst_type' => 'export', 'terms_conditions' => null,
            'items' => [['product_name' => 'Native carton', 'quantity' => 1, 'unit_price' => '1', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::where('invoice_number', 'MYSQL-PO-LIFECYCLE')->firstOrFail();
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '0.999'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.001', DB::table('purchase_invoices')->where('id', $order->id)->value('total_amount'));
        $this->assertTrue(PurchaseInvoice::notFullyConverted()->whereKey($order->id)->exists());
        $this->post(route('purchase-invoices.convert-partial', $order->fresh()), ['kind' => 'bill', 'mode' => 'amount', 'value' => '0.001'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame('0.000', DB::table('purchase_invoices')->where('id', $order->id)->value('total_amount'));
        $this->assertTrue(PurchaseInvoice::fullyConverted()->whereKey($order->id)->exists());
        $this->assertFalse($this->get(route('purchase-invoices.index'))->assertOk()->viewData('invoices')->contains('id', $order->id));
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('0.001', DB::table('purchase_invoices')->where('id', $order->id)->value('total_amount'));
        $this->assertTrue(PurchaseInvoice::notFullyConverted()->whereKey($order->id)->exists());
        $this->assertTrue($this->get(route('purchase-invoices.index'))->assertOk()->viewData('invoices')->contains('id', $order->id));
    }
}
