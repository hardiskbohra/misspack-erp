<?php

namespace Tests\Database;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Native MySQL exercises the HTTP writer, DECIMAL storage and conversion on the full migrated schema. */
class MySqlInvoiceAutoRoundingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'cache.default' => 'array',
            'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Invoice checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if (! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) {
            throw new \LogicException('Invoice checks require strict MySQL SQL mode.');
        }
        $manager = new DatabaseTransactionsManager([$db->getName()]);
        $this->app->instance('db.transactions', $manager);
        $db->setTransactionManager($manager);
        $db->beginTransaction();
        Storage::fake('public');
        $this->actingAs(User::create(['name' => 'Accounts', 'email' => 'rounding-mysql@example.test', 'password' => 'password', 'role' => 'admin']));
    }

    protected function tearDown(): void
    {
        if (app()->bound('db')) {
            $db = DB::connection();
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
        parent::tearDown();
    }

    private function data(string $type, string $price, array $extra = []): array
    {
        return array_replace([
            'invoice_number' => null, 'invoice_type' => $type, 'status' => $type === 'proforma' ? 'sent' : 'draft',
            'currency' => 'INR', 'gst_type' => $type === 'cash' ? 'none' : 'export', 'discount_type' => 'amount',
            'discount_value' => 0, 'client_company_name' => 'Buyer',
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => $price, 'gst_percent' => 0]],
        ], $extra);
    }

    private function createInvoice(array $data): SalesInvoice
    {
        $this->post(route('sales-invoices.store'), $data)->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        return SalesInvoice::query()->latest('id')->firstOrFail();
    }

    public function test_auto_manual_and_ineligible_amounts_are_stored_at_mysql_decimal_precision(): void
    {
        $down = $this->createInvoice($this->data('proforma', '100.49'));
        $up = $this->createInvoice($this->data('tax', '100.50', ['round_off' => '999.99', 'auto_round_total' => '1']));
        $manual = $this->createInvoice($this->data('tax', '100.49', ['round_off' => '0.22', 'auto_round_total' => '0']));
        $cash = $this->createInvoice($this->data('cash', '100.49', ['auto_round_total' => '1']));
        $usd = $this->createInvoice($this->data('tax', '100.49', ['currency' => 'USD', 'auto_round_total' => '1']));
        $this->assertSame(['100.00', '-0.49', true], [$down->total_amount, $down->round_off, $down->auto_round_total]);
        $this->assertSame(['101.00', '0.50', true], [$up->total_amount, $up->round_off, $up->auto_round_total]);
        $this->assertSame(['100.71', '0.22', false], [$manual->total_amount, $manual->round_off, $manual->auto_round_total]);
        $this->assertSame(['100.49', false], [$cash->total_amount, $cash->auto_round_total]);
        $this->assertSame(['100.49', false], [$usd->total_amount, $usd->auto_round_total]);
    }

    public function test_partial_conversion_keeps_the_fractional_tax_remainder_on_mysql(): void
    {
        $pi = $this->createInvoice($this->data('proforma', '100.49'));
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount', 'conversion_value' => '30.11', 'transfer_received' => 0])
            ->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $this->post(route('sales-invoices.convert', $pi->fresh()))->assertSessionHasNoErrors()->assertStatus(302)->assertSessionHas('success');
        $tax = SalesInvoice::query()->where('source_proforma_id', $pi->id)->where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame('69.89', $tax->total_amount);
        $this->assertSame('69.89', $tax->balance_amount);
        $this->assertFalse($tax->auto_round_total);
        $this->assertSame('100.00', $pi->fresh()->total_amount);
    }
}
