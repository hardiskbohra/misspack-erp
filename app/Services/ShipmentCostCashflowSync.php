<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Shipment;
use App\Models\ShipmentCost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps one INR cashflow entry in step with a shipment cost head that has
 * been paid from a bank/cash account.
 *
 * Same rule as vendor payments: the module that owns the money is the source
 * of truth (here the cost head), the cashflow row is a managed mirror. A head
 * with no account yet is simply an unpaid cost — nothing is written to the
 * cashflow ledger, which is exactly what "not paid yet" should mean.
 */
class ShipmentCostCashflowSync
{
    public function available(): bool
    {
        return class_exists(CashflowEntry::class)
            && class_exists(ShipmentCost::class)
            && Schema::hasTable('shipment_costs')
            && Schema::hasColumn('shipment_costs', 'cashflow_entry_id')
            && CashflowLedger::available();
    }

    /**
     * Whether this head should have an INR cashflow mirror: money actually
     * went out of an account and we know how much it was in INR.
     */
    public function mirrorable(ShipmentCost $cost): bool
    {
        return $this->available()
            && (int) $cost->paid_account_id > 0
            && (float) $cost->amount_in_inr > 0;
    }

    public function sync(ShipmentCost $cost, bool $requested = true): ?int
    {
        if (! $this->available()) {
            return null;
        }

        $mirror = $cost->cashflow_entry_id ? CashflowEntry::find($cost->cashflow_entry_id) : null;

        if (! $requested || ! $this->mirrorable($cost)) {
            if ($mirror) {
                $this->deleteMirror($mirror);
            }

            $this->setLink($cost, null);

            return null;
        }

        $shipment = $cost->relationLoaded('shipment') ? $cost->shipment : Shipment::find($cost->shipment_id);
        $isNew = ! $mirror;
        $mirror = $mirror ?: new CashflowEntry();

        $mirror->entry_date = $cost->paid_on ?: ($cost->incurred_on ?: now()->toDateString());
        $mirror->particular = trim(($shipment ? $shipment->shipment_number.' — ' : '').$cost->headLabel());
        $mirror->invoice_bill_number = $cost->document_number;
        $mirror->transaction_type = 'debit'; // money paid out
        $mirror->credit_amount = 0;
        $mirror->debit_amount = $cost->amount_in_inr;
        $mirror->currency = 'INR';
        $mirror->account_id = $cost->paid_account_id;
        $mirror->payment_mode = CashflowEntry::normalisePaymentMode($cost->payment_mode) ?: 'neft';
        $mirror->vendor_id = $cost->vendor_id;
        $mirror->client_id = $shipment->client_id ?? null;
        $mirror->expense_head = $cost->cost_head;
        $mirror->related_party_type = $cost->vendor_id ? 'vendor' : 'other';
        $mirror->related_party_name = $cost->vendor->vendor_name ?? null;
        $mirror->notes = $this->mirrorNotes($cost, $shipment);
        $mirror->created_by = $mirror->created_by ?: ($cost->created_by ?: Auth::id());

        if ($isNew) {
            // Workflow fields are only set on create, so reconciliation done in
            // the cashflow module survives later edits of the cost head.
            $mirror->accounting_status = 'booked';
            $mirror->category_id = null;
        }

        if ($shipment && Schema::hasColumn('cashflow_entries', 'project_id')) {
            $mirror->project_id = $shipment->project_id;
        }

        if ($shipment && Schema::hasColumn('cashflow_entries', 'sales_invoice_id')) {
            $mirror->sales_invoice_id = $shipment->sales_invoice_id;
        }

        $mirror->save();

        if ($isNew) {
            $this->setLink($cost, (int) $mirror->id);
        }

        CashflowLedger::recalculateAccount((int) $mirror->account_id);

        return (int) $mirror->id;
    }

    /**
     * Remove the mirror when a cost head is deleted.
     */
    public function remove(ShipmentCost $cost): void
    {
        if (! $this->available()) {
            return;
        }

        $mirror = $cost->cashflow_entry_id ? CashflowEntry::find($cost->cashflow_entry_id) : null;

        if ($mirror) {
            $this->deleteMirror($mirror);
        }

        $this->setLink($cost, null);
    }

    /**
     * Break the link when the cashflow entry itself is deleted from the
     * cashflow module, so the shipment screen never points at a missing row.
     */
    public function detach(CashflowEntry $cashflow): int
    {
        if (! $this->available()) {
            return 0;
        }

        return ShipmentCost::query()
            ->where('cashflow_entry_id', $cashflow->id)
            ->update(['cashflow_entry_id' => null]);
    }

    /**
     * Batch variant for list screens: [cashflow_id => shipment_cost_id].
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

        return ShipmentCost::query()
            ->whereIn('cashflow_entry_id', $ids->all())
            ->pluck('id', 'cashflow_entry_id')
            ->all();
    }

    /**
     * Batch variant for list screens: [cashflow_id => shipment_id], so the
     * cashflow list can link straight to the shipment.
     */
    public function linkedShipmentMapFor(iterable $cashflowIds): array
    {
        if (! $this->available()) {
            return [];
        }

        $ids = collect($cashflowIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return ShipmentCost::query()
            ->whereIn('cashflow_entry_id', $ids->all())
            ->pluck('shipment_id', 'cashflow_entry_id')
            ->all();
    }

    /**
     * The cost head behind a cashflow entry, for the cashflow detail screens.
     */
    public function linkedCostFor(CashflowEntry $cashflow): ?ShipmentCost
    {
        if (! $this->available()) {
            return null;
        }

        return ShipmentCost::query()
            ->with('shipment')
            ->where('cashflow_entry_id', $cashflow->id)
            ->first();
    }

    private function deleteMirror(CashflowEntry $mirror): void
    {
        $accountId = (int) $mirror->account_id;

        $mirror->delete();

        CashflowLedger::recalculateAccount($accountId);
    }

    private function setLink(ShipmentCost $cost, ?int $cashflowId): void
    {
        ShipmentCost::whereKey($cost->id)->update(['cashflow_entry_id' => $cashflowId]);
        $cost->cashflow_entry_id = $cashflowId;
    }

    private function mirrorNotes(ShipmentCost $cost, ?Shipment $shipment): string
    {
        $lines = [];

        if (trim((string) $cost->notes) !== '') {
            $lines[] = trim((string) $cost->notes);
        }

        $lines[] = 'Auto-synced from shipment cost #'.$cost->id
            .($shipment ? ' ('.$shipment->shipment_number.')' : '')
            .' — '.$cost->headLabel().': '.$cost->amountLabel()
            .($cost->exchange_rate ? ' @ '.rtrim(rtrim(number_format((float) $cost->exchange_rate, 6), '0'), '.') : '');

        return implode("\n", $lines);
    }
}
