<?php

namespace Tests\Integration;

use App\Models\PartyStatementShare;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\PartyStatement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Support\IsolatedDatabase;
use Tests\TestCase;

/** Fully migrated, disposable ledger and HTTP coverage for converted PO visibility. */
class PurchaseConvertedVisibilityTest extends TestCase
{
    use IsolatedDatabase;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]));
        Carbon::setTestNow('2026-11-01 12:00:00');
        $this->actingAs(User::create(['name' => 'Accounts', 'email' => 'visibility@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']));
        $this->vendor = Vendor::create(['vendor_number' => 'VIS-SUP', 'vendor_name' => 'Visibility Supplier',
            'preferred_currency' => 'INR']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function document(string $number, string $type, array $attributes = []): PurchaseInvoice
    {
        return PurchaseInvoice::create(array_replace([
            'invoice_number' => $number, 'invoice_type' => $type, 'status' => $type === 'order' ? 'approved' : 'received',
            'public_token' => Str::random(48), 'vendor_id' => $this->vendor->id,
            'vendor_company_name' => $this->vendor->vendor_name, 'invoice_date' => '2026-10-05',
            'currency' => 'INR', 'total_amount' => 0,
        ], $attributes));
    }

    private function entry(PurchaseInvoice $document, string $type, string $category, string $amount, array $attributes = []): VendorPaymentEntry
    {
        return VendorPaymentEntry::create(array_replace([
            'vendor_id' => $document->vendor_id, 'purchase_invoice_id' => $document->id,
            'invoice_number' => $document->invoice_number, 'transaction_date' => '2026-10-05',
            'foreign_currency' => $document->currency, 'foreign_amount' => $amount, 'exchange_rate' => 1,
            'transaction_type' => $type, 'entry_category' => $category, 'particular' => $category.' '.$document->invoice_number,
            'status' => $type === 'debit' ? 'paid' : 'booked', 'amount_in_inr' => $amount,
        ], $attributes));
    }

    private function listed($response): array
    {
        return $response->viewData('invoices')->getCollection()->pluck('invoice_number')->sort()->values()->all();
    }

    public function test_listing_filters_legacy_and_exhausted_orders_without_duplicate_totals(): void
    {
        $legacyBill = $this->document('B-LEGACY', 'bill', ['total_amount' => '900']);
        $legacy = $this->document('PO-LEGACY', 'order', ['status' => 'billed', 'total_amount' => '900',
            'converted_invoice_id' => $legacyBill->id]);
        $full = $this->document('PO-FULL', 'order', ['total_amount' => '0', 'converted_amount' => '6000', 'original_total_amount' => '6000']);
        $this->document('B-FULL', 'bill', ['total_amount' => '6000', 'purchase_order_id' => $full->id]);
        $partial = $this->document('PO-PARTIAL', 'order', ['total_amount' => '7500', 'converted_amount' => '2500', 'original_total_amount' => '10000']);
        $this->document('B-PARTIAL', 'bill', ['total_amount' => '2500', 'purchase_order_id' => $partial->id]);
        $this->document('B-STANDALONE', 'bill', ['total_amount' => '300']);
        $this->document('PO-OPEN', 'order', ['total_amount' => '10000']);
        $this->document('PO-ZERO', 'order'); // A zero-value draft/order is not a conversion.
        $this->entry($full, 'debit', 'payment', '125'); // Still real, but not double-counted in listing tiles.

        $default = $this->get(route('purchase-invoices.index'))->assertOk();
        $this->assertSame(['B-FULL', 'B-LEGACY', 'B-PARTIAL', 'B-STANDALONE', 'PO-OPEN', 'PO-PARTIAL', 'PO-ZERO'], $this->listed($default));
        $this->assertSame('hide', $default->viewData('converted'));
        $this->assertSame(3, $default->viewData('chipCounts')['order']);
        $this->assertSame(4, $default->viewData('chipCounts')['bill']);
        $this->assertSame(['billed' => 9700.0, 'open' => 17500.0, 'paid' => 0.0, 'outstanding' => 27200.0],
            $default->viewData('pageTotals')['byCurrency']['INR']);
        $default->assertSee('Converted orders')->assertSee('Hide fully converted orders');

        $included = $this->get(route('purchase-invoices.index', ['converted' => 'include']))->assertOk();
        $this->assertSame(['B-FULL', 'B-LEGACY', 'B-PARTIAL', 'B-STANDALONE', 'PO-FULL', 'PO-LEGACY', 'PO-OPEN', 'PO-PARTIAL', 'PO-ZERO'], $this->listed($included));
        $this->assertSame($default->viewData('pageTotals')['byCurrency']['INR'], $included->viewData('pageTotals')['byCurrency']['INR']);
        $this->assertSame(5, $included->viewData('chipCounts')['order']);
        $this->assertSame('Include fully converted orders', $included->viewData('appliedChips')['converted']['value']);
        $included->assertSee('Fully converted')->assertSee('aria-label="Remove Converted orders filter"', false);

        $only = $this->get(route('purchase-invoices.index', ['converted' => 'only']))->assertOk();
        $this->assertSame(['PO-FULL', 'PO-LEGACY'], $this->listed($only));
        $this->assertSame(['billed' => 0.0, 'open' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0],
            $only->viewData('pageTotals')['byCurrency']['INR']);
        $this->assertSame(2, $only->viewData('chipCounts')['order']);
        $this->assertSame(0, $only->viewData('chipCounts')['bill']);
        $this->assertSame('Only fully converted orders', $only->viewData('appliedChips')['converted']['value']);

        $combined = $this->get(route('purchase-invoices.index', ['converted' => 'include', 'invoice_type' => 'order',
            'search' => 'PARTIAL', 'vendor' => $this->vendor->id, 'currency' => 'INR',
            'date_from' => '2026-10-01', 'date_to' => '2026-10-31']))->assertOk();
        $this->assertSame(['PO-PARTIAL'], $this->listed($combined));
        $this->assertSame(7500.0, $combined->viewData('pageTotals')['byCurrency']['INR']['open']);
        $this->assertArrayHasKey('converted', $combined->viewData('appliedChips'));
        $this->assertArrayHasKey('vendor', $combined->viewData('appliedChips'));
        $this->assertArrayHasKey('dates', $combined->viewData('appliedChips'));
        foreach (['unexpected', ['include']] as $invalid) {
            $bad = $this->get(route('purchase-invoices.index', ['converted' => $invalid]))->assertOk();
            $this->assertSame('hide', $bad->viewData('converted'));
            $this->assertSame($this->listed($default), $this->listed($bad));
        }
        $this->assertSame($legacy->id, $legacy->fresh()->id); // listing is read-only
    }

    public function test_listing_pagination_and_chips_preserve_the_conversion_filter(): void
    {
        for ($i = 1; $i <= 27; $i++) {
            $this->document(sprintf('PO-OPEN-%02d', $i), 'order', ['total_amount' => '0.001']);
            $this->document(sprintf('PO-DONE-%02d', $i), 'order', ['total_amount' => '0', 'converted_amount' => '0.001']);
        }
        $default = $this->get(route('purchase-invoices.index', ['invoice_type' => 'order', 'page' => 2]))->assertOk();
        $this->assertSame(27, $default->viewData('invoices')->total());
        $this->assertCount(2, $default->viewData('invoices')->items());
        $this->assertSame(27, $default->viewData('chipCounts')['order']);
        $included = $this->get(route('purchase-invoices.index', ['converted' => 'include', 'invoice_type' => 'order', 'page' => 2]))->assertOk();
        $this->assertSame(54, $included->viewData('invoices')->total());
        $this->assertCount(25, $included->viewData('invoices')->items());
        $this->assertSame(54, $included->viewData('chipCounts')['order']);
        $this->assertStringContainsString('converted=include', html_entity_decode($included->viewData('invoices')->nextPageUrl()));
        $only = $this->get(route('purchase-invoices.index', ['converted' => 'only', 'invoice_type' => 'order', 'page' => 2]))->assertOk();
        $this->assertSame(27, $only->viewData('invoices')->total());
        $this->assertCount(2, $only->viewData('invoices')->items());
        $this->assertSame(27, $only->viewData('chipCounts')['order']);
    }

    public function test_conversion_and_reversal_change_visibility_without_losing_payments(): void
    {
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PO-LIFECYCLE', 'invoice_type' => 'order',
            'status' => 'approved', 'vendor_id' => $this->vendor->id, 'currency' => 'INR', 'gst_type' => 'export',
            'invoice_date' => '2026-10-01', 'terms_conditions' => null,
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit_price' => '10000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::where('invoice_number', 'PO-LIFECYCLE')->firstOrFail();
        $payment = $this->entry($order, 'debit', 'payment', '1000', ['particular' => 'Actual advance']);
        $this->post(route('purchase-invoices.convert-partial', $order), ['kind' => 'cash', 'mode' => 'amount', 'value' => '2500'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $cash = $order->partialConversions()->firstOrFail();
        $this->assertSame(7500.0, (float) $order->fresh()->total_amount);
        $partialList = $this->get(route('purchase-invoices.index'))->assertOk();
        $this->assertContains('PO-LIFECYCLE', $this->listed($partialList));
        $this->assertContains($cash->invoice_number, $this->listed($partialList));
        $partialStatement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertContains('PO-LIFECYCLE', array_column($partialStatement['rows'], 'reference'));
        $this->assertSame(10000.0, $partialStatement['totals']['credit']);
        $this->assertSame(1000.0, $partialStatement['totals']['debit']);

        $this->post(route('purchase-invoices.convert-partial', $order->fresh()), ['kind' => 'bill', 'mode' => 'amount', 'value' => '7500'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->partialConversions()->reorder('id', 'desc')->firstOrFail();
        $this->assertSame(0.0, (float) $order->fresh()->total_amount);
        $completed = $this->get(route('purchase-invoices.index'))->assertOk();
        $this->assertNotContains('PO-LIFECYCLE', $this->listed($completed));
        $this->assertContains($cash->invoice_number, $this->listed($completed));
        $this->assertContains($bill->invoice_number, $this->listed($completed));
        $included = $this->get(route('purchase-invoices.index', ['converted' => 'include']))->assertOk();
        $this->assertContains('PO-LIFECYCLE', $this->listed($included));
        $fullStatement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertNotContains('PO-LIFECYCLE', collect($fullStatement['rows'])->where('credit', '>', 0)->pluck('reference')->all());
        $this->assertSame(10000.0, $fullStatement['totals']['credit']);
        $this->assertSame(1000.0, $fullStatement['totals']['debit']);
        $this->assertSame(3, $fullStatement['totals']['count']);
        $this->assertSame($order->id, $payment->fresh()->purchase_invoice_id);

        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(7500.0, (float) $order->fresh()->total_amount);
        $this->assertContains('PO-LIFECYCLE', $this->listed($this->get(route('purchase-invoices.index'))->assertOk()));
        $reopened = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertContains('PO-LIFECYCLE', collect($reopened['rows'])->where('credit', '>', 0)->pluck('reference')->all());
        $this->assertSame(10000.0, $reopened['totals']['credit']);
        $this->assertSame(1000.0, $reopened['totals']['debit']);
        $this->assertSame('1000.0000', $payment->fresh()->foreign_amount);
    }

    public function test_legacy_bill_deletion_reopens_the_order_and_its_statement_credit(): void
    {
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PO-LEGACY-RESTORE', 'invoice_type' => 'order',
            'status' => 'approved', 'vendor_id' => $this->vendor->id, 'currency' => 'INR', 'gst_type' => 'export',
            'invoice_date' => '2026-10-01', 'terms_conditions' => null,
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit_price' => '1000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::where('invoice_number', 'PO-LEGACY-RESTORE')->firstOrFail();
        $this->post(route('purchase-invoices.convert', $order))->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->fresh()->convertedInvoice;
        $this->assertNotNull($bill);
        $this->assertNotContains($order->invoice_number, $this->listed($this->get(route('purchase-invoices.index'))->assertOk()));
        $statement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertSame(1000.0, $statement['totals']['credit']);
        $this->assertSame([$bill->invoice_number], array_column($statement['rows'], 'reference'));
        $adjustment = $this->entry($order, 'credit', 'adjustment', '30', ['particular' => 'Preserve manual credit']);

        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('success');
        $this->assertContains($order->invoice_number, $this->listed($this->get(route('purchase-invoices.index'))->assertOk()));
        $reopened = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertSame(1030.0, $reopened['totals']['credit']);
        $this->assertContains('Purchase order '.$order->invoice_number, array_column($reopened['rows'], 'particular'));
        $this->assertContains('Preserve manual credit', array_column($reopened['rows'], 'particular'));
        $this->assertSame('adjustment', $adjustment->fresh()->entry_category);
        $this->assertSame('30.0000', $adjustment->fresh()->foreign_amount);
    }

    public function test_legacy_bill_with_a_carried_opening_advance_cannot_be_deleted(): void
    {
        $this->post(route('purchase-invoices.store'), ['invoice_number' => 'PO-LEGACY-ADVANCE', 'invoice_type' => 'order',
            'status' => 'approved', 'vendor_id' => $this->vendor->id, 'currency' => 'INR', 'gst_type' => 'export',
            'amount_paid' => '125', 'terms_conditions' => null,
            'items' => [['product_name' => 'Cartons', 'quantity' => 1, 'unit_price' => '1000', 'gst_percent' => 0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $order = PurchaseInvoice::where('invoice_number', 'PO-LEGACY-ADVANCE')->firstOrFail();
        $this->post(route('purchase-invoices.convert', $order))->assertRedirect()->assertSessionHasNoErrors();
        $bill = $order->fresh()->convertedInvoice;
        $this->assertSame(0.0, (float) $order->fresh()->amount_paid);
        $this->assertSame(125.0, (float) $bill->fresh()->amount_paid);
        $this->delete(route('purchase-invoices.destroy', $bill))->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull($bill->fresh());
        $this->assertSame($bill->id, (int) $order->fresh()->converted_invoice_id);
        $this->assertSame(125.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_bulk_delete_does_not_discard_a_draft_bill_opening_advance(): void
    {
        $bill = $this->document('B-DRAFT-ADVANCE', 'bill', ['status' => 'draft', 'total_amount' => '400', 'amount_paid' => '20']);
        $order = $this->document('PO-DRAFT-ADVANCE', 'order', ['status' => 'billed', 'total_amount' => '400',
            'converted_invoice_id' => $bill->id]);
        $this->post(route('purchase-invoices.bulk'), ['action' => 'delete_drafts', 'ids' => [$bill->id]])->assertRedirect();
        $this->assertNotNull($bill->fresh());
        $this->assertSame($bill->id, (int) $order->fresh()->converted_invoice_id);
        $this->assertSame(20.0, (float) $bill->fresh()->amount_paid);
    }

    public function test_bulk_deleting_a_legacy_draft_bill_reposts_the_reopened_order(): void
    {
        $bill = $this->document('B-LEGACY-DRAFT', 'bill', ['status' => 'draft', 'total_amount' => '400']);
        $order = $this->document('PO-LEGACY-DRAFT', 'order', ['status' => 'billed', 'total_amount' => '400',
            'converted_invoice_id' => $bill->id]);
        $this->post(route('purchase-invoices.bulk'), ['action' => 'delete_drafts', 'ids' => [$bill->id]])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertNull($order->fresh()->converted_invoice_id);
        $this->assertContains($order->invoice_number, $this->listed($this->get(route('purchase-invoices.index'))->assertOk()));
        $statement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertSame(400.0, $statement['totals']['credit']);
        $this->assertSame([$order->invoice_number], array_column($statement['rows'], 'reference'));
    }

    public function test_statement_excludes_stale_full_po_credits_but_preserves_payments_opening_and_currencies(): void
    {
        $this->vendor->update(['preferred_currency' => 'USD']);
        $legacyBill = $this->document('B-OLD', 'bill', ['total_amount' => '900']);
        $legacy = $this->document('PO-OLD', 'order', ['total_amount' => '900', 'converted_invoice_id' => $legacyBill->id]);
        $full = $this->document('PO-EXHAUSTED', 'order', ['total_amount' => 0, 'converted_amount' => '6000']);
        $fullBill = $this->document('B-FULL', 'bill', ['total_amount' => '6000', 'purchase_order_id' => $full->id]);
        $partial = $this->document('PO-REMAINDER', 'order', ['total_amount' => '7500', 'converted_amount' => '2500']);
        $partialBill = $this->document('B-PARTIAL', 'bill', ['total_amount' => '2500', 'purchase_order_id' => $partial->id]);
        $usd = $this->document('PO-USD-EXHAUSTED', 'order', ['currency' => 'USD', 'converted_amount' => '700']);
        $staleLegacy = $this->entry($legacy, 'credit', 'order', '900', ['transaction_date' => '2026-09-01']);
        $staleFull = $this->entry($full, 'credit', 'order', '6000', ['transaction_date' => '2026-09-02']);
        $staleUsd = $this->entry($usd, 'credit', 'order', '700', ['transaction_date' => '2026-10-03']);
        $this->entry($legacyBill, 'credit', 'bill', '900');
        $this->entry($fullBill, 'credit', 'bill', '6000');
        $this->entry($partial, 'credit', 'order', '7500');
        $this->entry($partialBill, 'credit', 'bill', '2500');
        $advance = $this->entry($full, 'debit', 'payment', '125', ['transaction_date' => '2026-09-20', 'particular' => 'Real earlier payment']);
        $otherDebit = $this->entry($full, 'debit', 'order', '30', ['particular' => 'Real debit on an order']);
        $adjustment = $this->entry($full, 'credit', 'adjustment', '20', ['particular' => 'Real supplier adjustment']);
        $rmbDebit = $this->entry($full, 'debit', 'payment', '40', ['foreign_currency' => 'RMB', 'particular' => 'RMB payment']);
        $before = VendorPaymentEntry::orderBy('id')->get()->toArray();

        $service = app(PartyStatement::class);
        $this->assertSame('INR', $service->defaultCurrency('vendor', $this->vendor->id), 'A hidden USD row must not pick an empty USD statement.');
        $statement = $service->build('vendor', $this->vendor->id, '2026-10-01', '2026-10-31', ['currency' => 'INR']);
        $refs = array_column($statement['rows'], 'reference');
        $this->assertNotContains('PO-OLD', $refs);
        $this->assertContains('PO-REMAINDER', $refs);
        $this->assertContains('B-OLD', $refs);
        $this->assertContains('B-FULL', $refs);
        $this->assertContains('B-PARTIAL', $refs);
        $this->assertContains('Real debit on an order', array_column($statement['rows'], 'particular'));
        $this->assertContains('Real supplier adjustment', array_column($statement['rows'], 'particular'));
        $this->assertSame(-125.0, $statement['opening']);
        $this->assertSame(['debit' => 30.0, 'credit' => 16920.0, 'closing' => 16765.0, 'count' => 6], $statement['totals']);
        $this->assertSame(16920.0, $statement['ageing']['total']);
        $this->assertNotContains('PO-OLD', array_column($statement['ageing']['rows'], 'reference'));
        $this->assertNotContains('order PO-EXHAUSTED', array_column($statement['ageing']['rows'], 'particular'));
        $this->assertContains('Real supplier adjustment', array_column($statement['ageing']['rows'], 'particular'));
        $this->assertSame([['currency' => 'RMB', 'debit' => 40.0, 'credit' => 0.0, 'rows' => 1]], $statement['other_currencies']);
        $activity = $service->activity('vendor', '2026-10-01', '2026-10-31', null, 'INR');
        $this->assertCount(1, $activity['rows']);
        $this->assertSame(-125.0, $activity['rows'][0]['opening']);
        $this->assertSame(16920.0, $activity['rows'][0]['credit']);
        $this->assertSame(30.0, $activity['rows'][0]['debit']);
        $this->assertSame(16765.0, $activity['rows'][0]['closing']);
        $this->assertSame($before, VendorPaymentEntry::orderBy('id')->get()->toArray(), 'Viewing a statement must not mutate historical money.');
        foreach ([$staleLegacy, $staleFull, $staleUsd, $advance, $otherDebit, $adjustment, $rmbDebit] as $entry) {
            $this->assertNotNull($entry->fresh());
        }

        $this->vendor->update(['preferred_currency' => 'INR']);
        $this->assertNotContains('USD', $service->currencies('vendor', $this->vendor->id));
        $this->assertContains('RMB', $service->currencies('vendor', $this->vendor->id));
    }

    public function test_order_credits_without_a_matching_owned_order_or_with_real_cashflow_are_not_hidden(): void
    {
        $otherVendor = Vendor::create(['vendor_number' => 'OTHER-SUP', 'vendor_name' => 'Other supplier']);
        $otherOrder = $this->document('OTHER-PO', 'order', ['vendor_id' => $otherVendor->id, 'converted_amount' => '100']);
        $this->entry($otherOrder, 'credit', 'order', '25', [
            'vendor_id' => $this->vendor->id, 'particular' => 'Mismatched vendor record',
        ]);
        $unlinked = $this->entry($otherOrder, 'credit', 'order', '40', [
            'vendor_id' => $this->vendor->id, 'purchase_invoice_id' => null, 'particular' => 'Unlinked manual order credit',
        ]);
        $ourOrder = $this->document('PO-CASHFLOW', 'order', ['converted_amount' => '100']);
        $account = \App\Models\CashflowAccount::create(['account_name' => 'Fixture account', 'currency' => 'INR']);
        $cashflow = \App\Models\CashflowEntry::create(['vendor_id' => $this->vendor->id, 'account_id' => $account->id,
            'entry_date' => '2026-10-05', 'particular' => 'Real cashflow credit', 'currency' => 'INR',
            'transaction_type' => 'credit', 'credit_amount' => 7, 'debit_amount' => 0, 'accounting_status' => 'booked']);
        $this->entry($ourOrder, 'credit', 'order', '7', [
            'cashflow_entry_id' => $cashflow->id, 'particular' => 'Real cashflow credit',
        ]);

        $statement = app(PartyStatement::class)->build('vendor', $this->vendor->id, null, null, ['currency' => 'INR']);
        $this->assertSame(['debit' => 0.0, 'credit' => 72.0, 'closing' => 72.0, 'count' => 3], $statement['totals']);
        $this->assertSame(['Mismatched vendor record', 'Unlinked manual order credit', 'Real cashflow credit'],
            array_column($statement['rows'], 'particular'));
        $this->assertNotNull($unlinked->fresh());
    }

    public function test_print_pdf_and_public_share_use_the_same_visible_ledger(): void
    {
        $order = $this->document('PO-HISTORY', 'order', ['converted_amount' => '1000']);
        $bill = $this->document('B-KEPT', 'bill', ['total_amount' => '1000', 'purchase_order_id' => $order->id]);
        $this->entry($order, 'credit', 'order', '1000', ['particular' => 'Old converted order row']);
        $this->entry($bill, 'credit', 'bill', '1000', ['particular' => 'Real bill']);
        $this->entry($order, 'debit', 'payment', '200', ['particular' => 'Real payment']);
        $share = PartyStatementShare::issue(['party_type' => 'vendor', 'party_id' => $this->vendor->id,
            'party_name' => $this->vendor->vendor_name, 'party_currency' => 'INR', 'expires_in_days' => 0]);

        $assertStatement = function ($response) {
            $response->assertOk()->assertDontSee('Old converted order row')->assertSee('Real bill')->assertSee('Real payment');
            $this->assertSame(['debit' => 200.0, 'credit' => 1000.0, 'closing' => 800.0, 'count' => 2], $response->viewData('statement')['totals']);
        };
        foreach ([
            route('cashflows.statements.show', ['partyType' => 'vendor', 'party' => $this->vendor->id, 'period' => 'all', 'currency' => 'INR']),
            route('cashflows.statements.print', ['partyType' => 'vendor', 'party' => $this->vendor->id, 'period' => 'all', 'currency' => 'INR']),
            route('cashflows.statements.pdf', ['partyType' => 'vendor', 'party' => $this->vendor->id, 'period' => 'all', 'currency' => 'INR']),
        ] as $url) {
            $assertStatement($this->get($url));
        }
        auth()->logout();
        $assertStatement($this->get(route('statements.public', $share->token)));
        $this->assertSame(1, $share->fresh()->views);
    }
}
