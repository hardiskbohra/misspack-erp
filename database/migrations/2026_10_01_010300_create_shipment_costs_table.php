<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Freight cost no longer lives in one opaque number on the shipment:
        // every head (freight, duty, CHA, demurrage…) is its own row, in its own
        // currency, with an INR snapshot for the cashflow mirror.
        Schema::create('shipment_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('cost_head', 40); // freight, insurance, customs_duty, cha, last_mile, demurrage, other
            $table->string('label')->nullable(); // free text when head = other
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->decimal('amount_in_inr', 16, 2)->default(0);
            $table->unsignedBigInteger('vendor_id')->nullable(); // who was paid (forwarder / CHA)
            $table->date('incurred_on')->nullable();
            $table->string('document_number')->nullable(); // bill / invoice reference
            $table->unsignedBigInteger('paid_account_id')->nullable(); // set = already paid from this account
            $table->string('payment_mode', 40)->nullable();
            $table->date('paid_on')->nullable();
            // Mirror link, same convention as vendor_payment_entries.cashflow_entry_id
            // (nullable, indexed, no FK, detach instead of cascade).
            $table->unsignedBigInteger('cashflow_entry_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shipment_id', 'sort_order']);
            $table->index('cost_head');
            $table->index('vendor_id');
            $table->index('cashflow_entry_id');
            $table->index('paid_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_costs');
    }
};
