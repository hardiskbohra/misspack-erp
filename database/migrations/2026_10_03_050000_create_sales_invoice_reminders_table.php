<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every time an invoice was chased, and what came of it.
 *
 * The invoice's "last reminded" is read from this log (never stored beside it):
 * a log is the record, a counter is a copy of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();

            $table->string('channel', 20)->default('whatsapp'); // whatsapp, email, phone, other
            $table->date('reminded_at');
            $table->text('message')->nullable();
            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // the list asks "nudged in the last week?" per invoice, and the record
            // page reads one invoice's log newest first
            $table->index(['sales_invoice_id', 'reminded_at']);
            $table->index('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_reminders');
    }
};
