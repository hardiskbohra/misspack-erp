<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait WideningMigrationScenarios
{
    protected function scenario_gate_summary_migration_preserves_existing_values_and_refuses_lossy_rollback(): void
    {
        Schema::create('project_gate_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->string('summary');
        });
        DB::table('project_gate_evaluations')->insert(['summary' => 'Existing evaluation']);
        $migration = require database_path('migrations/2026_10_27_120000_widen_gate_evaluation_summary_column.php');
        $migration->up();
        $this->assertSame('Existing evaluation', DB::table('project_gate_evaluations')->value('summary'));
        $long = str_repeat('é', 256);
        DB::table('project_gate_evaluations')->insert(['summary' => $long]);

        $failure = null;
        try {
            $migration->down();
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertNotNull($failure, 'Rollback must refuse a long existing summary, not silently truncate it.');
        $this->assertStringContainsString('project_gate_evaluations.summary', $failure->getMessage());
        $this->assertSame('text', Schema::getColumnType('project_gate_evaluations', 'summary'));
        $this->assertSame($long, DB::table('project_gate_evaluations')->where('id', 2)->value('summary'));

        DB::table('project_gate_evaluations')->where('id', 2)->delete();
        $migration->down();
        $this->assertSame('varchar', Schema::getColumnType('project_gate_evaluations', 'summary'));
        $this->assertSame('Existing evaluation', DB::table('project_gate_evaluations')->value('summary'));
    }

    protected function scenario_message_widening_preserves_old_values_and_checks_every_column_before_rollback(): void
    {
        foreach (['office_alerts', 'client_portal_notifications'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('title');
                $table->string('action_url')->nullable();
            });
            DB::table($name)->insert(['title' => 'Existing title', 'action_url' => '/existing']);
        }
        Schema::create('project_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
        });
        DB::table('project_exceptions')->insert(['title' => 'Existing exception']);
        Schema::create('sales_invoice_sends', function (Blueprint $table): void {
            $table->id();
            $table->string('recipients', 500);
            $table->string('cc', 500)->nullable();
        });
        DB::table('sales_invoice_sends')->insert(['recipients' => 'a@example.test', 'cc' => null]);
        $migration = require database_path('migrations/2026_10_27_130000_widen_generated_messages_and_invoice_recipients.php');
        $migration->up();
        $this->assertSame('Existing title', DB::table('office_alerts')->value('title'));
        $this->assertSame('a@example.test', DB::table('sales_invoice_sends')->value('recipients'));
        $long = str_repeat('a', 501);
        DB::table('sales_invoice_sends')->where('id', 1)->update(['cc' => $long]);

        $failure = null;
        try {
            $migration->down();
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }
        $this->assertNotNull($failure, 'Rollback must refuse any value that will not fit.');
        $this->assertStringContainsString('sales_invoice_sends.cc', $failure->getMessage());
        $this->assertSame('text', Schema::getColumnType('office_alerts', 'title'), 'No earlier column should be narrowed before all values are checked.');
        $this->assertSame($long, DB::table('sales_invoice_sends')->value('cc'));

        DB::table('sales_invoice_sends')->where('id', 1)->update(['cc' => null]);
        $migration->down();
        $this->assertSame('varchar', Schema::getColumnType('office_alerts', 'title'));
        $this->assertSame('varchar', Schema::getColumnType('sales_invoice_sends', 'cc'));
        $this->assertNull(DB::table('sales_invoice_sends')->value('cc'));
        $this->assertSame('Existing exception', DB::table('project_exceptions')->value('title'));
    }
}
