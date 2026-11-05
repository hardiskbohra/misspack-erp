<?php

namespace App\Services;

use App\Models\CashflowEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Cash already moved for a party but not yet assigned to documents/projects.
 *
 * These are not receivables or payables. They are real advances sitting in the
 * ledger, so party and project records must surface them until allocation uses
 * the remainder.
 */
class UnallocatedCashflows
{
    /** Client receipts with cash still available to allocate. */
    public function forClient(int $clientId): array
    {
        if (! $this->available()) return $this->emptySnapshot();

        $entries = $this->baseQuery()
            ->where('transaction_type', 'credit')
            /* Legacy scalar links represent the whole entry as already applied. */
            ->whereNull('sales_invoice_id')
            ->whereNull('project_id')
            ->where(function ($query) use ($clientId) {
                $query->where('client_id', $clientId)
                    ->orWhereHas('allocations.salesInvoice', fn ($invoice) => $invoice->where('client_id', $clientId));
            })
            ->get()
            ->filter(function (CashflowEntry $entry) use ($clientId) {
                if ($entry->client_id) return (int) $entry->client_id === $clientId;

                $clients = $entry->allocations->pluck('salesInvoice.client_id')->filter()->unique()->values();

                return $clients->count() === 1 && (int) $clients->first() === $clientId;
            });

        return $this->snapshot($entries);
    }

    /** Vendor payments with cash still available to allocate. */
    public function forVendor(int $vendorId): array
    {
        if (! $this->available()) return $this->emptySnapshot();

        $query = $this->baseQuery()
            ->where('transaction_type', 'debit')
            ->where(function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId)
                    ->orWhereHas('allocations.purchaseInvoice', fn ($invoice) => $invoice->where('vendor_id', $vendorId));
            });

        /* A mirrored vendor-ledger row linked to a purchase document is already
           applied even when it predates cashflow_allocations. */
        if (Schema::hasTable('vendor_payment_entries')
            && Schema::hasColumn('vendor_payment_entries', 'cashflow_entry_id')
            && Schema::hasColumn('vendor_payment_entries', 'purchase_invoice_id')) {
            $query->whereNotIn('cashflow_entries.id', \App\Models\VendorPaymentEntry::query()
                ->select('cashflow_entry_id')
                ->whereNotNull('cashflow_entry_id')
                ->where(function ($payment) {
                    $payment->whereNotNull('purchase_invoice_id');
                    if (Schema::hasTable('purchase_payment_splits')) {
                        $payment->orWhereHas('purchaseSplits');
                    }
                }));
        }

        $entries = $query->get()
            ->filter(function (CashflowEntry $entry) use ($vendorId) {
                if ($entry->vendor_id) return (int) $entry->vendor_id === $vendorId;

                $vendors = $entry->allocations->pluck('purchaseInvoice.vendor_id')->filter()->unique()->values();

                return $vendors->count() === 1 && (int) $vendors->first() === $vendorId;
            });

        return $this->snapshot($entries);
    }

    /**
     * A remainder belongs on a project only when every allocated slice points
     * to that one project. For a multi-project cashflow the remainder is not yet
     * any project's money and remains visible on the client/vendor instead.
     */
    public function forProject(int $projectId): array
    {
        if (! $this->available()) return $this->emptySnapshot();

        $entries = $this->baseQuery()
            ->whereHas('allocations', fn ($query) => $query->where('project_id', $projectId))
            ->get()
            ->filter(function (CashflowEntry $entry) use ($projectId) {
                $projects = $entry->allocations->pluck('project_id')->filter()->unique()->values();

                return $projects->count() === 1
                    && (int) $projects->first() === $projectId;
            });

        return $this->snapshot($entries);
    }

    private function baseQuery()
    {
        return CashflowEntry::query()
            ->with(['allocations.salesInvoice', 'allocations.purchaseInvoice'])
            ->latest('entry_date')
            ->latest('id');
    }

    private function available(): bool
    {
        return Schema::hasTable('cashflow_entries') && Schema::hasTable('cashflow_allocations');
    }

    private function snapshot(Collection $entries): array
    {
        $entries = $entries
            ->filter(fn (CashflowEntry $entry) => $entry->unallocatedAmount() >= 0.001)
            ->values();

        $totals = $entries
            ->groupBy(fn (CashflowEntry $entry) => strtoupper((string) ($entry->currency ?: 'INR')))
            ->map(fn (Collection $rows) => round((float) $rows->sum(
                fn (CashflowEntry $entry) => $entry->unallocatedAmount()
            ), 3));

        return [
            'entries' => $entries,
            'totals' => $totals,
            'count' => $entries->count(),
        ];
    }

    private function emptySnapshot(): array
    {
        return ['entries' => collect(), 'totals' => collect(), 'count' => 0];
    }
}
