<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('shipment_type');
                $table->index('project_id');
            }

            if (! Schema::hasColumn('shipments', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('shipment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'vendor_id')) {
                $table->dropColumn('vendor_id');
            }

            if (Schema::hasColumn('shipments', 'project_id')) {
                $table->dropIndex(['project_id']);
                $table->dropColumn('project_id');
            }
        });
    }
};
