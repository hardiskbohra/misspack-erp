<?php

namespace App\Services;

use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\SalesInvoice;
use Illuminate\Validation\ValidationException;

class CashInvoiceConversion
{
    /**
     * Amounts are in invoice currency, excluding GST but including charges/round-off.
     *
     * @return array{amount: float, gross_consumed: float, full: bool}
     */
    public function plan(SalesInvoice $pi, string $mode, float $value): array
    {
        $base = $pi->cashConvertibleTotal();
        $remaining = $pi->cashConvertibleRemaining();
        if ($pi->invoice_type !== 'proforma' || $pi->isSuperseded() || $pi->status === 'cancelled' || $base <= 0 || $remaining <= 0) {
            throw ValidationException::withMessages(['conversion_value' => 'Only an active PI with an uninvoiced amount can be converted.']);
        }
        $amount = $mode === 'percent' ? round($remaining * $value / 100, 2) : round($value, 2);
        if (! in_array($mode, ['amount', 'percent'], true) || ! is_finite($value) || $value <= 0 || ($mode === 'percent' && $value > 100) || $amount <= 0 || $amount > $remaining) {
            throw ValidationException::withMessages(['conversion_value' => 'Choose a positive amount no greater than the remaining PI value, or a percentage up to 100.']);
        }
        // Cumulative rounding makes several partial conversions reconcile to the last cent.
        $cumulative = round((float) $pi->cash_converted_base_amount + $amount, 2);
        $grossConsumed = round((float) $pi->total_amount * $cumulative / $base, 2) - (float) $pi->cash_converted_total_amount;
        return ['amount' => $amount, 'gross_consumed' => round($grossConsumed, 2), 'full' => $amount === $remaining];
    }

    /** Move an explicitly chosen amount without changing any bank/cash entry's value. Caller owns transaction and PI lock. */
    public function transferReceipts(SalesInvoice $pi, SalesInvoice $cash, float $amount): void
    {
        $pi->unsetRelation('payments')->unsetRelation('cashflowAllocations');
        if (! is_finite($amount) || $amount < 0 || $amount > $pi->receivedAmount() + 0.001) {
            throw ValidationException::withMessages(['transfer_received' => 'The transfer cannot exceed the PI’s recorded receipts.']);
        }
        if ($amount > (float) $cash->total_amount + 0.001) {
            throw ValidationException::withMessages(['transfer_received' => 'Transfer no more than the Cash Invoice value. The remaining receipts must stay against the PI.']);
        }
        $left = round($amount, 2);
        $allocations = CashflowAllocation::where('sales_invoice_id', $pi->id)->with('cashflowEntry.account')->orderBy('id')->lockForUpdate()->get();
        $entries = CashflowEntry::where('sales_invoice_id', $pi->id)->with('account')->orderBy('id')->lockForUpdate()->get();
        // Cash receipts fund the Cash Invoice first, even if the bank receipt was
        // entered earlier. Then opening credit, then other existing receipts.
        // Keep the same row-lock order; only the application order changes.
        $sources = collect()->concat($allocations)->concat($entries)->push($pi)->sortBy(function ($source) {
            if ($source instanceof SalesInvoice) return 1;
            $entry = $source instanceof CashflowAllocation ? $source->cashflowEntry : $source;
            return $entry && ($entry->payment_mode === 'cash' || $entry->isCashAccount()) ? 0 : 2;
        });
        foreach ($sources as $source) {
            if ($left <= 0) break;
            if ($source instanceof SalesInvoice) {
                $opening = min(max((float) $pi->amount_paid, 0), $left);
                if ($opening > 0) {
                    $pi->amount_paid = round((float) $pi->amount_paid - $opening, 2);
                    $cash->amount_paid = round((float) $cash->amount_paid + $opening, 2);
                    $pi->save();
                    $cash->save();
                    $left = round($left - $opening, 2);
                }
                continue;
            }
            if ($source instanceof CashflowAllocation) {
                $allocation = $source;
                $documentAmount = $allocation->documentAmount();
                if ($documentAmount <= 0) {
                    continue;
                }
                $take = min($left, $documentAmount);
                if ($take === $documentAmount) {
                    $allocation->update(['sales_invoice_id' => $cash->id]);
                } else {
                    $native = round((float) $allocation->amount * $take / $documentAmount, 2);
                    $copy = $allocation->replicate();
                    $copy->sales_invoice_id = $cash->id;
                    $copy->amount = $native;
                    $copy->document_amount = $take;
                    $copy->save();
                    $allocation->update(['amount' => round((float) $allocation->amount - $native, 2), 'document_amount' => round($documentAmount - $take, 2)]);
                }
                $left = round($left - $take, 2);
                continue;
            }
            $entry = $source;
            $net = round((float) $entry->credit_amount - (float) $entry->debit_amount, 2);
            if ($net <= 0) {
                continue;
            }
            if ($entry->allocations()->exists() || strtoupper($entry->currency ?: 'INR') !== strtoupper($pi->currency ?: 'INR')) {
                throw ValidationException::withMessages(['transfer_received' => 'Allocate this mixed/foreign-currency receipt in Cashflow before transferring it. No conversion was saved.']);
            }
            $take = min($left, $net);
            if ($take === $net) {
                $entry->update(['sales_invoice_id' => $cash->id]);
            } else {
                // Replace the direct invoice link with two monetary slices of the SAME receipt.
                $entry->update(['sales_invoice_id' => null]);
                foreach ([[$cash, $take], [$pi, round($net - $take, 2)]] as [$invoice, $slice]) {
                    $entry->allocations()->create(['sales_invoice_id' => $invoice->id, 'project_id' => $entry->project_id ? null : $invoice->project_id,
                        'amount' => $slice, 'document_amount' => $slice, 'currency' => $entry->currency ?: 'INR',
                        'document_currency' => $invoice->currency, 'note' => 'PI to cash invoice receipt transfer']);
                }
            }
            $left = round($left - $take, 2);
        }
        if ($left > 0.001) {
            throw ValidationException::withMessages(['transfer_received' => 'The selected receipts changed. Reload the PI and try again.']);
        }
    }
}
