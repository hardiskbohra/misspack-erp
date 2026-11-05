<?php

namespace Tests\Integration;

use Tests\Support\IsolatedDatabase;
use Tests\Support\WideningMigrationScenarios;
use Tests\TestCase;

class WideningMigrationSafetyTest extends TestCase
{
    use IsolatedDatabase;
    use WideningMigrationScenarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
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
