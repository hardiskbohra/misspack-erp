<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number')->unique();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->unsignedBigInteger('client_id')->nullable(); // optional link to clients table if installed
            $table->string('customer_company_name')->nullable();
            $table->string('customer_contact_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_mobile', 40)->nullable();
            $table->string('title');
            $table->string('status', 40)->default('draft'); // draft, sent, accepted, rejected, revised, expired
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->string('delivery_time')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->string('discount_type', 20)->default('amount'); // amount, percent
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 8, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['status', 'quote_date']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_quotes');
    }
};
