<?php

namespace Tests\Database;

use Illuminate\Support\Facades\DB;
use Tests\Support\InvoiceAutoRoundingMigrationScenarios;
use Tests\Support\WideningMigrationScenarios;
use Tests\TestCase;

/** DDL commits implicitly on MySQL, so use a newly created, uniquely named test schema for each case. */
class MySqlWideningMigrationSafetyTest extends TestCase
{
    use WideningMigrationScenarios;
    use InvoiceAutoRoundingMigrationScenarios;

    private ?string $originalConnection = null;
    private ?string $createdDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Migration checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if (! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) {
            throw new \LogicException('Migration checks require strict MySQL SQL mode.');
        }

        $this->originalConnection = DB::getDefaultConnection();
        $name = 'erp_widen_'.bin2hex(random_bytes(6)).'_test';
        $db->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->createdDatabase = $name;
        config(['database.connections.reliability_upgrade' => array_merge(config('database.connections.'.$this->originalConnection), ['database' => $name])]);
        DB::setDefaultConnection('reliability_upgrade');
    }

    protected function tearDown(): void
    {
        if ($this->originalConnection && $this->createdDatabase) {
            DB::disconnect('reliability_upgrade');
            DB::setDefaultConnection($this->originalConnection);
            DB::connection($this->originalConnection)->statement('DROP DATABASE `'.$this->createdDatabase.'`');
        }
        parent::tearDown();
    }

    public function test_gate_summary_migration_preserves_existing_values_and_refuses_lossy_rollback(): void
    {
        $this->scenario_gate_summary_migration_preserves_existing_values_and_refuses_lossy_rollback();
    }

    public function test_message_widening_preserves_old_values_and_checks_every_column_before_rollback(): void
    {
        $this->scenario_message_widening_preserves_old_values_and_checks_every_column_before_rollback();
    }

    public function test_existing_invoice_money_survives_migration_and_active_policy_cannot_be_dropped(): void
    {
        $this->scenario_auto_rounding_migration_preserves_existing_invoice_money_and_guards_rollback();
    }

    public function test_precision_migration_preserves_old_decimals_and_preflights_every_table_before_rollback(): void
    {
        DB::statement('CREATE TABLE sales_invoices (id BIGINT PRIMARY KEY, subtotal DECIMAL(16,2) NOT NULL DEFAULT 0, total_amount DECIMAL(16,2) NOT NULL DEFAULT 0)');
        DB::statement('CREATE TABLE sales_invoice_items (id BIGINT PRIMARY KEY, unit_price DECIMAL(16,2) NOT NULL DEFAULT 0)');
        DB::statement('CREATE TABLE cashflow_entries (id BIGINT PRIMARY KEY, credit_amount DECIMAL(16,2) NOT NULL DEFAULT 0, balance DECIMAL(16,2) NULL)');
        DB::statement('CREATE TABLE cashflow_allocations (id BIGINT PRIMARY KEY, amount DECIMAL(18,2) NOT NULL, document_amount DECIMAL(18,2) NULL)');
        DB::table('sales_invoices')->insert(['id' => 1, 'subtotal' => '123.45', 'total_amount' => '123.45']);
        DB::table('sales_invoice_items')->insert(['id' => 1, 'unit_price' => '0.57']);
        DB::table('cashflow_entries')->insert(['id' => 1, 'credit_amount' => '45.67', 'balance' => null]);
        DB::table('cashflow_allocations')->insert(['id' => 1, 'amount' => '45.67', 'document_amount' => null]);
        $migration = require database_path('migrations/2026_10_28_100000_expand_invoice_cashflow_precision.php');
        $column = fn ($table, $name) => DB::selectOne('SELECT numeric_precision AS p, numeric_scale AS s, is_nullable AS n, column_default AS d FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $name]);

        $migration->up();
        $this->assertSame('123.450', DB::table('sales_invoices')->value('total_amount'));
        $this->assertSame('0.570', DB::table('sales_invoice_items')->value('unit_price'));
        $this->assertNull(DB::table('cashflow_entries')->value('balance'));
        $this->assertSame([17, 3, 'NO'], [(int) $column('sales_invoice_items', 'unit_price')->p, (int) $column('sales_invoice_items', 'unit_price')->s, $column('sales_invoice_items', 'unit_price')->n]);
        $this->assertSame([19, 3, 'YES'], [(int) $column('cashflow_allocations', 'document_amount')->p, (int) $column('cashflow_allocations', 'document_amount')->s, $column('cashflow_allocations', 'document_amount')->n]);
        DB::table('cashflow_entries')->where('id', 1)->update(['credit_amount' => '45.674']);
        try {
            $migration->down();
            $this->fail('Rollback must refuse to discard a non-zero third digit.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('cashflow_entries', $error->getMessage());
        }
        $this->assertSame(3, (int) $column('sales_invoices', 'total_amount')->s, 'No earlier table may be narrowed before the preflight completes.');
        $this->assertSame('45.674', DB::table('cashflow_entries')->value('credit_amount'));
        DB::table('cashflow_entries')->where('id', 1)->update(['credit_amount' => '45.67']);
        $migration->down();
        $this->assertSame('123.45', DB::table('sales_invoices')->value('total_amount'));
        $this->assertSame([16, 2, 'NO'], [(int) $column('sales_invoice_items', 'unit_price')->p, (int) $column('sales_invoice_items', 'unit_price')->s, $column('sales_invoice_items', 'unit_price')->n]);
        $this->assertNull(DB::table('cashflow_allocations')->value('document_amount'));
        $migration->up();
        $this->assertSame('45.670', DB::table('cashflow_entries')->value('credit_amount'));
    }
}
