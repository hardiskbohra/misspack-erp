<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buying, the mirror of selling.
 *
 * The sales side is one table with `invoice_type` (proforma / tax) and one link
 * between the two (`converted_invoice_id`), and this is the same shape on the
 * purchase side: a Purchase Order and the Purchase Bill raised from it are one
 * document at two stages of its life, not two tables that have to be kept in
 * step. `invoice_type` is `order` or `bill`; `purchase_order_id` is the order a
 * bill came from; `converted_invoice_id` is the bill an order became.
 *
 * Every party detail is snapshotted the way the sales table snapshots a client:
 * the bill keeps its own copy of the vendor's name, GSTIN, PAN and address, so
 * a later edit to the vendor record never rewrites a document that has already
 * been sent. The money columns are the sales table's to the rupee, including
 * `amount_paid` meaning the *opening* figure — money paid before or outside the
 * ledger. Payments live in `vendor_payment_entries` (see the id column added to
 * it) and the balance is computed from both, never stored as a second opinion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('invoice_type', 30)->default('order'); // order, bill
            $table->string('status', 40)->default('draft');
            /* The key to the public print link — the same shape as an invoice's. */
            $table->string('public_token', 48)->unique();

            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('purchase_order_id')->nullable();     // the order a bill came from
            $table->unsignedBigInteger('converted_invoice_id')->nullable();  // the bill an order became

            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->date('expected_date')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 16, 6)->nullable();
            $table->string('gst_type', 30)->default('intra_state'); // intra_state, inter_state, export
            $table->string('place_of_supply')->nullable();

            // The vendor's own paperwork, when a bill arrives with one
            $table->string('vendor_bill_number')->nullable();
            $table->date('vendor_bill_date')->nullable();
            $table->string('our_reference')->nullable();

            // Buyer snapshot: our company, the party that pays
            $table->string('buyer_company_name')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_city')->nullable();
            $table->string('buyer_state')->nullable();
            $table->string('buyer_country')->nullable();
            $table->string('buyer_pincode', 30)->nullable();
            $table->string('buyer_gstin', 30)->nullable();
            $table->string('buyer_pan', 20)->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_mobile', 40)->nullable();
            $table->string('buyer_website')->nullable();

            // Vendor snapshot: what the vendor record said when this was raised
            $table->string('vendor_company_name')->nullable();
            $table->string('vendor_contact_name')->nullable();
            $table->string('vendor_email')->nullable();
            $table->string('vendor_mobile', 40)->nullable();
            $table->string('vendor_gstin', 30)->nullable();
            $table->string('vendor_pan', 20)->nullable();
            $table->text('vendor_address')->nullable();
            $table->string('vendor_city')->nullable();
            $table->string('vendor_state')->nullable();
            $table->string('vendor_country')->nullable();
            $table->string('vendor_pincode', 30)->nullable();

            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->string('dispatch_terms')->nullable();
            $table->string('transport_mode')->nullable();
            $table->string('purchase_person')->nullable();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->string('discount_type', 20)->default('amount'); // amount, percent
            $table->decimal('discount_value', 16, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('taxable_amount', 16, 2)->default(0);
            $table->decimal('cgst_amount', 16, 2)->default(0);
            $table->decimal('sgst_amount', 16, 2)->default(0);
            $table->decimal('igst_amount', 16, 2)->default(0);
            $table->decimal('freight_amount', 16, 2)->default(0);
            $table->decimal('packing_amount', 16, 2)->default(0);
            $table->decimal('other_charges', 16, 2)->default(0);
            $table->decimal('round_off', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->decimal('amount_paid', 16, 2)->default(0);
            $table->decimal('balance_amount', 16, 2)->default(0);
            $table->text('amount_in_words')->nullable();

            $table->longText('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_type', 'status']);
            $table->index(['vendor_id', 'invoice_date']);
            $table->index(['project_id', 'invoice_date']);
            $table->index('purchase_order_id');
            $table->index('converted_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
