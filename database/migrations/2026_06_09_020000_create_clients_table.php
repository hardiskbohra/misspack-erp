<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_number')->unique();
            $table->string('company_name');
            $table->string('brand_name')->nullable();
            $table->string('client_type', 40)->default('customer'); // customer, vendor, both
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->string('status', 30)->default('draft'); // draft, under_review, approved, rejected, revision

            $table->string('ceo_name')->nullable();
            $table->string('ceo_email')->nullable();
            $table->string('ceo_contact', 40)->nullable();

            $table->string('account_person_name')->nullable();
            $table->string('account_person_email')->nullable();
            $table->string('account_person_contact', 40)->nullable();

            $table->string('marketing_person_name')->nullable();
            $table->string('marketing_person_email')->nullable();
            $table->string('marketing_person_contact', 40)->nullable();

            $table->string('dispatch_person_name')->nullable();
            $table->string('dispatch_person_email')->nullable();
            $table->string('dispatch_person_contact', 40)->nullable();

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
            $table->boolean('shipping_same_as_billing')->default(false);

            $table->string('gstin', 30)->nullable();
            $table->string('pan', 20)->nullable();
            $table->string('tan', 20)->nullable();
            $table->string('cin')->nullable();
            $table->string('msme_number')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code', 30)->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('swift_code')->nullable();

            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->unsignedInteger('credit_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('preferred_currency', 10)->default('INR');
            $table->text('notes')->nullable();

            $table->string('public_token', 80)->unique();
            $table->timestamp('kyc_sent_at')->nullable();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->foreignId('kyc_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revision_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'client_type']);
            $table->index('company_name');
            $table->index('brand_name');
            $table->index('gstin');
            $table->index('pan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
