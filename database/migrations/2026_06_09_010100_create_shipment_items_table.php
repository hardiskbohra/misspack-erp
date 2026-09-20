<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('hs_code')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('declared_value', 14, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('net_weight', 12, 3)->nullable();
            $table->decimal('gross_weight', 12, 3)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('shipment_id');
            $table->index('hs_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
    }
};
