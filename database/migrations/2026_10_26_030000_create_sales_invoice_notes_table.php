<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections are documents, not edits. A credit note lowers what a tax
 * invoice collects; a debit note raises it; a return is a credit note that
 * carries the lines back (restocking is its flag until the inventory ledger
 * exists). Notes never rewrite the parent invoice — they chain onto it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->string('number', 40)->unique();                // MP/CN/26-27/001, MP/DN/26-27/001
            $table->string('kind', 8);                             // credit | debit
            $table->boolean('is_return')->default(false);          // a return is a credit note with lines
            $table->boolean('restock')->default(false);            // the inventory hook
            $table->json('lines')->nullable();                     // return lines: [{product_name, hsn_sac, quantity, unit, rate, amount}]
            $table->date('note_date');
            $table->text('reason');                                // a correction that cannot say why is an audit write-off
            $table->decimal('subtotal', 14, 2);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('issued');       // issued | cancelled (cancellation keeps its own reason)
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['sales_invoice_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_notes');
    }
};
