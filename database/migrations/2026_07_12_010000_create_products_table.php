<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_number')->unique();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('category')->nullable();
            $table->string('status', 30)->default('active'); // active, inactive, discontinued
            $table->json('ml_capacities')->nullable(); // multiple capacities possible
            $table->text('finish_details')->nullable();
            $table->text('printing_details')->nullable();
            $table->boolean('ready_stock_available')->default(false);
            $table->unsignedInteger('ready_stock_moq')->nullable();
            $table->unsignedInteger('customisation_moq')->nullable();
            $table->text('available_stock_colors')->nullable();
            $table->text('customisation_details')->nullable();
            $table->text('size_measurements')->nullable();
            $table->text('weight_measurements')->nullable();
            $table->text('material_details')->nullable();
            $table->text('packaging_details')->nullable();
            $table->text('description')->nullable();
            $table->boolean('show_price_ladder_public')->default(false);
            $table->string('public_token', 80)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('name');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
