<?php

namespace Tests\Integration;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Full-schema HTTP and accounting checks for purchase conversion splits. */
class PurchasePartialConversionsTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]));
        $this->actingAs(User::create(['name' => 'Accounts', 'email' => 'purchase-split@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
    }

    private function order(string $currency = 'INR', array $overrides = []): PurchaseInvoice
    {
        $vendor = Vendor::create(['vendor_number' => 'SPLIT-'.Vendor::count(), 'vendor_name' => 'Supplier']);
        $data = array_replace(['invoice_number' => 'PO-SPLIT-'.PurchaseInvoice::count(), 'invoice_type' => 'order',
            'status' => 'approved', 'vendor_id' => $vendor->id, 'currency' => $currency, 'gst_type' => 'export',
            'terms_conditions' => null, 'items' => [['product_name' => 'Packaging', 'quantity' => 1,
                'unit' => 'pcs', 'unit_price' => '10000', 'gst_percent' => 0]]], $overrides);
        $this->post(route('purchase-invoices.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        return PurchaseInvoice::latest('id')->firstOrFail();
    }

    private function payment(PurchaseInvoice $order, string $amount, string $mode, float $rate = 1): VendorPaymentEntry
    {
        $account = CashflowAccount::create(['account_name' => $mode.' '.CashflowAccount::count(),
            'account_type' => $mode === 'cash' ? 'cash' : 'current', 'currency' => 'INR', 'opening_balance' => '1000000']);
        $this->post(route('purchase-invoices.payments.store', $order), [
            'foreign_amount' => $amount, 'foreign_currency' => $order->currency, 'exchange_rate' => $rate,
            'amount_in_inr' => (string) ((float) $amount * $rate), 'transaction_date' => '2026-10-28',
            'particular' => $mode.' advance', 'status' => 'paid', 'payment_mode' => $mode,
            'paid_account_id' => $account->id, 'record_cashflow' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        return VendorPaymentEntry::where('purchase_invoice_id', $order->id)->where('transaction_type', 'debit')->latest('id')->firstOrFail();
    }

    private function convert(PurchaseInvoice $order, string $kind, string $value, array $sources = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/purchase-invoices/'.$order->id.'/partial-conversions', array_replace([
            'kind' => $kind, 'mode' => 'amount', 'value' => $value,
        ], $sources));
    }

    public function test_user_example_preserves_payment_provenance_in_all_three_currencies(): void
    {
        foreach (['INR' => 1, 'RMB' => 12, 'USD' => 82] as $currency => $rate) {
            $order = $this->order($currency);
            $cashPayment = $this->payment($order, '2000', 'cash', $rate);
            $bankPayment = $this->payment($order, '1000', 'bank_transfer', $rate);
            $this->assertEqualsWithDelta(3000, $order->fresh()->paidAmount(), 0.000001);
            $this->assertEqualsWithDelta(7000, $order->fresh()->openAmount(), 0.000001);
            $cashflowIds = [$cashPayment->cashflow_entry_id, $bankPayment->cashflow_entry_id];
            $cashflowBefore = CashflowEntry::whereIn('id', $cashflowIds)->orderBy('id')->pluck('debit_amount', 'id')->all();
            $this->post('/purchase-invoices/'.$order->id.'/partial-conversions', [
                'kind' => 'cash', 'mode' => 'amount', 'value' => '2500',
                'payments' => [$cashPayment->id => '2000', $bankPayment->id => '500'],
            ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
            $bill = PurchaseInvoice::where('purchase_order_id', $order->id)->where('conversion_kind', 'cash')->firstOrFail();
            $order->refresh();
            $this->assertEqualsWithDelta(7500, (float) $order->total_amount, 0.000001);
            $this->assertEqualsWithDelta(10000, (float) $order->original_total_amount, 0.000001);
            $this->assertEqualsWithDelta(500, $order->paidAmount(), 0.000001);
            $this->assertEqualsWithDelta(7000, $order->openAmount(), 0.000001);
            $this->assertEqualsWithDelta(2500, (float) $bill->total_amount, 0.000001);
            $this->assertEqualsWithDelta(2500, $bill->paidAmount(), 0.000001);
            $this->assertEqualsWithDelta(0, $bill->balanceDue(), 0.000001);
            $this->assertEqualsWithDelta(0, (float) $bill->cgst_amount + (float) $bill->sgst_amount + (float) $bill->igst_amount, 0.000001);
            $this->assertSame(2, VendorPaymentEntry::where('vendor_id', $order->vendor_id)->where('transaction_type', 'debit')->count());
            $this->assertSame($cashflowBefore, CashflowEntry::whereIn('id', $cashflowIds)->orderBy('id')->pluck('debit_amount', 'id')->all());
            $this->assertEqualsWithDelta(10000, (float) VendorPaymentEntry::where('vendor_id', $order->vendor_id)->where('transaction_type', 'credit')->sum('foreign_amount'), 0.000001);
            $this->get(route('purchase-invoices.show', $order))->assertOk()->assertSee('7,500')->assertSee('500');
        }
    }

    public function test_multiple_partial_bills_and_cash_reduce_the_gross_and_apportion_gst(): void
    {
        $order = $this->order('INR', ['gst_type' => 'inter_state', 'items' => [['product_name' => 'Packaging',
            'quantity' => 100, 'unit' => 'pcs', 'unit_price' => '100', 'gst_percent' => 18]]]);
        $this->assertEqualsWithDelta(11800, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'cash', '2950')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(8850, (float) $order->fresh()->total_amount, 0.000001);
        $this->convert($order->fresh(), 'bill', '2950', ['vendor_bill_number' => 'SUP-001', 'vendor_bill_date' => '2026-10-28'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($order->fresh(), 'bill', '5900', ['vendor_bill_number' => 'SUP-002'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $children = PurchaseInvoice::where('purchase_order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(3, $children);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(11800, (float) $children->sum('total_amount'), 0.000001);
        $this->assertEqualsWithDelta(0, (float) $children[0]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(450, (float) $children[1]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(900, (float) $children[2]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(25, (float) $children[1]->items->first()->quantity, 0.000001);
        $this->assertEqualsWithDelta(100, (float) $children[1]->items->first()->unit_price, 0.000001);
        $this->assertSame('SUP-001', $children[1]->vendor_bill_number);
        $this->assertEqualsWithDelta(11800, (float) VendorPaymentEntry::where('vendor_id', $order->vendor_id)->where('transaction_type', 'credit')->sum('foreign_amount'), 0.000001);
    }

    public function test_foreign_or_excess_payment_selections_fail_atomically_and_unselected_advance_stays(): void
    {
        $order = $this->order();
        $other = $this->order();
        $payment = $this->payment($order, '1000', 'cash');
        $otherPayment = $this->payment($other, '1000', 'cash');
        foreach ([['payments' => [$otherPayment->id => '100']], ['payments' => [$payment->id => '1001']],
            ['payments' => [$payment->id => '600'], 'opening' => '0.001']] as $invalid) {
            $this->convert($order, 'cash', '500', $invalid)->assertRedirect()->assertSessionHasErrors();
            $this->assertEqualsWithDelta(10000, (float) $order->fresh()->total_amount, 0.000001);
            $this->assertEqualsWithDelta(1000, $order->fresh()->paidAmount(), 0.000001);
            $this->assertSame(0, PurchaseInvoice::where('purchase_order_id', $order->id)->count());
        }
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $bill = PurchaseInvoice::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000, $order->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(0, $bill->paidAmount(), 0.000001);
    }

    public function test_latest_conversion_reversal_restores_claim_payments_and_bank_without_duplication(): void
    {
        $order = $this->order();
        $cash = $this->payment($order, '2000', 'cash');
        $bank = $this->payment($order, '1000', 'bank_transfer');
        $cashflows = CashflowEntry::whereIn('id', [$cash->cashflow_entry_id, $bank->cashflow_entry_id])->orderBy('id')->pluck('debit_amount', 'id')->all();
        $this->convert($order, 'cash', '2500', ['payments' => [$cash->id => '2000', $bank->id => '500']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $first = PurchaseInvoice::where('purchase_order_id', $order->id)->firstOrFail();
        $this->convert($order->fresh(), 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $last = PurchaseInvoice::where('purchase_order_id', $order->id)->latest('id')->firstOrFail();
        $this->delete(route('purchase-invoices.destroy', $first))->assertRedirect()->assertSessionHas('error');
        $this->delete(route('purchase-invoices.destroy', $last))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEqualsWithDelta(7500, (float) $order->fresh()->total_amount, 0.000001);
        $this->delete(route('purchase-invoices.destroy', $first))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $order->refresh();
        $this->assertEqualsWithDelta(10000, (float) $order->total_amount, 0.000001);
        $this->assertEqualsWithDelta(3000, $order->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(7000, $order->openAmount(), 0.000001);
        $this->assertNull($order->original_total_amount);
        $this->assertSame($order->id, $cash->fresh()->purchase_invoice_id);
        $this->assertSame($order->id, $bank->fresh()->purchase_invoice_id);
        $this->assertSame(0, \App\Models\PurchasePaymentSplit::count());
        $this->assertSame($cashflows, CashflowEntry::whereIn('id', array_keys($cashflows))->orderBy('id')->pluck('debit_amount', 'id')->all());
        $this->assertEqualsWithDelta(10000, (float) VendorPaymentEntry::where('vendor_id', $order->vendor_id)->where('transaction_type', 'credit')->sum('foreign_amount'), 0.000001);
    }

    public function test_opening_and_fx_allocation_split_and_reverse_without_changing_the_bank_entry(): void
    {
        $order = $this->order('RMB');
        $order->update(['amount_paid' => 500]);
        $account = CashflowAccount::create(['account_name' => 'FX cash', 'account_type' => 'current', 'currency' => 'INR', 'opening_balance' => '50000']);
        $this->post(route('cashflows.store'), [
            'entry_date' => '2026-10-28', 'particular' => 'FX advance', 'transaction_type' => 'debit',
            'amount' => '24000', 'currency' => 'INR', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'related_party_type' => 'vendor', 'vendor_id' => $order->vendor_id,
            'allocations' => [['purchase_invoice_id' => $order->id, 'amount' => '24000', 'document_amount' => '2000']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $cashflow = CashflowEntry::latest('id')->firstOrFail();
        $allocation = $cashflow->allocations()->firstOrFail();
        $this->assertEqualsWithDelta(2500, $order->fresh()->paidAmount(), 0.000001);
        $before = $cashflow->only(['debit_amount', 'account_id', 'currency', 'bank_reference_number']);
        $this->convert($order, 'cash', '1000', ['opening' => '250', 'allocations' => [$allocation->id => '750']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $bill = PurchaseInvoice::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertEqualsWithDelta(9000, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(1500, $order->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(1000, $bill->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(9000, (float) $bill->cashflowAllocations()->sum('amount'), 0.000001);
        $this->assertEqualsWithDelta(15000, (float) $order->cashflowAllocations()->sum('amount'), 0.000001);
        $this->assertSame($before, $cashflow->fresh()->only(array_keys($before)));
        // A cashflow allocation row is owned by the conversion until reversal.
        $billAllocation = $bill->cashflowAllocations()->firstOrFail();
        $this->delete(route('cashflows.allocations.destroy', [$cashflow, $billAllocation]))->assertRedirect()->assertSessionHasErrors();
        $this->delete(route('cashflows.destroy', $cashflow))->assertRedirect()->assertSessionHasErrors();
        $this->assertEqualsWithDelta(1000, $bill->fresh()->paidAmount(), 0.000001);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertEqualsWithDelta(2500, $order->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(24000, (float) $order->cashflowAllocations()->sum('amount'), 0.000001);
        $this->assertEqualsWithDelta(2000, (float) $order->cashflowAllocations()->sum('document_amount'), 0.000001);
        $this->assertSame($before, $cashflow->fresh()->only(array_keys($before)));
    }

    public function test_split_payment_is_not_unallocated_and_closure_retains_its_provenance(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'SPLIT-C', 'company_name' => 'Client', 'preferred_currency' => 'INR']);
        $project = \App\Models\Project::create(['project_number' => 'SPLIT-P', 'name' => 'Split project', 'client_id' => $client->id, 'currency' => 'INR']);
        $order = $this->order('INR', ['project_id' => $project->id]);
        $payment = $this->payment($order, '1000', 'bank_transfer');
        $this->convert($order, 'cash', '500', ['payments' => [$payment->id => '500']])->assertRedirect()->assertSessionHasNoErrors();
        $bill = PurchaseInvoice::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertEqualsWithDelta(10000, $project->fresh()->budgetAmount(), 0.000001);
        $this->assertEqualsWithDelta(500, $order->fresh()->billedAmount(), 0.000001);
        $this->assertSame(0, app(\App\Services\UnallocatedCashflows::class)->forVendor($order->vendor_id)['count']);
        $snapshot = app(\App\Services\ProjectFinancialClosure::class)->snapshot($project->fresh());
        $payable = collect($snapshot['payables'])->firstWhere('source_id', $bill->id);
        $this->assertEqualsWithDelta(500, $payable['allocated_payments'], 0.000001);
        $source = collect($payable['settlement_sources'])->firstWhere('source', 'vendor_payment_split');
        $this->assertNotNull($source);
        $this->assertSame($payment->id, $source['vendor_payment_entry_id']);
        $this->assertSame($payment->cashflow_entry_id, $source['cashflow_entry_id']);
        $this->assertEqualsWithDelta(500, $source['amount'], 0.000001);
        $this->get(route('vendors.show', ['vendor' => $order->vendor_id, 'tab' => 'money']))
            ->assertOk()->assertSee('Linked conversion')->assertSee($order->invoice_number)->assertSee($bill->invoice_number);
        $entries = VendorPaymentEntry::where('vendor_id', $order->vendor_id)->with(['purchaseInvoice', 'purchaseSplits.purchaseInvoice'])->get();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        foreach ($entries as $entry) $entry->protectedByPurchaseConversion();
        $this->assertSame([], \Illuminate\Support\Facades\DB::getQueryLog(), 'Eager-loaded conversion protection must not add per-payment schema or ledger queries.');
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->get(route('purchase-invoices.print', $order))->assertOk()->assertSee('Original PO total')->assertSee('Less linked bills')->assertSee('9,000');
    }

    public function test_unrepresentable_item_and_fx_splits_are_atomic(): void
    {
        $order = $this->order('INR', ['items' => [['product_name' => 'Cartons', 'quantity' => 100, 'unit_price' => '1000', 'gst_percent' => 0]]]);
        $this->convert($order, 'bill', '33333.333')->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertEqualsWithDelta(100000, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertSame(0, $order->partialConversions()->count());
        $fx = $this->order('USD');
        $account = CashflowAccount::create(['account_name' => 'Tiny FX', 'currency' => 'INR']);
        $this->post(route('cashflows.store'), [
            'entry_date' => '2026-10-28', 'particular' => 'Tiny FX payment', 'transaction_type' => 'debit',
            'amount' => '0.001', 'currency' => 'INR', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'related_party_type' => 'other', 'related_party_name' => 'FX',
            'allocations' => [['purchase_invoice_id' => $fx->id, 'amount' => '0.001', 'document_amount' => '1000']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $allocation = CashflowEntry::latest('id')->firstOrFail()->allocations()->firstOrFail();
        $this->convert($fx, 'cash', '1', ['allocations' => [$allocation->id => '0.001']])->assertRedirect()->assertSessionHasErrors('allocations');
        $this->assertEqualsWithDelta(10000, (float) $fx->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(1000, $fx->fresh()->paidAmount(), 0.000001);
        $this->assertSame(0, $fx->partialConversions()->count());
    }

    public function test_repeated_manual_splits_and_full_transferred_payment_are_protected(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, '2000', 'bank_transfer');
        $whole = $this->payment($order, '200', 'cash');
        $this->convert($order, 'cash', '500', ['payments' => [$payment->id => '500']])->assertRedirect()->assertSessionHasNoErrors();
        $first = $order->partialConversions()->firstOrFail();
        $remainingSlice = $order->paymentSplits()->where('vendor_payment_entry_id', $payment->id)->firstOrFail();
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'percent',
            'value' => '10', 'splits' => [$remainingSlice->id => '500'], 'payments' => [$whole->id => '200']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $second = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $this->assertEqualsWithDelta(950, (float) $second->total_amount, 0.000001);
        $this->assertEqualsWithDelta(700, $second->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(1000, $order->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(8550, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(2200, $order->fresh()->paidAmount() + $first->fresh()->paidAmount() + $second->fresh()->paidAmount(), 0.000001);
        // Even a fully transferred original payment is evidence for the split.
        $this->delete(route('vendors.payments.destroy', $whole))->assertRedirect()->assertSessionHas('error');
        $this->delete(route('cashflows.destroy', $whole->cashflowEntry))->assertRedirect()->assertSessionHasErrors();
        $this->assertNotNull($whole->fresh());
        $this->assertEqualsWithDelta(700, $second->fresh()->paidAmount(), 0.000001);
        $this->patch(route('purchase-invoices.conversion-reference', $second), ['vendor_bill_number' => 'SUP-UPDATED', 'vendor_bill_date' => '2026-10-27'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('SUP-UPDATED', $second->fresh()->vendor_bill_number);
        $this->assertEqualsWithDelta(950, (float) $second->fresh()->total_amount, 0.000001);
        $this->assertSame('SUP-UPDATED', $second->fresh()->ledgerEntries()->where('transaction_type', 'credit')->firstOrFail()->invoice_number);
    }

    public function test_stale_controller_models_cannot_cancel_or_delete_a_newly_split_order(): void
    {
        $order = $this->order();
        $stale = PurchaseInvoice::findOrFail($order->id);
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $request = \Illuminate\Http\Request::create('/', 'PATCH', ['status' => 'cancelled']);
        $controller = app(\App\Http\Controllers\PurchaseInvoiceController::class);
        $controller->updateStatus($request, $stale);
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertNotNull($order->fresh());
        $controller->destroy($stale);
        $this->assertNotNull($order->fresh());
        $this->assertSame(1, $order->partialConversions()->count());
        $this->assertEqualsWithDelta(9500, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_fully_converted_order_cannot_receive_a_new_advance(): void
    {
        $order = $this->order();
        $this->convert($order, 'cash', '10000')->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('purchase-invoices.payments.store', $order), ['foreign_amount' => '1', 'foreign_currency' => 'INR'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertEqualsWithDelta(0, $order->fresh()->paidAmount(), 0.000001);
    }

    public function test_stale_order_cannot_record_a_payment_after_full_conversion(): void
    {
        $order = $this->order();
        $stale = PurchaseInvoice::findOrFail($order->id);
        $this->convert($order, 'cash', '10000')->assertRedirect()->assertSessionHasNoErrors();
        $request = \Illuminate\Http\Request::create('/', 'POST', ['foreign_amount' => '1', 'foreign_currency' => 'INR']);
        app(\App\Http\Controllers\PurchaseInvoiceController::class)->recordPayment($request, $stale);
        $this->assertEqualsWithDelta(0, $order->fresh()->paidAmount(), 0.000001);
        $this->assertSame(0, $order->ledgerEntries()->where('transaction_type', 'debit')->count());
    }

    public function test_reversed_bill_number_is_reserved_against_automatic_and_manual_reuse(): void
    {
        $order = $this->order();
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $first = $order->partialConversions()->firstOrFail();
        $number = $first->invoice_number;
        $this->delete(route('purchase-invoices.destroy', $first))->assertRedirect()->assertSessionHas('success');
        $this->convert($order->fresh(), 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame($number, $order->partialConversions()->firstOrFail()->invoice_number);
        $this->post(route('purchase-invoices.store'), ['invoice_number' => $number, 'invoice_type' => 'bill', 'status' => 'draft',
            'vendor_id' => $order->vendor_id, 'currency' => 'INR', 'gst_type' => 'export', 'terms_conditions' => null,
            'items' => [['product_name' => 'Packaging', 'quantity' => 1, 'unit_price' => 100, 'gst_percent' => 0]]])
            ->assertRedirect()->assertSessionHasErrors('invoice_number');
    }

    public function test_reversal_refuses_downstream_packaging_and_manual_credits(): void
    {
        $order = $this->order();
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $packingId = \Illuminate\Support\Facades\DB::table('packaging_lists')->insertGetId([
            'packing_number' => 'SPLIT-PACK', 'packing_date' => '2026-10-28', 'purchase_invoice_id' => $bill->id,
            'from_name' => 'Supplier', 'bill_to_name' => 'Buyer', 'ship_to_name' => 'Buyer',
        ]);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->assertEqualsWithDelta(9500, (float) $order->fresh()->total_amount, 0.000001);
        \Illuminate\Support\Facades\DB::table('packaging_lists')->where('id', $packingId)->delete();
        $extra = VendorPaymentEntry::create(['vendor_id' => $order->vendor_id, 'purchase_invoice_id' => $bill->id,
            'transaction_date' => '2026-10-28', 'foreign_currency' => 'INR', 'foreign_amount' => 1,
            'transaction_type' => 'credit', 'entry_category' => 'adjustment', 'particular' => 'Manual credit', 'status' => 'booked']);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($extra->fresh());
        $extra->delete();
        $posting = VendorPaymentEntry::where('purchase_invoice_id', $bill->id)->where('transaction_type', 'credit')->firstOrFail();
        $originalRemarks = $posting->remarks;
        $posting->update(['remarks' => 'Manually changed credit']);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->patch(route('purchase-invoices.conversion-reference', $bill), ['vendor_bill_number' => 'NEW-REF'])
            ->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertSame('Manually changed credit', $posting->fresh()->remarks);
        $posting->update(['remarks' => $originalRemarks]);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertEqualsWithDelta(10000, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_reversal_refuses_vendor_collaboration_and_share_links(): void
    {
        $order = $this->order();
        $this->convert($order, 'bill', '500')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $share = \Illuminate\Support\Facades\DB::table('vendor_share_links')->insertGetId([
            'vendor_id' => $order->vendor_id, 'token' => 'test-split-link', 'label' => 'Bill link',
            'resource_type' => 'purchase_invoice', 'resource_id' => $bill->id, 'permissions' => '[]',
        ]);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($bill->fresh());
        \Illuminate\Support\Facades\DB::table('vendor_share_links')->where('id', $share)->delete();
        $update = \Illuminate\Support\Facades\DB::table('vendor_collaboration_updates')->insertGetId([
            'vendor_id' => $order->vendor_id, 'purchase_invoice_id' => $bill->id, 'status' => 'noted',
            'message' => 'Supplier response', 'author_type' => 'staff',
        ]);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->assertEqualsWithDelta(9500, (float) $order->fresh()->total_amount, 0.000001);
        \Illuminate\Support\Facades\DB::table('vendor_collaboration_updates')->where('id', $update)->delete();
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertEqualsWithDelta(10000, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_purchase_reader_and_guest_cannot_create_or_change_conversions(): void
    {
        $order = $this->order();
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '500'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $reader = new User(['role' => 'admin', 'name' => 'Reader', 'email' => 'purchase-reader@example.test',
            'access_profile' => 'custom', 'permissions' => ['purchase.view']]);
        $reader->id = 9102;
        $this->actingAs($reader)->get(route('purchase-invoices.show', $order))->assertOk();
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '500'])->assertForbidden();
        $this->patch(route('purchase-invoices.conversion-reference', $bill), ['vendor_bill_number' => 'FORGED'])->assertForbidden();
        $this->delete(route('purchase-invoices.destroy', $bill))->assertForbidden();
        auth()->logout();
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '500'])->assertRedirect(route('login'));
        $this->assertNull($bill->fresh()->vendor_bill_number);
        $this->assertEqualsWithDelta(9500, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertSame(1, $order->partialConversions()->count());
    }

    public function test_linked_documents_and_split_payment_cannot_be_mutated_or_deleted_out_of_band(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, '1000', 'cash');
        $this->convert($order, 'cash', '500', ['payments' => [$payment->id => '500']])->assertRedirect()->assertSessionHasNoErrors();
        $bill = PurchaseInvoice::where('purchase_order_id', $order->id)->firstOrFail();
        $this->put(route('purchase-invoices.update', $order), ['invoice_type' => 'order', 'status' => 'approved',
            'currency' => 'INR', 'gst_type' => 'export', 'terms_conditions' => null,
            'items' => [['product_name' => 'Tampered', 'quantity' => 1, 'unit_price' => '1']]])
            ->assertRedirect()->assertSessionHas('error');
        $this->patch(route('purchase-invoices.status', $bill), ['status' => 'cancelled'])->assertRedirect()->assertSessionHas('error');
        $this->delete(route('purchase-invoices.destroy', $order))->assertRedirect()->assertSessionHas('error');
        $this->delete(route('vendors.payments.destroy', $payment))->assertRedirect()->assertSessionHas('error');
        $this->delete(route('cashflows.destroy', $payment->cashflowEntry))->assertRedirect()->assertSessionHasErrors();
        $this->assertNotNull($payment->fresh());
        $this->assertNotNull($payment->fresh()->cashflowEntry);
        $this->assertEqualsWithDelta(9500, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(500, $order->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(500, $bill->fresh()->paidAmount(), 0.000001);
    }
}
