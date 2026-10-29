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

    public function test_scaled_prices_keep_all_original_quantities_on_cash_and_purchase_bills(): void
    {
        $order = $this->order('INR', ['items' => [
            ['product_name' => 'First', 'description' => 'First details', 'hsn_sac' => '1001', 'quantity' => 1000, 'unit' => 'pcs', 'unit_price' => '1', 'gst_percent' => 0],
            ['product_name' => 'Second', 'quantity' => 1000, 'unit' => 'sets', 'unit_price' => '2', 'gst_percent' => 0],
            ['product_name' => 'Third', 'quantity' => 1000, 'unit' => 'box', 'unit_price' => '3', 'gst_percent' => 0],
        ]]);
        $cashPayment = $this->payment($order, '2000', 'cash');
        $bankPayment = $this->payment($order, '1000', 'bank_transfer');
        $cashflows = CashflowEntry::whereIn('id', [$cashPayment->cashflow_entry_id, $bankPayment->cashflow_entry_id])
            ->orderBy('id')->pluck('debit_amount', 'id')->all();
        $this->assertEqualsWithDelta(6000, (float) $order->total_amount, 0.000001);
        $this->get(route('purchase-invoices.show', $order))->assertOk()
            ->assertSee('full PO quantities')->assertSee('round off');

        $this->convert($order, 'cash', '3000', ['payments' => [$cashPayment->id => '2000', $bankPayment->id => '1000']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $cash = $order->partialConversions()->firstOrFail();
        $this->assertEqualsWithDelta(3000, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(3000, $cash->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(0, $order->fresh()->paidAmount(), 0.000001);
        $this->convert($order->fresh(), 'bill', '3000')->assertRedirect()->assertSessionHasNoErrors();
        $taxBill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();

        foreach ([$cash, $taxBill] as $bill) {
            $items = $bill->items()->orderBy('sort_order')->get();
            $this->assertCount(3, $items);
            $this->assertSame(['First', 'Second', 'Third'], $items->pluck('product_name')->all());
            $this->assertSame(['pcs', 'sets', 'box'], $items->pluck('unit')->all());
            $this->assertSame('First details', $items[0]->description);
            $this->assertSame('1001', $items[0]->hsn_sac);
            foreach ([0.5, 1, 1.5] as $i => $rate) {
                $this->assertEqualsWithDelta(1000, (float) $items[$i]->quantity, 0.000001);
                $this->assertEqualsWithDelta($rate, (float) $items[$i]->unit_price, 0.000001);
                $this->assertEqualsWithDelta(1000 * $rate, (float) $items[$i]->gross_amount, 0.000001);
            }
            $this->assertEqualsWithDelta(3000, (float) $bill->total_amount, 0.000001);
            $this->assertEqualsWithDelta(0, (float) $bill->round_off, 0.000001);
            $this->get(route('purchase-invoices.show', $bill))->assertOk()->assertSee('financial references');
            $this->get(route('purchase-invoices.print', $bill))->assertOk()->assertSee('financial references');
        }
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertSame([1.0, 2.0, 3.0], $order->items()->orderBy('sort_order')->get()->map(fn ($item) => (float) $item->unit_price)->all());
        $this->assertEqualsWithDelta(6000, (float) VendorPaymentEntry::where('vendor_id', $order->vendor_id)
            ->where('transaction_type', 'credit')->sum('foreign_amount'), 0.000001);
        $this->assertSame($cashflows, CashflowEntry::whereIn('id', array_keys($cashflows))->orderBy('id')->pluck('debit_amount', 'id')->all());

        $this->delete(route('purchase-invoices.destroy', $taxBill))->assertRedirect()->assertSessionHas('success');
        $this->delete(route('purchase-invoices.destroy', $cash))->assertRedirect()->assertSessionHas('success');
        $this->assertEqualsWithDelta(6000, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(3000, $order->fresh()->paidAmount(), 0.000001);
        $this->assertSame($cashflows, CashflowEntry::whereIn('id', array_keys($cashflows))->orderBy('id')->pluck('debit_amount', 'id')->all());
    }

    public function test_cash_prices_include_gst_discounts_and_charges_without_separate_components(): void
    {
        $order = $this->order('INR', ['gst_type' => 'inter_state', 'discount_type' => 'amount', 'discount_value' => '100',
            'freight_amount' => '100', 'packing_amount' => '50', 'other_charges' => '25', 'items' => [
                ['product_name' => 'A', 'quantity' => 100, 'unit_price' => '20', 'discount_percent' => 10, 'gst_percent' => 18],
                ['product_name' => 'B', 'quantity' => 100, 'unit_price' => '10', 'discount_percent' => 20, 'gst_percent' => 18],
            ]]);
        $this->assertEqualsWithDelta(3125, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'cash', '625')->assertRedirect()->assertSessionHasNoErrors();
        $cash = $order->partialConversions()->firstOrFail();
        $items = $cash->items()->orderBy('sort_order')->get();
        $this->assertCount(2, $items);
        $this->assertSame('export', $cash->gst_type);
        foreach ([4.167, 2.083] as $i => $rate) {
            $this->assertEqualsWithDelta(100, (float) $items[$i]->quantity, 0.000001);
            $this->assertEqualsWithDelta($rate, (float) $items[$i]->unit_price, 0.000001);
            $this->assertEqualsWithDelta(round(100 * $rate, 3), (float) $items[$i]->gross_amount, 0.000001);
            foreach (['discount_percent', 'discount_amount', 'gst_percent', 'cgst_amount', 'sgst_amount', 'igst_amount'] as $field) {
                $this->assertEqualsWithDelta(0, (float) $items[$i]->$field, 0.000001, $field);
            }
        }
        foreach (['discount_value', 'discount_amount', 'cgst_amount', 'sgst_amount', 'igst_amount', 'freight_amount', 'packing_amount', 'other_charges'] as $field) {
            $this->assertEqualsWithDelta(0, (float) $cash->$field, 0.000001, $field);
        }
        $this->assertEqualsWithDelta(625, (float) $cash->subtotal + (float) $cash->round_off, 0.000001);
        $this->assertEqualsWithDelta(2500, (float) $order->fresh()->total_amount, 0.000001);

        // The next purchase bill keeps the normal tax convention: line GST is
        // calculated before the fixed invoice discount; the header uses its tax ratio.
        $this->convert($order->fresh(), 'bill', '1250')->assertRedirect()->assertSessionHasNoErrors();
        $taxBill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $taxItems = $taxBill->items()->orderBy('sort_order')->get();
        $this->assertSame([8.0, 4.0], $taxItems->map(fn ($item) => (float) $item->unit_price)->all());
        $this->assertSame([100.0, 100.0], $taxItems->map(fn ($item) => (float) $item->quantity)->all());
        $this->assertEqualsWithDelta(80, (float) $taxItems[0]->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(80, (float) $taxItems[1]->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(129.6, (float) $taxItems[0]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(57.6, (float) $taxItems[1]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(40, (float) $taxBill->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(180, (float) $taxBill->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(40, (float) $taxBill->freight_amount, 0.000001);
        $this->assertEqualsWithDelta(20, (float) $taxBill->packing_amount, 0.000001);
        $this->assertEqualsWithDelta(10, (float) $taxBill->other_charges, 0.000001);
        $this->assertEqualsWithDelta(1250, (float) $taxBill->taxable_amount + (float) $taxBill->igst_amount
            + (float) $taxBill->freight_amount + (float) $taxBill->packing_amount + (float) $taxBill->other_charges + (float) $taxBill->round_off, 0.000001);
    }

    public function test_visible_price_rounding_preserves_exact_totals_and_never_fabricates_a_price(): void
    {
        $order = $this->order('INR', ['items' => [
            ['product_name' => 'Paid', 'quantity' => 1000, 'unit_price' => '1', 'gst_percent' => 0],
            ['product_name' => 'Free', 'quantity' => 1000, 'unit_price' => '0', 'gst_percent' => 0],
        ]]);
        $this->convert($order, 'cash', '333.333')->assertRedirect()->assertSessionHasNoErrors();
        $cash = $order->partialConversions()->firstOrFail();
        $paid = $cash->items()->orderBy('sort_order')->firstOrFail();
        $this->assertEqualsWithDelta(1000, (float) $paid->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.333, (float) $paid->unit_price, 0.000001);
        $this->assertEqualsWithDelta(333, (float) $paid->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(0.333, (float) $cash->round_off, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $cash->items()->where('product_name', 'Free')->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(333.333, (float) $cash->total_amount, 0.000001);
        $this->get(route('purchase-invoices.show', $cash))->assertOk()->assertSee('Round off')->assertSee('₹0.333');
        $this->get(route('purchase-invoices.print', $cash))->assertOk()->assertSee('Round off')->assertSee('₹0.333');

        $this->convert($order->fresh(), 'bill', '666.667')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $paid = $bill->items()->where('product_name', 'Paid')->firstOrFail();
        $this->assertEqualsWithDelta(1000, (float) $paid->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.667, (float) $paid->unit_price, 0.000001);
        $this->assertEqualsWithDelta(667, (float) $paid->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(-0.333, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(666.667, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->total_amount, 0.000001);
        $this->get(route('purchase-invoices.show', $bill))->assertOk()->assertSee('Round off')->assertSee('-₹0.333');
        $this->get(route('purchase-invoices.print', $bill))->assertOk()->assertSee('Round off')->assertSee('-₹0.333');

        // A genuinely zero three-decimal scaled rate must not be artificially
        // promoted to 0.001 to make the maths look closer; expose the residual.
        $tiny = $this->order('INR', ['items' => [['product_name' => 'Tiny rate', 'quantity' => 10000, 'unit_price' => '1', 'gst_percent' => 0]]]);
        $this->convert($tiny, 'bill', '1')->assertRedirect()->assertSessionHasNoErrors();
        $tinyBill = $tiny->partialConversions()->firstOrFail();
        $this->assertEqualsWithDelta(0, (float) $tinyBill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(1, (float) $tinyBill->round_off, 0.000001);
        $this->assertEqualsWithDelta(1, (float) $tinyBill->total_amount, 0.000001);
        $this->get(route('purchase-invoices.show', $tinyBill))->assertOk()->assertSee('A zero rate may be');
        $this->get(route('purchase-invoices.print', $tinyBill))->assertOk()->assertSee('A zero rate may be');
    }

    public function test_intra_state_percentage_discount_and_original_manual_round_off_are_preserved(): void
    {
        $order = $this->order('INR', ['gst_type' => 'intra_state', 'discount_type' => 'percent', 'discount_value' => '10',
            'freight_amount' => '5', 'round_off' => '0.05', 'items' => [['product_name' => 'Item',
                'quantity' => 1000, 'unit_price' => '1', 'gst_percent' => 18]]]);
        $this->assertEqualsWithDelta(1067.05, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'bill', '533.525')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $item = $bill->items()->firstOrFail();
        $this->assertEqualsWithDelta(1000, (float) $item->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.5, (float) $item->unit_price, 0.000001);
        $this->assertEqualsWithDelta(500, (float) $item->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(45, (float) $item->cgst_amount, 0.000001);
        $this->assertEqualsWithDelta(45, (float) $item->sgst_amount, 0.000001);
        $this->assertSame('percent', $bill->discount_type);
        $this->assertEqualsWithDelta(10, (float) $bill->discount_value, 0.000001);
        $this->assertEqualsWithDelta(50, (float) $bill->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(450, (float) $bill->taxable_amount, 0.000001);
        $this->assertEqualsWithDelta(40.5, (float) $bill->cgst_amount, 0.000001);
        $this->assertEqualsWithDelta(40.5, (float) $bill->sgst_amount, 0.000001);
        $this->assertEqualsWithDelta(2.5, (float) $bill->freight_amount, 0.000001);
        $this->assertEqualsWithDelta(0.025, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(533.525, (float) $bill->total_amount, 0.000001);

        $this->convert($order->fresh(), 'cash', '533.525')->assertRedirect()->assertSessionHasNoErrors();
        $cash = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $this->assertEqualsWithDelta(0.534, (float) $cash->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(-0.475, (float) $cash->round_off, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $cash->discount_amount + (float) $cash->cgst_amount
            + (float) $cash->sgst_amount + (float) $cash->freight_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_purchase_bill_recalculates_mixed_gst_rates_after_invoice_discount(): void
    {
        $order = $this->order('INR', ['gst_type' => 'inter_state', 'discount_type' => 'amount', 'discount_value' => 100,
            'freight_amount' => 30, 'items' => [
                ['product_name' => 'Five percent', 'quantity' => 100, 'unit_price' => 10, 'gst_percent' => 5],
                ['product_name' => 'Eighteen percent', 'quantity' => 100, 'unit_price' => 20, 'gst_percent' => 18],
            ]]);
        $this->assertEqualsWithDelta(3326.333, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'bill', '1663.167')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $items = $bill->items()->orderBy('sort_order')->get();
        $this->assertSame([100.0, 100.0], $items->map(fn ($item) => (float) $item->quantity)->all());
        $this->assertSame([5.0, 10.0], $items->map(fn ($item) => (float) $item->unit_price)->all());
        $this->assertSame([5.0, 18.0], $items->map(fn ($item) => (float) $item->gst_percent)->all());
        $this->assertSame([25.0, 180.0], $items->map(fn ($item) => (float) $item->igst_amount)->all());
        $this->assertEqualsWithDelta(50, (float) $bill->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(1450, (float) $bill->taxable_amount, 0.000001);
        $this->assertEqualsWithDelta(198.167, (float) $bill->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(15, (float) $bill->freight_amount, 0.000001);
        $this->assertEqualsWithDelta(1663.167, (float) $bill->taxable_amount + (float) $bill->igst_amount
            + (float) $bill->freight_amount + (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(1663.166, (float) $order->fresh()->total_amount, 0.000001);
        $this->get(route('purchase-invoices.print', $bill))->assertOk()->assertSee('5%')->assertSee('18%');
    }

    public function test_valid_legacy_float_halfway_item_gross_is_accepted(): void
    {
        $order = $this->order('INR', ['items' => [['product_name' => 'Halfway gross', 'quantity' => '0.29',
            'unit_price' => '0.05', 'gst_percent' => 0]]]);
        $item = $order->items()->firstOrFail();
        // The existing purchase controller computes round(0.29 * 0.05, 3)
        // with PHP floats as 0.014; exact decimal half-up would be 0.015.
        $this->assertEqualsWithDelta(0.014, (float) $item->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(0.014, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'cash', '0.007')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $this->assertEqualsWithDelta(0.29, (float) $bill->items()->firstOrFail()->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.025, (float) $bill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.007, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.007, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_valid_legacy_float_halfway_percentages_are_accepted_without_accepting_arbitrary_errors(): void
    {
        $order = $this->order('INR', ['gst_type' => 'inter_state', 'items' => [
            ['product_name' => 'GST halfway', 'quantity' => 29, 'unit_price' => '0.01', 'gst_percent' => 5],
            ['product_name' => 'Discount halfway', 'quantity' => 29, 'unit_price' => '0.01', 'discount_percent' => 5, 'gst_percent' => 0],
        ]]);
        $source = $order->items()->orderBy('sort_order')->get();
        // The existing controller used PHP floats, where 0.29 × 5% rounds to
        // 0.014 on this runtime rather than decimal half-up's 0.015.
        $this->assertEqualsWithDelta(0.014, (float) $source[0]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(0.014, (float) $source[1]->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(0.58, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'bill', '0.29')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $items = $bill->items()->orderBy('sort_order')->get();
        $this->assertSame([0.005, 0.005], $items->map(fn ($item) => (float) $item->unit_price)->all());
        $this->assertEqualsWithDelta(0.007, (float) $items[0]->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(0.007, (float) $items[1]->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(0.29, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.29, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_inconsistent_purchase_source_is_not_hidden_as_round_off(): void
    {
        foreach (['cash', 'bill'] as $kind) {
            $order = $this->order('INR', ['items' => [['product_name' => 'Tampered', 'quantity' => 1000, 'unit_price' => '1', 'gst_percent' => 0]]]);
            $payment = $this->payment($order, '100', 'cash');
            $flow = $payment->cashflow_entry_id;
            $before = CashflowEntry::findOrFail($flow)->debit_amount;
            $order->items()->update(['gross_amount' => '1001']);
            $this->convert($order, $kind, '500', ['payments' => [$payment->id => '100']])
                ->assertRedirect()->assertSessionHasErrors('conversion');
            $this->assertSame(0, $order->partialConversions()->count());
            $this->assertEqualsWithDelta(1000, (float) $order->fresh()->total_amount, 0.000001);
            $this->assertEqualsWithDelta(100, $order->fresh()->paidAmount(), 0.000001);
            $this->assertSame($before, CashflowEntry::findOrFail($flow)->debit_amount);
        }
        // A fully self-balanced yet incorrect item gross must also fail, even
        // when the discrepancy is only one thousandth.
        $tinyGross = $this->order('INR', ['items' => [['product_name' => 'Wrong gross',
            'quantity' => 1000, 'unit_price' => 1, 'gst_percent' => 0]]]);
        $tinyGross->items()->update(['gross_amount' => '1000.001', 'taxable_amount' => '1000.001', 'line_total' => '1000.001']);
        $tinyGross->update(['subtotal' => '1000.001', 'taxable_amount' => '1000.001', 'total_amount' => '1000.001']);
        $this->convert($tinyGross->fresh(), 'cash', '500')->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertSame(0, $tinyGross->partialConversions()->count());

        // A self-balanced but falsified GST amount must not be masked merely
        // because the tampered header and line total still add up.
        $wrongGst = $this->order('INR', ['gst_type' => 'inter_state', 'items' => [['product_name' => 'Wrong GST',
            'quantity' => 1000, 'unit_price' => 1, 'gst_percent' => 18]]]);
        $wrongGst->items()->update(['igst_amount' => 185, 'line_total' => 1185]);
        $wrongGst->update(['igst_amount' => 185, 'total_amount' => 1185]);
        foreach (['cash', 'bill'] as $kind) {
            $this->convert($wrongGst->fresh(), $kind, '500')->assertRedirect()->assertSessionHasErrors('conversion');
            $this->assertEqualsWithDelta(1185, (float) $wrongGst->fresh()->total_amount, 0.000001);
            $this->assertSame(0, $wrongGst->partialConversions()->count());
        }
        // A balanced but incorrect single thousandth is not automatically a
        // legacy float rounding error; it cannot be hidden in the new round-off.
        $tinyGst = $this->order('INR', ['gst_type' => 'inter_state', 'items' => [['product_name' => 'Wrong GST',
            'quantity' => 1000, 'unit_price' => 1, 'gst_percent' => 18]]]);
        $tinyGst->items()->update(['igst_amount' => '180.001', 'line_total' => '1180.001']);
        $tinyGst->update(['igst_amount' => '180.001', 'total_amount' => '1180.001']);
        $this->convert($tinyGst->fresh(), 'bill', '500')->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertEqualsWithDelta(1180.001, (float) $tinyGst->fresh()->total_amount, 0.000001);
        $this->assertSame(0, $tinyGst->partialConversions()->count());
        $noItems = $this->order();
        $noItems->items()->delete();
        $this->convert($noItems, 'cash', '500')->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertEqualsWithDelta(10000, (float) $noItems->fresh()->total_amount, 0.000001);
        $zero = $this->order('INR', ['freight_amount' => '1000', 'items' => [['product_name' => 'No value',
            'quantity' => 100, 'unit_price' => 0, 'gst_percent' => 0]]]);
        $this->convert($zero, 'cash', '500')->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertSame(0, $zero->partialConversions()->count());
    }

    public function test_converted_bill_packing_requires_actual_physical_quantities(): void
    {
        $order = $this->order('INR', ['items' => [['product_name' => 'Cartons', 'quantity' => 1000, 'unit_price' => '1', 'gst_percent' => 0]]]);
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($order->fresh(), 'bill', '500')->assertRedirect()->assertSessionHasNoErrors();
        foreach ($order->partialConversions()->get() as $bill) {
            $source = app(\App\Services\PackagingListSource::class)->purchaseInvoice($bill);
            $row = $source['cartons'][0]['items'][0];
            $this->assertSame('Cartons', $row['item_description']);
            $this->assertSame($bill->items()->firstOrFail()->id, $row['source_item_id']);
            $this->assertNull($row['source_quantity']);
            $this->assertSame('', $row['quantity_per_carton']);
            $this->assertSame('', $row['total_quantity']);
            $this->get(route('packaging-lists.create', ['purchase_invoice' => $bill->id]))->assertOk()
                ->assertSee('Enter actual physical quantities');
        }
        // Ordinary purchase bills still prefill actual source quantities as before.
        $regular = $this->order('INR', ['invoice_type' => 'bill', 'status' => 'received',
            'items' => [['product_name' => 'Regular', 'quantity' => 42, 'unit_price' => '1', 'gst_percent' => 0]]]);
        $this->assertTrue($regular->isBill());
        $source = app(\App\Services\PackagingListSource::class)->purchaseInvoice($regular);
        $this->assertEqualsWithDelta(42, $source['cartons'][0]['items'][0]['quantity_per_carton'], 0.000001);
    }

    public function test_existing_legacy_split_is_not_rewritten_when_a_new_bill_is_created(): void
    {
        $order = $this->order('INR', ['items' => [['product_name' => 'Original item', 'quantity' => 1000, 'unit_price' => 1, 'gst_percent' => 0]]]);
        $this->convert($order, 'cash', '400')->assertRedirect()->assertSessionHasNoErrors();
        $legacy = $order->partialConversions()->firstOrFail();
        // Represent the previously saved synthetic cash line; no migration of
        // historical invoice items should occur when another bill is created.
        $legacy->items()->delete();
        $legacy->items()->create(['product_name' => 'Legacy cash lot', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 400,
            'gross_amount' => 400, 'taxable_amount' => 400, 'line_total' => 400, 'sort_order' => 1]);
        $this->convert($order->fresh(), 'bill', '600')->assertRedirect()->assertSessionHasNoErrors();
        $freshLegacy = $legacy->fresh()->items()->firstOrFail();
        $this->assertSame('Legacy cash lot', $freshLegacy->product_name);
        $this->assertEqualsWithDelta(1, (float) $freshLegacy->quantity, 0.000001);
        $this->assertEqualsWithDelta(400, (float) $freshLegacy->unit_price, 0.000001);
        $newLine = $order->partialConversions()->reorder('id', 'desc')->firstOrFail()->items()->firstOrFail();
        $this->assertSame('Original item', $newLine->product_name);
        $this->assertEqualsWithDelta(1000, (float) $newLine->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.6, (float) $newLine->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->total_amount, 0.000001);
    }

    public function test_packing_verification_does_not_claim_linked_financial_quantities_are_physical(): void
    {
        $order = $this->order();
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $list = \App\Models\PackagingList::create(['packing_number' => 'PL-FINANCIAL', 'packing_date' => '2026-10-29',
            'source_type' => 'purchase_invoice', 'source_id' => $bill->id, 'purchase_invoice_id' => $bill->id,
            'from_name' => 'Supplier', 'bill_to_name' => 'Buyer', 'ship_to_name' => 'Buyer']);
        $carton = $list->cartons()->create(['carton_count' => 1, 'length_cm' => 30, 'width_cm' => 20,
            'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Manually counted', 'quantity_per_carton' => 10, 'total_quantity' => 10, 'unit' => 'pcs']);
        $check = collect(app(\App\Services\PackagingListVerifier::class)->inspect($list->fresh())['checks'])->firstWhere('key', 'source_allocation');
        $this->assertSame('warning', $check['status']);
        $this->assertStringContainsString('physical quantities', $check['message']);
        $this->get(route('packaging-lists.edit', $list))->assertOk()->assertSee('Enter actual physical quantities');
    }

    public function test_packaging_from_a_real_shipment_is_not_misclassified_by_an_associated_financial_bill(): void
    {
        $order = $this->order();
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $list = \App\Models\PackagingList::create(['packing_number' => 'PL-SHIPMENT', 'packing_date' => '2026-10-29',
            'source_type' => 'shipment', 'source_id' => 123, 'purchase_invoice_id' => $bill->id,
            'from_name' => 'Supplier', 'bill_to_name' => 'Buyer', 'ship_to_name' => 'Buyer']);
        $carton = $list->cartons()->create(['carton_count' => 1, 'length_cm' => 30, 'width_cm' => 20,
            'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3]);
        $carton->items()->create(['item_description' => 'Shipment item', 'source_type' => 'shipment', 'source_item_id' => 7,
            'source_quantity' => 20, 'quantity_per_carton' => 10, 'total_quantity' => 10, 'unit' => 'pcs']);
        $check = collect(app(\App\Services\PackagingListVerifier::class)->inspect($list->fresh())['checks'])->firstWhere('key', 'source_allocation');
        $this->assertSame('pass', $check['status']);
        $this->get(route('packaging-lists.edit', $list))->assertOk()->assertDontSee('Enter actual physical quantities');
    }

    public function test_manual_packing_quantities_are_saved_without_inventing_a_physical_bill_balance(): void
    {
        $order = $this->order('INR', ['items' => [['product_name' => 'Cartons', 'quantity' => 1000, 'unit_price' => 1, 'gst_percent' => 0]]]);
        $this->convert($order, 'cash', '500')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $line = $bill->items()->firstOrFail();
        $this->post(route('packaging-lists.store'), [
            'packing_number' => 'MP/PL/26-27/999', 'packing_date' => '2026-10-29', 'transport_mode' => 'road', 'status' => 'draft',
            'source_type' => 'purchase_invoice', 'source_id' => $bill->id, 'purchase_invoice_id' => $bill->id,
            'from_name' => 'Supplier', 'bill_to_name' => 'Buyer', 'ship_to_name' => 'Buyer',
            'cartons' => [['packing_mode' => 'grouped', 'carton_type' => 'box', 'carton_count' => 2,
                'length_cm' => 30, 'width_cm' => 20, 'height_cm' => 10, 'net_weight_per_carton' => 2, 'gross_weight_per_carton' => 3,
                'items' => [['source_type' => 'purchase_invoice', 'source_item_id' => $line->id, 'source_quantity' => null,
                    'item_description' => 'Cartons', 'quantity_per_carton' => 12, 'total_quantity' => 24, 'unit' => 'pcs']]]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $list = \App\Models\PackagingList::where('packing_number', 'MP/PL/26-27/999')->firstOrFail();
        $item = $list->cartons()->firstOrFail()->items()->firstOrFail();
        $this->assertSame($line->id, $item->source_item_id);
        $this->assertNull($item->source_quantity);
        $this->assertEqualsWithDelta(12, (float) $item->quantity_per_carton, 0.000001);
        $this->assertEqualsWithDelta(24, (float) $item->total_quantity, 0.000001);
        $check = collect(app(\App\Services\PackagingListVerifier::class)->inspect($list)['checks'])->firstWhere('key', 'source_allocation');
        $this->assertSame('warning', $check['status']);
        $this->get(route('packaging-lists.show', $list))->assertOk()->assertSee('Compare actual physical quantities');
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
        $this->assertEqualsWithDelta(100, (float) $children[1]->items->first()->quantity, 0.000001);
        $this->assertEqualsWithDelta(25, (float) $children[1]->items->first()->unit_price, 0.000001);
        $this->assertSame('SUP-001', $children[1]->vendor_bill_number);
        $this->assertEqualsWithDelta(11800, (float) VendorPaymentEntry::where('vendor_id', $order->vendor_id)->where('transaction_type', 'credit')->sum('foreign_amount'), 0.000001);
    }

    public function test_rmb_remainder_bill_scales_rate_and_preserves_payments(): void
    {
        $order = $this->order('RMB', ['items' => [['product_name' => 'Packaging', 'quantity' => 10000,
            'unit' => 'pcs', 'unit_price' => '2.1', 'gst_percent' => 0]]]);
        $this->assertEqualsWithDelta(21000, (float) $order->total_amount, 0.000001);
        $this->get(route('purchase-invoices.show', $order))->assertOk()->assertSee('full PO quantities')->assertSee('round off');
        $cashPayment = $this->payment($order, '9000', 'cash', 12);
        $bankPayment = $this->payment($order, '3000', 'bank_transfer', 12);
        $cashflowIds = [$cashPayment->cashflow_entry_id, $bankPayment->cashflow_entry_id];
        $cashflows = CashflowEntry::whereIn('id', $cashflowIds)->orderBy('id')->pluck('debit_amount', 'id')->all();

        $this->convert($order, 'cash', '9000', ['payments' => [$cashPayment->id => '9000']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $cashBill = $order->partialConversions()->firstOrFail();
        $this->convert($order->fresh(), 'bill', '12000', ['payments' => [$bankPayment->id => '3000'],
            'vendor_bill_number' => 'SUP-RMB'])->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $item = $bill->items()->firstOrFail();
        $this->assertEqualsWithDelta(10000, (float) $item->quantity, 0.000001);
        $this->assertEqualsWithDelta(1.2, (float) $item->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.9, (float) $cashBill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(10000, (float) $cashBill->items()->firstOrFail()->quantity, 0.000001);
        $this->assertEqualsWithDelta(12000, (float) $item->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(12000, (float) $item->line_total, 0.000001);
        $this->assertEqualsWithDelta(12000, (float) $bill->subtotal, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(12000, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(9000, $cashBill->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(3000, $bill->fresh()->paidAmount(), 0.000001);
        $this->assertEqualsWithDelta(21000, (float) VendorPaymentEntry::where('vendor_id', $order->vendor_id)
            ->where('transaction_type', 'credit')->sum('foreign_amount'), 0.000001);
        $this->assertSame($cashflows, CashflowEntry::whereIn('id', $cashflowIds)->orderBy('id')->pluck('debit_amount', 'id')->all());
        $this->assertSame(2, CashflowEntry::count());
        $this->get(route('purchase-invoices.show', $bill))->assertOk()->assertSee('10000')->assertSee('¥1.20')->assertSee('¥12,000');
        $this->get(route('purchase-invoices.print', $bill))->assertOk()->assertSee('10,000')->assertSee('¥1.20')->assertSee('¥12,000');

        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertEqualsWithDelta(12000, (float) $order->fresh()->total_amount, 0.000001);
        $this->assertEqualsWithDelta(3000, $order->fresh()->paidAmount(), 0.000001);
        $this->assertSame($cashflows, CashflowEntry::whereIn('id', $cashflowIds)->orderBy('id')->pluck('debit_amount', 'id')->all());
    }

    public function test_rate_rounding_uses_decimal_half_up_and_preserves_amount(): void
    {
        $order = $this->order('RMB', ['items' => [['product_name' => 'Packaging', 'quantity' => 10000,
            'unit_price' => '2.1', 'gst_percent' => 0]]]);
        $this->convert($order, 'cash', '12000')->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($order->fresh(), 'bill', '9000')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $this->assertEqualsWithDelta(10000, (float) $bill->items()->firstOrFail()->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.9, (float) $bill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(9000, (float) $bill->subtotal, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(9000, (float) $bill->total_amount, 0.000001);

        $boundary = $this->order('INR', ['items' => [['product_name' => 'Boundary', 'quantity' => 100,
            'unit_price' => '10', 'gst_percent' => 0]]]);
        // 10 × 1.05 / 1000 = 0.0105, which must become 0.011, not 0.010.
        $this->convert($boundary, 'bill', '1.05')->assertRedirect()->assertSessionHasNoErrors();
        $boundaryBill = $boundary->partialConversions()->firstOrFail();
        $this->assertEqualsWithDelta(100, (float) $boundaryBill->items()->firstOrFail()->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.011, (float) $boundaryBill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(1.1, (float) $boundaryBill->subtotal, 0.000001);
        $this->assertEqualsWithDelta(-0.05, (float) $boundaryBill->round_off, 0.000001);
        $this->assertEqualsWithDelta(1.05, (float) $boundaryBill->total_amount, 0.000001);
        $this->get(route('purchase-invoices.show', $boundaryBill))->assertOk()->assertSee('Round off')->assertSee('-₹0.05');
    }

    public function test_scaled_prices_recalculate_gst_and_reconcile_bill_total(): void
    {
        $order = $this->order('INR', ['gst_type' => 'inter_state', 'items' => [['product_name' => 'GST item',
            'quantity' => 10000, 'unit_price' => '2.1', 'gst_percent' => 18]]]);
        $this->assertEqualsWithDelta(24780, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'cash', '9000')->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($order->fresh(), 'bill', '15780')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $item = $bill->items()->firstOrFail();
        $this->assertEqualsWithDelta(10000, (float) $item->quantity, 0.000001);
        $this->assertEqualsWithDelta(1.337, (float) $item->unit_price, 0.000001);
        $this->assertEqualsWithDelta(13370, (float) $item->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(2406.6, (float) $item->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(2406.6, (float) $bill->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(3.4, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(15780, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(15780, (float) $bill->taxable_amount + (float) $bill->igst_amount + (float) $bill->round_off, 0.000001);
    }

    public function test_scaled_prices_preserve_discounts_gst_and_freight(): void
    {
        $order = $this->order('INR', ['gst_type' => 'inter_state', 'discount_type' => 'amount', 'discount_value' => '100',
            'freight_amount' => '7', 'items' => [['product_name' => 'Discounted item', 'quantity' => 10000,
                'unit_price' => '2.1', 'discount_percent' => 10, 'gst_percent' => 18]]]);
        $this->assertEqualsWithDelta(22191, (float) $order->total_amount, 0.000001);
        $this->convert($order, 'cash', '8801')->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($order->fresh(), 'bill', '13390')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $item = $bill->items()->firstOrFail();
        $this->assertEqualsWithDelta(10000, (float) $item->quantity, 0.000001);
        $this->assertEqualsWithDelta(1.267, (float) $item->unit_price, 0.000001);
        $this->assertEqualsWithDelta(12670, (float) $item->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(1267, (float) $item->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(2052.54, (float) $item->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(60.340, (float) $bill->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(2041.679, (float) $bill->igst_amount, 0.000001);
        $this->assertEqualsWithDelta(4.224, (float) $bill->freight_amount, 0.000001);
        $this->assertEqualsWithDelta(1.437, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(13390, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(13390, (float) $bill->taxable_amount + (float) $bill->igst_amount
            + (float) $bill->freight_amount + (float) $bill->round_off, 0.000001);
    }

    public function test_rounding_cannot_make_line_or_invoice_taxable_amount_negative(): void
    {
        $lineDiscount = $this->order('INR', ['items' => [
            ['product_name' => 'Free', 'quantity' => 10000, 'unit_price' => '2.1', 'discount_percent' => 100, 'gst_percent' => 0],
            ['product_name' => 'Paid', 'quantity' => 10000, 'unit_price' => '2.1', 'gst_percent' => 0],
        ]]);
        $this->convert($lineDiscount, 'cash', '12000')->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($lineDiscount->fresh(), 'bill', '9000')->assertRedirect()->assertSessionHasNoErrors();
        $free = $lineDiscount->partialConversions()->reorder('id', 'desc')->firstOrFail()->items()->where('product_name', 'Free')->firstOrFail();
        $this->assertEqualsWithDelta((float) $free->gross_amount, (float) $free->discount_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $free->taxable_amount, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $lineDiscount->fresh()->total_amount, 0.000001);

        // A fixed invoice discount of 9,000.001 exceeds the 9,000 gross after
        // rate rounding; the discount must not make the invoice taxable negative.
        $invoiceDiscount = $this->order('INR', ['discount_type' => 'amount', 'discount_value' => '21000', 'freight_amount' => '21000',
            'items' => [['product_name' => 'Discounted', 'quantity' => 10000, 'unit_price' => '2.1', 'gst_percent' => 0]]]);
        $this->convert($invoiceDiscount, 'cash', '11999.999')->assertRedirect()->assertSessionHasNoErrors();
        $this->convert($invoiceDiscount->fresh(), 'bill', '9000.001')->assertRedirect()->assertSessionHasErrors('conversion');
        $this->assertEqualsWithDelta(9000.001, (float) $invoiceDiscount->fresh()->total_amount, 0.000001);
        $this->assertSame(1, $invoiceDiscount->partialConversions()->count());
    }

    public function test_rate_rounding_allows_cancelling_lines_and_tiny_slices_with_exact_totals(): void
    {
        $order = $this->order('INR', ['items' => [
            ['product_name' => 'First', 'quantity' => 10, 'unit_price' => '12', 'gst_percent' => 0],
            ['product_name' => 'Second', 'quantity' => 20, 'unit_price' => '10', 'gst_percent' => 0],
        ]]);
        $this->convert($order, 'bill', '1.002')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $items = $bill->items()->orderBy('sort_order')->get();
        $this->assertEqualsWithDelta(10, (float) $items[0]->quantity, 0.000001);
        $this->assertEqualsWithDelta(20, (float) $items[1]->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.038, (float) $items[0]->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.031, (float) $items[1]->unit_price, 0.000001);
        $this->assertEqualsWithDelta(1, (float) $bill->subtotal, 0.000001);
        $this->assertEqualsWithDelta(0.002, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(1.002, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(318.998, (float) $order->fresh()->total_amount, 0.000001);

        $tiny = $this->order('INR', ['items' => [['product_name' => 'Tiny portion', 'quantity' => 1,
            'unit_price' => '100', 'gst_percent' => 0]]]);
        $this->convert($tiny, 'bill', '0.001')->assertRedirect()->assertSessionHasNoErrors();
        $tinyBill = $tiny->partialConversions()->firstOrFail();
        $this->assertEqualsWithDelta(1, (float) $tinyBill->items()->firstOrFail()->quantity, 0.000001);
        $this->assertEqualsWithDelta(0.001, (float) $tinyBill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0, (float) $tinyBill->round_off, 0.000001);
        $this->assertEqualsWithDelta(0.001, (float) $tinyBill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(99.999, (float) $tiny->fresh()->total_amount, 0.000001);
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

    public function test_price_rounding_is_visible_while_unrepresentable_fx_splits_are_atomic(): void
    {
        $order = $this->order('INR', ['items' => [['product_name' => 'Cartons', 'quantity' => 100, 'unit_price' => '1000', 'gst_percent' => 0]]]);
        $this->convert($order, 'bill', '33333.333')->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->firstOrFail();
        $this->assertEqualsWithDelta(100, (float) $bill->items()->firstOrFail()->quantity, 0.000001);
        $this->assertEqualsWithDelta(333.333, (float) $bill->items()->firstOrFail()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.033, (float) $bill->round_off, 0.000001);
        $this->assertEqualsWithDelta(33333.333, (float) $bill->total_amount, 0.000001);
        $this->assertEqualsWithDelta(66666.667, (float) $order->fresh()->total_amount, 0.000001);
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
