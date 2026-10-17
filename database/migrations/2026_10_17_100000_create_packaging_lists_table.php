<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_lists', function (Blueprint $table) {
            $table->id();
            $table->string('packing_number')->unique();
            $table->date('packing_date');
            $table->string('invoice_number')->nullable()->index();
            $table->string('transport_mode', 30)->default('air');
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_name');
            $table->text('from_address')->nullable();
            $table->string('from_contact')->nullable();
            $table->string('from_email')->nullable();
            $table->string('bill_to_name');
            $table->text('bill_to_address')->nullable();
            $table->string('ship_to_name');
            $table->text('ship_to_address')->nullable();
            $table->text('declaration')->nullable();
            $table->string('customer_signature')->nullable();
            $table->string('supplier_signature')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_cartons')->default(0);
            $table->decimal('total_net_weight', 14, 3)->default(0);
            $table->decimal('total_gross_weight', 14, 3)->default(0);
            $table->decimal('total_cbm', 14, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('packaging_list_cartons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_list_id')->constrained()->cascadeOnDelete();
            $table->string('carton_label')->nullable();
            $table->unsignedInteger('carton_count')->default(1);
            $table->decimal('length_cm', 10, 2)->default(0);
            $table->decimal('width_cm', 10, 2)->default(0);
            $table->decimal('height_cm', 10, 2)->default(0);
            $table->decimal('net_weight_per_carton', 12, 3)->default(0);
            $table->decimal('gross_weight_per_carton', 12, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('packaging_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_list_carton_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description');
            $table->decimal('quantity_per_carton', 14, 3)->default(0);
            $table->decimal('total_quantity', 14, 3)->default(0);
            $table->string('unit', 30)->default('pcs');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_list_items');
        Schema::dropIfExists('packaging_list_cartons');
        Schema::dropIfExists('packaging_lists');
    }
};
