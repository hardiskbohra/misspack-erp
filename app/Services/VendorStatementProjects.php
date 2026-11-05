<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Vendor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Resolve only recorded project links for the rows of one vendor statement. */
class VendorStatementProjects
{
    /**
     * @return array{ledger: array<int, list<string>>, cashflow: array<int, list<string>>}
     */
    public function forRows(Vendor $vendor, Collection $ledger, Collection $unlinkedCashflow): array
    {
        $empty = ['ledger' => [], 'cashflow' => []];
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'name')) {
            return $empty;
        }

        $cashflowIds = $ledger->pluck('cashflow_entry_id')->concat($unlinkedCashflow->pluck('id'))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $cashflows = $cashflowIds && Schema::hasTable('cashflow_entries')
            ? CashflowEntry::query()->whereIn('id', $cashflowIds)->get()
                ->filter(fn (CashflowEntry $entry) => $this->belongsToVendor($entry, $vendor))->keyBy('id')
            : collect();

        $allocations = $cashflows->isNotEmpty() && Schema::hasTable('cashflow_allocations')
            && Schema::hasColumn('cashflow_allocations', 'cashflow_entry_id')
            ? DB::table('cashflow_allocations')->whereIn('cashflow_entry_id', $cashflows->keys()->all())->orderBy('id')->get()
            : collect();
        // Partial PO conversions can replace the payment's direct document
        // link with several purchase-payment splits, without splitting money.
        $splits = $ledger->isNotEmpty() && Schema::hasTable('purchase_payment_splits')
            && Schema::hasColumn('purchase_payment_splits', 'vendor_payment_entry_id')
            ? DB::table('purchase_payment_splits')->whereIn('vendor_payment_entry_id', $ledger->pluck('id')->all())->orderBy('id')->get()
            : collect();
        $purchaseIds = $ledger->pluck('purchase_invoice_id')
            ->concat($allocations->pluck('purchase_invoice_id'))->concat($splits->pluck('purchase_invoice_id'))
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        // A bad/stale cross-vendor document link must never disclose its project.
        $purchases = $purchaseIds && Schema::hasTable('purchase_invoices')
            && Schema::hasColumn('purchase_invoices', 'vendor_id') && Schema::hasColumn('purchase_invoices', 'project_id')
            ? DB::table('purchase_invoices')->where('vendor_id', $vendor->id)->whereIn('id', $purchaseIds)->pluck('project_id', 'id')
            : collect();

        $cashflowProjects = [];
        foreach ($cashflows as $cashflow) {
            $cashflowProjects[(int) $cashflow->id] = [(int) ($cashflow->project_id ?? 0)];
        }
        foreach ($allocations as $allocation) {
            // A sales document is not evidence of this vendor's purchase.
            if ((int) ($allocation->sales_invoice_id ?? 0) > 0) {
                continue;
            }
            $purchaseId = (int) ($allocation->purchase_invoice_id ?? 0);
            if ($purchaseId && ! $purchases->has($purchaseId)) {
                continue;
            }
            $documentProject = $purchaseId ? (int) $purchases->get($purchaseId) : 0;
            $projectId = (int) ($allocation->project_id ?? 0);
            // The allocation editor disallows this mismatch; don't guess for
            // a stale or corrupted historical row either.
            if ($projectId && $documentProject && $projectId !== $documentProject) {
                continue;
            }
            $cashflowProjects[(int) $allocation->cashflow_entry_id][] = $projectId ?: $documentProject;
        }

        $splitProjects = [];
        foreach ($splits as $split) {
            $purchaseId = (int) ($split->purchase_invoice_id ?? 0);
            if ($purchaseId && $purchases->has($purchaseId)) {
                $splitProjects[(int) $split->vendor_payment_entry_id][] = (int) $purchases->get($purchaseId);
            }
        }
        $ledgerProjects = [];
        foreach ($ledger as $entry) {
            $purchaseId = (int) ($entry->purchase_invoice_id ?? 0);
            $cashflowId = (int) ($entry->cashflow_entry_id ?? 0);
            $ledgerProjects[(int) $entry->id] = array_merge(
                [(int) ($entry->project_id ?? 0), $purchaseId ? (int) $purchases->get($purchaseId) : 0],
                $splitProjects[(int) $entry->id] ?? [],
                $cashflowProjects[$cashflowId] ?? []
            );
        }

        $allIds = collect($ledgerProjects)->concat(array_values($cashflowProjects))->flatten()
            ->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        if (! $allIds) {
            return $empty;
        }
        // Query the table directly so soft-deleted projects remain identifiable
        // on historical statements. This is one lookup for the whole document.
        $names = DB::table('projects')->whereIn('id', $allIds)->pluck('name', 'id');
        $resolve = static function (array $ids) use ($names): array {
            $result = [];
            foreach (array_unique(array_filter($ids)) as $id) {
                $name = trim((string) $names->get($id, ''));
                if ($name !== '') {
                    $result[] = $name;
                }
            }
            return $result;
        };

        return [
            'ledger' => collect($ledgerProjects)->map($resolve)->all(),
            'cashflow' => collect($cashflowProjects)->map($resolve)->all(),
        ];
    }

    /** A name-only legacy link is safe only when it names this vendor exactly. */
    private function belongsToVendor(CashflowEntry $entry, Vendor $vendor): bool
    {
        $owner = (int) ($entry->vendor_id ?? 0);
        if ($owner > 0) {
            return $owner === (int) $vendor->id;
        }
        $vendorName = Str::lower(trim((string) $vendor->vendor_name));
        return $vendorName !== ''
            && Str::lower(trim((string) ($entry->related_party_type ?? ''))) === 'vendor'
            && Str::lower(trim((string) ($entry->related_party_name ?? ''))) === $vendorName;
    }
}
