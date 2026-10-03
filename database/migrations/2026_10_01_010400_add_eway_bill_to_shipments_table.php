<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Transport document that every Indian road consignment must carry.
            // The number identifies it; the validity date is what actually
            // needs watching, because an expired bill holds the truck at a
            // check-post — hence the index the attention filter reads.
            if (! Schema::hasColumn('shipments', 'eway_bill_number')) {
                $table->string('eway_bill_number', 40)->nullable()->after('bill_of_entry_number');
            }

            if (! Schema::hasColumn('shipments', 'eway_bill_valid_until')) {
                $table->date('eway_bill_valid_until')->nullable()->after('eway_bill_number');
                $table->index('eway_bill_valid_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'eway_bill_valid_until')) {
                $table->dropIndex(['eway_bill_valid_until']);
                $table->dropColumn('eway_bill_valid_until');
            }

            if (Schema::hasColumn('shipments', 'eway_bill_number')) {
                $table->dropColumn('eway_bill_number');
            }
        });
    }
};
