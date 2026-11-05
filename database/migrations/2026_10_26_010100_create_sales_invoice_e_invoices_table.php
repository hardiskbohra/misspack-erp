<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* One e-invoice registration per tax invoice. The IRN is legally the
           invoice's identity on the GST network, so the row is unique on the
           invoice and the IRN is unique on its own; the request/response JSON
           is kept verbatim because a dispute with the portal is settled by
           what was sent, not by what we meant. */
        Schema::create('sales_invoice_e_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->unique()->constrained('sales_invoices')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending registration, registered, cancelled, failed
            $table->string('provider', 40)->default('manual'); // which gateway produced it

            $table->string('irn', 64)->nullable()->unique();
            $table->string('ack_no', 30)->nullable();
            $table->timestampTz('ack_date')->nullable();

            // What the portal returns: the signed JSON and the signed QR string
            // (both base64/JWT-flavoured, far longer than a price column).
            $table->longText('signed_invoice')->nullable();
            $table->longText('signed_qr_code')->nullable();

            $table->json('request_payload')->nullable();  // the NIC-schema JSON we generated/submitted
            $table->json('response_payload')->nullable(); // the gateway's raw answer, when there is one
            $table->text('error')->nullable();            // last failure, so the UI can say why

            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason', 10)->nullable();  // 1 duplicate, 2 data entry mistake, 3 order cancelled, 4 other
            $table->string('cancel_remark')->nullable();
            $table->timestamps();

            $table->index(['status', 'ack_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_e_invoices');
    }
};
