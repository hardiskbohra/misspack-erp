<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration removes the old/legacy vendor quote module artifacts
        // after data is migrated by 2026_07_10_010800_upgrade_vendor_quotes_to_simple_v2.php.
        if (Schema::hasTable('vendor_quote_items')) {
            Schema::dropIfExists('vendor_quote_items');
        }

        if (Schema::hasTable('vendor_quotes')) {
            Schema::table('vendor_quotes', function (Blueprint $table) {
                foreach ([
                    'exchange_rate_to_inr',
                    'freight_cost_inr',
                    'operational_cost_percent',
                    'duty_percent',
                    'bcd_percent',
                    'sws_percent',
                    'local_transport_cost_inr',
                    'margin_percent',
                    'final_landing_cost_inr',
                    'final_suggested_price_inr',
                    'size_measurements',
                    'weight_measurements',
                    'vendor_notes',
                    'custom_color_moq',
                    'custom_color_specification',
                ] as $column) {
                    if (Schema::hasColumn('vendor_quotes', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // No rollback. The old vendor quote module has been replaced by the simplified V2 structure.
    }
};
