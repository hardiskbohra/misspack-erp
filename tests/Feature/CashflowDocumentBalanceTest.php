<?php

namespace Tests\Feature;

use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\CashflowPickers;
use App\Services\CashflowVendorPaymentSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CashflowDocumentBalanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('v', 32))]);
        DB::purge('sqlite');
        Storage::fake('public');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_06_09_020000_create_clients_table',
            '2026_06_09_030000_create_vendors_table',
            '2026_06_10_010000_create_cashflow_accounts_table',
            '2026_06_10_010100_create_cashflow_categories_table',
            '2026_06_10_010200_create_cashflow_entries_table',
            '2026_07_12_010000_create_products_table',
            '2026_07_18_010000_create_projects_table',
            '2026_07_18_010700_add_project_id_to_cashflow_entries_table',
            '2026_07_25_010100_create_vendor_payment_entries_table',
            '2026_07_25_010200_create_vendor_payment_attachments_table',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_08_29_000000_add_sales_invoice_id_to_cashflow_entries_table',
            '2026_10_04_100000_add_vendor_payables_and_contacts',
            '2026_10_04_130000_create_purchase_invoices_table',
            '2026_10_04_130100_create_purchase_invoice_items_table',
            '2026_10_04_130200_add_purchase_invoice_id_to_vendor_payment_entries_table',
            '2026_10_06_060000_create_organisation_tables',
            '2026_10_15_100000_create_cashflow_allocations_table',
            '2026_10_19_120000_add_soft_deletes_to_projects',
            '2026_10_20_160000_link_cashflow_vendor_payments_to_the_ledger',
            '2026_10_02_010000_create_cashflow_attachments_table',
            '2026_10_02_020000_let_cashflow_documents_stand_alone',
            '2026_10_03_020000_promote_employee_on_cashflow_entries_table',
            '2026_10_03_060000_add_conversion_link_to_sales_invoices_table',
            '2026_10_21_130000_add_cash_invoice_conversions',
            '2026_10_07_130000_create_cashflow_recurrence_rules_table',
            '2026_10_07_130100_create_cashflow_recurrence_occurrences_table',
        ] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
        $this->actingAs(User::create(['name' => 'Finance', 'email' => 'finance@example.test', 'password' => 'password', 'role' => 'admin']));
    }

    protected function sale(array $attributes = []): SalesInvoice
    {
        return SalesInvoice::create(array_merge(['invoice_number' => 'SI-'.(SalesInvoice::count() + 1),
            'invoice_type' => 'proforma', 'status' => 'sent', 'currency' => 'INR',
            'total_amount' => 1000, 'amount_paid' => 0, 'balance_amount' => 1000], $attributes));
    }

    protected function bill(array $attributes = []): PurchaseInvoice
    {
        $vendor = Vendor::firstOrCreate(['vendor_number' => 'V-01'], ['vendor_name' => 'Supplier']);
        return PurchaseInvoice::create(array_merge(['invoice_number' => 'PB-'.(PurchaseInvoice::count() + 1),
            'public_token' => 'bill-token-'.(PurchaseInvoice::count() + 1),
            'invoice_type' => 'bill', 'status' => 'received', 'vendor_id' => $vendor->id,
            'currency' => 'INR', 'total_amount' => 1000, 'amount_paid' => 0, 'balance_amount' => 1000], $attributes));
    }

    protected function entry(array $attributes = []): CashflowEntry
    {
        $account = CashflowAccount::firstOrCreate(['account_name' => 'Bank'], ['account_type' => 'bank',
            'currency' => 'INR', 'opening_balance' => 0, 'current_balance' => 0]);
        return CashflowEntry::create(array_merge(['entry_date' => '2026-10-23', 'particular' => 'Receipt',
            'transaction_type' => 'credit', 'credit_amount' => 1000, 'debit_amount' => 0,
            'currency' => 'INR', 'account_id' => $account->id], $attributes));
    }

    protected function allocate(CashflowEntry $entry, SalesInvoice|PurchaseInvoice $invoice, float $cash, ?float $document = null): CashflowAllocation
    {
        return $entry->allocations()->create([
            $invoice instanceof SalesInvoice ? 'sales_invoice_id' : 'purchase_invoice_id' => $invoice->id,
            'amount' => $cash, 'document_amount' => $document,
            'currency' => $entry->currency, 'document_currency' => $invoice->currency,
        ]);
    }

    private function summary(SalesInvoice|PurchaseInvoice $invoice): array
    {
        $html = view('cashflows.partials.document-payment-option-data', compact('invoice'))->render();
        $matches = [];
        preg_match_all('/data-payment-([a-z-]+)="([^"]*)"/', $html, $matches, PREG_SET_ORDER);
        $data = [];
        foreach ($matches as $match) $data[$match[1]] = html_entity_decode($match[2], ENT_QUOTES);
        return $data;
    }

    public function test_sales_position_includes_opening_receipts_refunds_and_document_currency_allocations(): void
    {
        $invoice = $this->sale(['amount_paid' => 100, 'currency' => 'USD']);
        $this->entry(['sales_invoice_id' => $invoice->id, 'currency' => 'USD', 'credit_amount' => 200]);
        $this->entry(['sales_invoice_id' => $invoice->id, 'currency' => 'USD', 'transaction_type' => 'debit', 'credit_amount' => 0, 'debit_amount' => 50]);
        $this->allocate($this->entry(), $invoice, 8000, 80);
        $loaded = app(CashflowPickers::class)->salesInvoices()->firstWhere('id', $invoice->id);
        $data = $this->summary($loaded);
        $this->assertSame('1000', $data['total']);
        $this->assertSame('330', $data['paid']);
        $this->assertSame('670', $data['balance']);
        $this->assertSame('Balance due', $data['balance-label']);
        $this->assertSame('USD', $loaded->currency);
    }

    public function test_purchase_position_uses_active_vendor_payments_and_fx_allocations_without_derived_duplicates(): void
    {
        $bill = $this->bill(['amount_paid' => 100, 'currency' => 'USD']);
        foreach ([['paid', 250], ['cancelled', 300]] as [$status, $amount]) {
            VendorPaymentEntry::create(['vendor_id' => $bill->vendor_id, 'purchase_invoice_id' => $bill->id,
                'transaction_date' => '2026-10-23', 'foreign_currency' => 'USD', 'foreign_amount' => $amount,
                'exchange_rate' => 90, 'amount_in_inr' => $amount * 90, 'transaction_type' => 'debit',
                'entry_category' => 'payment', 'particular' => 'Bill payment', 'status' => $status]);
        }
        $entry = $this->entry(['transaction_type' => 'debit', 'credit_amount' => 0, 'debit_amount' => 4500, 'vendor_id' => $bill->vendor_id]);
        $this->allocate($entry, $bill, 4500, 50);
        $derived = app(CashflowVendorPaymentSync::class)->sync($entry);
        $this->assertNotNull($derived);
        $this->assertNull($derived->purchase_invoice_id);
        $data = $this->summary(app(CashflowPickers::class)->purchaseInvoices()->firstWhere('id', $bill->id));
        $this->assertSame('1000', $data['total']);
        $this->assertSame('400', $data['paid']);
        $this->assertSame('600', $data['balance']);
    }

    public function test_saved_editing_allocation_is_counted_once_and_legacy_amount_is_supported(): void
    {
        $invoice = $this->sale(['total_amount' => 630356]);
        $this->allocate($this->entry(['credit_amount' => 244440]), $invoice, 244440);
        $data = $this->summary(app(CashflowPickers::class)->salesInvoices()->first());
        $this->assertSame('244440', $data['paid']);
        $this->assertSame('385916', $data['balance']);
    }

    public function test_partial_cash_conversion_shows_current_receivable_and_not_the_original_pi_claim(): void
    {
        $invoice = $this->sale(['amount_paid' => 100, 'cash_converted_total_amount' => 200, 'cash_converted_base_amount' => 200]);
        $data = $this->summary(app(CashflowPickers::class)->salesInvoices()->first());
        $this->assertSame('800', $data['total']);
        $this->assertSame('100', $data['paid']);
        $this->assertSame('700', $data['balance']);
        $this->assertSame('Current receivable', $data['total-label']);
        $this->assertStringContainsString('excludes', $data['note']);
    }

    public function test_superseded_proforma_has_no_duplicate_balance(): void
    {
        $tax = $this->sale(['invoice_type' => 'tax']);
        $pi = $this->sale();
        $pi->forceFill(['converted_invoice_id' => $tax->id])->save();
        $data = $this->summary(app(CashflowPickers::class)->salesInvoices()->firstWhere('id', $pi->id));
        $this->assertSame('0', $data['balance']);
        $this->assertStringContainsString('converted', $data['note']);
    }

    public function test_purchase_order_shows_advance_capacity_and_preserves_approval_gate(): void
    {
        $order = $this->bill(['invoice_type' => 'order', 'status' => 'approved', 'amount_paid' => 200]);
        $data = $this->summary(app(CashflowPickers::class)->purchaseInvoices()->first());
        $this->assertSame('200', $data['paid']);
        $this->assertSame('800', $data['balance']);
        $this->assertSame('Available for advance', $data['balance-label']);
        $this->assertStringContainsString('no bill is due yet', $data['note']);
        $order->update(['status' => 'draft']);
        $data = $this->summary(app(CashflowPickers::class)->purchaseInvoices()->first());
        $this->assertSame('0', $data['balance']);
        $this->assertStringContainsString('approve', $data['note']);
    }

    public function test_fully_paid_and_overpaid_documents_never_show_negative_balance(): void
    {
        $this->sale(['amount_paid' => 1000]);
        $this->bill(['amount_paid' => 1200]);
        $sales = $this->summary(app(CashflowPickers::class)->salesInvoices()->first());
        $purchase = $this->summary(app(CashflowPickers::class)->purchaseInvoices()->first());
        $this->assertSame('1000', $sales['paid']);
        $this->assertSame('0', $sales['balance']);
        $this->assertSame('1200', $purchase['paid']);
        $this->assertSame('0', $purchase['balance']);
    }

    public function test_rendering_all_picker_options_adds_no_per_document_queries_and_excludes_cancelled_documents(): void
    {
        for ($i = 0; $i < 4; $i++) { $this->sale(); $this->bill(); }
        $this->sale(['status' => 'cancelled']);
        $this->bill(['status' => 'cancelled']);
        $sales = app(CashflowPickers::class)->salesInvoices();
        $purchases = app(CashflowPickers::class)->purchaseInvoices();
        $this->assertCount(4, $sales);
        $this->assertCount(4, $purchases);
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($sales->concat($purchases) as $invoice) $this->summary($invoice);
        $this->assertSame([], DB::getQueryLog(), 'All figures must use the eager-loaded sums, not N+1 ledger queries.');
        DB::disableQueryLog();
    }

    public function test_both_allocation_dialogs_render_payment_metadata_and_accessible_summary(): void
    {
        $invoice = $this->sale(['amount_paid' => 125]);
        $bill = $this->bill(['amount_paid' => 250]);
        $entry = $this->entry();
        view()->share('errors', new ViewErrorBag());
        $data = ['entry' => $entry, 'projects' => collect(), 'salesInvoices' => app(CashflowPickers::class)->salesInvoices(),
            'purchaseInvoices' => app(CashflowPickers::class)->purchaseInvoices(), 'allocationRows' => []];
        foreach (['detail-allocation-modal', 'allocation-builder'] as $partial) {
            $html = view('cashflows.partials.'.$partial, $data)->render();
            $this->assertStringContainsString('data-payment-paid="125"', $html);
            $this->assertStringContainsString('data-payment-paid="250"', $html);
            $this->assertStringContainsString('data-payment-balance="875"', $html);
            $this->assertStringContainsString('data-payment-balance="750"', $html);
            $this->assertStringContainsString('data-document-payment-summary hidden aria-live="polite"', $html);
            $this->assertStringContainsString('Unsaved changes are not included.', $html);
        }
    }

    public function test_real_cashflow_pages_load_payment_summary_script_before_dialog_handlers(): void
    {
        $this->sale(['amount_paid' => 125]);
        $this->bill(['amount_paid' => 250]);
        $entry = $this->entry();
        foreach (['cashflows.show', 'cashflows.edit'] as $route) {
            $response = $this->get(route($route, $entry))->assertOk()
                ->assertSee('data-payment-paid="125"', false)
                ->assertSee('data-payment-paid="250"', false)
                ->assertSee('Paid so far');
            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, 'src="'.asset('assets/js/cashflow-document-balance.js')));
            $this->assertLessThan(strpos($html, 'window.CashflowDocumentBalance.refresh'), strpos($html, 'src="'.asset('assets/js/cashflow-document-balance.js')));
        }
    }

    public function test_vendor_owned_mirror_still_prevents_duplicate_purchase_allocation(): void
    {
        $bill = $this->bill();
        $payment = VendorPaymentEntry::create(['vendor_id' => $bill->vendor_id, 'purchase_invoice_id' => $bill->id,
            'transaction_date' => '2026-10-23', 'foreign_currency' => 'INR', 'foreign_amount' => 100,
            'exchange_rate' => 1, 'amount_in_inr' => 100, 'transaction_type' => 'debit', 'particular' => 'Bill payment', 'status' => 'paid']);
        view()->share('errors', new ViewErrorBag());
        $data = ['entry' => $this->entry(), 'projects' => collect(), 'salesInvoices' => collect(),
            'purchaseInvoices' => app(CashflowPickers::class)->purchaseInvoices(), 'allocationRows' => [], 'linkedVendorPayment' => $payment];
        foreach (['detail-allocation-modal', 'allocation-builder'] as $partial) {
            $html = view('cashflows.partials.'.$partial, $data)->render();
            $this->assertStringContainsString('disabled', $html);
            $this->assertStringContainsString('Settled by the vendor payment', $html);
            $this->assertStringNotContainsString('data-payment-reference="'.$bill->invoice_number.'"', $html);
        }
    }
}
