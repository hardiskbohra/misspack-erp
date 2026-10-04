<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lines of a purchase order or a purchase bill.
 *
 * The sales item table with the flags turned around: the totals are worked out
 * on the server from these rows exactly the way `sales_invoice_items` are, so a
 * printed purchase bill adds up the same way a tax invoice does (see
 * `PurchaseInvoiceController::syncItemsAndTotals()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('project_product_id')->nullable();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->string('hsn_sac', 40)->nullable();
            $table->decimal('quantity', 16, 3)->default(1);
            $table->string('unit', 40)->default('pcs');
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('gross_amount', 16, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('taxable_amount', 16, 2)->default(0);
            $table->decimal('gst_percent', 8, 2)->default(0);
            $table->decimal('cgst_amount', 16, 2)->default(0);
            $table->decimal('sgst_amount', 16, 2)->default(0);
            $table->decimal('igst_amount', 16, 2)->default(0);
            $table->decimal('line_total', 16, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index('purchase_invoice_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
