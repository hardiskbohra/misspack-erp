<?php

namespace Tests\Database;

use Illuminate\Support\Facades\DB;
use Tests\Support\WideningMigrationScenarios;
use Tests\TestCase;

/** DDL commits implicitly on MySQL, so use a newly created, uniquely named test schema for each case. */
class MySqlWideningMigrationSafetyTest extends TestCase
{
    use WideningMigrationScenarios;

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
}
