<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_quote_id')->constrained('lead_quotes')->cascadeOnDelete();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->string('unit', 30)->default('pcs');
            $table->string('capacity')->nullable();
            $table->string('finish_type')->nullable();
            $table->string('printing_type')->nullable();
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('lead_quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_quote_items');
    }
};
