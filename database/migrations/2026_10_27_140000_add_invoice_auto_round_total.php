<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            // Existing invoices keep their original/manual rounding policy.
            $table->boolean('auto_round_total')->default(false);
        });
    }

    public function down(): void
    {
        // Dropping this flag would silently change the policy on later edits.
        // Quiesce invoice writers before rollback; MySQL DDL cannot lock out
        // a concurrent insert between this check and the ALTER TABLE.
        if (DB::table('sales_invoices')->where('auto_round_total', true)->exists()) {
            throw new RuntimeException('Cannot drop auto_round_total while invoices use automatic rounding. Back up and resolve their policies first.');
        }

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn('auto_round_total');
        });
    }
};
