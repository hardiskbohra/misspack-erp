<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipment_attachments', 'document_type')) {
                // packing_list, commercial_invoice, bill_of_lading, awb,
                // shipping_bill, bill_of_entry, certificate_of_origin,
                // insurance, eway_bill, cha_checklist, delivery_challan, other.
                // Photos and plain documents keep this null.
                $table->string('document_type', 40)->nullable()->after('attachment_type');
                $table->index(['shipment_id', 'document_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipment_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('shipment_attachments', 'document_type')) {
                $table->dropIndex(['shipment_id', 'document_type']);
                $table->dropColumn('document_type');
            }
        });
    }
};
