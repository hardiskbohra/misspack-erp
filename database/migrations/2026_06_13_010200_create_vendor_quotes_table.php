<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->unique();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->unsignedBigInteger('vendor_id')->nullable(); // Optional link to vendors table if vendor module exists

            $table->string('vendor_name')->nullable();
            $table->string('vendor_contact_name')->nullable();
            $table->string('vendor_email')->nullable();
            $table->string('vendor_mobile', 40)->nullable();

            $table->string('product_name')->nullable();
            $table->string('product_image_path')->nullable();
            $table->string('status', 40)->default('requested'); // requested, received, shortlisted, rejected, approved, converted
            $table->string('currency', 10)->default('RMB');
            $table->string('incoterm', 30)->nullable();

            $table->unsignedInteger('quantity')->nullable();
            $table->string('unit', 30)->default('pcs');
            $table->decimal('vendor_unit_price', 14, 4)->nullable();
            $table->unsignedInteger('moq')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();

            $table->boolean('sample_available')->default(false);
            $table->boolean('ready_stock_available')->default(false);
            $table->text('available_colors')->nullable();
            $table->text('finish_options')->nullable();
            $table->text('printing_options')->nullable();
            $table->text('size_details')->nullable();
            $table->text('weight_details')->nullable();
            $table->text('packaging_details')->nullable();
            $table->text('photo_video_notes')->nullable();

            // Manual INR pricing entered by purchase team; no automatic calculation.
            $table->decimal('landing_cost_inr', 14, 2)->nullable();
            $table->decimal('selling_price_inr', 14, 2)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'currency']);
            $table->index('product_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_quotes');
    }
};
