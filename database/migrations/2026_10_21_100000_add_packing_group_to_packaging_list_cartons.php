<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A packaging list is read in sections.
 *
 * The sheet the supplier sends back is not one row per carton and not one row
 * per product — it is a set of headings ("50ml Empty Spray Bottle", "Silicone
 * Ring Case") under which the carton blocks of that packing run sit. Two very
 * different products can share a heading, because the heading describes what was
 * packed together, not what the product is.
 *
 * `packing_group` is that heading. It is typed on the carton block, so blocks
 * that carry the same heading print under one band and in the order they were
 * entered. A block with no heading stays its own section, titled by its carton
 * label or its first item — the sheet it printed before this column existed, so
 * nothing has to be re-entered to keep working and no copy goes stale when an
 * item is renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packaging_list_cartons')
            || Schema::hasColumn('packaging_list_cartons', 'packing_group')) {
            return;
        }

        Schema::table('packaging_list_cartons', function (Blueprint $table) {
            $table->string('packing_group', 120)->nullable()->after('carton_label');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('packaging_list_cartons')
            || ! Schema::hasColumn('packaging_list_cartons', 'packing_group')) {
            return;
        }

        Schema::table('packaging_list_cartons', function (Blueprint $table) {
            $table->dropColumn('packing_group');
        });
    }
};
