<?php

namespace Tests\Integration;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\EInvoice\EInvoicePayload;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Full-schema, real HTTP checks for the user's three-decimal decisions. */
class ThreeDecimalPrecisionTest extends TestCase
{
    use IsolatedDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]));
        $this->actingAs(User::create(['name' => 'Accounts', 'email' => 'three-decimal@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
    }

    private function salesData(string $type = 'proforma', string $rate = '0.574', array $overrides = []): array
    {
        return array_replace([
            'invoice_number' => null, 'invoice_type' => $type, 'status' => 'draft', 'currency' => 'INR',
            'gst_type' => $type === 'cash' ? 'none' : 'export', 'auto_round_total' => '0', 'round_off' => 0,
            'items' => [['product_name' => 'Cartons', 'quantity' => '1', 'unit' => 'pcs', 'unit_price' => $rate, 'gst_percent' => 0]],
        ], $overrides);
    }

    private function createSales(array $data): SalesInvoice
    {
        $this->post(route('sales-invoices.store'), $data)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        return SalesInvoice::latest('id')->firstOrFail();
    }

    private function cashData(CashflowAccount $account, string $amount, string $type = 'credit', array $extra = []): array
    {
        return array_replace([
            'entry_date' => '2026-10-28', 'particular' => 'Precision fixture', 'transaction_type' => $type,
            'amount' => $amount, 'currency' => 'INR', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'related_party_type' => 'other', 'related_party_name' => 'Test counterparty',
        ], $extra);
    }

    public function test_proforma_keeps_three_decimal_unit_price_total_print_and_words(): void
    {
        $invoice = $this->createSales($this->salesData());
        $this->assertEqualsWithDelta(0.574, (float) $invoice->items->first()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $invoice->items->first()->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $invoice->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $invoice->balance_amount, 0.000001);
        $this->get(route('sales-invoices.print', $invoice))->assertOk()->assertSee('0.574');
        $this->assertStringContainsString('Four Tenths', $invoice->amount_in_words);
    }

    public function test_tax_invoice_keeps_the_rate_but_calculates_monetary_amounts_at_two_decimals(): void
    {
        $invoice = $this->createSales($this->salesData('tax'));
        $this->assertEqualsWithDelta(0.574, (float) $invoice->items->first()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.57, (float) $invoice->items->first()->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(0.57, (float) $invoice->total_amount, 0.000001);
        $this->get(route('sales-invoices.print', $invoice))->assertOk()->assertSee('0.574');
    }

    public function test_quantity_5000_times_0574_is_2870_and_the_tax_payload_keeps_the_rate(): void
    {
        $invoice = $this->createSales($this->salesData('tax', '0.574', [
            'invoice_date' => '2026-10-28', 'place_of_supply' => '24',
            'items' => [['product_name' => 'Cartons', 'hsn_sac' => '4819', 'quantity' => 5000,
                'unit' => 'pcs', 'unit_price' => '0.574', 'gst_percent' => 0]],
        ]));
        $this->assertEqualsWithDelta(2870, (float) $invoice->total_amount, 0.000001);
        $payload = (new \App\Services\EInvoice\EInvoicePayload($invoice))->build();
        $this->assertEqualsWithDelta(0.574, $payload['ItemList'][0]['UnitPrice'], 0.000001);
        $this->assertEqualsWithDelta(2870, $payload['ItemList'][0]['TotAmt'], 0.000001);
        $this->assertEqualsWithDelta(2870, $payload['ValDtls']['TotInvVal'], 0.000001);
    }

    public function test_proforma_auto_rounding_keeps_the_precise_adjustment_before_rounding_to_whole_rupees(): void
    {
        $invoice = $this->createSales($this->salesData('proforma', '0.574', ['auto_round_total' => '1']));
        $this->assertEqualsWithDelta(0.574, (float) $invoice->subtotal, 0.000001);
        $this->assertEqualsWithDelta(0.426, (float) $invoice->round_off, 0.000001);
        $this->assertEqualsWithDelta(1.0, (float) $invoice->total_amount, 0.000001);
    }

    public function test_purchase_order_and_bill_store_three_decimal_unit_price_and_total(): void
    {
        foreach (['order', 'bill'] as $type) {
            $this->post(route('purchase-invoices.store'), [
                'invoice_number' => 'PRECISION-'.$type, 'invoice_type' => $type, 'status' => 'draft', 'currency' => 'INR',
                'gst_type' => 'export', 'terms_conditions' => null, 'items' => [['product_name' => 'Cartons', 'quantity' => 1,
                    'unit' => 'pcs', 'unit_price' => '0.574', 'gst_percent' => 0]],
            ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
            $invoice = PurchaseInvoice::latest('id')->firstOrFail();
            $this->assertSame($type, $invoice->invoice_type);
            $this->assertEqualsWithDelta(0.574, (float) $invoice->items->first()->unit_price, 0.000001);
            $this->assertEqualsWithDelta(0.574, (float) $invoice->total_amount, 0.000001);
            $this->get(route('purchase-invoices.print', $invoice))->assertOk()->assertSee('0.574');
        }
    }

    public function test_sales_and_purchase_payment_filters_count_one_thousandth_outstanding(): void
    {
        $pi = $this->createSales($this->salesData('proforma', '1.001', ['status' => 'sent', 'amount_paid' => '1.00']));
        $partial = $this->get(route('sales-invoices.index', ['payment' => 'partial']))->assertOk();
        $this->assertTrue($partial->viewData('invoices')->contains('id', $pi->id));
        $this->assertEqualsWithDelta(0.001, $partial->viewData('pageTotals')['outstanding'], 0.000001);
        $paid = $this->get(route('sales-invoices.index', ['payment' => 'paid']))->assertOk();
        $this->assertFalse($paid->viewData('invoices')->contains('id', $pi->id));
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PRECISION-FILTER-BILL', 'invoice_type' => 'bill',
            'status' => 'sent', 'currency' => 'INR', 'gst_type' => 'export', 'terms_conditions' => null, 'amount_paid' => '1.00',
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '1.001', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $bill = PurchaseInvoice::latest('id')->firstOrFail();
        $partial = $this->get(route('purchase-invoices.index', ['payment' => 'partial']))->assertOk();
        $this->assertTrue($partial->viewData('invoices')->contains('id', $bill->id));
        $this->assertEqualsWithDelta(0.001, $partial->viewData('pageTotals')['byCurrency']['INR']['outstanding'], 0.000001);
        $paid = $this->get(route('purchase-invoices.index', ['payment' => 'paid']))->assertOk();
        $this->assertFalse($paid->viewData('invoices')->contains('id', $bill->id));
    }

    public function test_prices_and_cashflow_amounts_over_three_decimal_places_are_refused(): void
    {
        $this->post(route('sales-invoices.store'), $this->salesData('proforma', '0.5741'))
            ->assertSessionHasErrors(['items.0.unit_price']);
        $account = CashflowAccount::create(['account_name' => 'Precision account', 'currency' => 'INR']);
        $this->post(route('cashflows.store'), $this->cashData($account, '0.5741'))
            ->assertSessionHasErrors(['amount']);
    }

    public function test_cashflow_entries_and_running_balances_keep_three_decimal_places(): void
    {
        $account = CashflowAccount::create(['account_name' => 'Precision account', 'currency' => 'INR', 'opening_balance' => '10.00']);
        $this->post(route('cashflows.store'), $this->cashData($account, '0.574'))->assertRedirect()->assertSessionHasNoErrors();
        $credit = CashflowEntry::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0.574, (float) $credit->credit_amount, 0.000001);
        $this->assertEqualsWithDelta(10.574, (float) $credit->balance, 0.000001);
        $this->post(route('cashflows.store'), $this->cashData($account, '0.426', 'debit'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(10.148, (float) $account->fresh()->current_balance, 0.000001);
        $this->get(route('cashflows.show', $credit))->assertOk()->assertSee('0.574');
        $this->post(route('cashflows.store'), $this->cashData($account, '0.001'))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(10.149, (float) $account->fresh()->current_balance, 0.000001);
    }

    public function test_statement_and_report_csv_preserve_the_third_decimal(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'PREC-STMT', 'company_name' => 'Precision Statement', 'preferred_currency' => 'INR']);
        $pi = $this->createSales($this->salesData('proforma', '0.574', ['status' => 'sent', 'client_id' => $client->id, 'invoice_date' => '2026-10-28']));
        $account = CashflowAccount::create(['account_name' => 'Statement account', 'currency' => 'INR']);
        $this->post(route('sales-invoices.payments.store', $pi), ['amount' => '0.001', 'entry_date' => '2026-10-28',
            'account_id' => $account->id, 'accounting_status' => 'booked', 'particular' => 'Fractional receipt'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $statement = app(\App\Services\PartyStatement::class)->build('client', $client->id, null, null, ['currency' => 'INR']);
        $this->assertEqualsWithDelta(0.574, $statement['totals']['debit'], 0.000001);
        $this->assertEqualsWithDelta(0.001, $statement['totals']['credit'], 0.000001);
        $this->assertEqualsWithDelta(0.573, $statement['totals']['closing'], 0.000001);
        $csv = $this->get(route('cashflows.reports.export', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'dimension' => 'none']))->assertOk()->streamedContent();
        $this->assertStringContainsString('0.001', $csv);
    }

    public function test_editing_an_account_recalculates_running_balances_without_losing_thousandths(): void
    {
        $account = CashflowAccount::create(['account_name' => 'Precision bank', 'account_type' => 'current', 'currency' => 'INR', 'opening_balance' => '10']);
        $this->post(route('cashflows.store'), $this->cashData($account, '0.574'))->assertRedirect()->assertSessionHasNoErrors();
        $entry = CashflowEntry::latest('id')->firstOrFail();
        $this->put(route('settings.cashflow.accounts.update', $account), ['account_name' => 'Precision bank',
            'account_type' => 'current', 'currency' => 'INR', 'opening_balance' => '10.001', 'is_active' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(10.575, (float) $account->fresh()->current_balance, 0.000001);
        $this->assertEqualsWithDelta(10.575, (float) $entry->fresh()->balance, 0.000001);
    }

    public function test_purchase_order_conversion_and_vendor_posting_retain_fractional_values(): void
    {
        $vendor = \App\Models\Vendor::create(['vendor_number' => 'PREC-V', 'vendor_name' => 'Precision Vendor']);
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PREC-PO', 'invoice_type' => 'order',
            'vendor_id' => $vendor->id, 'status' => 'approved', 'currency' => 'INR', 'gst_type' => 'export',
            'terms_conditions' => null, 'amount_paid' => '0.001', 'items' => [['product_name' => 'Cartons',
                'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '0.574', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::latest('id')->firstOrFail();
        $posting = \App\Models\VendorPaymentEntry::where('purchase_invoice_id', $order->id)->where('transaction_type', 'credit')->firstOrFail();
        $this->assertEqualsWithDelta(0.574, (float) $posting->foreign_amount, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $posting->amount_in_inr, 0.000001);
        $this->post(route('purchase-invoices.convert', $order))->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->fresh()->convertedInvoice;
        $this->assertEqualsWithDelta(0.001, (float) $bill->amount_paid, 0.000001);
        $this->assertEqualsWithDelta(0.573, $bill->balanceDue(), 0.000001);
        $this->assertEqualsWithDelta(0, (float) $order->fresh()->amount_paid, 0.000001);
    }

    public function test_vendor_payment_mirror_keeps_three_decimal_bank_and_ledger_amounts(): void
    {
        $vendor = \App\Models\Vendor::create(['vendor_number' => 'PREC-MIRROR', 'vendor_name' => 'Precision Vendor']);
        $account = CashflowAccount::create(['account_name' => 'Precision account', 'currency' => 'INR', 'opening_balance' => '10']);
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PREC-MIRROR-BILL', 'invoice_type' => 'bill',
            'vendor_id' => $vendor->id, 'status' => 'received', 'currency' => 'INR', 'gst_type' => 'export', 'terms_conditions' => null,
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit_price' => '1.00', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $bill = PurchaseInvoice::latest('id')->firstOrFail();
        $payment = ['transaction_date' => '2026-10-28', 'foreign_amount' => '0.574', 'foreign_currency' => 'INR',
            'purchase_invoice_id' => $bill->id, 'amount_in_inr' => '0.574', 'particular' => 'Precision vendor payment', 'status' => 'paid',
            'transaction_type' => 'debit', 'entry_category' => 'payment', 'paid_account_id' => $account->id, 'also_create_cashflow' => '1'];
        $this->post(route('vendors.payments.store', $vendor), $payment)->assertRedirect()->assertSessionHasNoErrors();
        $entry = \App\Models\VendorPaymentEntry::where('vendor_id', $vendor->id)->where('transaction_type', 'debit')->firstOrFail();
        $this->assertEqualsWithDelta(0.574, (float) $entry->amount_in_inr, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $entry->cashflowEntry->debit_amount, 0.000001);
        $this->assertEqualsWithDelta(9.426, (float) $account->fresh()->current_balance, 0.000001);
        $payment['foreign_amount'] = '0.426';
        $payment['amount_in_inr'] = '0.426';
        $this->put(route('vendors.payments.update', [$vendor, $entry]), $payment)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.426, (float) $entry->fresh()->cashflowEntry->debit_amount, 0.000001);
        $this->assertEqualsWithDelta(9.574, (float) $account->fresh()->current_balance, 0.000001);
    }

    public function test_financial_closure_detects_a_thousandth_balance_and_client_summary_preserves_it(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'PREC-C', 'company_name' => 'Precision Client', 'preferred_currency' => 'INR']);
        $project = \App\Models\Project::create(['project_number' => 'PREC-CLOSE', 'name' => 'Precision closure', 'client_id' => $client->id, 'currency' => 'INR']);
        $this->createSales($this->salesData('proforma', '0.574', ['status' => 'sent', 'client_id' => $client->id,
            'project_id' => $project->id, 'amount_paid' => '0.573']));
        $snapshot = app(\App\Services\ProjectFinancialClosure::class)->snapshot($project->fresh());
        $this->assertTrue($snapshot['has_outstanding_financials']);
        $this->assertEqualsWithDelta(0.001, $snapshot['totals']['receivables']['INR'], 0.000001);
        $summary = app(\App\Services\ClientAccountSummary::class)->forClient($client->fresh());
        $this->assertEqualsWithDelta(0.001, $summary['currencies'][0]['balance_due'], 0.000001);
    }

    public function test_three_decimal_cashflow_allocation_settles_the_exact_document_balance(): void
    {
        $invoice = $this->createSales($this->salesData('proforma', '100.574', ['status' => 'sent']));
        $account = CashflowAccount::create(['account_name' => 'Precision account', 'currency' => 'INR']);
        $this->post(route('cashflows.store'), $this->cashData($account, '0.574', 'credit', [
            'allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => '0.574']],
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $entry = CashflowEntry::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(0.574, $entry->allocatedAmount(), 0.000001);
        $this->assertEqualsWithDelta(0.0, $entry->unallocatedAmount(), 0.000001);
        $this->assertEqualsWithDelta(100.0, $invoice->fresh()->balanceDue(), 0.000001);
    }

    public function test_exact_claim_conversion_recalculates_tax_components_and_keeps_fractional_receipts(): void
    {
        $pi = $this->createSales($this->salesData('proforma', '0.574', ['status' => 'sent', 'auto_round_total' => '1']));
        $account = CashflowAccount::create(['account_name' => 'Conversion account', 'currency' => 'INR']);
        $this->post(route('sales-invoices.payments.store', $pi), [
            'amount' => '0.574', 'entry_date' => '2026-10-28', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'particular' => 'Advance',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.426, $pi->fresh()->balanceDue(), 0.000001);
        $this->post(route('sales-invoices.convert', $pi))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $tax = SalesInvoice::where('invoice_type', 'tax')->firstOrFail();
        $this->assertEqualsWithDelta(0.574, (float) $tax->items->first()->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.57, (float) $tax->items->first()->gross_amount, 0.000001);
        $this->assertEqualsWithDelta(0.43, (float) $tax->round_off, 0.000001);
        $this->assertEqualsWithDelta(1, (float) $tax->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.574, $tax->receivedAmount(), 0.000001);
        $this->assertEqualsWithDelta(0.426, $tax->balanceDue(), 0.000001);
        $this->assertEqualsWithDelta(0.426, (float) $tax->fresh()->balance_amount, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $account->fresh()->current_balance, 0.000001);
    }

    public function test_partial_conversion_refuses_an_unrepresentable_tax_line_without_changing_the_claim(): void
    {
        $data = $this->salesData('proforma', '1000', ['status' => 'sent']);
        $data['items'][0]['quantity'] = 100;
        $pi = $this->createSales($data);
        $this->post(route('sales-invoices.convert-cash', $pi), [
            'conversion_mode' => 'amount', 'conversion_value' => '33333.33', 'transfer_received' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $before = $pi->fresh()->receivableTotal();
        $this->assertEqualsWithDelta(66666.67, $before, 0.000001);
        $this->post(route('sales-invoices.convert', $pi))->assertRedirect()->assertSessionHasErrors('invoice');
        $this->assertSame(0, SalesInvoice::where('invoice_type', 'tax')->count());
        $this->assertNull($pi->fresh()->converted_invoice_id);
        $this->assertEqualsWithDelta($before, $pi->fresh()->receivableTotal(), 0.000001);
    }

    public function test_einvoice_allocates_an_invoice_wide_discount_without_double_counting(): void
    {
        $data = $this->salesData('tax', '10.00', ['status' => 'sent', 'invoice_date' => '2026-10-28',
            'seller_gstin' => '24AATCM8816E1Z5', 'client_gstin' => '24ABCDE1234F1Z6', 'place_of_supply' => '24',
            'gst_type' => 'inter_state', 'discount_type' => 'amount', 'discount_value' => '3.00']);
        $data['items'] = [
            ['product_name' => 'A', 'hsn_sac' => '4819', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '10', 'gst_percent' => 18],
            ['product_name' => 'B', 'hsn_sac' => '4819', 'quantity' => 1, 'unit' => 'pcs', 'unit_price' => '20', 'gst_percent' => 5],
        ];
        $tax = $this->createSales($data);
        $payload = (new EInvoicePayload($tax->fresh()))->build();
        $this->assertEqualsWithDelta(9.0, (float) $payload['ItemList'][0]['AssAmt'], 0.000001);
        $this->assertEqualsWithDelta(18.0, (float) $payload['ItemList'][1]['AssAmt'], 0.000001);
        $this->assertEqualsWithDelta(1.0, (float) $payload['ItemList'][0]['Discount'], 0.000001);
        $this->assertEqualsWithDelta(2.0, (float) $payload['ItemList'][1]['Discount'], 0.000001);
        $this->assertEqualsWithDelta(1.62, (float) $payload['ItemList'][0]['IgstAmt'], 0.000001);
        $this->assertEqualsWithDelta(0.9, (float) $payload['ItemList'][1]['IgstAmt'], 0.000001);
        $this->assertEqualsWithDelta(27.0, (float) $payload['ValDtls']['AssVal'], 0.000001);
        $this->assertEqualsWithDelta(0.0, (float) $payload['ValDtls']['Discount'], 0.000001);
        $this->assertEqualsWithDelta(29.52, (float) $payload['ValDtls']['TotInvVal'], 0.000001);
    }

    public function test_einvoice_refuses_inconsistent_saved_line_and_header_figures(): void
    {
        $data = $this->salesData('tax', '666.667', ['status' => 'sent', 'invoice_date' => '2026-10-28', 'seller_gstin' => '24AATCM8816E1Z5',
            'client_gstin' => '24ABCDE1234F1Z6', 'place_of_supply' => '24']);
        $data['items'][0]['quantity'] = 100;
        $data['items'][0]['hsn_sac'] = '4819';
        $tax = $this->createSales($data);
        $this->assertSame([], (new EInvoicePayload($tax->fresh()))->issues());
        // A historical/prorated line can claim a different gross than its own rate × quantity.
        $tax->items()->update(['gross_amount' => '66666.67']);
        $issues = (new EInvoicePayload($tax->fresh()))->issues();
        $this->assertStringContainsString('quantity', implode(' ', $issues));
        try {
            (new EInvoicePayload($tax->fresh()))->build();
            $this->fail('An inconsistent e-invoice must not be exported.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('quantity', $exception->getMessage());
        }
        $tax->items()->update(['gross_amount' => '66666.70']);
        $tax->forceFill(['taxable_amount' => '66666.69'])->save();
        $this->assertStringContainsString('total', implode(' ', (new EInvoicePayload($tax->fresh()))->issues()));
        $tax->forceFill(['gst_type' => 'inter_state', 'taxable_amount' => '66666.70', 'igst_amount' => '100', 'total_amount' => '66766.70'])->save();
        $tax->items()->update(['gst_percent' => '18', 'igst_amount' => '100', 'line_total' => '66766.70']);
        $this->assertStringContainsString('tax rate', implode(' ', (new EInvoicePayload($tax->fresh()))->issues()));
    }

    public function test_issued_tax_invoice_rejects_a_one_thousandth_rate_change(): void
    {
        $invoice = $this->createSales($this->salesData('tax', '0.571', ['status' => 'sent']));
        $this->put(route('sales-invoices.update', $invoice), $this->salesData('tax', '0.572', [
            'invoice_number' => $invoice->invoice_number, 'status' => 'sent',
        ]))->assertRedirect()->assertSessionHasErrors(['invoice']);
        $this->assertEqualsWithDelta(0.571, (float) $invoice->fresh()->items->first()->unit_price, 0.000001);
    }

    public function test_over_allocating_by_one_thousandth_is_rejected_atomically(): void
    {
        $invoice = $this->createSales($this->salesData('proforma', '1.00', ['status' => 'sent']));
        $account = CashflowAccount::create(['account_name' => 'Precision account', 'currency' => 'INR']);
        $this->post(route('cashflows.store'), $this->cashData($account, '0.574', 'credit', [
            'allocations' => [['sales_invoice_id' => $invoice->id, 'amount' => '0.575']],
        ]))->assertRedirect()->assertSessionHasErrors(['allocations']);
        $this->assertSame(0, CashflowEntry::count());
        $this->assertEqualsWithDelta(0, (float) $account->fresh()->current_balance, 0.000001);
        $this->assertEqualsWithDelta(1, $invoice->fresh()->balanceDue(), 0.000001);
    }

    public function test_invoice_product_snapshot_preserves_the_three_decimal_rate(): void
    {
        $project = \App\Models\Project::forceCreate(['project_number' => 'PRECISION-PROJECT',
            'client_id' => 1, 'name' => 'Precision project']);
        $this->createSales($this->salesData('proforma', '0.574', ['project_id' => $project->id]));
        $product = $project->products()->firstOrFail();
        $this->assertEqualsWithDelta(0.574, (float) $product->unit_price, 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $product->total_amount, 0.000001);
    }

    public function test_fractional_cash_conversion_of_a_paise_pi_preserves_the_exact_claim(): void
    {
        $pi = $this->createSales($this->salesData('proforma', '1.000', ['amount_paid' => '0.001']));
        $this->post(route('sales-invoices.convert-cash', $pi), ['conversion_mode' => 'amount',
            'conversion_value' => '0.001', 'transfer_received' => '0.001'])->assertRedirect()->assertSessionHasNoErrors();
        $cash = SalesInvoice::where('source_proforma_id', $pi->id)->where('invoice_type', 'cash')->firstOrFail();
        $this->assertEqualsWithDelta(0.001, (float) $cash->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.001, (float) $pi->fresh()->cash_converted_total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.999, $pi->fresh()->receivableTotal(), 0.000001);
        $this->assertEqualsWithDelta(1, $pi->fresh()->receivableTotal() + (float) $cash->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.001, $cash->fresh()->receivedAmount(), 0.000001);
        $this->post(route('sales-invoices.convert', $pi))->assertRedirect()->assertSessionHas('error');
        $this->assertNull($pi->fresh()->converted_invoice_id);
    }

    public function test_cash_conversion_and_reversal_preserve_fractional_receipts_and_claims(): void
    {
        $pi = $this->createSales($this->salesData('proforma', '1.574', ['status' => 'sent']));
        $account = CashflowAccount::create(['account_name' => 'Cash account', 'currency' => 'INR']);
        $this->post(route('sales-invoices.payments.store', $pi), [
            'amount' => '0.574', 'entry_date' => '2026-10-28', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'particular' => 'Advance',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('sales-invoices.convert-cash', $pi), [
            'conversion_mode' => 'amount', 'conversion_value' => '0.574', 'transfer_received' => '0.574',
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $cash = SalesInvoice::where('invoice_type', 'cash')->firstOrFail();
        $this->assertEqualsWithDelta(0.574, (float) $cash->total_amount, 0.000001);
        $this->assertEqualsWithDelta(0.574, $cash->receivedAmount(), 0.000001);
        $this->assertEqualsWithDelta(1, $pi->fresh()->receivableTotal(), 0.000001);
        $this->delete(route('sales-invoices.destroy', $cash))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1.574, $pi->fresh()->receivableTotal(), 0.000001);
        $this->assertEqualsWithDelta(0.574, $pi->fresh()->receivedAmount(), 0.000001);
        $this->assertEqualsWithDelta(1, $pi->fresh()->balanceDue(), 0.000001);
        $this->assertEqualsWithDelta(0.574, (float) $account->fresh()->current_balance, 0.000001);
    }

    public function test_unrepresentable_proforma_to_tax_conversion_is_blocked_without_changing_the_claim(): void
    {
        $invoice = $this->createSales($this->salesData('proforma', '0.574', ['status' => 'sent']));
        $this->post(route('sales-invoices.convert', $invoice))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, SalesInvoice::where('invoice_type', 'tax')->count());
        $this->assertEqualsWithDelta(0.574, (float) $invoice->fresh()->total_amount, 0.000001);
    }
}
