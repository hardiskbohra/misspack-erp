<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\Project;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A saved, all-time account position. Never adds different currencies together. */
class ClientAccountSummary
{
    public function forClient(Client $client): array
    {
        // Keep invoice sums and unapplied cash in one read snapshot, so an
        // allocation saved between queries cannot be counted twice.
        return DB::transaction(fn () => $this->snapshot($client));
    }

    private function snapshot(Client $client): array
    {
        $hasInvoices = Schema::hasTable('sales_invoices');
        $hasCash = Schema::hasTable('cashflow_entries');
        $hasProjects = Schema::hasTable('projects');
        $hasAllocations = $hasCash && Schema::hasTable('cashflow_allocations');
        $rows = [];
        $invoices = collect();
        if ($hasInvoices) {
            $query = SalesInvoice::where('client_id', $client->id)->where('status', '!=', 'cancelled');
            if (Schema::hasColumn('sales_invoices', 'converted_invoice_id')) $query->notSuperseded();
            if ($hasCash && Schema::hasColumn('cashflow_entries', 'sales_invoice_id')) {
                $query->withReceived();
            } else {
                $query->selectRaw('sales_invoices.*, 0 as received_credit, 0 as received_debit, 0 as allocation_received');
            }
            $invoices = $query->get();
        }
        foreach ($invoices as $invoice) {
            $code = $this->currency($invoice->currency);
            $rows[$code] ??= $this->emptyRow($code);
            $rows[$code]['invoice_total'] += $invoice->receivableTotal();
            $rows[$code]['payments'] += $invoice->receivedAmount();
            $rows[$code]['invoice_balance'] += $invoice->balanceDue();
            $rows[$code]['unapplied_credit'] += $invoice->advanceAmount();
        }

        if ($hasCash) {
            // Explicit links only: similar names must never move another client's money.
            $relations = [];
            if ($hasInvoices) $relations[] = 'salesInvoice';
            if ($hasProjects) $relations['project'] = fn ($project) => $project->withTrashed();
            if ($hasAllocations) {
                $relations[] = 'allocations';
                if ($hasInvoices) $relations[] = 'allocations.salesInvoice';
                if ($hasProjects) $relations['allocations.project'] = fn ($project) => $project->withTrashed();
            }
            $entries = CashflowEntry::with($relations)->where(function ($query) use ($client, $hasInvoices, $hasProjects, $hasAllocations) {
                $query->where('client_id', $client->id);
                if ($hasInvoices) $query->orWhereHas('salesInvoice', fn ($invoice) => $invoice->where('client_id', $client->id));
                if ($hasProjects) $query->orWhere(fn ($receipt) => $receipt
                    ->where('transaction_type', 'credit')->whereNull('vendor_id')
                    ->whereHas('project', fn ($project) => $project->withTrashed()->where('client_id', $client->id)));
                if ($hasAllocations && $hasInvoices) $query->orWhereHas('allocations.salesInvoice', fn ($invoice) => $invoice->where('client_id', $client->id));
                if ($hasAllocations && $hasProjects) $query->orWhere(fn ($receipt) => $receipt
                    ->where('transaction_type', 'credit')->whereNull('vendor_id')
                    ->whereHas('allocations.project', fn ($project) => $project->withTrashed()->where('client_id', $client->id)));
            })->get();
            foreach ($entries as $entry) {
                $directInvoice = $hasInvoices ? $entry->salesInvoice : null;
                // Its entire receipt/refund is already in receivedAmount(), including FX.
                if ($this->counts($directInvoice)) continue;
                $allocations = $hasAllocations ? $entry->allocations : collect();
                $owner = (int) $entry->client_id;
                if (! $owner) $owner = (int) $directInvoice?->client_id;
                $projectReceipt = ! $entry->isMoneyOut() && ! $entry->vendor_id;
                if (! $owner && $hasProjects && $projectReceipt) $owner = (int) $entry->project?->client_id;
                if (! $owner) {
                    $owners = $allocations->map(fn ($allocation) =>
                        ($hasInvoices ? $allocation->salesInvoice?->client_id : null)
                        ?: ($hasProjects && $projectReceipt ? $allocation->project?->client_id : null)
                    )->filter()->unique();
                    // An unowned remainder split across clients is not anybody's advance.
                    if ($owners->count() === 1) $owner = (int) $owners->first();
                }
                if ($owner !== (int) $client->id) continue;
                $appliedCash = $hasInvoices ? (float) $allocations->sum(fn ($allocation) =>
                    $this->counts($allocation->salesInvoice) ? (float) $allocation->amount : 0
                ) : 0;
                // Applied portions are already counted in document currency above. Only
                // the remainder stays in bank currency; project-only advances stay here.
                $remainder = max($entry->amountMoved() - $appliedCash, 0);
                $signed = $entry->isMoneyOut() ? -$remainder : $remainder;
                if (abs($signed) < 0.005) continue;
                $code = $this->currency($entry->currency);
                $rows[$code] ??= $this->emptyRow($code);
                $rows[$code]['payments'] += $signed;
                $rows[$code]['unapplied_credit'] += $signed;
            }
        }
        $preferred = $this->currency($client->preferred_currency);
        if (! $rows) $rows[$preferred] = $this->emptyRow($preferred);
        foreach ($rows as &$row) {
            $row['unapplied_credit'] = max($row['unapplied_credit'], 0);
            $net = round($row['invoice_total'] - $row['payments'], 2);
            $row['balance_due'] = max($net, 0);
            $row['net_credit'] = max(-$net, 0);
            foreach ($row as $key => $value) if ($key !== 'currency') $row[$key] = round($value, 2);
        }
        unset($row);
        uksort($rows, fn ($a, $b) => (($a !== $preferred) <=> ($b !== $preferred)) ?: strcmp($a, $b));

        return [
            'project_count' => $hasProjects ? Project::where('client_id', $client->id)->count() : 0,
            'invoice_count' => $invoices->count(),
            'currencies' => array_values($rows),
        ];
    }

    private function counts(?SalesInvoice $invoice): bool
    {
        return $invoice && $invoice->status !== 'cancelled' && ! $invoice->isSuperseded();
    }

    private function currency(?string $code): string
    {
        return strtoupper(trim((string) $code)) ?: 'INR';
    }

    private function emptyRow(string $code): array
    {
        return ['currency' => $code, 'invoice_total' => 0.0, 'payments' => 0.0,
            'invoice_balance' => 0.0, 'unapplied_credit' => 0.0];
    }
}
