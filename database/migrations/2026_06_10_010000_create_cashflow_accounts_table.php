<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name'); // Hardik HDFC, Neha IDFC, MissPack Current etc.
            $table->string('account_type', 30)->default('current'); // current, saving, cash, credit_card, loan
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code', 30)->nullable();
            $table->string('branch')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('current_balance', 16, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['account_type', 'is_active']);
            $table->index('account_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_accounts');
    }
};
