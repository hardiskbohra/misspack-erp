<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_packing_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('packaging_list_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_product_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_quantity', 14, 3);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['shipment_item_id', 'packaging_list_item_id'], 'shipment_pack_item_unique');
            $table->index(['packaging_list_item_id', 'allocated_quantity'], 'shipment_pack_source_qty_idx');
            $table->index(['project_product_id', 'allocated_quantity'], 'shipment_pack_product_qty_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_packing_allocations');
    }
};
