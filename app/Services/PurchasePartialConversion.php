<?php

namespace App\Services;

use App\Helpers\CommonHelper;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePaymentSplit;
use App\Models\VendorPaymentEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Split an approved PO without creating, changing, or duplicating a payment. */
class PurchasePartialConversion
{
    public const REVERSAL_EVENT = 'purchase-order.partial-conversion-reversed';

    public static function numberWasReversed(string $number): bool
    {
        return Schema::hasTable('audit_logs') && \App\Models\AuditLog::where('event', self::REVERSAL_EVENT)
            ->where('context->bill_number', $number)->exists();
    }

    /** Caller supplies the next bill number inside the transaction. */
    public function convert(PurchaseInvoice $source, array $data, callable $number): PurchaseInvoice
    {
        // Different POs have different row locks. If they choose the same
        // automatic number, retry the entire rolled-back transaction so the
        // number generator sees the newly committed bill.
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction(function () use ($source, $data, $number) {
                    $order = PurchaseInvoice::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
                    if (! $order->isOrder() || $order->status !== 'approved' || $order->isSuperseded()) {
                        throw ValidationException::withMessages(['conversion' => 'Only an approved, active purchase order can be split.']);
                    }
                    $remaining = round((float) $order->total_amount, 3);
                    $already = round((float) ($order->converted_amount ?? 0), 3);
                    $original = round((float) ($order->original_total_amount ?? $remaining), 3);
                    $amount = $data['mode'] === 'percent' ? round($remaining * (float) $data['value'] / 100, 3) : round((float) $data['value'], 3);
                    if ($original <= 0 || $amount <= 0 || $amount > $remaining + 0.0005 || ($data['mode'] === 'percent' && (float) $data['value'] > 100)) {
                        throw ValidationException::withMessages(['value' => 'Choose an amount greater than zero and no more than the remaining PO total.']);
                    }
                    if (abs($original - $remaining - $already) > 0.0005
                        || abs((float) $order->partialConversions()->sum('conversion_amount') - $already) > 0.0005) {
                        throw ValidationException::withMessages(['conversion' => 'The PO conversion history does not reconcile. Ask Accounts to review it; nothing was changed.']);
                    }

                    $selection = $this->lockSelection($order, $data, $amount);
                    $bill = $order->replicate(['public_token', 'converted_invoice_id', 'original_total_amount', 'converted_amount',
                        'conversion_amount', 'conversion_kind', 'sent_at', 'approved_at', 'received_at', 'cancelled_at']);
                    $bill->fill([
                        'invoice_type' => PurchaseInvoice::TYPE_BILL,
                        'invoice_number' => $number(), 'status' => 'received', 'purchase_order_id' => $order->id,
                        'conversion_kind' => $data['kind'], 'conversion_amount' => $amount,
                        'original_total_amount' => null, 'converted_amount' => 0,
                        'public_token' => Str::random(48), 'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                        'vendor_bill_number' => $data['vendor_bill_number'] ?? null, 'vendor_bill_date' => $data['vendor_bill_date'] ?? null,
                        'received_at' => now(), 'amount_paid' => 0, 'balance_amount' => $amount, 'created_by' => Auth::id(),
                        'notes' => trim(($data['kind'] === 'cash' ? 'Cash purchase' : 'Purchase bill').' from '.$order->invoice_number.'. '.($order->notes ?? '')),
                    ]);
                    if ($data['kind'] === 'cash') {
                        $bill->fill(['gst_type' => 'export', 'place_of_supply' => null, 'subtotal' => $amount,
                            'discount_type' => 'amount', 'discount_value' => 0, 'discount_amount' => 0,
                            'taxable_amount' => $amount, 'cgst_amount' => 0, 'sgst_amount' => 0, 'igst_amount' => 0,
                            'freight_amount' => 0, 'packing_amount' => 0, 'other_charges' => 0, 'round_off' => 0,
                            'total_amount' => $amount, 'amount_in_words' => CommonHelper::inWords($amount, $order->currency)]);
                        $bill->save();
                        $bill->items()->create(['product_name' => 'Cash purchase against '.$order->invoice_number,
                            'quantity' => 1, 'unit' => 'lot', 'unit_price' => $amount, 'gross_amount' => $amount,
                            'discount_percent' => 0, 'discount_amount' => 0, 'taxable_amount' => $amount,
                            'gst_percent' => 0, 'cgst_amount' => 0, 'sgst_amount' => 0, 'igst_amount' => 0,
                            'line_total' => $amount, 'sort_order' => 1]);
                    } else {
                        $this->createItemisedBill($order, $bill, $original, $already, $amount);
                    }

                    $this->applySelection($order, $bill, $selection);
                    $order->original_total_amount = $original;
                    $order->converted_amount = round($already + $amount, 3);
                    $order->total_amount = round($remaining - $amount, 3);
                    $order->amount_in_words = CommonHelper::inWords((float) $order->total_amount, $order->currency);
                    $order->save();
                    app(PurchaseBillLedger::class)->sync($order->fresh());
                    app(PurchaseBillLedger::class)->sync($bill->fresh());
                    app(PurchaseInvoiceMoney::class)->refresh([$order->id, $bill->id]);

                    if (Schema::hasTable('audit_logs')) {
                        app(AuditTrail::class)->event('purchase-order.partial-conversion', [
                            'actor_type' => Auth::user() ? 'office_user' : 'system', 'actor_id' => Auth::id(),
                            'auditable_type' => PurchaseInvoice::class, 'auditable_id' => $order->id,
                            'context' => ['bill_id' => $bill->id, 'kind' => $data['kind'], 'currency' => $order->currency,
                                'amount' => $amount, 'payments' => $selection['audit']],
                        ]);
                    }
                    return $bill->fresh(['items']);
                }, 3);
            } catch (\Illuminate\Database\QueryException $error) {
                $detail = strtolower((string) ($error->errorInfo[2] ?? ''));
                $duplicateNumber = ((int) ($error->errorInfo[1] ?? 0) === 1062 && str_contains($detail, 'purchase_invoices_invoice_number_unique'))
                    || str_contains($detail, 'unique constraint failed: purchase_invoices.invoice_number');
                if (! $duplicateNumber) throw $error;
                if ($attempt >= 2) {
                    throw ValidationException::withMessages(['conversion' => 'Another bill was numbered at the same time. Please retry the conversion.']);
                }
            }
        }
    }

    /** Reverse only the most recent slice, keeping the original payment movements. */
    public function reverse(PurchaseInvoice $document): PurchaseInvoice
    {
        return DB::transaction(function () use ($document) {
            $order = PurchaseInvoice::whereKey($document->purchase_order_id)->lockForUpdate()->firstOrFail();
            $bill = PurchaseInvoice::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $children = $order->partialConversions()->lockForUpdate()->get();
            if (! $bill->isPartialConversion() || ! $order->isOrder() || $order->status !== 'approved'
                || ! $children->last() || $children->last()->id !== $bill->id
                || abs((float) $children->sum('conversion_amount') - (float) $order->converted_amount) > 0.0005) {
                throw ValidationException::withMessages(['conversion' => 'Reverse the latest linked bill first, and ask Accounts to review any inconsistent history.']);
            }
            if (! Schema::hasTable('audit_logs')) {
                throw ValidationException::withMessages(['conversion' => 'The audit register is required to reverse a linked bill and reserve its number.']);
            }
            foreach (['packaging_lists', 'vendor_collaboration_updates'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'purchase_invoice_id')
                    && DB::table($table)->where('purchase_invoice_id', $bill->id)->exists()) {
                    throw ValidationException::withMessages(['conversion' => 'This bill has downstream records. Review those records before reversing it.']);
                }
            }
            if (Schema::hasTable('vendor_share_links') && DB::table('vendor_share_links')
                ->where('resource_type', 'purchase_invoice')->where('resource_id', $bill->id)->exists()) {
                throw ValidationException::withMessages(['conversion' => 'This bill has vendor share links. Review those links before reversing it.']);
            }
            $this->assertBillPostingIntact($bill);

            $splitRows = PurchasePaymentSplit::where('purchase_invoice_id', $bill->id)->orderBy('vendor_payment_entry_id')->lockForUpdate()->get();
            foreach ($splitRows as $split) {
                $payment = VendorPaymentEntry::whereKey($split->vendor_payment_entry_id)->lockForUpdate()->firstOrFail();
                $onOrder = PurchasePaymentSplit::where('vendor_payment_entry_id', $payment->id)
                    ->where('purchase_invoice_id', $order->id)->lockForUpdate()->first();
                if ($onOrder) {
                    $onOrder->amount = round((float) $onOrder->amount + (float) $split->amount, 4);
                    $onOrder->save();
                    $split->delete();
                } else {
                    $split->purchase_invoice_id = $order->id;
                    $split->save();
                }
                $parts = PurchasePaymentSplit::where('vendor_payment_entry_id', $payment->id)->lockForUpdate()->get();
                if ($parts->count() === 1 && (int) $parts->first()->purchase_invoice_id === (int) $order->id
                    && abs((float) $parts->first()->amount - (float) $payment->foreign_amount) < 0.00005) {
                    $payment->purchase_invoice_id = $order->id;
                    $payment->save();
                    $parts->first()->delete();
                }
            }
            VendorPaymentEntry::where('purchase_invoice_id', $bill->id)->where('transaction_type', 'debit')
                ->lockForUpdate()->get()->each(function ($payment) use ($order) {
                    $payment->purchase_invoice_id = $order->id;
                    $payment->save();
                });
            $cashflowIds = CashflowAllocation::where('purchase_invoice_id', $bill->id)->pluck('cashflow_entry_id')->unique()->all();
            CashflowEntry::whereIn('id', $cashflowIds)->orderBy('id')->lockForUpdate()->get();
            CashflowAllocation::where('purchase_invoice_id', $bill->id)->lockForUpdate()->get()->each(function ($allocation) use ($order) {
                $allocation->purchase_invoice_id = $order->id;
                $allocation->save();
            });
            $order->amount_paid = round((float) $order->amount_paid + (float) $bill->amount_paid, 3);
            $order->total_amount = round((float) $order->total_amount + (float) $bill->conversion_amount, 3);
            $order->converted_amount = round((float) $order->converted_amount - (float) $bill->conversion_amount, 3);
            if ((float) $order->converted_amount < 0.0005) {
                $order->converted_amount = 0;
                $order->original_total_amount = null;
            }
            $order->amount_in_words = CommonHelper::inWords($order->total_amount, $order->currency);
            $order->save();
            app(PurchaseBillLedger::class)->remove($bill);
            $billNumber = $bill->invoice_number;
            $billId = $bill->id;
            $bill->items()->delete();
            $bill->delete();
            app(PurchaseBillLedger::class)->sync($order->fresh());
            app(PurchaseInvoiceMoney::class)->refresh([$order->id]);
            app(AuditTrail::class)->event(self::REVERSAL_EVENT, [
                'actor_type' => Auth::user() ? 'office_user' : 'system', 'actor_id' => Auth::id(),
                'auditable_type' => PurchaseInvoice::class, 'auditable_id' => $order->id,
                'context' => ['bill_id' => $billId, 'bill_number' => $billNumber],
            ]);
            return $order->fresh();
        }, 3);
    }

    /** Refuse to overwrite or delete a missing, replaced, or manually altered ledger posting. */
    public function assertBillPostingIntact(PurchaseInvoice $bill): void
    {
        $credits = VendorPaymentEntry::where('purchase_invoice_id', $bill->id)->where('transaction_type', 'credit')
            ->lockForUpdate()->get();
        $entry = $credits->first();
        $currency = $bill->currency ?: 'INR';
        $rate = $currency === 'INR' ? 1.0 : (float) ($bill->exchange_rate ?: 1);
        if ($credits->count() !== 1 || ! $entry || (int) $entry->vendor_id !== (int) $bill->vendor_id
            || $entry->entry_category !== 'bill' || $entry->status !== 'booked'
            || $entry->remarks !== 'Auto-posted from purchase bill '.$bill->invoice_number.'.'
            || $entry->invoice_number !== $bill->referenceNumber() || $entry->foreign_currency !== $currency
            || $entry->cashflow_entry_id || abs((float) $entry->foreign_amount - (float) $bill->total_amount) > 0.0005
            || abs((float) $entry->amount_in_inr - round((float) $bill->total_amount * $rate, 3)) > 0.0005) {
            throw ValidationException::withMessages(['conversion' => 'The bill ledger posting is missing or has changed. Ask Accounts to review it before changing or reversing this conversion.']);
        }
    }

    /** Lock and validate every explicitly selected source before any write. */
    private function lockSelection(PurchaseInvoice $order, array $data, float $billAmount): array
    {
        $opening = (float) ($data['opening'] ?? 0);
        if ($opening > (float) $order->amount_paid + 0.0005) {
            throw ValidationException::withMessages(['opening' => 'The selected opening advance is no longer available.']);
        }
        // Cashflow edits rebuild allocation rows. Lock the parent entries first
        // so a stale allocation id cannot be transferred during such a rebuild.
        $allocationIds = array_keys(array_filter($data['allocations'] ?? [], fn ($value) => (float) $value > 0));
        if ($allocationIds) {
            $entryIds = CashflowAllocation::whereIn('id', $allocationIds)->pluck('cashflow_entry_id')->unique()->all();
            CashflowEntry::whereIn('id', $entryIds)->orderBy('id')->lockForUpdate()->get();
        }
        $total = $opening;
        $payments = [];
        $splits = [];
        $allocations = [];
        $audit = ['opening' => $opening, 'payments' => [], 'splits' => [], 'allocations' => []];
        foreach (['payments', 'splits', 'allocations'] as $kind) {
            $values = $data[$kind] ?? [];
            ksort($values, SORT_NUMERIC);
            foreach ($values as $id => $selected) {
                $take = (float) $selected;
                if ($take <= 0) continue;
                if (! ctype_digit((string) $id) || (int) $id <= 0) {
                    throw ValidationException::withMessages([$kind => 'A selected payment is invalid.']);
                }
                if ($kind === 'payments') {
                    $row = VendorPaymentEntry::whereKey($id)->where('purchase_invoice_id', $order->id)
                        ->where('transaction_type', 'debit')->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'cancelled'))
                        ->lockForUpdate()->first();
                    $available = (float) ($row?->foreign_amount ?? 0);
                    $currency = $row?->foreign_currency;
                } elseif ($kind === 'splits') {
                    $row = PurchasePaymentSplit::whereKey($id)->where('purchase_invoice_id', $order->id)->lockForUpdate()->first();
                    $payment = $row ? VendorPaymentEntry::whereKey($row->vendor_payment_entry_id)->lockForUpdate()->first() : null;
                    if (! $payment || $payment->transaction_type !== 'debit' || $payment->status === 'cancelled') $row = null;
                    $available = (float) ($row?->amount ?? 0);
                    $currency = $payment?->foreign_currency;
                } else {
                    $row = CashflowAllocation::whereKey($id)->where('purchase_invoice_id', $order->id)->lockForUpdate()->first();
                    $available = $row?->documentAmount() ?? 0;
                    $currency = $row?->document_currency ?: $row?->currency;
                }
                if (! $row || $take > $available + 0.0005 || $available <= 0) {
                    throw ValidationException::withMessages([$kind => 'A selected payment is no longer available at that amount. Reload the PO; nothing was changed.']);
                }
                if (strtoupper((string) $currency) !== strtoupper((string) $order->currency)) {
                    throw ValidationException::withMessages([$kind => 'A selected payment is in a different currency. Record its document-currency allocation before splitting it.']);
                }
                if (abs($available - round($available, 3)) > 0.00005) {
                    throw ValidationException::withMessages([$kind => 'A selected payment has more than three decimal places. Ask Accounts to reconcile it before splitting.']);
                }
                if ($kind === 'allocations' && $take < $available - 0.0005) {
                    $native = round((float) $row->amount * $take / $available, 3);
                    if ($native <= 0 || $native >= (float) $row->amount) {
                        throw ValidationException::withMessages([$kind => 'This foreign-currency payment cannot be split into two positive three-decimal cash amounts.']);
                    }
                }
                ${$kind}[] = [$row, $take, $available];
                $audit[$kind][(string) $id] = $take;
                $total += $take;
            }
        }
        if (round($total, 3) > $billAmount + 0.0005) {
            throw ValidationException::withMessages(['payments' => 'The selected payments cannot exceed the new bill amount.']);
        }
        return compact('opening', 'payments', 'splits', 'allocations', 'audit');
    }

    private function applySelection(PurchaseInvoice $order, PurchaseInvoice $bill, array $selection): void
    {
        if ($selection['opening'] > 0) {
            $order->amount_paid = round((float) $order->amount_paid - $selection['opening'], 3);
            $bill->amount_paid = $selection['opening'];
            $bill->save();
        }
        foreach ($selection['payments'] as [$payment, $take, $available]) {
            if (abs($take - $available) < 0.0005) {
                $payment->purchase_invoice_id = $bill->id;
                $payment->save();
            } else {
                $payment->purchase_invoice_id = null;
                $payment->save();
                PurchasePaymentSplit::create(['vendor_payment_entry_id' => $payment->id, 'purchase_invoice_id' => $order->id, 'amount' => round($available - $take, 3)]);
                PurchasePaymentSplit::create(['vendor_payment_entry_id' => $payment->id, 'purchase_invoice_id' => $bill->id, 'amount' => $take]);
            }
        }
        foreach ($selection['splits'] as [$split, $take, $available]) {
            if (abs($take - $available) < 0.0005) {
                $split->purchase_invoice_id = $bill->id;
                $split->save();
            } else {
                $split->amount = round($available - $take, 3);
                $split->save();
                PurchasePaymentSplit::create(['vendor_payment_entry_id' => $split->vendor_payment_entry_id, 'purchase_invoice_id' => $bill->id, 'amount' => $take]);
            }
        }
        foreach ($selection['allocations'] as [$allocation, $take, $available]) {
            if (abs($take - $available) < 0.0005) {
                $allocation->purchase_invoice_id = $bill->id;
                $allocation->save();
            } else {
                $native = round((float) $allocation->amount * $take / $available, 3);
                $copy = $allocation->replicate();
                $copy->purchase_invoice_id = $bill->id;
                $copy->amount = $native;
                $copy->document_amount = $take;
                $copy->save();
                $allocation->amount = round((float) $allocation->amount - $native, 3);
                $allocation->document_amount = round($available - $take, 3);
                $allocation->save();
            }
        }
    }

    private function createItemisedBill(PurchaseInvoice $order, PurchaseInvoice $bill, float $original, float $before, float $amount): void
    {
        $items = $order->items()->get();
        if ($items->isEmpty()) throw ValidationException::withMessages(['conversion' => 'The PO has no item lines to apportion. Ask Accounts to review it.']);
        // Cumulative decimal apportionment prevents a binary-float half-way
        // value such as 0.1005 from being rounded down to 0.100 by accident.
        $beforeDecimal = \Brick\Math\BigDecimal::of(number_format($before, 3, '.', ''));
        $afterDecimal = $beforeDecimal->plus(number_format($amount, 3, '.', ''));
        $originalDecimal = \Brick\Math\BigDecimal::of(number_format($original, 3, '.', ''));
        $portion = static function ($value) use ($beforeDecimal, $afterDecimal, $originalDecimal): float {
            $value = \Brick\Math\BigDecimal::of((string) ($value ?? '0'));
            $after = $value->multipliedBy($afterDecimal)->dividedBy($originalDecimal, 3, \Brick\Math\RoundingMode::HalfUp);
            $before = $value->multipliedBy($beforeDecimal)->dividedBy($originalDecimal, 3, \Brick\Math\RoundingMode::HalfUp);
            return (float) (string) $after->minus($before);
        };
        $bill->save();
        $subtotal = $lineTaxable = 0.0;
        $roundingBudget = \Brick\Math\BigDecimal::zero();
        foreach ($items as $item) {
            $apportionedGross = round($portion($item->gross_amount), 3);
            $quantity = round($portion($item->quantity), 3);
            // The visible line gross must equal its displayed quantity × original
            // rate. Up to five thousandths across the *whole bill* may be
            // reconciled by the visible round-off below; summing absolute line
            // differences prevents opposite discrepancies from hiding each other.
            $grossDecimal = \Brick\Math\BigDecimal::of(number_format($quantity, 3, '.', ''))
                ->multipliedBy((string) ($item->unit_price ?? '0'))
                ->toScale(3, \Brick\Math\RoundingMode::HalfUp);
            $gross = (float) (string) $grossDecimal;
            $difference = $grossDecimal->minus(number_format($apportionedGross, 3, '.', ''))->abs();
            $roundingBudget = $roundingBudget->plus($difference);
            if ($roundingBudget->isGreaterThan('0.005') || ($apportionedGross > 0 && ($quantity <= 0 || $gross <= 0))
                || ($apportionedGross <= 0 && $gross > 0)) {
                throw ValidationException::withMessages(['conversion' => 'This bill portion cannot be represented by the original item rates and three-decimal quantities within the small rounding limit. Ask Accounts to review the split; nothing was changed.']);
            }
            $line = $item->replicate(['purchase_invoice_id']);
            $line->purchase_invoice_id = $bill->id;
            $line->quantity = $quantity;
            $line->gross_amount = $gross;
            $line->discount_amount = round($portion($item->discount_amount), 3);
            if ((float) $line->discount_amount > $gross + 0.0005) {
                throw ValidationException::withMessages(['conversion' => 'The apportioned discount exceeds the rounded item amount. Ask Accounts to review the split; nothing was changed.']);
            }
            $line->taxable_amount = round($gross - (float) $line->discount_amount, 3);
            $line->cgst_amount = round($portion($item->cgst_amount), 3);
            $line->sgst_amount = round($portion($item->sgst_amount), 3);
            $line->igst_amount = round($portion($item->igst_amount), 3);
            $line->line_total = round((float) $line->taxable_amount + (float) $line->cgst_amount + (float) $line->sgst_amount + (float) $line->igst_amount, 3);
            $line->save();
            $subtotal += $gross;
            $lineTaxable += (float) $line->taxable_amount;
        }
        $discount = round($portion($order->discount_amount), 3);
        if ($discount > $lineTaxable + 0.0005) {
            throw ValidationException::withMessages(['conversion' => 'The apportioned invoice discount exceeds the rounded taxable amount. Ask Accounts to review the split; nothing was changed.']);
        }
        $taxable = round($lineTaxable - $discount, 3);
        $cgst = round($portion($order->cgst_amount), 3);
        $sgst = round($portion($order->sgst_amount), 3);
        $igst = round($portion($order->igst_amount), 3);
        $freight = round($portion($order->freight_amount), 3);
        $packing = round($portion($order->packing_amount), 3);
        $other = round($portion($order->other_charges), 3);
        $roundOff = round($amount - $taxable - $cgst - $sgst - $igst - $freight - $packing - $other, 3);
        if (abs($roundOff - $portion($order->round_off)) > max(0.005, $items->count() * 0.005)) {
            throw ValidationException::withMessages(['conversion' => 'The PO amounts cannot be apportioned consistently. Ask Accounts to review them; nothing was changed.']);
        }
        $bill->fill(['subtotal' => round($subtotal, 3), 'discount_amount' => $discount,
            'discount_value' => $order->discount_type === 'amount' ? $discount : $order->discount_value,
            'taxable_amount' => $taxable, 'cgst_amount' => $cgst, 'sgst_amount' => $sgst, 'igst_amount' => $igst,
            'freight_amount' => $freight, 'packing_amount' => $packing, 'other_charges' => $other,
            'round_off' => $roundOff, 'total_amount' => $amount, 'amount_in_words' => CommonHelper::inWords($amount, $order->currency)]);
        $bill->save();
    }
}
