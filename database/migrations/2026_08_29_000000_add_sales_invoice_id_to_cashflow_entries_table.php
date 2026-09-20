<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cashflow_entries') && ! Schema::hasColumn('cashflow_entries', 'sales_invoice_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('sales_invoice_id')->nullable()->after('project_id');
                $table->index('sales_invoice_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cashflow_entries') && Schema::hasColumn('cashflow_entries', 'sales_invoice_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->dropIndex(['sales_invoice_id']);
                $table->dropColumn('sales_invoice_id');
            });
        }
    }
};
