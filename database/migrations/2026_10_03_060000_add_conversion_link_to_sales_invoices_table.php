<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A proforma becomes a tax invoice once, and the money moves with it.
 *
 * The link lives on the proforma (`converted_invoice_id` -> the tax invoice it
 * became): one column on the source document is the "only once" rule made
 * structural, and it is what every sum in the app reads to tell the two apart.
 *
 * The conversions made before this column existed are linked here by the note
 * the old `convert()` wrote ("Converted from X."), and their advances move the
 * same way a conversion moves them now: the tax invoice is the document that is
 * owed, so the proforma's opening figure and the receipts filed against it
 * belong to it. Without that, the office's own history counts the same money
 * twice — once on the proforma, once on the tax invoice raised from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('converted_invoice_id')
                ->nullable()
                ->after('customer_quote_id')
                ->constrained('sales_invoices')
                ->nullOnDelete();
        });

        $this->linkExistingConversions();
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_invoice_id');
        });
    }

    /**
     * The pairs made before this column existed.
     *
     * Oldest tax invoice first: if a proforma was converted twice under the old
     * code, the first tax invoice claims the link and the second is left where it
     * is — a document the office can delete, rather than money moved twice.
     */
    private function linkExistingConversions(): void
    {
        $taxInvoices = DB::table('sales_invoices')
            ->where('invoice_type', 'tax')
            ->where('notes', 'like', 'Converted from %')
            ->orderBy('id')
            ->get(['id', 'notes']);

        foreach ($taxInvoices as $tax) {
            if (preg_match('/^Converted from (\S+)\./', (string) $tax->notes, $matches) !== 1) {
                continue;
            }

            $proforma = DB::table('sales_invoices')
                ->where('invoice_number', $matches[1])
                ->where('invoice_type', 'proforma')
                ->first(['id']);

            if (! $proforma) {
                continue;
            }

            /* Someone else already claimed it (or the office linked it by hand). */
            if (DB::table('sales_invoices')->where('id', $proforma->id)->value('converted_invoice_id')) {
                continue;
            }

            DB::table('sales_invoices')->where('id', $proforma->id)
                ->update(['converted_invoice_id' => $tax->id]);

            $this->moveAdvance((int) $proforma->id, (int) $tax->id);
        }
    }

    /** One advance, one document: the money follows the link. */
    private function moveAdvance(int $proformaId, int $taxId): void
    {
        $opening = (float) DB::table('sales_invoices')->where('id', $proformaId)->value('amount_paid');

        if ($opening > 0) {
            DB::table('sales_invoices')->where('id', $taxId)->increment('amount_paid', $opening);
            DB::table('sales_invoices')->where('id', $proformaId)->update(['amount_paid' => 0]);
        }

        if (Schema::hasTable('cashflow_entries')) {
            DB::table('cashflow_entries')->where('sales_invoice_id', $proformaId)
                ->update(['sales_invoice_id' => $taxId]);
        }

        $this->refreshBalance($proformaId);
        $this->refreshBalance($taxId);
    }

    /** The stored balance the office reads, asked the way the model asks it. */
    private function refreshBalance(int $invoiceId): void
    {
        $invoice = DB::table('sales_invoices')->where('id', $invoiceId)->first(['total_amount', 'amount_paid']);

        if (! $invoice) {
            return;
        }

        $received = 0.0;

        if (Schema::hasTable('cashflow_entries')) {
            $received = (float) (DB::table('cashflow_entries')->where('sales_invoice_id', $invoiceId)
                ->selectRaw('coalesce(sum(credit_amount - debit_amount), 0) as received')
                ->first()?->received ?? 0);
        }

        DB::table('sales_invoices')->where('id', $invoiceId)->update([
            'balance_amount' => round(max(
                (float) $invoice->total_amount - ((float) $invoice->amount_paid + $received),
                0
            ), 2),
        ]);
    }
};
