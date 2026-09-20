<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payment_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cashflow_entry_id')->nullable();
            $table->date('transaction_date');
            $table->string('invoice_number')->nullable();
            $table->string('foreign_currency', 10)->default('RMB');
            $table->decimal('foreign_amount', 16, 4)->default(0);
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->string('transaction_type', 20); // credit = bill/payable, debit = payment/settlement
            $table->string('entry_category', 40)->default('bill'); // bill, payment, expense, adjustment, refund
            $table->text('particular');
            $table->string('status', 40)->default('pending');
            $table->unsignedBigInteger('paid_account_id')->nullable();
            $table->string('payment_mode', 40)->nullable();
            $table->string('bank_reference_number')->nullable();
            $table->decimal('amount_in_inr', 16, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'transaction_date']);
            $table->index(['vendor_id', 'foreign_currency']);
            $table->index(['vendor_id', 'transaction_type']);
            $table->index('project_id');
            $table->index('cashflow_entry_id');
            $table->index('paid_account_id');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_entries');
    }
};
