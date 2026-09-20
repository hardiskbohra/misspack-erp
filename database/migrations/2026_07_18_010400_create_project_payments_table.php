<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('cashflow_entry_id')->nullable();
            $table->string('transaction_type', 30); // inward, outward
            $table->date('payment_date');
            $table->decimal('amount', 16, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('payment_mode', 30)->nullable();
            $table->string('reference_number')->nullable();
            $table->string('party_type', 30)->nullable(); // client, vendor, employee, other
            $table->string('party_name')->nullable();
            $table->string('category')->nullable();
            $table->string('status', 30)->default('booked');
            $table->boolean('is_public')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'transaction_type']);
            $table->index(['project_id', 'is_public']);
            $table->index('cashflow_entry_id');
            $table->index('payment_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_payments');
    }
};
