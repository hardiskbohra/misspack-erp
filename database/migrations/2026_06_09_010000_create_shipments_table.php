<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number')->unique();
            $table->string('identity_name');
            $table->string('shipment_type', 30)->default('domestic'); // domestic, import, export
            $table->string('shipment_mode', 30)->nullable(); // courier, air, sea, road, rail
            $table->date('pickup_date')->nullable();
            $table->date('drop_date')->nullable();

            $table->string('from_name')->nullable();
            $table->text('from_address')->nullable();
            $table->string('from_city')->nullable();
            $table->string('from_state')->nullable();
            $table->string('from_country')->nullable();
            $table->string('from_pincode', 30)->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_mobile', 40)->nullable();

            $table->string('to_name')->nullable();
            $table->text('to_address')->nullable();
            $table->string('to_city')->nullable();
            $table->string('to_state')->nullable();
            $table->string('to_country')->nullable();
            $table->string('to_pincode', 30)->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_mobile', 40)->nullable();

            $table->string('logistic_partner')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('bill_of_entry_number')->nullable();
            $table->string('origin_port')->nullable();
            $table->string('destination_port')->nullable();

            $table->string('status', 30)->default('planning'); // planning, picked_up, in_transit, custom_hold, delayed, delivered, cancelled
            $table->decimal('shipment_cost', 14, 2)->nullable();
            $table->string('currency', 10)->default('INR'); // INR, RMB, USD
            $table->string('cost_borne_by', 30)->default('misspack'); // shipper, receiver, misspack
            $table->unsignedInteger('package_count')->nullable();
            $table->decimal('gross_weight', 12, 3)->nullable();
            $table->decimal('chargeable_weight', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->string('public_token', 80)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_type', 'status']);
            $table->index('pickup_date');
            $table->index('drop_date');
            $table->index('tracking_number');
            $table->index('bill_of_entry_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
