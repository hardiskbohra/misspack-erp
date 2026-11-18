<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Linked purchase conversions and document slices of one vendor payment. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_invoices', 'original_total_amount')) $table->decimal('original_total_amount', 17, 3)->nullable();
                if (! Schema::hasColumn('purchase_invoices', 'converted_amount')) $table->decimal('converted_amount', 17, 3)->default(0);
                if (! Schema::hasColumn('purchase_invoices', 'conversion_amount')) $table->decimal('conversion_amount', 17, 3)->nullable();
                if (! Schema::hasColumn('purchase_invoices', 'conversion_kind')) $table->string('conversion_kind', 20)->nullable();
            });
        }

        if (! Schema::hasTable('purchase_payment_splits') && Schema::hasTable('vendor_payment_entries') && Schema::hasTable('purchase_invoices')) {
            Schema::create('purchase_payment_splits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_payment_entry_id')->constrained('vendor_payment_entries')->restrictOnDelete();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->restrictOnDelete();
                $table->decimal('amount', 16, 4);
                $table->timestamps();
                $table->unique(['vendor_payment_entry_id', 'purchase_invoice_id'], 'purchase_payment_split_unique');
                $table->index('purchase_invoice_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_payment_splits') && DB::table('purchase_payment_splits')->exists()) {
            throw new RuntimeException('Cannot remove purchase conversion allocations while payment slices exist.');
        }
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'conversion_kind')
            && DB::table('purchase_invoices')->whereNotNull('conversion_kind')->exists()) {
            throw new RuntimeException('Cannot remove purchase conversion history while linked bills exist.');
        }
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'converted_amount')
            && DB::table('purchase_invoices')->where('converted_amount', '!=', 0)->exists()) {
            throw new RuntimeException('Cannot remove the remaining purchase-order claim while conversions exist.');
        }
        Schema::dropIfExists('purchase_payment_splits');
        if (Schema::hasTable('purchase_invoices')) {
            $columns = array_values(array_filter(['original_total_amount', 'converted_amount', 'conversion_amount', 'conversion_kind'],
                fn ($column) => Schema::hasColumn('purchase_invoices', $column)));
            if ($columns) Schema::table('purchase_invoices', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
