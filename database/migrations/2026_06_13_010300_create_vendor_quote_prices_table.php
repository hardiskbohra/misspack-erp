<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_quote_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_quote_id')->constrained('vendor_quotes')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('unit', 30)->default('pcs');
            $table->string('finish_type')->nullable();
            $table->string('printing_type')->nullable();
            $table->decimal('vendor_unit_price', 14, 4)->nullable();
            $table->decimal('landing_cost_inr', 14, 2)->nullable();
            $table->decimal('selling_price_inr', 14, 2)->nullable();
            $table->unsignedInteger('moq')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['vendor_quote_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_quote_prices');
    }
};
