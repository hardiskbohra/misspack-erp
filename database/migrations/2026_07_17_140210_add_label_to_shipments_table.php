<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shipment_type', 30)
                  ->nullable()
                  ->after('shipment_type');
            $table->unsignedBigInteger('project_id')->nullable()->after('client_id');
            $table->unsignedBigInteger('vendor_id')->nullable()->after('client_id');
                $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('shipment_type');
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
            $table->dropColumn('vendor_id');
        });
    }
};