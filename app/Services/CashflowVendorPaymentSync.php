<?php

namespace App\Services;

use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
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
 * Which currency the ledger row is in
 * ------------------------------------
 * The bank moved rupees; the vendor was invoiced in their own currency. When
 * the entry carries a purchase-bill allocation in a foreign currency, that
 * allocation's **document amount** is the vendor-currency figure the office
 * typed, and it is the one the vendor ledger must show — a ¥8,000 bill settled
 * by a ₹10,000 payment is a ¥8,000 row at the rate the two imply, not a rupee
 * row dropped into a yen ledger. Without such an allocation there is no rate to
 * convert at, so the row stays in rupees rather than inventing one.
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
        $amount = round((float) $entry->amountMoved(), 3);
        $documented = $this->documentDenomination($entry);

        /* `exchange_rate` is rupees per unit of the ledger currency, which is
           the arithmetic the whole vendor ledger already uses. */
        $rate = $documented ? round($amount / $documented['amount'], 6) : 1;

        $row->fill([
            'vendor_id' => (int) $entry->vendor_id,
            'project_id' => $entry->project_id ? (int) $entry->project_id : null,
            'purchase_invoice_id' => null,
            'cashflow_entry_id' => (int) $entry->id,
            'transaction_date' => $entry->entry_date ?: now()->toDateString(),
            'invoice_number' => $entry->invoice_bill_number ?: ($documented['invoice'] ?? null),
            'foreign_currency' => $documented['currency'] ?? 'INR',
            'foreign_amount' => $documented['amount'] ?? $amount,
            'exchange_rate' => $rate,
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
     * Ledger-owned payments are handled by VendorPaymentCashflowSync::reverseForDeletion()
     * on explicit cashflow deletion. They are retained as cancelled history.
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

    /**
     * The vendor-currency figure the cashflow entry documents, if it documents
     * one.
     *
     * Only purchase-bill allocations count: a project slice has no currency of
     * its own. One currency across every bill is required — a payment split
     * between a yen and a dollar bill has no single honest denomination, and
     * converting either half would invent a rate nobody wrote down.
     *
     * @return array{currency: string, amount: float, invoice: string|null}|null
     */
    private function documentDenomination(CashflowEntry $entry): ?array
    {
        if (! Schema::hasTable('cashflow_allocations') || ! class_exists(CashflowAllocation::class)) {
            return null;
        }

        $allocations = CashflowAllocation::query()
            ->where('cashflow_entry_id', $entry->id)
            ->whereNotNull('purchase_invoice_id')
            ->whereNotNull('document_currency')
            ->get();

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
        ), 3);

        if ($amount <= 0) {
            return null;
        }

        return [
            'currency' => $currency,
            'amount' => $amount,
            'invoice' => $this->allocatedInvoiceNumber($allocations->pluck('purchase_invoice_id')->all()),
        ];
    }

    /** The document number the ledger row should show for an allocated bill. */
    private function allocatedInvoiceNumber(array $invoiceIds): ?string
    {
        $invoiceIds = array_values(array_filter($invoiceIds));

        if ($invoiceIds === [] || ! Schema::hasTable('purchase_invoices') || ! class_exists(PurchaseInvoice::class)) {
            return null;
        }

        $invoice = PurchaseInvoice::query()->whereKey($invoiceIds[0])->first();

        return $invoice ? $invoice->referenceNumber() : null;
    }

    private function paymentMode(CashflowEntry $entry): string
    {
        $mode = strtolower((string) $entry->payment_mode);

        return self::MODE_MAP[$mode] ?? ($entry->account_id ? 'bank_transfer' : 'other');
    }
}
