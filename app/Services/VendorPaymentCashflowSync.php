<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps one INR cashflow entry in step with a vendor payment entry.
 *
 * Why this exists
 * ---------------
 * A payment to a vendor lives in two currencies at once: the vendor ledger
 * (RMB / USD, at the agreed rate) and the INR cashflow it actually moves out
 * of a bank account. Asking the user to type the same payment twice is slow
 * and drifts immediately — edit the vendor amount and the cashflow copy is
 * stale, delete one and the other survives.
 *
 * Rule: the vendor payment entry is the source of truth. Its cashflow entry
 * is a mirror, created/updated/removed by this service, so a single entry
 * keeps every screen correct.
 *
 * Only "debit" entries (money actually paid to the vendor) produce a mirror.
 * A "credit" entry is a bill we received — it creates a payable, not a cash
 * movement, so nothing is written to the cashflow ledger for it.
 */
class VendorPaymentCashflowSync
{
    /**
     * Vendor payment modes mapped onto the cashflow module's vocabulary.
     * A null value means there is no matching cashflow mode (adjustments are
     * book entries, not bank movements).
     */
    private const PAYMENT_MODE_MAP = [
        'bank_transfer' => 'neft',
        'wire' => 'rtgs',
        'cash' => 'cash',
        'upi' => 'upi',
        'cheque' => 'cheque',
        'card' => 'card',
        'other' => 'other',
        'adjustment' => null,
    ];

    /**
     * Whether the vendor ledger and cashflow module are both available.
     */
    public function available(): bool
    {
        return class_exists(CashflowEntry::class)
            && class_exists(VendorPaymentEntry::class)
            && Schema::hasTable('vendor_payment_entries')
            && Schema::hasColumn('vendor_payment_entries', 'cashflow_entry_id')
            && CashflowLedger::available();
    }

    /**
     * Whether this entry should have an INR cashflow mirror: an actual
     * payment (debit), not cancelled, with an INR value and a bank account.
     */
    public function mirrorable(VendorPaymentEntry $entry): bool
    {
        return $this->available()
            && $entry->transaction_type === 'debit'
            && (string) $entry->status !== 'cancelled'
            && (float) $entry->amount_in_inr > 0
            && (int) $entry->paid_account_id > 0;
    }

    /**
     * Create or refresh the mirror for a vendor payment entry.
     *
     * Returns the cashflow entry id when a mirror exists afterwards, or null
     * when the entry does not need one (in which case any previous mirror is
     * removed and the vendor entry's link cleared).
     */
    public function sync(VendorPaymentEntry $entry, bool $requested = true): ?int
    {
        if (! $this->available()) {
            return null;
        }

        $mirror = $entry->cashflow_entry_id
            ? CashflowEntry::find($entry->cashflow_entry_id)
            : null;

        if (! $requested || ! $this->mirrorable($entry)) {
            if ($mirror) {
                $this->deleteMirror($mirror);
            }

            $this->setLink($entry, null);

            return null;
        }

        $vendor = $entry->relationLoaded('vendor') ? $entry->vendor : Vendor::find($entry->vendor_id);
        $isNew = ! $mirror;
        $mirror = $mirror ?: new CashflowEntry();

        $mirror->entry_date = $entry->transaction_date;
        $mirror->particular = $entry->particular;
        $mirror->invoice_bill_number = $entry->invoice_number;
        $mirror->bank_reference_number = $entry->bank_reference_number;
        $mirror->transaction_type = 'debit'; // money paid out
        $mirror->credit_amount = 0;
        $mirror->debit_amount = $entry->amount_in_inr;
        $mirror->balance = $mirror->balance ?? null;
        $mirror->currency = 'INR';
        $mirror->account_id = $entry->paid_account_id;
        $mirror->payment_mode = self::PAYMENT_MODE_MAP[$entry->payment_mode] ?? null;
        $mirror->vendor_id = $entry->vendor_id;
        $mirror->client_id = null;
        $mirror->expense_head = $entry->entry_category;
        $mirror->related_party_type = 'vendor';
        $mirror->related_party_name = $vendor?->vendor_name ?: null;
        $mirror->notes = $this->mirrorNotes($entry, $vendor);
        $mirror->created_by = $mirror->created_by ?: ($entry->created_by ?: Auth::id());

        if ($isNew) {
            // Only set the workflow status on create so that work done in the
            // cashflow module (reconciled / ignored) is never clobbered by a
            // later edit of the vendor payment.
            $mirror->accounting_status = in_array($entry->status, ['paid', 'reconciled'], true) ? 'booked' : 'pending';
            $mirror->category_id = null;
        }

        if (Schema::hasColumn('cashflow_entries', 'project_id')) {
            $mirror->project_id = $entry->project_id;
        }

        $mirror->save();

        if ($isNew) {
            $this->setLink($entry, (int) $mirror->id);
        }

        CashflowLedger::recalculateAccount((int) $mirror->account_id);

        return (int) $mirror->id;
    }

    /**
     * Remove the mirror for a vendor payment that is being deleted, so the
     * cashflow ledger cannot keep an orphan entry.
     */
    public function remove(VendorPaymentEntry $entry): void
    {
        if (! $this->available()) {
            return;
        }

        $mirror = $entry->cashflow_entry_id ? CashflowEntry::find($entry->cashflow_entry_id) : null;

        if ($mirror) {
            $this->deleteMirror($mirror);
        }

        $this->setLink($entry, null);
    }

    /**
     * Break the link when a cashflow entry is deleted from the cashflow
     * module itself, so the vendor screen never points at a missing entry.
     */
    public function detach(CashflowEntry $cashflow): int
    {
        if (! $this->available()) {
            return 0;
        }

        return VendorPaymentEntry::query()
            ->where('cashflow_entry_id', $cashflow->id)
            ->update(['cashflow_entry_id' => null]);
    }

    /**
     * The vendor payment entry that mirrors a cashflow entry, if any.
     */
    public function linkedPaymentFor(CashflowEntry $cashflow): ?VendorPaymentEntry
    {
        if (! $this->available()) {
            return null;
        }

        return VendorPaymentEntry::query()
            ->with('vendor')
            ->where('cashflow_entry_id', $cashflow->id)
            ->first();
    }

    /**
     * Batch variant for list screens: [cashflow_id => vendor_payment_id].
     * One query per page instead of one per row.
     */
    public function linkedMapFor(iterable $cashflowIds): array
    {
        if (! $this->available()) {
            return [];
        }

        $ids = collect($cashflowIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return VendorPaymentEntry::query()
            ->whereIn('cashflow_entry_id', $ids->all())
            ->pluck('id', 'cashflow_entry_id')
            ->all();
    }

    private function deleteMirror(CashflowEntry $mirror): void
    {
        $accountId = (int) $mirror->account_id;

        $mirror->delete();

        CashflowLedger::recalculateAccount($accountId);
    }

    private function setLink(VendorPaymentEntry $entry, ?int $cashflowId): void
    {
        VendorPaymentEntry::whereKey($entry->id)->update(['cashflow_entry_id' => $cashflowId]);
        $entry->cashflow_entry_id = $cashflowId;
    }

    private function mirrorNotes(VendorPaymentEntry $entry, ?Vendor $vendor): string
    {
        $lines = [];

        if (trim((string) $entry->remarks) !== '') {
            $lines[] = trim((string) $entry->remarks);
        }

        $lines[] = 'Auto-synced from vendor payment #'.$entry->id
            .($vendor ? ' ('.$vendor->vendor_name.')' : '')
            .' — vendor currency: '.$entry->foreign_currency.' '.number_format((float) $entry->foreign_amount, 4)
            .($entry->exchange_rate ? ' @ '.rtrim(rtrim(number_format((float) $entry->exchange_rate, 6), '0'), '.') : '');

        return implode("\n", $lines);
    }
}
