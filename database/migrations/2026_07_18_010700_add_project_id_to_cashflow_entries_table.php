<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cashflow_entries') && ! Schema::hasColumn('cashflow_entries', 'project_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->after('vendor_id');
                $table->index('project_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cashflow_entries') && Schema::hasColumn('cashflow_entries', 'project_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->dropIndex(['project_id']);
                $table->dropColumn('project_id');
            });
        }
    }
};
