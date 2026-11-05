<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What left the building: every invoice e-mail the office sends, with who
 * pressed send, to whom, what it said, and whether the mailer accepted it.
 * Collections runs on this log — "the customer never received it" is the
 * first excuse every chase hears, and this table is the answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->string('channel')->default('email');           // v1 is email only; the column is where dunning adds SMS/WhatsApp later
            $table->string('recipients', 500);                     // the To line, as sent (snapshot, not a relation)
            $table->string('cc', 500)->nullable();
            $table->string('subject', 190);
            $table->text('message');                               // the body, as sent — never "whatever the template says today"
            $table->string('status', 20)->default('sent');         // sent | failed
            $table->text('error')->nullable();                     // transport's reason when it failed
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['sales_invoice_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_sends');
    }
};
