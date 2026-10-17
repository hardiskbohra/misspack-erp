<?php

namespace App\Services;

use App\Models\CashflowEntry;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\VendorPaymentEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Validate and persist the monetary slices behind one ledger entry. */
class CashflowAllocations
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function prepare(CashflowEntry $entry, array $rows): array
    {
        if (! Schema::hasTable('cashflow_allocations')) {
            return [];
        }

        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && (
            filled($row['amount'] ?? null)
            || filled($row['project_id'] ?? null)
            || filled($row['sales_invoice_id'] ?? null)
            || filled($row['purchase_invoice_id'] ?? null)
        )));

        $projects = Project::query()->whereIn('id', array_filter(array_column($rows, 'project_id')))->get()->keyBy('id');
        $sales = SalesInvoice::query()->whereIn('id', array_filter(array_column($rows, 'sales_invoice_id')))->get()->keyBy('id');
        $purchases = PurchaseInvoice::query()->whereIn('id', array_filter(array_column($rows, 'purchase_invoice_id')))->get()->keyBy('id');
        $errors = [];
        $prepared = [];
        $total = 0.0;

        foreach ($rows as $index => $row) {
            $field = 'allocations.'.$index;
            $projectId = (int) ($row['project_id'] ?? 0) ?: null;
            $salesId = (int) ($row['sales_invoice_id'] ?? 0) ?: null;
            $purchaseId = (int) ($row['purchase_invoice_id'] ?? 0) ?: null;
            $amount = round((float) ($row['amount'] ?? 0), 2);

            if ($amount <= 0) {
                $errors[$field.'.amount'] = 'Enter an allocation amount greater than zero.';
            }
            if (! $projectId && ! $salesId && ! $purchaseId) {
                $errors[$field.'.project_id'] = 'Link this allocation to a project, sales invoice, purchase order or bill.';
            }
            if ($salesId && $purchaseId) {
                $errors[$field.'.sales_invoice_id'] = 'One allocation cannot point to both a sales and purchase document.';
            }
            if ($projectId && ! $projects->has($projectId)) {
                $errors[$field.'.project_id'] = 'The selected project no longer exists.';
            }

            $document = null;
            if ($salesId) {
                $document = $sales->get($salesId);
                if (! $document) {
                    $errors[$field.'.sales_invoice_id'] = 'The selected sales invoice no longer exists.';
                } elseif ($document->status === 'cancelled') {
                    $errors[$field.'.sales_invoice_id'] = 'A cancelled sales document cannot receive an allocation.';
                }
            } elseif ($purchaseId) {
                $document = $purchases->get($purchaseId);
                if (! $document) {
                    $errors[$field.'.purchase_invoice_id'] = 'The selected purchase document no longer exists.';
                } elseif (! $document->canReceiveMoney()) {
                    $errors[$field.'.purchase_invoice_id'] = $document->moneyGateMessage() ?: 'This purchase document cannot receive a payment allocation.';
                }
            }

            if ($document && $projectId && $document->project_id && (int) $document->project_id !== $projectId) {
                $errors[$field.'.project_id'] = 'The selected document belongs to a different project.';
            }
            if ($document && ! $projectId && $document->project_id) {
                $projectId = (int) $document->project_id;
            }

            $documentCurrency = $document ? strtoupper((string) ($document->currency ?: $entry->currency)) : null;
            $documentAmount = filled($row['document_amount'] ?? null)
                ? round((float) $row['document_amount'], 2)
                : null;

            if ($document) {
                if ($documentAmount === null && $documentCurrency !== strtoupper((string) $entry->currency)) {
                    $errors[$field.'.document_amount'] = 'Enter the amount applied in '.$documentCurrency.' for this foreign-currency document.';
                }
                $documentAmount ??= $amount;
                if ($documentAmount <= 0) {
                    $errors[$field.'.document_amount'] = 'The document amount must be greater than zero.';
                }
            } else {
                $documentAmount = null;
            }

            $total += $amount;
            $prepared[] = [
                'project_id' => $projectId,
                'sales_invoice_id' => $salesId,
                'purchase_invoice_id' => $purchaseId,
                'amount' => $amount,
                'document_amount' => $documentAmount,
                'currency' => strtoupper((string) ($entry->currency ?: 'INR')),
                'document_currency' => $documentCurrency,
                'note' => filled($row['note'] ?? null) ? trim((string) $row['note']) : null,
            ];
        }

        if ($total > $entry->amountMoved() + 0.009) {
            $errors['allocations'] = 'Allocated amount '.number_format($total, 2)
                .' exceeds the cashflow entry amount '.number_format($entry->amountMoved(), 2).'.';
        }

        if (array_filter(array_column($prepared, 'purchase_invoice_id'))
            && Schema::hasTable('vendor_payment_entries')
            && Schema::hasColumn('vendor_payment_entries', 'cashflow_entry_id')
            && VendorPaymentEntry::query()->where('cashflow_entry_id', $entry->id)->exists()) {
            $errors['allocations'] = 'This cashflow row mirrors a vendor-ledger payment. Remove that mirror or allocate the original vendor payment; linking it again would count the same payment twice.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $prepared;
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function sync(CashflowEntry $entry, array $rows): void
    {
        if (! Schema::hasTable('cashflow_allocations')) {
            return;
        }

        $prepared = $this->prepare($entry, $rows);
        $oldSales = $entry->allocations()->whereNotNull('sales_invoice_id')->pluck('sales_invoice_id')->all();
        if ($entry->sales_invoice_id) $oldSales[] = (int) $entry->sales_invoice_id;
        $oldPurchases = $entry->allocations()->whereNotNull('purchase_invoice_id')->pluck('purchase_invoice_id')->all();
        $entry->allocations()->delete();

        if ($prepared) {
            $entry->allocations()->createMany($prepared);
        }
        /* The full cashflow form owns these links now. Legacy rows are rendered
           as an allocation before an edit; removing that row must really unlink
           it rather than silently preserving the old columns. Other module
           writers that still use the single links do not call this service. */
        if ($entry->project_id || $entry->sales_invoice_id) {
            $entry->forceFill(['project_id' => null, 'sales_invoice_id' => null])->saveQuietly();
        }

        $newSales = array_filter(array_column($prepared, 'sales_invoice_id'));
        $newPurchases = array_filter(array_column($prepared, 'purchase_invoice_id'));
        $this->refreshDocuments(array_unique(array_merge($oldSales, $newSales)), array_unique(array_merge($oldPurchases, $newPurchases)));
    }

    /** Keep stored balances/statuses aligned with allocation changes. */
    private function refreshDocuments(array $salesIds, array $purchaseIds): void
    {
        SalesInvoice::query()->whereIn('id', $salesIds)->get()->each(function (SalesInvoice $invoice) {
            $invoice->forceFill(['balance_amount' => $invoice->balanceDue()])->saveQuietly();
        });

        PurchaseInvoice::query()->whereIn('id', $purchaseIds)->get()->each(function (PurchaseInvoice $invoice) {
            $changes = ['balance_amount' => $invoice->balanceDue()];
            if ($invoice->isBill() && ! in_array($invoice->status, ['draft', 'cancelled'], true)) {
                $state = $invoice->paymentState();
                if ($state !== 'unpaid') $changes['status'] = $state;
            }
            $invoice->forceFill($changes)->saveQuietly();
        });
    }
}
