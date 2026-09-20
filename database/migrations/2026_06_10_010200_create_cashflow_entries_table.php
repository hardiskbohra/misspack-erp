<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->text('particular');
            $table->string('invoice_bill_number')->nullable();
            $table->string('bank_reference_number')->nullable();
            $table->string('transaction_type', 20); // credit, debit
            $table->decimal('credit_amount', 16, 2)->default(0);
            $table->decimal('debit_amount', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->foreignId('account_id')->constrained('cashflow_accounts')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('cashflow_categories')->nullOnDelete();
            $table->string('accounting_status', 30)->default('pending'); // pending, booked, reconciled, disputed, ignored
            $table->string('payment_mode', 30)->nullable(); // neft, rtgs, imps, upi, cash, cheque, card, other

            // Kept nullable without FK so this module works even if client/vendor modules are not installed yet.
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('expense_head')->nullable();
            $table->string('related_party_type', 30)->default('other'); // client, vendor, expense, owner, employee, other
            $table->string('related_party_name')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('entry_date');
            $table->index(['transaction_type', 'entry_date']);
            $table->index(['account_id', 'entry_date']);
            $table->index(['category_id', 'entry_date']);
            $table->index(['client_id', 'entry_date']);
            $table->index(['vendor_id', 'entry_date']);
            $table->index(['accounting_status', 'entry_date']);
            $table->index('bank_reference_number');
            $table->index('invoice_bill_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_entries');
    }
};
