<?php

namespace Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

class FreshSchemaTest extends TestCase
{
    use IsolatedDatabase;

    public function test_all_migrations_apply_to_a_disposable_fresh_sqlite_database(): void
    {
        $this->isolatedDatabase();

        $exitCode = Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertTrue(Schema::hasTable('project_gate_evaluations'));
        $this->assertTrue(Schema::hasTable('sales_invoice_sends'));
        $this->assertTrue(Schema::hasTable('office_alerts'));
        $this->assertFalse(Schema::hasColumn('projects', 'customer_quote_id'));
        $this->assertFalse(Schema::hasColumn('sales_invoices', 'customer_quote_id'));
    }

    public function test_progress_migration_copies_matching_workflow_and_preserves_unmatched_project(): void
    {
        $this->isolatedDatabase();
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('progress_percent');
        });
        Schema::create('project_workflows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id')->unique();
            $table->unsignedTinyInteger('calculated_progress');
        });
        DB::table('projects')->insert([
            ['id' => 1, 'progress_percent' => 30],
            ['id' => 2, 'progress_percent' => 40],
        ]);
        DB::table('project_workflows')->insert(['project_id' => 1, 'calculated_progress' => 80]);

        (require database_path('migrations/2026_10_20_140000_remove_milestones_and_manual_progress.php'))->up();

        $this->assertSame(80, DB::table('projects')->where('id', 1)->value('progress_percent'));
        $this->assertSame(40, DB::table('projects')->where('id', 2)->value('progress_percent'));
    }
}
