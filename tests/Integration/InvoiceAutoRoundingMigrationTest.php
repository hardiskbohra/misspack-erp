<?php

namespace Tests\Integration;

use Tests\Support\InvoiceAutoRoundingMigrationScenarios;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class InvoiceAutoRoundingMigrationTest extends TestCase
{
    use InvoiceAutoRoundingMigrationScenarios;
    use IsolatedDatabase;

    public function test_existing_invoice_money_survives_migration_and_active_policy_cannot_be_dropped(): void
    {
        $this->isolatedDatabase();
        $this->scenario_auto_rounding_migration_preserves_existing_invoice_money_and_guards_rollback();
    }
}
