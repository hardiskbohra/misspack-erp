<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number')->unique();
            $table->string('title');
            $table->unsignedBigInteger('client_id')->nullable(); // Optional link to clients table if client module exists
            $table->string('client_company_name')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_mobile', 40)->nullable();
            $table->string('lead_source', 40)->default('whatsapp');
            $table->string('priority', 20)->default('medium');
            $table->string('status', 40)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('product_name')->nullable();
            $table->string('product_image_path')->nullable();
            $table->text('product_description')->nullable();
            $table->decimal('capacity_value', 12, 3)->nullable();
            $table->string('capacity_unit', 20)->default('ml');
            $table->unsignedInteger('required_quantity')->nullable();
            $table->text('quantity_notes')->nullable();
            $table->json('quote_quantities')->nullable(); // e.g. [3000, 5000, 10000]

            $table->string('finish_required', 40)->nullable(); // matte, glossy, both, any, custom
            $table->string('printing_required', 60)->nullable(); // none, one_color, multi_color, label, embossing, custom
            $table->text('printing_details')->nullable();
            $table->boolean('ready_stock_required')->default(false);
            $table->text('ready_stock_color_requirement')->nullable();
            $table->text('ready_stock_moq_notes')->nullable();
            $table->boolean('custom_color_required')->default(false);
            $table->text('custom_color_specification')->nullable();

            $table->decimal('target_price', 14, 2)->nullable();
            $table->string('target_currency', 10)->default('INR');
            $table->date('expected_order_date')->nullable();
            $table->date('required_delivery_date')->nullable();
            $table->text('sales_notes')->nullable();
            $table->text('purchase_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['lead_source', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('client_id');
            $table->index('product_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
