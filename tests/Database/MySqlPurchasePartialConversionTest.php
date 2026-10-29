<?php

namespace Tests\Database;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\PurchasePartialConversion;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Native DECIMAL, rollback guard and overlapping conversion checks. */
class MySqlPurchasePartialConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'cache.default' => 'array',
            'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Purchase tests require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if (! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) {
            throw new \LogicException('Purchase tests require strict MySQL SQL mode.');
        }
    }

    private function createOrder(User $user, Vendor $vendor, string $number): PurchaseInvoice
    {
        $this->actingAs($user)->post(route('purchase-invoices.store'), [
            'invoice_number' => $number, 'invoice_type' => 'order', 'status' => 'approved',
            'vendor_id' => $vendor->id, 'currency' => 'USD', 'gst_type' => 'export', 'terms_conditions' => null,
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '10000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        return PurchaseInvoice::where('invoice_number', $number)->firstOrFail();
    }

    public function test_native_money_slices_and_lossy_rollback_guard(): void
    {
        $db = DB::connection();
        $db->setTransactionManager(new DatabaseTransactionsManager([$db->getName()]));
        $db->beginTransaction();
        try {
            $user = User::create(['name' => 'Accounts', 'email' => 'purchase-mysql@example.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
            $vendor = Vendor::create(['vendor_number' => 'MYSQL-SPLIT', 'vendor_name' => 'MySQL supplier']);
            $order = $this->createOrder($user, $vendor, 'MYSQL-SPLIT-PO');
            $payment = VendorPaymentEntry::create(['vendor_id' => $vendor->id, 'purchase_invoice_id' => $order->id,
                'transaction_date' => '2026-10-28', 'foreign_currency' => 'USD', 'foreign_amount' => '1000.001',
                'transaction_type' => 'debit', 'entry_category' => 'payment', 'particular' => 'Advance', 'status' => 'paid']);
            $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '2500.001',
                'payments' => [$payment->id => '500.001']])->assertRedirect()->assertSessionHasNoErrors();
            $bill = $order->partialConversions()->firstOrFail();
            $this->assertSame('7499.999', DB::table('purchase_invoices')->where('id', $order->id)->value('total_amount'));
            $this->assertSame('2500.001', DB::table('purchase_invoices')->where('id', $bill->id)->value('total_amount'));
            $this->assertEqualsWithDelta(500, $order->fresh()->paidAmount(), 0.000001);
            $this->assertEqualsWithDelta(500.001, $bill->fresh()->paidAmount(), 0.000001);
            $this->assertSame('1000.0010', DB::table('vendor_payment_entries')->where('id', $payment->id)->value('foreign_amount'));
            $sqlPaid = PurchaseInvoice::whereKey($bill->id)->selectRaw(PurchaseInvoice::paidSql().' as paid')->firstOrFail();
            $this->assertEqualsWithDelta(500.001, (float) $sqlPaid->paid, 0.000001);
            $migration = require database_path('migrations/2026_10_28_110000_add_partial_purchase_conversions.php');
            try {
                $migration->down();
                $this->fail('Rollback must refuse to discard active conversion history.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('allocations', $error->getMessage());
            }
            $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
            $this->assertEqualsWithDelta(10000, (float) $order->fresh()->total_amount, 0.000001);
            $this->assertEqualsWithDelta(1000.001, $order->fresh()->paidAmount(), 0.000001);
        } finally {
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
    }

    public function test_native_rmb_remainder_saves_a_visible_thousandth_round_off(): void
    {
        $db = DB::connection();
        $db->setTransactionManager(new DatabaseTransactionsManager([$db->getName()]));
        $db->beginTransaction();
        try {
            $user = User::create(['name' => 'Accounts', 'email' => 'purchase-round-mysql@example.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
            $vendor = Vendor::create(['vendor_number' => 'MYSQL-RMB-ROUND', 'vendor_name' => 'RMB supplier']);
            $this->actingAs($user)->post(route('purchase-invoices.store'), [
                'invoice_number' => 'MYSQL-RMB-ROUND-PO', 'invoice_type' => 'order', 'status' => 'approved',
                'vendor_id' => $vendor->id, 'currency' => 'RMB', 'gst_type' => 'export', 'terms_conditions' => null,
                'items' => [['product_name' => 'Cartons', 'quantity' => 10000, 'unit_price' => '2.1', 'gst_percent' => 0]],
            ])->assertRedirect()->assertSessionHasNoErrors();
            $order = PurchaseInvoice::where('invoice_number', 'MYSQL-RMB-ROUND-PO')->firstOrFail();
            $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '9000'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'bill', 'mode' => 'amount', 'value' => '12000'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
            $line = DB::table('purchase_invoice_items')->where('purchase_invoice_id', $bill->id)->first();
            $saved = DB::table('purchase_invoices')->where('id', $bill->id)->first();
            $this->assertSame('5714.286', $line->quantity);
            $this->assertSame('2.100', $line->unit_price);
            $this->assertSame('12000.001', $line->gross_amount);
            $this->assertSame('12000.001', $saved->subtotal);
            $this->assertSame('-0.001', $saved->round_off);
            $this->assertSame('12000.000', $saved->total_amount);
            $this->assertSame('0.000', DB::table('purchase_invoices')->where('id', $order->id)->value('total_amount'));
        } finally {
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
    }

    public function test_cross_order_bill_number_collision_retries_without_losing_either_conversion(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::create(['name' => 'Race account', 'email' => 'numbers-'.$suffix.'@example.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $vendor = Vendor::create(['vendor_number' => 'NUM-'.$suffix, 'vendor_name' => 'Race supplier']);
        $orders = [];
        $process = null;
        $pipes = [];
        try {
            $orders[] = $this->createOrder($user, $vendor, 'NUM-PO-A-'.$suffix);
            $orders[] = $this->createOrder($user, $vendor, 'NUM-PO-B-'.$suffix);
            $number = 'NUM-BILL-'.$suffix;
            DB::beginTransaction();
            app(PurchasePartialConversion::class)->convert($orders[0], ['kind' => 'cash', 'mode' => 'amount', 'value' => '6000'], fn () => $number);
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY to run the native concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/PurchaseConversionConcurrencyWorker.php'), (string) $orders[1]->id, (string) $user->id, $number],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(250000);
            $this->assertTrue(proc_get_status($process)['running'], 'The duplicate number must wait for the first transaction.');
            DB::commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('CREATED', $output);
            $this->assertSame($number, $orders[0]->partialConversions()->firstOrFail()->invoice_number);
            $this->assertSame($number.'-NEXT', $orders[1]->partialConversions()->firstOrFail()->invoice_number);
            foreach ($orders as $order) $this->assertEqualsWithDelta(4000, (float) $order->fresh()->total_amount, 0.000001);
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            foreach ($orders as $order) if ($order->fresh()) {
                foreach ($order->partialConversions()->reorder('id', 'desc')->get() as $bill) app(PurchasePartialConversion::class)->reverse($bill);
                app(\App\Services\PurchaseBillLedger::class)->remove($order);
                $order->items()->delete();
                $order->delete();
            }
            $vendor->delete();
            $user->delete();
        }
    }

    public function test_two_overlapping_conversions_serialize_and_cannot_consume_the_same_remainder(): void
    {
        $user = User::create(['name' => 'Race account', 'email' => 'race-'.bin2hex(random_bytes(4)).'@example.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
        $vendor = Vendor::create(['vendor_number' => 'RACE-'.bin2hex(random_bytes(4)), 'vendor_name' => 'Race supplier']);
        $order = null;
        $process = null;
        $pipes = [];
        try {
            $order = $this->createOrder($user, $vendor, 'RACE-PO-'.bin2hex(random_bytes(4)));
            DB::beginTransaction();
            PurchaseInvoice::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY to run the native concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/PurchaseConversionConcurrencyWorker.php'), (string) $order->id, (string) $user->id],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(250000);
            $this->assertTrue(proc_get_status($process)['running'], 'The second conversion should wait on the locked PO.');
            app(PurchasePartialConversion::class)->convert($order, ['kind' => 'cash', 'mode' => 'amount', 'value' => '6000'],
                fn () => 'RACE-BILL-'.bin2hex(random_bytes(6)));
            DB::commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('REJECTED', $output);
            $this->assertSame(1, $order->partialConversions()->count());
            $this->assertEqualsWithDelta(4000, (float) $order->fresh()->total_amount, 0.000001);
            $this->assertEqualsWithDelta(6000, (float) $order->fresh()->converted_amount, 0.000001);
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            if ($order && $order->fresh()) {
                foreach ($order->partialConversions()->reorder('id', 'desc')->get() as $bill) {
                    app(PurchasePartialConversion::class)->reverse($bill);
                }
                app(\App\Services\PurchaseBillLedger::class)->remove($order);
                $order->items()->delete();
                $order->delete();
            }
            $vendor->delete();
            $user->delete();
        }
    }
}
