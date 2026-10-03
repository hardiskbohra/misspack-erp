<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Planned delivery date — the single field that turns the list from
            // "what happened" into "what needs attention".
            if (! Schema::hasColumn('shipments', 'eta_date')) {
                $table->date('eta_date')->nullable()->after('drop_date');
                $table->index('eta_date');
            }

            if (! Schema::hasColumn('shipments', 'delay_reason')) {
                $table->string('delay_reason', 60)->nullable()->after('eta_date');
            }

            // Commercial link: which sales invoice this consignment belongs to,
            // so freight can be measured against what it earned.
            if (! Schema::hasColumn('shipments', 'sales_invoice_id')) {
                $table->unsignedBigInteger('sales_invoice_id')->nullable()->after('vendor_id');
                $table->index('sales_invoice_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'sales_invoice_id')) {
                $table->dropIndex(['sales_invoice_id']);
                $table->dropColumn('sales_invoice_id');
            }

            if (Schema::hasColumn('shipments', 'delay_reason')) {
                $table->dropColumn('delay_reason');
            }

            if (Schema::hasColumn('shipments', 'eta_date')) {
                $table->dropIndex(['eta_date']);
                $table->dropColumn('eta_date');
            }
        });
    }
};
