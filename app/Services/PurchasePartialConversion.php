<?php

namespace App\Services;

use App\Helpers\CommonHelper;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchasePaymentSplit;
use App\Models\VendorPaymentEntry;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
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
                    $this->createItemisedBill($order, $bill, $original, $already, $amount, $data['kind'] === 'cash');

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

    /**
     * Keep the full ordered quantities; only the financial rates are scaled.
     * Cash bills spread the selected gross over the original item gross values,
     * while purchase bills scale against the original PO total and preserve
     * the PO's tax/discount/charge treatment.
     */
    private function createItemisedBill(PurchaseInvoice $order, PurchaseInvoice $bill, float $original, float $before, float $amount, bool $cash): void
    {
        $items = $order->items()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
        $originalDecimal = self::decimal(number_format($original, 3, '.', ''));
        $amountDecimal = self::decimal(number_format($amount, 3, '.', ''));
        $beforeDecimal = self::decimal(number_format($before, 3, '.', ''));
        $afterDecimal = $beforeDecimal->plus($amountDecimal);
        $originalSubtotal = $this->assertSourceComponents($order, $items, $originalDecimal);
        if ($cash && $originalSubtotal->isZero()) {
            throw ValidationException::withMessages(['conversion' => 'The PO has no priced items to distribute the cash amount. Ask Accounts to review it; nothing was changed.']);
        }

        // Cumulative apportionment of fixed components makes repeated portions
        // add back to the original even at half-thousandth boundaries.
        $portion = static function ($value) use ($beforeDecimal, $afterDecimal, $originalDecimal): BigDecimal {
            $value = self::decimal($value);
            $after = $value->multipliedBy($afterDecimal)->dividedBy($originalDecimal, 3, RoundingMode::HalfUp);
            $before = $value->multipliedBy($beforeDecimal)->dividedBy($originalDecimal, 3, RoundingMode::HalfUp);
            return $after->minus($before);
        };
        $denominator = $cash ? $originalSubtotal : $originalDecimal;
        if ($cash) {
            $bill->fill(['gst_type' => 'export', 'place_of_supply' => null, 'discount_type' => 'amount', 'discount_value' => 0]);
        }
        $bill->save();
        $subtotal = $lineTaxable = $lineCgst = $lineSgst = $lineIgst = BigDecimal::zero();
        foreach ($items as $item) {
            $price = self::decimal($item->unit_price)->multipliedBy($amountDecimal)
                ->dividedBy($denominator, 3, RoundingMode::HalfUp);
            $gross = self::decimal($item->quantity)->multipliedBy($price)->toScale(3, RoundingMode::HalfUp);
            $discountPercent = $cash ? BigDecimal::zero() : self::decimal($item->discount_percent);
            $gstPercent = $cash ? BigDecimal::zero() : self::decimal($item->gst_percent);
            $discount = $gross->multipliedBy($discountPercent)->dividedBy(100, 3, RoundingMode::HalfUp);
            $taxable = $gross->minus($discount);
            [$cgst, $sgst, $igst] = $this->itemTaxes($bill->gst_type, $taxable, $gstPercent);
            $total = $taxable->plus($cgst)->plus($sgst)->plus($igst);
            foreach ([$price, $gross, $discount, $taxable, $cgst, $sgst, $igst, $total] as $value) $this->assertMoneyFits($value);

            $line = $item->replicate(['purchase_invoice_id']);
            $line->purchase_invoice_id = $bill->id;
            // Replication keeps the PO quantity, description, product links,
            // unit, HSN, remarks and sort order unchanged.
            $line->unit_price = (string) $price;
            $line->gross_amount = (string) $gross;
            $line->discount_percent = (string) $discountPercent;
            $line->discount_amount = (string) $discount;
            $line->taxable_amount = (string) $taxable;
            $line->gst_percent = (string) $gstPercent;
            $line->cgst_amount = (string) $cgst;
            $line->sgst_amount = (string) $sgst;
            $line->igst_amount = (string) $igst;
            $line->line_total = (string) $total;
            $line->save();
            $subtotal = $subtotal->plus($gross);
            $lineTaxable = $lineTaxable->plus($taxable);
            $lineCgst = $lineCgst->plus($cgst);
            $lineSgst = $lineSgst->plus($sgst);
            $lineIgst = $lineIgst->plus($igst);
        }

        $zero = BigDecimal::zero();
        $discount = $cash ? $zero : ($order->discount_type === 'percent'
            ? $lineTaxable->multipliedBy(self::decimal($order->discount_value))->dividedBy(100, 3, RoundingMode::HalfUp)
            : $portion($order->discount_amount));
        if ($discount->isGreaterThan($lineTaxable)) {
            throw ValidationException::withMessages(['conversion' => 'The apportioned invoice discount exceeds the scaled taxable amount. Ask Accounts to review the split; nothing was changed.']);
        }
        $taxable = $lineTaxable->minus($discount);
        // This mirrors the normal purchase calculation: line GST is computed
        // before a document discount, then header GST is reduced by its ratio.
        $headerTax = static fn (BigDecimal $tax): BigDecimal => $lineTaxable->isZero() ? $tax
            : $tax->multipliedBy($taxable)->dividedBy($lineTaxable, 3, RoundingMode::HalfUp);
        $cgst = $headerTax($lineCgst);
        $sgst = $headerTax($lineSgst);
        $igst = $headerTax($lineIgst);
        $freight = $cash ? $zero : $portion($order->freight_amount);
        $packing = $cash ? $zero : $portion($order->packing_amount);
        $other = $cash ? $zero : $portion($order->other_charges);
        // Source components have been validated independently below, so this
        // residual consists of the apportioned original round-off and the
        // unavoidable price/line/tax apportionment precision, not a hidden
        // arbitrary mismatch in the PO.
        $roundOff = $amountDecimal->minus($taxable)->minus($cgst)->minus($sgst)->minus($igst)
            ->minus($freight)->minus($packing)->minus($other);
        foreach ([$subtotal, $discount, $taxable, $cgst, $sgst, $igst, $freight, $packing, $other, $roundOff] as $value) $this->assertMoneyFits($value);
        $bill->fill(['subtotal' => (string) $subtotal, 'discount_amount' => (string) $discount,
            'discount_value' => $cash || $order->discount_type === 'amount' ? (string) $discount : $order->discount_value,
            'taxable_amount' => (string) $taxable, 'cgst_amount' => (string) $cgst,
            'sgst_amount' => (string) $sgst, 'igst_amount' => (string) $igst,
            'freight_amount' => (string) $freight, 'packing_amount' => (string) $packing,
            'other_charges' => (string) $other, 'round_off' => (string) $roundOff,
            'total_amount' => (string) $amountDecimal, 'amount_in_words' => CommonHelper::inWords($amount, $order->currency)]);
        $bill->save();
    }

    /**
     * Validate original item and header arithmetic before allowing an unbounded
     * price-rounding residual. Otherwise a stale or edited PO could be made to
     * look balanced merely by writing its unrelated error into round-off.
     */
    private function assertSourceComponents(PurchaseInvoice $order, Collection $items, BigDecimal $original): BigDecimal
    {
        if ($items->isEmpty()) throw ValidationException::withMessages(['conversion' => 'The PO has no item lines to apportion. Ask Accounts to review it.']);
        $invalid = static function (): never {
            throw ValidationException::withMessages(['conversion' => 'The PO item, discount or tax amounts do not reconcile. Ask Accounts to review them; nothing was changed.']);
        };
        // Older POs were calculated with PHP floats; at a half-thousandth,
        // those can differ by 0.001 from decimal half-up. Accept either exact
        // calculation, not an arbitrary ±0.001 tolerance that could conceal
        // a genuinely wrong gross, tax or discount.
        $matchesRounded = static fn (BigDecimal $actual, BigDecimal $decimal, float $legacy): bool =>
            $actual->isEqualTo($decimal) || $actual->isEqualTo(self::decimal(number_format(round($legacy, 3), 3, '.', '')));
        $subtotal = $lineTaxable = $lineCgst = $lineSgst = $lineIgst = BigDecimal::zero();
        $legacyLineTaxable = $legacyLineCgst = $legacyLineSgst = $legacyLineIgst = 0.0;
        foreach ($items as $item) {
            $quantity = self::decimal($item->quantity);
            $price = self::decimal($item->unit_price);
            $gross = self::decimal($item->gross_amount);
            $discountPercent = self::decimal($item->discount_percent);
            $discount = self::decimal($item->discount_amount);
            $taxable = self::decimal($item->taxable_amount);
            $gstPercent = self::decimal($item->gst_percent);
            $cgst = self::decimal($item->cgst_amount);
            $sgst = self::decimal($item->sgst_amount);
            $igst = self::decimal($item->igst_amount);
            if ($quantity->isNegative() || $price->isNegative() || $gross->isNegative() || $discountPercent->isNegative()
                || $discountPercent->isGreaterThan(100) || $discount->isNegative() || $discount->isGreaterThan($gross)
                || $taxable->isNegative() || $gstPercent->isNegative() || $cgst->isNegative() || $sgst->isNegative() || $igst->isNegative()) $invalid();
            $expectedGross = $quantity->multipliedBy($price)->toScale(3, RoundingMode::HalfUp);
            $expectedDiscount = $gross->multipliedBy($discountPercent)->dividedBy(100, 3, RoundingMode::HalfUp);
            [$expectedCgst, $expectedSgst, $expectedIgst] = $this->itemTaxes($order->gst_type, $taxable, $gstPercent);
            $legacyTaxable = max((float) (string) $gross - (float) (string) $discount, 0);
            $legacyDiscount = (float) (string) $gross * ((float) (string) $discountPercent / 100);
            $legacyTax = $legacyTaxable * ((float) (string) $gstPercent / 100);
            $legacyHalfTax = $legacyTaxable * (((float) (string) $gstPercent / 2) / 100);
            $legacyCgst = $order->gst_type === 'intra_state' ? $legacyHalfTax : 0.0;
            $legacySgst = $legacyCgst;
            $legacyIgst = $order->gst_type === 'inter_state' ? $legacyTax : 0.0;
            if (! $matchesRounded($gross, $expectedGross, (float) (string) $quantity * (float) (string) $price)
                || ! $matchesRounded($discount, $expectedDiscount, $legacyDiscount)
                || ! $taxable->isEqualTo($gross->minus($discount)) || ! $matchesRounded($cgst, $expectedCgst, $legacyCgst)
                || ! $matchesRounded($sgst, $expectedSgst, $legacySgst) || ! $matchesRounded($igst, $expectedIgst, $legacyIgst)
                || ! self::decimal($item->line_total)->isEqualTo($taxable->plus($cgst)->plus($sgst)->plus($igst))) $invalid();
            $subtotal = $subtotal->plus($gross);
            $lineTaxable = $lineTaxable->plus($taxable);
            $lineCgst = $lineCgst->plus($cgst);
            $lineSgst = $lineSgst->plus($sgst);
            $lineIgst = $lineIgst->plus($igst);
            $legacyLineTaxable += $legacyTaxable;
            $legacyLineCgst += (float) (string) $cgst;
            $legacyLineSgst += (float) (string) $sgst;
            $legacyLineIgst += (float) (string) $igst;
        }
        $discount = self::decimal($order->discount_amount);
        $discountValue = self::decimal($order->discount_value);
        $taxable = self::decimal($order->taxable_amount);
        if ($discount->isNegative() || $discountValue->isNegative() || $discount->isGreaterThan($lineTaxable) || $taxable->isNegative()) $invalid();
        $expectedDiscount = $order->discount_type === 'percent'
            ? $lineTaxable->multipliedBy($discountValue)->dividedBy(100, 3, RoundingMode::HalfUp)
            : ($discountValue->isGreaterThan($lineTaxable) ? $lineTaxable : $discountValue);
        $legacyDiscount = $order->discount_type === 'percent'
            ? $legacyLineTaxable * ((float) (string) $discountValue / 100)
            : min((float) (string) $discountValue, $legacyLineTaxable);
        if (! $subtotal->isEqualTo(self::decimal($order->subtotal)) || ! $matchesRounded($discount, $expectedDiscount, $legacyDiscount)
            || ! $taxable->isEqualTo($lineTaxable->minus($discount))) $invalid();
        $headerTax = static fn (BigDecimal $tax): BigDecimal => $lineTaxable->isZero() ? $tax
            : $tax->multipliedBy($taxable)->dividedBy($lineTaxable, 3, RoundingMode::HalfUp);
        $legacyRatio = $legacyLineTaxable > 0 ? max($legacyLineTaxable - (float) (string) $discount, 0) / $legacyLineTaxable : 1;
        $cgst = self::decimal($order->cgst_amount);
        $sgst = self::decimal($order->sgst_amount);
        $igst = self::decimal($order->igst_amount);
        $freight = self::decimal($order->freight_amount);
        $packing = self::decimal($order->packing_amount);
        $other = self::decimal($order->other_charges);
        if ($cgst->isNegative() || $sgst->isNegative() || $igst->isNegative() || $freight->isNegative()
            || $packing->isNegative() || $other->isNegative() || ! $matchesRounded($cgst, $headerTax($lineCgst), $legacyLineCgst * $legacyRatio)
            || ! $matchesRounded($sgst, $headerTax($lineSgst), $legacyLineSgst * $legacyRatio)
            || ! $matchesRounded($igst, $headerTax($lineIgst), $legacyLineIgst * $legacyRatio)
            || ! $original->isEqualTo($taxable->plus($cgst)->plus($sgst)->plus($igst)->plus($freight)->plus($packing)
                ->plus($other)->plus(self::decimal($order->round_off)))) $invalid();
        return $subtotal;
    }

    /** Item taxes use the same pre-document-discount basis as ordinary purchases. */
    private function itemTaxes(?string $type, BigDecimal $taxable, BigDecimal $percent): array
    {
        $zero = BigDecimal::zero();
        if ($type === 'inter_state') {
            return [$zero, $zero, $taxable->multipliedBy($percent)->dividedBy(100, 3, RoundingMode::HalfUp)];
        }
        if ($type === 'intra_state') {
            $half = $taxable->multipliedBy($percent)->dividedBy(200, 3, RoundingMode::HalfUp);
            return [$half, $half, $zero];
        }
        return [$zero, $zero, $zero];
    }

    private static function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? '0'));
    }

    private function assertMoneyFits(BigDecimal $value): void
    {
        // Purchase monetary columns are DECIMAL(17,3) after the precision migration.
        if ($value->abs()->isGreaterThan('99999999999999.999')) {
            throw ValidationException::withMessages(['conversion' => 'A scaled price or total is too large to save. Ask Accounts to review the split; nothing was changed.']);
        }
    }
}
