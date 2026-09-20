<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_number')->unique();
            $table->string('vendor_name');
            $table->string('brand_name')->nullable();
            $table->string('vendor_type', 50)->default('manufacturer'); // manufacturer, trader, distributor, service_provider
            $table->string('category')->nullable();
            $table->string('status', 30)->default('active'); // active, inactive, on_hold, blacklisted
            $table->string('image_path')->nullable();

            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_mobile', 40)->nullable();
            $table->string('whatsapp_number', 40)->nullable();
            $table->string('alternate_contact', 40)->nullable();

            $table->string('website')->nullable();
            $table->string('alibaba_link')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('pincode', 30)->nullable();
            $table->text('address')->nullable();

            $table->string('gstin', 30)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('tax_id')->nullable();
            $table->string('import_export_code')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code', 30)->nullable();
            $table->string('swift_code')->nullable();
            $table->string('bank_branch')->nullable();

            $table->string('preferred_currency', 10)->default('INR');
            $table->string('payment_terms')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('minimum_order_value', 14, 2)->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'vendor_type']);
            $table->index('vendor_name');
            $table->index('brand_name');
            $table->index('country');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
