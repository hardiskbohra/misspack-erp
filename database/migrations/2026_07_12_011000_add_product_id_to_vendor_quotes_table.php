<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_quotes', 'product_id')) {
                // Nullable and no hard FK so Vendor Quotes can still work if Product module is installed later.
                $table->unsignedBigInteger('product_id')->nullable()->after('lead_id');
                $table->index('product_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_quotes', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_quotes', 'product_id')) {
                $table->dropIndex(['product_id']);
                $table->dropColumn('product_id');
            }
        });
    }
};
