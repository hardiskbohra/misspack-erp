<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('invoice_type', 30)->default('proforma'); // proforma, tax
            $table->string('status', 40)->default('draft');
            $table->string('public_token', 80)->unique();

            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('customer_quote_id')->nullable();

            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->string('gst_type', 30)->default('intra_state'); // intra_state, inter_state, export
            $table->string('place_of_supply')->nullable();
            $table->string('po_number')->nullable();
            $table->date('po_date')->nullable();

            // Seller snapshot
            $table->string('seller_company_name')->nullable();
            $table->text('seller_address')->nullable();
            $table->string('seller_city')->nullable();
            $table->string('seller_state')->nullable();
            $table->string('seller_country')->nullable();
            $table->string('seller_pincode', 30)->nullable();
            $table->string('seller_gstin', 30)->nullable();
            $table->string('seller_pan', 20)->nullable();
            $table->string('seller_email')->nullable();
            $table->string('seller_mobile', 40)->nullable();
            $table->string('seller_website')->nullable();
            $table->string('seller_bank_name')->nullable();
            $table->string('seller_account_holder')->nullable();
            $table->string('seller_account_number')->nullable();
            $table->string('seller_ifsc')->nullable();
            $table->string('seller_branch')->nullable();
            $table->string('seller_swift')->nullable();

            // Client snapshot derived from client object but stored for history
            $table->string('client_company_name')->nullable();
            $table->string('client_brand_name')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_mobile', 40)->nullable();
            $table->string('client_gstin', 30)->nullable();
            $table->string('client_pan', 20)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state')->nullable();
            $table->string('billing_country')->nullable();
            $table->string('billing_pincode', 30)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_pincode', 30)->nullable();

            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->string('dispatch_terms')->nullable();
            $table->string('transport_mode')->nullable();
            $table->string('sales_person')->nullable();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->string('discount_type', 20)->default('amount'); // amount, percent
            $table->decimal('discount_value', 16, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('taxable_amount', 16, 2)->default(0);
            $table->decimal('cgst_amount', 16, 2)->default(0);
            $table->decimal('sgst_amount', 16, 2)->default(0);
            $table->decimal('igst_amount', 16, 2)->default(0);
            $table->decimal('freight_amount', 16, 2)->default(0);
            $table->decimal('packing_amount', 16, 2)->default(0);
            $table->decimal('other_charges', 16, 2)->default(0);
            $table->decimal('round_off', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->decimal('amount_paid', 16, 2)->default(0);
            $table->decimal('balance_amount', 16, 2)->default(0);
            $table->text('amount_in_words')->nullable();

            $table->longText('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('show_client_portal')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_type', 'status']);
            $table->index(['client_id', 'invoice_date']);
            $table->index(['project_id', 'invoice_date']);
            $table->index('customer_quote_id');
            $table->index('show_client_portal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
