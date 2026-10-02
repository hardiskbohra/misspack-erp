<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores shipments.shipment_label.
 *
 * History: migration 2026_07_17_140210 is named "add_label_to_shipments_table"
 * but its up() re-added the already-existing shipment_type column instead of
 * the label, so it failed on install and no part of it was applied. The label
 * column therefore never existed on a freshly migrated database, while the
 * model, the form and the list all read and write it — saving a shipment threw
 * SQLSTATE 42S22 "Unknown column 'shipment_label' in 'field list'".
 *
 * Deliberately a NEW migration rather than another edit to the 2026_07_17 file:
 * databases that already recorded that migration will never re-read it, so an
 * edit could not repair them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipments', 'shipment_label')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            // Short free-text tag shown as a coloured chip on the list, the
            // detail page and the shipping mark (e.g. "Urgent", "Hold").
            $table->string('shipment_label', 255)->nullable()->after('shipment_type');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'shipment_label')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('shipment_label');
            });
        }
    }
};
