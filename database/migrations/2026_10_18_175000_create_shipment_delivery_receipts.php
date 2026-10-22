<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_delivery_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->string('receipt_reference')->nullable();
            $table->string('recipient_name');
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->string('evidence_path');
            $table->string('evidence_original_name');
            $table->string('evidence_mime', 120)->nullable();
            $table->unsignedBigInteger('evidence_size')->default(0);
            $table->string('evidence_hash', 64);
            $table->string('content_hash', 64);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['shipment_id', 'received_at'], 'delivery_receipts_shipment_date_idx');
        });

        Schema::create('shipment_delivery_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_delivery_receipt_id');
            $table->foreign('shipment_delivery_receipt_id', 'delivery_receipt_lines_receipt_fk')->references('id')->on('shipment_delivery_receipts')->cascadeOnDelete();
            $table->foreignId('shipment_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('received_quantity', 14, 3)->default(0);
            $table->decimal('accepted_quantity', 14, 3)->default(0);
            $table->decimal('short_quantity', 14, 3)->default(0);
            $table->decimal('damaged_quantity', 14, 3)->default(0);
            $table->decimal('rejected_quantity', 14, 3)->default(0);
            $table->decimal('returned_quantity', 14, 3)->default(0);
            $table->decimal('replacement_quantity', 14, 3)->default(0);
            $table->text('discrepancy_reason')->nullable();
            $table->timestamps();
            $table->index(['shipment_item_id', 'accepted_quantity'], 'delivery_lines_item_accepted_idx');
            $table->index(['project_product_id', 'accepted_quantity'], 'delivery_lines_product_accepted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_delivery_receipt_lines');
        Schema::dropIfExists('shipment_delivery_receipts');
    }
};
