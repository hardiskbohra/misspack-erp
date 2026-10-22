<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cashflow_allocations')) {
            return;
        }

        Schema::create('cashflow_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cashflow_entry_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->unsignedBigInteger('purchase_invoice_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('document_amount', 18, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->string('document_currency', 10)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index('cashflow_entry_id');
            $table->index('project_id');
            $table->index('sales_invoice_id');
            $table->index('purchase_invoice_id');
            $table->index(['sales_invoice_id', 'cashflow_entry_id'], 'cf_alloc_sales_entry_idx');
            $table->index(['purchase_invoice_id', 'cashflow_entry_id'], 'cf_alloc_purchase_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_allocations');
    }
};
