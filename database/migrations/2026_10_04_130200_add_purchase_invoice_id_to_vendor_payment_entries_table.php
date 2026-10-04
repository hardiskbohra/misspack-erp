<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The purchase bill's line into the vendor ledger.
 *
 * A purchase bill is not a document that lives beside the ledger — it *is* a
 * payable, so it posts one row into `vendor_payment_entries` (a credit, the way
 * a supplier bill does in the vendor's own Money tab) and every payment made
 * against it posts a debit carrying the same id. That is what makes the Payables
 * ageing, the vendor statement and the bill's own balance read the same number
 * without any of them being told twice.
 *
 * Guarded like the sales side's equivalent: the ledger table predates this
 * module and may not exist at all on an old install.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payment_entries')
            || Schema::hasColumn('vendor_payment_entries', 'purchase_invoice_id')) {
            return;
        }

        Schema::table('vendor_payment_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->after('vendor_id');
            $table->index('purchase_invoice_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_payment_entries')
            || ! Schema::hasColumn('vendor_payment_entries', 'purchase_invoice_id')) {
            return;
        }

        Schema::table('vendor_payment_entries', function (Blueprint $table) {
            $table->dropIndex(['purchase_invoice_id']);
            $table->dropColumn('purchase_invoice_id');
        });
    }
};
