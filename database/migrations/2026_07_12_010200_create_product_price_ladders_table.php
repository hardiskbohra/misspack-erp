<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_ladders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('unit', 30)->default('pcs');
            $table->string('capacity')->nullable();
            $table->string('finish_type')->nullable();
            $table->string('printing_type')->nullable();
            $table->decimal('landing_cost_inr', 14, 2)->nullable();
            $table->decimal('selling_cost_inr', 14, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_ladders');
    }
};
