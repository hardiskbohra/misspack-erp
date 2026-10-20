<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A vendor payment recorded in the cashflow module now has its vendor-ledger
 * row derived from it (`CashflowVendorPaymentSync`). The column marks which
 * side of the mirror owns the row; the backfill gives the payments already in
 * the cashflow ledger the row they were missing, so they appear on the vendor
 * record and the statement without anyone re-entering them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vendor_payment_entries') || ! Schema::hasTable('cashflow_entries')) {
            return;
        }

        if (! Schema::hasColumn('vendor_payment_entries', 'source')) {
            Schema::table('vendor_payment_entries', function (Blueprint $table) {
                $table->string('source', 20)->nullable()->after('cashflow_entry_id');
            });
        }

        $hasVendor = Schema::hasColumn('cashflow_entries', 'vendor_id');
        if (! $hasVendor) {
            return;
        }

        DB::table('cashflow_entries')
            ->whereNotNull('vendor_id')
            ->where('transaction_type', 'debit')
            ->where(function ($query) {
                $query->whereNull('currency')->orWhere('currency', 'INR');
            })
            ->where('debit_amount', '>', 0)
            /* A cashflow row that already has a ledger row keeps it: the ledger
               entered first owns that row, and the backfill must not make a
               second one. A subquery, not a plucked list, so a long ledger does
               not become a bound parameter per row. */
            ->whereNotIn('id', function ($query) {
                $query->select('cashflow_entry_id')
                    ->from('vendor_payment_entries')
                    ->whereNotNull('cashflow_entry_id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($entries) {
                foreach ($entries as $entry) {
                    $amount = round((float) $entry->debit_amount, 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    DB::table('vendor_payment_entries')->insert([
                        'vendor_id' => (int) $entry->vendor_id,
                        'project_id' => $entry->project_id ? (int) $entry->project_id : null,
                        'purchase_invoice_id' => null,
                        'cashflow_entry_id' => (int) $entry->id,
                        'source' => 'cashflow',
                        'transaction_date' => $entry->entry_date ?: now()->toDateString(),
                        'invoice_number' => $entry->invoice_bill_number ?: null,
                        'foreign_currency' => 'INR',
                        'foreign_amount' => $amount,
                        'exchange_rate' => 1,
                        'transaction_type' => 'debit',
                        'entry_category' => 'payment',
                        'particular' => filled($entry->particular) ? $entry->particular : 'Paid to vendor',
                        'status' => 'paid',
                        'paid_account_id' => $entry->account_id ? (int) $entry->account_id : null,
                        'payment_mode' => $this->paymentMode($entry->payment_mode, $entry->account_id),
                        'bank_reference_number' => $entry->bank_reference_number ?: null,
                        'amount_in_inr' => $amount,
                        'remarks' => 'Recorded in the cashflow ledger as entry #'.$entry->id.'.',
                        'created_by' => $entry->created_by ?: null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /** The cashflow vocabulary in the vendor ledger's words. */
    private function paymentMode(?string $mode, $accountId): ?string
    {
        $mode = strtolower((string) $mode);

        if ($mode === '') {
            return $accountId ? 'bank_transfer' : null;
        }

        return [
            'neft' => 'bank_transfer',
            'rtgs' => 'bank_transfer',
            'imps' => 'bank_transfer',
            'upi' => 'upi',
            'cash' => 'cash',
            'cheque' => 'cheque',
            'card' => 'card',
            'adjustment' => 'adjustment',
            'other' => 'other',
        ][$mode] ?? ($accountId ? 'bank_transfer' : 'other');
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_payment_entries')) {
            return;
        }

        if (Schema::hasColumn('vendor_payment_entries', 'source')) {
            DB::table('vendor_payment_entries')->where('source', 'cashflow')->delete();

            Schema::table('vendor_payment_entries', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
