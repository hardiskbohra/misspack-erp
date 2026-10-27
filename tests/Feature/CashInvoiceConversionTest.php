<?php

namespace Tests\Feature;

use App\Http\Controllers\SalesInvoiceController;
use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\SalesInvoice;
use App\Services\CashInvoiceConversion;
use App\Services\CashflowLedger;
use App\Services\SalesInvoiceFilters;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashInvoiceConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Module fixture: avoid unrelated historical destructive migrations
        // (the legacy quote-removal migration cannot drop an indexed SQLite column).
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2026_06_09_020000_create_clients_table',
            '2026_06_10_010000_create_cashflow_accounts_table',
            '2026_06_10_010100_create_cashflow_categories_table',
            '2026_06_10_010200_create_cashflow_entries_table',
            '2026_07_18_010000_create_projects_table',
            '2026_07_18_010100_create_project_products_table',
            '2026_07_18_010700_add_project_id_to_cashflow_entries_table',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_10_27_140000_add_invoice_auto_round_total',
            '2026_08_26_010100_create_sales_invoice_items_table',
            '2026_08_26_010200_create_sales_invoice_attachments_table',
            '2026_08_29_000000_add_sales_invoice_id_to_cashflow_entries_table',
            '2026_10_03_050000_create_sales_invoice_reminders_table',
            '2026_10_03_060000_add_conversion_link_to_sales_invoices_table',
            '2026_10_06_060000_create_organisation_tables',
            '2026_10_15_100000_create_cashflow_allocations_table',
            '2026_10_18_170000_create_project_execution_workflow_tables',
            '2026_10_18_171000_add_structured_payment_terms_to_sales_invoices',
            '2026_10_19_120000_add_soft_deletes_to_projects',
            '2026_10_21_130000_add_cash_invoice_conversions',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
    }

    private function pi(array $attributes = []): SalesInvoice
    {
        $pi = SalesInvoice::create(array_merge([
            'invoice_number' => 'PI-'.(SalesInvoice::count() + 1), 'invoice_type' => 'proforma',
            'status' => 'sent', 'currency' => 'INR', 'gst_type' => 'intra_state',
            'invoice_date' => '2026-10-21', 'subtotal' => 100, 'taxable_amount' => 100,
            'cgst_amount' => 9, 'sgst_amount' => 9, 'igst_amount' => 0, 'total_amount' => 118,
            'amount_paid' => 0, 'seller_gstin' => 'SELLER-GST-SECRET', 'client_gstin' => 'BUYER-GST-SECRET',
            'seller_pan' => 'SELLER-PAN', 'client_pan' => 'BUYER-PAN',
            'terms_conditions' => SalesInvoice::defaultTerms(),
        ], $attributes));
        $pi->items()->create(['product_name' => 'Packaging', 'quantity' => 10, 'unit' => 'pcs',
            'unit_price' => 10, 'gross_amount' => 100, 'taxable_amount' => 100, 'gst_percent' => 18,
            'cgst_amount' => 9, 'sgst_amount' => 9, 'igst_amount' => 0, 'line_total' => 118]);
        return $pi;
    }

    private function convert(SalesInvoice $pi, float $value, float $transfer = 0, string $mode = 'amount'): SalesInvoice
    {
        (new SalesInvoiceController)->convertCash(Request::create('/', 'POST', [
            'conversion_mode' => $mode, 'conversion_value' => $value, 'transfer_received' => $transfer,
        ]), $pi, new CashInvoiceConversion);
        return SalesInvoice::where('invoice_type', 'cash')->latest('id')->firstOrFail();
    }

    private function receipt(?SalesInvoice $pi, float $credit = 100, string $currency = 'INR'): CashflowEntry
    {
        $account = CashflowAccount::firstOrCreate(['account_name' => 'Test bank'], ['currency' => $currency]);
        return CashflowEntry::create(['entry_date' => '2026-10-21', 'particular' => 'Advance',
            'transaction_type' => 'credit', 'credit_amount' => $credit, 'debit_amount' => 0,
            'currency' => $currency, 'account_id' => $account->id, 'accounting_status' => 'booked',
            'sales_invoice_id' => $pi?->id, 'project_id' => $pi?->project_id]);
    }

    private function pricedPi(array $items, array $attributes = []): SalesInvoice
    {
        (new SalesInvoiceController)->store(Request::create('/', 'POST', array_merge([
            'invoice_number' => null, 'invoice_type' => 'proforma', 'status' => 'sent', 'currency' => 'INR',
            'gst_type' => 'export', 'items' => $items,
        ], $attributes)));
        return SalesInvoice::where('invoice_type', 'proforma')->latest('id')->firstOrFail();
    }

    private function cashReceipt(SalesInvoice $pi, float $amount): CashflowEntry
    {
        $account = CashflowAccount::firstOrCreate(['account_name' => 'Cash drawer'], ['account_type' => 'cash', 'currency' => 'INR']);
        $entry = $this->receipt($pi, $amount);
        $entry->update(['account_id' => $account->id, 'payment_mode' => 'cash']);
        return $entry;
    }

    public function test_three_lakh_pi_keeps_the_bank_receipt_and_prorates_items_on_the_tax_remainder(): void
    {
        $pi = $this->pricedPi([
            ['product_name' => 'Bottles', 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 1000],
            ['product_name' => 'Cartons', 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 2000],
        ]);
        $this->assertSame(300000.0, (float) $pi->total_amount);
        // Deliberately record the bank receipt first: insertion order must not
        // consume that receipt instead of the cash payment.
        $bank = $this->receipt($pi, 50000);
        $cashReceipt = $this->cashReceipt($pi, 100000);
        foreach ([$bank->account_id, $cashReceipt->account_id] as $id) CashflowLedger::recalculateAccount($id);
        $balances = CashflowAccount::orderBy('id')->pluck('current_balance', 'id')->all();
        $cash = $this->convert($pi, 100000, 100000);
        $this->assertSame(100000.0, $cash->receivedAmount());
        $this->assertSame(0.0, $cash->balanceDue());
        $this->assertSame(0.0, $cash->advanceAmount());
        $this->assertSame(50000.0, $pi->fresh()->receivedAmount());
        $this->assertSame($pi->id, $bank->fresh()->sales_invoice_id);
        $this->assertSame($cash->id, $cashReceipt->fresh()->sales_invoice_id);
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame(200000.0, (float) $tax->total_amount);
        $this->assertSame(50000.0, $tax->receivedAmount());
        $this->assertSame(150000.0, $tax->balanceDue());
        $this->assertSame(100000.0, $cash->fresh()->receivedAmount());
        $this->assertSame($tax->id, $bank->fresh()->sales_invoice_id);
        $this->assertSame(['Bottles', 'Cartons'], $tax->items->pluck('product_name')->all());
        $this->assertSame(['100.000', '100.000'], $tax->items->pluck('quantity')->all());
        $this->assertSame(['666.67', '1333.33'], $tax->items->pluck('unit_price')->all());
        $this->assertSame(200000.0, (float) $tax->items->sum('line_total'));
        $this->assertSame(2, CashflowEntry::count());
        $this->assertSame(150000.0, (float) CashflowEntry::sum('credit_amount'));
        $this->assertSame($balances, CashflowAccount::orderBy('id')->pluck('current_balance', 'id')->all());
        $this->assertSame(300000.0, (float) SalesInvoice::notSuperseded()->sum(SalesInvoice::raw(SalesInvoice::RECEIVABLE_TOTAL_SQL)));
        $print = view('sales_invoices.print', ['invoice' => $tax, 'publicMode' => false])->render();
        $this->assertStringContainsString('Bottles', $print);
        $this->assertStringContainsString('Cartons', $print);
        $this->assertStringContainsString('unrounded proportion', $print);
        $this->assertStringNotContainsString('Remaining amount against', $print);
        $this->assertSame(['1000.00', '2000.00'], $pi->fresh()->items->pluck('unit_price')->all());
    }

    public function test_excess_transfer_is_rejected_atomically_even_when_the_pi_has_enough_receipts(): void
    {
        $pi = $this->pricedPi([['product_name' => 'Bottles', 'quantity' => 100, 'unit_price' => 3000]]);
        $bank = $this->receipt($pi, 50000);
        $cash = $this->cashReceipt($pi, 100000);
        try {
            $this->convert($pi, 100000, 150000);
            $this->fail('Cash Invoice must not be overpaid by conversion.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Cash Invoice value', $error->errors()['transfer_received'][0]);
        }
        $this->assertSame(1, SalesInvoice::count());
        $this->assertSame(150000.0, $pi->fresh()->receivedAmount());
        $this->assertSame(0.0, (float) $pi->cash_converted_base_amount);
        $this->assertSame($pi->id, $cash->fresh()->sales_invoice_id);
        $this->assertSame($pi->id, $bank->fresh()->sales_invoice_id);
    }

    public function test_percentage_conversion_caps_transfers_and_splits_only_the_cash_receipt(): void
    {
        $pi = $this->pricedPi([['product_name' => 'Bottles', 'quantity' => 100, 'unit_price' => 3000]]);
        $bank = $this->receipt($pi, 50000);
        $entry = $this->cashReceipt($pi, 100000);
        try {
            $this->convert($pi, 20, 100000, 'percent');
            $this->fail('20 percent is only 60,000, not 100,000.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('transfer_received', $error->errors());
        }
        $cash = $this->convert($pi, 20, 60000, 'percent');
        $this->assertSame(60000.0, (float) $cash->total_amount);
        $this->assertSame(60000.0, $cash->receivedAmount());
        $this->assertSame(90000.0, $pi->fresh()->receivedAmount());
        $this->assertSame($pi->id, $bank->fresh()->sales_invoice_id);
        $this->assertSame(100000.0, (float) $entry->fresh()->credit_amount);
        $this->assertSame(100000.0, (float) $entry->allocations()->sum('amount'));
        $this->assertSame(40000.0, (float) $entry->allocations()->where('sales_invoice_id', $pi->id)->sum('document_amount'));
    }

    public function test_cash_allocation_precedes_direct_bank_receipts_and_opening_credit(): void
    {
        $pi = $this->pi(['amount_paid' => 10]);
        $bank = $this->receipt($pi, 50);
        $cashReceipt = $this->cashReceipt($pi, 40);
        $cashReceipt->update(['sales_invoice_id' => null]);
        $slice = $cashReceipt->allocations()->create(['sales_invoice_id' => $pi->id, 'amount' => 40,
            'document_amount' => 40, 'currency' => 'INR', 'document_currency' => 'INR']);
        $cash = $this->convert($pi, 50, 40);
        $this->assertSame($cash->id, $slice->fresh()->sales_invoice_id);
        $this->assertSame($pi->id, $bank->fresh()->sales_invoice_id);
        $this->assertSame(10.0, (float) $pi->fresh()->amount_paid);
        $this->assertSame(60.0, $pi->receivedAmount());
        $this->assertSame(40.0, $cash->receivedAmount());
    }

    public function test_cash_payment_mode_is_prioritised_even_without_a_cash_account_type(): void
    {
        $pi = $this->pi();
        $bank = $this->receipt($pi, 50);
        $cashReceipt = $this->receipt($pi, 40);
        $cashReceipt->update(['payment_mode' => 'cash']);
        $cash = $this->convert($pi, 50, 40);
        $this->assertSame($cash->id, $cashReceipt->fresh()->sales_invoice_id);
        $this->assertSame($pi->id, $bank->fresh()->sales_invoice_id);
    }

    public function test_gst_inclusive_three_lakh_pi_restores_only_the_actual_taxable_remainder(): void
    {
        $pi = $this->pricedPi([['product_name' => 'Bottles', 'quantity' => 1, 'unit_price' => 254237.29,
            'gst_percent' => 18, 'hsn_sac' => '3923']], ['gst_type' => 'intra_state', 'round_off' => -0.01]);
        $this->assertSame(300000.0, (float) $pi->total_amount);
        $this->receipt($pi, 50000);
        $this->cashReceipt($pi, 100000);
        $cash = $this->convert($pi, 100000, 100000);
        $expected = $pi->fresh()->receivableTotal();
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame(182000.0, $expected);
        $this->assertSame($expected, (float) $tax->total_amount);
        $this->assertSame(50000.0, $tax->receivedAmount());
        $this->assertSame(100000.0, $cash->fresh()->receivedAmount());
        $this->assertSame('Bottles', $tax->items->first()->product_name);
        $this->assertSame('3923', $tax->items->first()->hsn_sac);
        $this->assertSame(18.0, (float) $tax->items->first()->gst_percent);
    }

    public function test_tax_remainder_keeps_metadata_discounts_gst_and_project_quantities(): void
    {
        $pi = $this->pricedPi([
            ['product_name' => 'Bottles', 'description' => 'Amber glass', 'hsn_sac' => '7010', 'quantity' => 100,
                'unit' => 'pcs', 'unit_price' => 1000, 'discount_percent' => 10, 'gst_percent' => 18, 'remarks' => 'Keep this remark'],
            ['product_name' => 'Cartons', 'description' => 'Printed cartons', 'hsn_sac' => '4819', 'quantity' => 50,
                'unit' => 'box', 'unit_price' => 2000, 'discount_percent' => 0, 'gst_percent' => 5],
        ], ['gst_type' => 'inter_state', 'discount_type' => 'percent', 'discount_value' => 10,
            'freight_amount' => 1000, 'packing_amount' => 500, 'other_charges' => 250, 'round_off' => 0.07]);
        $client = \App\Models\Client::create(['client_number' => 'CLIENT-P', 'company_name' => 'Buyer']);
        $project = new Project(['client_id' => $client->id, 'project_number' => 'PRJ-P', 'name' => 'Proration', 'currency' => 'INR']);
        $project->forceFill(['public_token' => 'proration'])->save();
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Bottles', 'quantity' => 100,
            'unit' => 'pcs', 'unit_price' => 1000]);
        $pi->update(['project_id' => $project->id]);
        $pi->items()->first()->update(['project_product_id' => $product->id, 'product_id' => 123]);
        $originalItems = $pi->items()->get();
        $this->convert($pi, 50000);
        $pi->refresh();
        $ratio = $pi->cashConvertibleRemaining() / $pi->cashConvertibleTotal();
        $expected = $pi->receivableTotal();
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame($expected, (float) $tax->total_amount);
        $this->assertSame('percent', $tax->discount_type);
        $this->assertSame(10.0, (float) $tax->discount_value);
        $this->assertSame(round((float) $pi->discount_amount * $ratio, 2), (float) $tax->discount_amount);
        $this->assertSame(round((float) $pi->igst_amount * $ratio, 2), (float) $tax->igst_amount);
        foreach ($tax->items as $index => $line) {
            foreach (['product_name', 'description', 'hsn_sac', 'quantity', 'unit', 'gst_percent', 'discount_percent', 'remarks', 'product_id', 'project_product_id', 'sort_order'] as $field) {
                $this->assertSame($originalItems[$index]->$field, $line->$field, $field);
            }
            $this->assertSame(round((float) $originalItems[$index]->unit_price * $ratio, 2), (float) $line->unit_price);
            $this->assertSame(round((float) $line->gross_amount - (float) $line->discount_amount, 2), (float) $line->taxable_amount);
        }
        $this->assertSame((float) $tax->subtotal, (float) $tax->items->sum('gross_amount'));
        $this->assertSame((float) $tax->taxable_amount, round((float) $tax->items->sum('taxable_amount') - (float) $tax->discount_amount, 2));
        $this->assertSame((float) $tax->total_amount, round((float) $tax->taxable_amount + (float) $tax->igst_amount
            + (float) $tax->freight_amount + (float) $tax->packing_amount + (float) $tax->other_charges + (float) $tax->round_off, 2));
        $this->assertSame(1, ProjectProduct::count());
        $this->assertSame(100.0, (float) $product->fresh()->quantity);
        $this->assertSame(1000.0, (float) $product->unit_price);
    }

    public function test_fixed_discounts_and_subcent_lines_reconcile_on_the_remainder(): void
    {
        // This exercises a fractional legacy/manual claim, not the new
        // default whole-rupee policy for eligible INR invoices.
        $pi = $this->pricedPi([
            ['product_name' => 'A', 'quantity' => 1, 'unit_price' => 0.01],
            ['product_name' => 'B', 'quantity' => 1, 'unit_price' => 0.01],
            ['product_name' => 'C', 'quantity' => 1, 'unit_price' => 0.01],
        ], ['discount_type' => 'amount', 'discount_value' => 0.01, 'auto_round_total' => false]);
        $this->convert($pi, 0.01);
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertCount(3, $tax->items);
        $this->assertSame(0.02, round((float) $tax->items->sum('taxable_amount'), 2));
        $this->assertSame(0.01, (float) $tax->discount_amount);
        $this->assertSame(0.01, (float) $tax->discount_value);
        $this->assertSame(0.01, (float) $tax->taxable_amount);
        $this->assertSame(0.01, (float) $tax->total_amount);
        $this->assertSame(0.0, (float) $tax->round_off);
    }

    public function test_several_partial_cash_conversions_leave_an_exact_itemised_tax_claim(): void
    {
        $pi = $this->pricedPi([
            ['product_name' => 'Bottles', 'quantity' => 3, 'unit_price' => 11.11, 'gst_percent' => 18],
            ['product_name' => 'Cartons', 'quantity' => 2, 'unit_price' => 22.22, 'gst_percent' => 5],
        ], ['gst_type' => 'intra_state', 'discount_type' => 'percent', 'discount_value' => 12.5,
            'freight_amount' => 2.03, 'round_off' => 0.01]);
        $first = $this->convert($pi, 15.01);
        $second = $this->convert($pi, 15.01);
        $pi->refresh();
        $remaining = $pi->receivableTotal();
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi);
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertCount(2, $tax->items);
        $this->assertSame($remaining, (float) $tax->total_amount);
        $this->assertSame((float) $pi->total_amount, round((float) $first->conversion_total_amount
            + (float) $second->conversion_total_amount + (float) $tax->total_amount, 2));
        $this->assertSame((float) $tax->total_amount, round((float) $tax->taxable_amount + (float) $tax->cgst_amount
            + (float) $tax->sgst_amount + (float) $tax->freight_amount + (float) $tax->round_off, 2));
        $this->assertSame((float) $tax->subtotal, round((float) $tax->items->sum('gross_amount'), 2));
        $this->assertSame(['3.000', '2.000'], $tax->items->pluck('quantity')->all());
        $print = view('sales_invoices.print', ['invoice' => $tax, 'publicMode' => false])->render();
        $this->assertStringNotContainsString('could not be allocated', $print);
    }

    public function test_a_pi_without_item_lines_does_not_fabricate_a_tax_remainder(): void
    {
        $pi = $this->pi(['amount_paid' => 20]);
        $this->convert($pi, 50);
        $pi->items()->delete();
        try {
            (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
            $this->fail('A tax invoice needs the original item lines.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('invoice', $error->errors());
        }
        $this->assertSame(0, SalesInvoice::where('invoice_type', 'tax')->count());
        $this->assertNull($pi->fresh()->converted_invoice_id);
        $this->assertSame(20.0, $pi->receivedAmount());
    }

    public function test_partial_conversion_removes_gst_and_keeps_only_the_uninvoiced_pi_remainder(): void
    {
        $pi = $this->pi(['amount_paid' => 40]);
        $cash = $this->convert($pi, 50, 15);
        $pi->refresh();
        $this->assertSame(50.0, (float) $cash->total_amount);
        $this->assertSame(0.0, (float) $cash->cgst_amount + (float) $cash->sgst_amount + (float) $cash->igst_amount);
        $this->assertNull($cash->seller_gstin);
        $this->assertNull($cash->client_pan);
        $this->assertSame($pi->id, $cash->source_proforma_id);
        $this->assertFalse($pi->isSuperseded());
        $this->assertSame(118.0, (float) $pi->total_amount);
        $this->assertSame(59.0, $pi->receivableTotal());
        $this->assertSame(25.0, $pi->receivedAmount());
        $this->assertSame(15.0, $cash->receivedAmount());
        $this->assertSame(34.0, $pi->balanceDue());
        $this->assertSame(35.0, $cash->balanceDue());
        $this->assertSame(0, CashflowEntry::count()); // opening credit moved, no new money
    }

    public function test_percentages_apply_to_the_remaining_base_and_multiple_children_reconcile(): void
    {
        $pi = $this->pi();
        $this->convert($pi, 50, 0, 'percent');
        $this->convert($pi, 50, 0, 'percent');
        $this->assertSame(25.0, $pi->fresh()->cashConvertibleRemaining());
        $last = $this->convert($pi, 100, 0, 'percent');
        $pi->refresh();
        $this->assertSame(3, $pi->cashInvoices()->count());
        $this->assertSame(100.0, (float) $pi->cashInvoices()->sum('total_amount'));
        $this->assertSame(118.0, (float) $pi->cashInvoices()->sum('conversion_total_amount'));
        $this->assertSame(0.0, $pi->receivableTotal());
        $this->assertSame($last->id, $pi->converted_invoice_id);
    }

    public function test_full_conversion_can_retain_a_chosen_pi_advance_without_losing_it(): void
    {
        $pi = $this->pi(['amount_paid' => 40]);
        $cash = $this->convert($pi, 100, 15);
        $pi->refresh();
        $this->assertSame(0.0, $pi->receivableTotal());
        $this->assertSame(25.0, $pi->advanceAmount());
        $this->assertFalse($pi->isSuperseded()); // retain the opening credit in statement queries
        $this->assertSame(15.0, $cash->receivedAmount());
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi);
        $this->assertSame(0, SalesInvoice::where('invoice_type', 'tax')->count());
    }

    public function test_partial_direct_receipt_becomes_two_slices_of_the_same_bank_entry(): void
    {
        $pi = $this->pi();
        $entry = $this->receipt($pi, 80);
        $cash = $this->convert($pi, 50, 30);
        $this->assertSame(1, CashflowEntry::count());
        $this->assertSame(80.0, (float) $entry->fresh()->credit_amount);
        $this->assertNull($entry->fresh()->sales_invoice_id);
        $this->assertSame(80.0, (float) $entry->allocations()->sum('amount'));
        $this->assertSame(50.0, $pi->fresh()->receivedAmount());
        $this->assertSame(30.0, $cash->fresh()->receivedAmount());
        $this->assertSame(30.0, $cash->fresh()->clientPortalReceivedAmount());
    }

    public function test_fx_allocation_splits_both_native_and_document_amounts_without_revaluation(): void
    {
        $pi = $this->pi(['currency' => 'USD']);
        $entry = $this->receipt(null, 8000);
        $entry->allocations()->create(['sales_invoice_id' => $pi->id, 'amount' => 8000,
            'document_amount' => 100, 'currency' => 'INR', 'document_currency' => 'USD']);
        $cash = $this->convert($pi, 50, 25);
        $this->assertSame(8000.0, (float) $entry->allocations()->sum('amount'));
        $this->assertSame(100.0, (float) $entry->allocations()->sum('document_amount'));
        $this->assertSame(2000.0, (float) $cash->cashflowAllocations()->sum('amount'));
        $this->assertSame(75.0, $pi->fresh()->receivedAmount());
        $this->assertSame(25.0, $cash->fresh()->receivedAmount());
        $this->assertSame('USD', $cash->currency);
    }

    public function test_unsupported_direct_fx_transfer_rolls_back_the_entire_conversion(): void
    {
        $pi = $this->pi(['currency' => 'USD', 'amount_paid' => 5]);
        $entry = $this->receipt($pi, 80, 'INR');
        try {
            $this->convert($pi, 50, 15);
            $this->fail('Direct mixed-currency receipts need an explicit allocation.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('transfer_received', $error->errors());
        }
        $this->assertSame(1, SalesInvoice::count());
        $this->assertSame(5.0, (float) $pi->fresh()->amount_paid);
        $this->assertSame(0.0, (float) $pi->fresh()->cash_converted_base_amount);
        $this->assertSame($pi->id, $entry->fresh()->sales_invoice_id);
        $this->assertSame(0, CashflowAllocation::count());
    }

    public function test_remainder_can_be_tax_invoiced_without_rebilling_cash_or_moving_cash_receipts(): void
    {
        $pi = $this->pi(['amount_paid' => 40]);
        $cash = $this->convert($pi, 50, 15);
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertSame(59.0, (float) $tax->total_amount);
        $this->assertSame(50.0, (float) $tax->taxable_amount);
        $this->assertSame(9.0, (float) $tax->cgst_amount + (float) $tax->sgst_amount);
        $this->assertSame(25.0, $tax->receivedAmount());
        $this->assertSame(15.0, $cash->fresh()->receivedAmount());
        $this->assertSame(0.0, $pi->fresh()->balanceDue());
        $this->assertSame(109.0, (float) SalesInvoice::notSuperseded()->sum(SalesInvoice::raw(SalesInvoice::RECEIVABLE_TOTAL_SQL)));
    }

    public function test_cash_print_has_no_gst_identifiers_or_tax_columns_even_with_a_stale_snapshot(): void
    {
        $cash = $this->convert($this->pi(), 50);
        $cash->seller_gstin = 'SHOULD-NOT-PRINT';
        $cash->client_pan = 'PRIVATE-PAN';
        $html = view('sales_invoices.print', ['invoice' => $cash, 'publicMode' => false])->render();
        $this->assertStringContainsString('Cash Invoice', $html);
        foreach (['SHOULD-NOT-PRINT', 'PRIVATE-PAN', 'GSTIN', 'HSN', 'CGST', 'SGST', 'IGST', 'GST %', 'Taxable'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
    }

    public function test_over_conversion_and_over_transfer_never_create_a_child(): void
    {
        $pi = $this->pi(['amount_paid' => 10]);
        foreach ([[101, 0], [50, 11]] as [$amount, $transfer]) {
            try {
                $this->convert($pi, $amount, $transfer);
                $this->fail('Invalid conversion should fail.');
            } catch (ValidationException) {
                $this->assertSame(1, SalesInvoice::count());
                $this->assertSame(10.0, $pi->fresh()->receivedAmount());
            }
        }
    }

    public function test_converted_sources_cannot_be_deleted_and_linked_documents_cannot_be_edited(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        foreach ([[$pi->fresh(), 'update'], [$cash, 'update'], [$pi->fresh(), 'destroy']] as [$invoice, $action]) {
            try {
                $controller = new SalesInvoiceController;
                $action === 'destroy' ? $controller->destroy($invoice) : $controller->update(Request::create('/', 'PATCH'), $invoice);
                $this->fail('Linked edits and original PI deletion must stay blocked.');
            } catch (ValidationException) {
                $this->assertModelExists($invoice);
            }
        }
    }

    public function test_direct_cash_creation_ignores_forged_tax_fields_and_uses_its_own_number_series(): void
    {
        $request = Request::create('/', 'POST', [
            'invoice_type' => 'cash', 'status' => 'draft', 'invoice_number' => null,
            'currency' => 'INR', 'invoice_date' => '2026-10-21', 'gst_type' => 'inter_state',
            'seller_gstin' => 'IGNORE-SELLER', 'client_gstin' => 'IGNORE-CLIENT', 'client_pan' => 'IGNORE-PAN',
            'terms_conditions' => null, 'freight_amount' => 10, 'discount_type' => 'percent', 'discount_value' => 10,
            'items' => [['product_name' => 'Cartons', 'quantity' => 2, 'unit_price' => 50,
                'discount_percent' => 0, 'gst_percent' => 18, 'hsn_sac' => '4819']],
        ]);
        (new SalesInvoiceController)->store($request);
        $cash = SalesInvoice::firstOrFail();
        $this->assertStringStartsWith('MP/CI/', $cash->invoice_number);
        $this->assertSame(100.0, (float) $cash->total_amount); // 100 - 10% + 10 freight, no 18% GST
        $this->assertSame(0.0, $cash->receivedAmount()); // "cash" is not an invented payment
        $this->assertSame('none', $cash->gst_type);
        $this->assertNull($cash->seller_gstin);
        $this->assertNull($cash->client_pan);
        $this->assertNull($cash->items->first()->hsn_sac);
        $this->assertSame(0.0, (float) $cash->items->first()->gst_percent);
        $this->assertStringNotContainsString('GST', $cash->terms_conditions);
    }

    public function test_rounding_charges_and_discounted_values_reconcile_after_three_partial_conversions(): void
    {
        $pi = $this->pi(['taxable_amount' => 83.33, 'freight_amount' => 10, 'round_off' => 0.01,
            'cgst_amount' => 7.50, 'sgst_amount' => 7.50, 'total_amount' => 108.34]);
        $this->convert($pi, 31.11);
        $this->convert($pi, 31.11);
        $this->convert($pi, 100, 0, 'percent');
        $this->assertSame(108.34, (float) $pi->cashInvoices()->sum('conversion_total_amount'));
        $this->assertSame(93.34, (float) $pi->cashInvoices()->sum('total_amount'));
        $this->assertSame(0.0, $pi->fresh()->cashConvertibleRemaining());
    }

    public function test_refunds_stay_intact_and_only_net_receipts_are_transferable(): void
    {
        $pi = $this->pi();
        $entry = $this->receipt($pi, 100);
        $refund = $this->receipt($pi, 0);
        $refund->update(['transaction_type' => 'debit', 'debit_amount' => 20]);
        $cash = $this->convert($pi, 100, 80);
        $this->assertSame(80.0, $cash->receivedAmount());
        $this->assertSame(0.0, $pi->fresh()->receivedAmount());
        $this->assertSame(2, CashflowEntry::count());
        $this->assertSame(100.0, (float) $entry->fresh()->credit_amount);
        $this->assertSame(20.0, (float) $refund->fresh()->debit_amount);
    }

    public function test_project_value_and_physical_products_do_not_double_count_amount_summary_invoices(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'CLIENT-P', 'company_name' => 'Buyer']);
        $project = new Project(['client_id' => $client->id, 'project_number' => 'PRJ-1', 'name' => 'Packaging', 'currency' => 'INR']);
        $project->forceFill(['public_token' => 'project-token'])->save();
        $pi = $this->pi(['project_id' => $project->id]);
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Packaging',
            'quantity' => 10, 'unit' => 'pcs', 'unit_price' => 10]);
        $entry = $this->receipt($pi, 80);
        $cash = $this->convert($pi, 50, 30);
        $this->assertSame(109.0, $project->fresh()->estimatedValue());
        $this->assertSame(1, ProjectProduct::count());
        $this->assertSame(10.0, (float) $product->fresh()->quantity);
        $this->assertSame(0, $entry->allocations()->whereNotNull('project_id')->count());
        (new SalesInvoiceController)->duplicate($cash);
        $this->assertSame(1, ProjectProduct::count());
        $copy = SalesInvoice::latest('id')->first();
        $this->assertNull($copy->source_proforma_id);
        $this->assertNull($copy->project_id);
    }

    public function test_allocated_receipts_are_never_classed_as_unpaid_and_residual_pi_can_be_paid(): void
    {
        $pi = $this->pi();
        $entry = $this->receipt($pi, 80);
        $cash = $this->convert($pi, 50, 30);
        $filter = new SalesInvoiceFilters;
        $unpaid = $filter->apply(SalesInvoice::query(), ['payment' => 'unpaid'])->pluck('id')->all();
        $this->assertNotContains($cash->id, $unpaid);
        $this->assertNotContains($pi->id, $unpaid);
        $topUp = $this->receipt(null, 9);
        $topUp->allocations()->create(['sales_invoice_id' => $pi->id, 'amount' => 9,
            'document_amount' => 9, 'currency' => 'INR', 'document_currency' => 'INR']);
        $paid = $filter->apply(SalesInvoice::query(), ['payment' => 'paid'])->pluck('id')->all();
        $this->assertContains($pi->id, $paid);
    }

    public function test_workflow_gates_include_every_cash_child_not_just_the_last_converted_invoice(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'CLIENT-W', 'company_name' => 'Buyer']);
        $project = new Project(['client_id' => $client->id, 'project_number' => 'PRJ-2', 'name' => 'Workflow', 'currency' => 'INR']);
        $project->forceFill(['public_token' => 'workflow-project'])->save();
        $pi = $this->pi(['project_id' => $project->id, 'balance_before_dispatch' => true,
            'advance_required' => true, 'advance_percentage' => 50]);
        $first = $this->convert($pi, 50);
        $last = $this->convert($pi, 50);
        $last->update(['amount_paid' => 50]);
        $workflow = \App\Models\ProjectWorkflow::create(['project_id' => $project->id,
            'commercial_sales_invoice_id' => $pi->id]);
        $evaluations = app(\App\Services\ProjectWorkflowGates::class)->evaluateAll($workflow);
        $dispatch = collect($evaluations)->firstWhere('gate_key', 'dispatch_balance');
        $this->assertSame('blocked', $dispatch->result);
        $this->assertEquals(100.0, $dispatch->details['invoice_total']);
        $this->assertEquals(50.0, $dispatch->details['received_amount']);
        $this->assertEquals(50.0, $dispatch->details['shortfall']);
        $first->update(['amount_paid' => 50]);
        $dispatch = collect(app(\App\Services\ProjectWorkflowGates::class)->evaluateAll($workflow))
            ->firstWhere('gate_key', 'dispatch_balance');
        $this->assertSame('passed', $dispatch->result);
    }

    public function test_statement_reconciles_original_opening_credit_and_effective_receivables(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'CLIENT-1', 'company_name' => 'Buyer', 'preferred_currency' => 'INR']);
        $pi = $this->pi(['client_id' => $client->id, 'amount_paid' => 40]);
        $this->convert($pi, 50, 15);
        $statement = app(\App\Services\PartyStatement::class)->build('client', $client->id, null, null);
        $this->assertSame(109.0, $statement['totals']['debit']);
        $this->assertSame(40.0, $statement['totals']['credit']);
        $this->assertSame(69.0, $statement['totals']['closing']);
    }

    public function test_stale_route_models_cannot_overwrite_a_pi_after_another_request_converts_it(): void
    {
        $stale = $this->pi();
        $this->convert($stale, 50);
        $this->assertFalse($stale->hasMonetaryConversions()); // deliberately stale request model
        try {
            (new SalesInvoiceController)->update(Request::create('/', 'PATCH', [
                'invoice_type' => 'proforma', 'status' => 'sent', 'currency' => 'INR',
                'gst_type' => 'intra_state', 'terms_conditions' => null, 'items' => [],
            ]), $stale);
            $this->fail('The transactional lock must recheck the current conversion state.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('invoice', $error->errors());
        }
        $this->assertSame(118.0, (float) $stale->fresh()->total_amount);
        $this->assertSame(1, $stale->fresh()->items()->count());
        $this->assertSame(59.0, $stale->fresh()->receivableTotal());
    }

    public function test_bulk_deletion_cannot_remove_linked_drafts(): void
    {
        $pi = $this->pi(['status' => 'draft']);
        $cash = $this->convert($pi, 50);
        (new SalesInvoiceController)->bulk(Request::create('/', 'POST', [
            'action' => 'delete_drafts', 'ids' => [$pi->id, $cash->id],
        ]));
        $this->assertModelExists($pi);
        $this->assertModelExists($cash);
    }

    public function test_migration_can_roll_back_before_use_without_changing_existing_invoice_money(): void
    {
        $pi = $this->pi(['amount_paid' => 40]);
        $migration = require database_path('migrations/2026_10_21_130000_add_cash_invoice_conversions.php');
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('sales_invoices', 'source_proforma_id'));
        $this->assertSame(118.0, (float) $pi->fresh()->total_amount);
        $migration->up();
        $this->assertSame(40.0, $pi->fresh()->receivedAmount());
        $this->assertSame(118.0, $pi->fresh()->receivableTotal());
    }

    public function test_migration_refuses_to_destroy_used_conversion_history(): void
    {
        $pi = $this->pi();
        $this->convert($pi, 50);
        try {
            (require database_path('migrations/2026_10_21_130000_add_cash_invoice_conversions.php'))->down();
            $this->fail('Dropping used conversion history would duplicate receivables.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('backup', $error->getMessage());
        }
        $this->assertSame(59.0, $pi->fresh()->receivableTotal());
        $this->assertSame(1, $pi->cashInvoices()->count());
    }

}
