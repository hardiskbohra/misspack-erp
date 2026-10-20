<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The other half of the vendor-payment mirror.
 *
 * `VendorPaymentCashflowSync` starts in the vendor ledger and writes a cashflow
 * row. This starts in the cashflow module and writes the vendor ledger row, so
 * a payment recorded against a vendor on the cashflow screen is not a payment
 * the vendor record has never heard of. Both screens read
 * `vendor_payment_entries`, which is what makes the vendor's Money tab, the
 * payables ageing and the statement agree.
 *
 * What is linked
 * --------------
 * A **debit in rupees with a vendor chosen** — money that left the bank for
 * that supplier. Credits (money received back) and foreign-currency rows are
 * not turned into ledger rows: the vendor ledger carries the rupees actually
 * paid, and a foreign amount has no rupee value on the cashflow row to carry.
 * Those still appear on the vendor page and the statement through
 * `unlinkedCashflow()` below, so nothing is invisible.
 *
 * Provenance
 * ----------
 * A linked row is written with `source = cashflow`, so the vendor module knows
 * the cashflow screen owns it: it is shown, not edited or deleted, there. A row
 * entered in the vendor ledger keeps its own provenance and is never touched by
 * this service — that payment's cashflow mirror is written by the other one.
 */
class CashflowVendorPaymentSync
{
    /** The ledger row was derived from a cashflow entry. */
    public const SOURCE = 'cashflow';

    /** Payment modes the vendor ledger understands, in the cashflow vocabulary. */
    private const MODE_MAP = [
        'neft' => 'bank_transfer',
        'rtgs' => 'bank_transfer',
        'imps' => 'bank_transfer',
        'upi' => 'upi',
        'cash' => 'cash',
        'cheque' => 'cheque',
        'card' => 'card',
        'adjustment' => 'adjustment',
        'other' => 'other',
    ];

    public function available(): bool
    {
        return Schema::hasTable('vendor_payment_entries')
            && Schema::hasTable('cashflow_entries')
            && Schema::hasColumn('vendor_payment_entries', 'cashflow_entry_id')
            && Schema::hasColumn('vendor_payment_entries', 'source');
    }

    /**
     * Create, refresh or remove the ledger row behind one cashflow entry.
     *
     * Returns the ledger row when one exists afterwards, or null when the
     * entry is not a rupee payment to a linked vendor.
     */
    public function sync(CashflowEntry $entry): ?VendorPaymentEntry
    {
        if (! $this->available()) {
            return null;
        }

        $linked = VendorPaymentEntry::query()
            ->where('cashflow_entry_id', $entry->id)
            ->first();

        /* A ledger row that was entered by hand is the source of truth; the
           cashflow row beside it is that payment's mirror. Leave it alone. */
        if ($linked && $linked->source !== self::SOURCE) {
            return $linked;
        }

        if (! $this->shouldLink($entry)) {
            $linked?->delete();

            return null;
        }

        $row = $linked ?: new VendorPaymentEntry();
        $amount = round((float) $entry->amountMoved(), 2);

        $row->fill([
            'vendor_id' => (int) $entry->vendor_id,
            'project_id' => $entry->project_id ? (int) $entry->project_id : null,
            'purchase_invoice_id' => null,
            'cashflow_entry_id' => (int) $entry->id,
            'transaction_date' => $entry->entry_date ?: now()->toDateString(),
            'invoice_number' => $entry->invoice_bill_number ?: null,
            'foreign_currency' => 'INR',
            'foreign_amount' => $amount,
            'exchange_rate' => 1,
            'transaction_type' => 'debit',
            'entry_category' => 'payment',
            'particular' => filled($entry->particular) ? (string) $entry->particular : 'Paid to vendor',
            'status' => 'paid',
            'paid_account_id' => $entry->account_id ? (int) $entry->account_id : null,
            'payment_mode' => $this->paymentMode($entry),
            'bank_reference_number' => $entry->bank_reference_number ?: null,
            'amount_in_inr' => $amount,
            'remarks' => 'Recorded in the cashflow ledger as entry #'.$entry->id.'.',
            'created_by' => $row->created_by ?: ($entry->created_by ?: auth()->id()),
            'source' => self::SOURCE,
        ]);

        $row->save();

        return $row;
    }

    /**
     * Remove the derived ledger row when the cashflow entry is deleted.
     *
     * A ledger row entered by hand is only unlinked, exactly as the reverse
     * mirror leaves it: the payment still stands, it just lost its cashflow.
     */
    public function remove(CashflowEntry $entry): void
    {
        if (! $this->available()) {
            return;
        }

        VendorPaymentEntry::query()
            ->where('cashflow_entry_id', $entry->id)
            ->where('source', self::SOURCE)
            ->delete();
    }

    /** Whether this cashflow row is money paid to a vendor we can link. */
    private function shouldLink(CashflowEntry $entry): bool
    {
        return (int) $entry->vendor_id > 0
            && $entry->transaction_type === 'debit'
            && strtoupper((string) ($entry->currency ?: 'INR')) === 'INR'
            && (float) $entry->amountMoved() > 0;
    }

    /**
     * Cashflow rows for a vendor that no ledger row stands behind.
     *
     * These are the rows the ledger cannot speak for — a name-matched vendor, a
     * foreign-currency payment, money received back — and the vendor page and
     * statement list them so a payment is never invisible. A mirrored row is
     * excluded: the ledger already shows it, and printing both would be the
     * same payment twice.
     */
    public function unlinkedCashflow(Vendor $vendor): Collection
    {
        if (! Schema::hasTable('cashflow_entries')) {
            return collect();
        }

        $query = CashflowEntry::query()
            ->with(['account'])
            ->where(function ($query) use ($vendor) {
                $query->where('vendor_id', $vendor->id);

                if (Schema::hasColumn('cashflow_entries', 'related_party_type')
                    && Schema::hasColumn('cashflow_entries', 'related_party_name')) {
                    $query->orWhere(function ($nested) use ($vendor) {
                        $nested->where('related_party_type', 'vendor')
                            ->where('related_party_name', 'like', '%'.$vendor->vendor_name.'%');
                    });
                }
            });

        if (Schema::hasTable('vendor_payment_entries')
            && Schema::hasColumn('vendor_payment_entries', 'cashflow_entry_id')) {
            /* `not exists`, not a plucked list: the answer is one query whether
               the ledger holds ten rows or ten thousand. */
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('vendor_payment_entries')
                    ->whereColumn('vendor_payment_entries.cashflow_entry_id', 'cashflow_entries.id');
            });
        }

        return $query->orderByDesc('entry_date')->orderByDesc('id')->get();
    }

    private function paymentMode(CashflowEntry $entry): string
    {
        $mode = strtolower((string) $entry->payment_mode);

        return self::MODE_MAP[$mode] ?? ($entry->account_id ? 'bank_transfer' : 'other');
    }
}
