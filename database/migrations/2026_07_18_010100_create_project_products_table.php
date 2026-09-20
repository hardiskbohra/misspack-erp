<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->json('product_snapshot')->nullable();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit', 30)->default('pcs');
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('status', 40)->default('planned');
            $table->string('stage', 60)->default('pending');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('vendor_invoice_number')->nullable();
            $table->date('expected_ready_date')->nullable();
            $table->date('actual_ready_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'stage']);
            $table->index('product_id');
            $table->index('vendor_id');
            $table->index('expected_ready_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_products');
    }
};
