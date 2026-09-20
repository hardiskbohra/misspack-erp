<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('project_product_id')->nullable();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->string('hsn_sac', 30)->nullable();
            $table->decimal('quantity', 16, 3)->default(1);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('gross_amount', 16, 2)->default(0);
            $table->decimal('discount_percent', 8, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('taxable_amount', 16, 2)->default(0);
            $table->decimal('gst_percent', 8, 2)->default(18);
            $table->decimal('cgst_amount', 16, 2)->default(0);
            $table->decimal('sgst_amount', 16, 2)->default(0);
            $table->decimal('igst_amount', 16, 2)->default(0);
            $table->decimal('line_total', 16, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['sales_invoice_id', 'product_id']);
            $table->index('project_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
    }
};
