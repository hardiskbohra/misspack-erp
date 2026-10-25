<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cashflow payment to a foreign vendor was filed in the vendor ledger in
 * rupees, because that is what left the bank. The vendor's account, though, is
 * kept in their currency: a ¥8,000 bill settled by a ₹10,000 transfer is a
 * ¥8,000 row at ₹1.25, not a rupee row in a yen ledger.
 *
 * The vendor-currency figure is already on the cashflow entry — the purchase
 * bill allocation's **document amount**, the amount the office typed in the
 * bill's own currency. This migration re-denominates the ledger rows the
 * previous migration created, so existing payments read the way the vendor and
 * the statement read them. Rows with no allocated bill in a foreign currency
 * stay in rupees: there is no written-down rate to convert them at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payment_entries')
            || ! Schema::hasTable('cashflow_allocations')
            || ! Schema::hasColumn('vendor_payment_entries', 'source')) {
            return;
        }

        $this->eachDerivedRow(function (object $row, array $allocations): void {
            DB::table('vendor_payment_entries')->where('id', $row->id)->update([
                'foreign_currency' => $allocations['currency'],
                'foreign_amount' => $allocations['amount'],
                'exchange_rate' => round((float) $row->amount_in_inr / $allocations['amount'], 6),
                'invoice_number' => filled($row->invoice_number) ? $row->invoice_number : $allocations['invoice'],
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_payment_entries') || ! Schema::hasColumn('vendor_payment_entries', 'source')) {
            return;
        }

        /* The bank amount is still the truth in rupees; the bill number the row
           now shows is true either way, so it is left in place. */
        DB::table('vendor_payment_entries')
            ->where('source', 'cashflow')
            ->update([
                'foreign_currency' => 'INR',
                'foreign_amount' => DB::raw('amount_in_inr'),
                'exchange_rate' => 1,
                'updated_at' => now(),
            ]);
    }

    /** @param callable(object, array):void $apply */
    private function eachDerivedRow(callable $apply): void
    {
        DB::table('vendor_payment_entries')
            ->where('source', 'cashflow')
            ->whereNotNull('cashflow_entry_id')
            ->chunkById(200, function ($rows) use ($apply) {
                $entryIds = $rows->pluck('cashflow_entry_id')->filter()->unique()->all();

                $allocations = DB::table('cashflow_allocations')
                    ->whereIn('cashflow_entry_id', $entryIds)
                    ->whereNotNull('purchase_invoice_id')
                    ->whereNotNull('document_currency')
                    ->get()
                    ->groupBy('cashflow_entry_id');

                $invoiceIds = $allocations->flatten(1)->pluck('purchase_invoice_id')->filter()->unique()->all();

                $invoices = $invoiceIds === []
                    ? collect()
                    : DB::table('purchase_invoices')->whereIn('id', $invoiceIds)->get()->keyBy('id');

                foreach ($rows as $row) {
                    $documented = $this->denomination($allocations->get($row->cashflow_entry_id, collect()), $invoices);

                    if ($documented !== null) {
                        $apply($row, $documented);
                    }
                }
            });
    }

    /**
     * One currency across every allocated bill, in a currency that is not the
     * one the money moved in — otherwise there is no single honest figure.
     *
     * @return array{currency: string, amount: float, invoice: string|null}|null
     */
    private function denomination($allocations, $invoices): ?array
    {
        if ($allocations->isEmpty()) {
            return null;
        }

        $currencies = $allocations
            ->map(fn ($allocation) => strtoupper((string) $allocation->document_currency))
            ->unique();

        if ($currencies->count() !== 1) {
            return null;
        }

        $currency = (string) $currencies->first();

        if ($currency === '' || $currency === 'INR') {
            return null;
        }

        $amount = round((float) $allocations->sum(
            fn ($allocation) => (float) ($allocation->document_amount ?? $allocation->amount)
        ), 2);

        if ($amount <= 0) {
            return null;
        }

        $invoice = $invoices->get($allocations->first()->purchase_invoice_id);
        $invoiceNumber = $invoice ? (string) (($invoice->vendor_bill_number ?? null) ?: '') : '';
        if ($invoiceNumber === '' && $invoice) {
            $invoiceNumber = (string) ($invoice->invoice_number ?? '');
        }

        return [
            'currency' => $currency,
            'amount' => $amount,
            'invoice' => $invoiceNumber !== '' ? $invoiceNumber : null,
        ];
    }
};
