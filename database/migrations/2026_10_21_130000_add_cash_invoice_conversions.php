<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('source_proforma_id')->nullable()->constrained('sales_invoices')->restrictOnDelete();
            $table->decimal('conversion_base_amount', 16, 2)->default(0);
            $table->decimal('conversion_total_amount', 16, 2)->default(0);
            $table->decimal('cash_converted_base_amount', 16, 2)->default(0);
            $table->decimal('cash_converted_total_amount', 16, 2)->default(0);
        });
    }
    public function down(): void
    {
        if (DB::table('sales_invoices')->where('invoice_type', 'cash')
            ->orWhereNotNull('source_proforma_id')->orWhere('cash_converted_base_amount', '>', 0)->exists()) {
            throw new RuntimeException('Cash invoice data exists. Restore a consistent backup before rolling back this feature; dropping conversion links would duplicate receivables.');
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_proforma_id');
            $table->dropColumn(['conversion_base_amount', 'conversion_total_amount', 'cash_converted_base_amount', 'cash_converted_total_amount']);
        });
    }
};
