<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve the old integer range while adding a thousandth to linked money.
 * MySQL DDL commits independently: deploy with writers stopped and a backup.
 */
return new class extends Migration
{
    /** Old precision, nullable, and default (null means no default). */
    private function columns(): array
    {
        $header = array_fill_keys([
            'subtotal', 'discount_value', 'discount_amount', 'taxable_amount', 'cgst_amount', 'sgst_amount',
            'igst_amount', 'freight_amount', 'packing_amount', 'other_charges', 'round_off', 'total_amount',
            'amount_paid', 'balance_amount',
        ], [16, false, 0]);
        $items = array_fill_keys([
            'unit_price', 'gross_amount', 'discount_amount', 'taxable_amount', 'cgst_amount', 'sgst_amount',
            'igst_amount', 'line_total',
        ], [16, false, 0]);

        return [
            'sales_invoices' => $header + array_fill_keys([
                'conversion_base_amount', 'conversion_total_amount', 'cash_converted_base_amount', 'cash_converted_total_amount',
            ], [16, false, 0]),
            'sales_invoice_items' => $items,
            'purchase_invoices' => $header,
            'purchase_invoice_items' => $items,
            'project_products' => ['unit_price' => [16, false, 0], 'total_amount' => [16, false, 0]],
            'cashflow_accounts' => ['opening_balance' => [16, false, 0], 'current_balance' => [16, false, 0]],
            'cashflow_entries' => ['credit_amount' => [16, false, 0], 'debit_amount' => [16, false, 0], 'balance' => [16, true, null]],
            'cashflow_allocations' => ['amount' => [18, false, null], 'document_amount' => [18, true, null]],
            'cashflow_recurrence_rules' => ['amount' => [15, false, null]],
            'cashflow_attachments' => ['amount' => [16, true, null]],
            'vendor_payment_entries' => ['amount_in_inr' => [16, false, 0]],
        ];
    }

    public function up(): void
    {
        $this->changeScale(3);
    }

    public function down(): void
    {
        // Validate every table before changing any of them. A MySQL ALTER cannot
        // be rolled back if a later table fails, and silently losing a thousandth
        // is unacceptable. Writers must be stopped for the full rollback.
        foreach ($this->columns() as $name => $columns) {
            if (! Schema::hasTable($name)) continue;
            $present = array_filter(array_keys($columns), fn ($column) => Schema::hasColumn($name, $column));
            if (! $present) continue;
            $grammar = DB::connection()->getQueryGrammar();
            $lossy = DB::table($name)->where(function ($query) use ($present, $grammar) {
                foreach ($present as $column) {
                    $wrapped = $grammar->wrap($column);
                    $query->orWhereRaw("{$wrapped} <> ROUND({$wrapped}, 2)");
                }
            })->exists();
            if ($lossy) {
                throw new RuntimeException("Cannot roll back three-decimal precision: {$name} contains values that would lose precision. Stop writers and restore a consistent backup instead.");
            }
        }
        $this->changeScale(2);
    }

    private function changeScale(int $scale): void
    {
        foreach ($this->columns() as $name => $columns) {
            if (! Schema::hasTable($name)) continue;
            $present = array_filter($columns, fn ($column) => Schema::hasColumn($name, $column), ARRAY_FILTER_USE_KEY);
            if (! $present) continue;
            Schema::table($name, function (Blueprint $table) use ($present, $scale) {
                foreach ($present as $name => [$oldPrecision, $nullable, $default]) {
                    $column = $table->decimal($name, $oldPrecision + ($scale - 2), $scale);
                    if ($nullable) $column->nullable();
                    if ($default !== null) $column->default($default);
                    $column->change();
                }
            });
        }
    }
};
