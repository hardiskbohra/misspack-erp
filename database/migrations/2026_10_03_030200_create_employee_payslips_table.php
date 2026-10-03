<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payslip is a month of somebody's pay, and it is the one HR document an
 * employee asks for by name ("send me March"). It is stored per month and per
 * person, with the figures the office actually paid — and optionally a link to
 * the cashflow entry that paid it, so the slip and the ledger can be read
 * against each other instead of argued about.
 *
 * The file is the issued slip (PDF, scan, whatever the office signs); the
 * figures are the record, whether or not a file exists for it. That order
 * matters: an office that pays by bank transfer often has no PDF at all, and a
 * payslip page that only lists uploaded files would show them nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_payslips')) {
            return;
        }

        Schema::create('employee_payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            /* The month it pays for, as Y-m. A string, not a date: "March 2026"
               is a period, and storing it as a day invites somebody to compare
               it with the 31st of a month that has 28. */
            $table->string('period', 7);

            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('INR');

            $table->date('paid_on')->nullable();

            /* The salary credit in the ledger, when there is one. */
            $table->foreignId('cashflow_entry_id')->nullable()->constrained('cashflow_entries')->nullOnDelete();

            /* draft · issued — an office often drafts next month's slip before
               the money moves, and the employee should not see that draft. */
            $table->string('status', 20)->default('issued');

            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* One slip per person per month: a correction edits the slip rather
               than becoming a second answer to "what was March". */
            $table->unique(['user_id', 'period']);
            $table->index(['period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payslips');
    }
};
