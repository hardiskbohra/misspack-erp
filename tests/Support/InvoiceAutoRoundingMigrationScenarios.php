<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InvoiceAutoRoundingMigrationScenarios
{
    protected function scenario_auto_rounding_migration_preserves_existing_invoice_money_and_guards_rollback(): void
    {
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_08_26_010000_create_sales_invoices_table.php'))->up();
        DB::table('sales_invoices')->insert([
            ['invoice_number' => 'LEGACY-PI', 'public_token' => 'legacy-pi', 'invoice_type' => 'proforma',
                'round_off' => '-0.17', 'total_amount' => '250.53', 'amount_paid' => '30.12', 'balance_amount' => '220.41', 'amount_in_words' => 'Original words'],
            ['invoice_number' => 'LEGACY-TAX', 'public_token' => 'legacy-tax', 'invoice_type' => 'tax',
                'round_off' => '0.25', 'total_amount' => '120.50', 'amount_paid' => '0.00', 'balance_amount' => '120.50', 'amount_in_words' => 'Other original words'],
        ]);
        $columns = ['id', 'invoice_number', 'invoice_type', 'round_off', 'total_amount', 'amount_paid', 'balance_amount', 'amount_in_words'];
        $snapshot = fn () => DB::table('sales_invoices')->orderBy('id')->get($columns)->map(fn ($row) => (array) $row)->all();
        $before = $snapshot();
        $migration = require database_path('migrations/2026_10_27_140000_add_invoice_auto_round_total.php');
        $migration->up();
        $this->assertSame($before, $snapshot());
        $this->assertEquals([0, 0], DB::table('sales_invoices')->orderBy('id')->pluck('auto_round_total')->all());

        DB::table('sales_invoices')->where('id', 1)->update(['auto_round_total' => true]);
        $failure = null;
        try {
            $migration->down();
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertNotNull($failure, 'A rollback must not silently discard an active auto-rounding policy.');
        $this->assertStringContainsString('auto_round_total', $failure->getMessage());
        $this->assertTrue(Schema::hasColumn('sales_invoices', 'auto_round_total'));
        $this->assertSame($before, $snapshot());

        DB::table('sales_invoices')->update(['auto_round_total' => false]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('sales_invoices', 'auto_round_total'));
        $this->assertSame($before, $snapshot());
        $migration->up();
        $this->assertSame($before, $snapshot());
        $this->assertEquals([0, 0], DB::table('sales_invoices')->orderBy('id')->pluck('auto_round_total')->all());
    }
}
