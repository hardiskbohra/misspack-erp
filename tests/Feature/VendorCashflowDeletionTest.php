<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPaymentEntry;
use App\Services\CashflowAllocations;
use App\Services\CashflowLedger;
use App\Services\CashflowVendorPaymentSync;
use App\Services\PurchaseInvoiceMoney;
use App\Services\VendorPaymentCashflowSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorCashflowDeletionTest extends TestCase
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
        ] as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
        $this->actingAs(User::create(['name' => 'Finance', 'email' => 'finance@example.test', 'password' => 'password', 'role' => 'admin']));
    }

    private function bill(array $attributes = []): PurchaseInvoice
    {
        $vendor = Vendor::firstOrCreate(['vendor_number' => 'V-01'], ['vendor_name' => 'Supplier']);
        return PurchaseInvoice::create(array_merge(['invoice_number' => 'PB-'.(PurchaseInvoice::count() + 1),
            'public_token' => 'bill-token-'.(PurchaseInvoice::count() + 1),
            'invoice_type' => 'bill', 'status' => 'received', 'vendor_id' => $vendor->id,
            'currency' => 'INR', 'total_amount' => 1000, 'amount_paid' => 0, 'balance_amount' => 1000], $attributes));
    }

    private function account(): CashflowAccount
    {
        return CashflowAccount::create(['account_name' => 'Bank '.(CashflowAccount::count() + 1),
            'account_type' => 'bank', 'currency' => 'INR', 'opening_balance' => 10000, 'current_balance' => 10000]);
    }

    private function payment(PurchaseInvoice $bill, float $amount = 1000, ?CashflowAccount $account = null): VendorPaymentEntry
    {
        $account ??= $this->account();
        $payment = VendorPaymentEntry::create(['vendor_id' => $bill->vendor_id, 'purchase_invoice_id' => $bill->id,
            'transaction_date' => '2026-10-23', 'foreign_currency' => $bill->currency, 'foreign_amount' => $amount,
            'exchange_rate' => $bill->currency === 'INR' ? 1 : 10, 'transaction_type' => 'debit',
            'entry_category' => 'payment', 'particular' => 'Payment against '.$bill->invoice_number,
            'status' => 'paid', 'paid_account_id' => $account->id, 'payment_mode' => 'bank_transfer',
            'amount_in_inr' => $amount * ($bill->currency === 'INR' ? 1 : 10)]);
        app(VendorPaymentCashflowSync::class)->sync($payment);
        app(PurchaseInvoiceMoney::class)->refresh([$bill->id]);
        return $payment->fresh();
    }

    private function deleteMirror(VendorPaymentEntry $payment): void
    {
        $this->delete(route('cashflows.destroy', $payment->cashflow_entry_id))
            ->assertRedirect(route('cashflows.index'))->assertSessionHasNoErrors();
    }

    public function test_deleting_mirror_cancels_payment_and_reopens_bill_with_audit_and_bank_balance(): void
    {
        $bill = $this->bill();
        $payment = $this->payment($bill);
        $id = $payment->cashflow_entry_id;
        Storage::disk('public')->put('evidence/payment.pdf', 'Payment evidence');
        $payment->attachments()->create(['file_path' => 'evidence/payment.pdf', 'original_name' => 'payment.pdf']);
        $this->assertSame('paid', $bill->fresh()->status);
        $this->deleteMirror($payment);
        $this->assertNull(CashflowEntry::find($id));
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->cashflow_entry_id);
        $this->assertSame(1, $payment->attachments()->count());
        Storage::disk('public')->assertExists('evidence/payment.pdf');
        $this->assertSame('received', $bill->fresh()->status);
        $this->assertSame(0.0, $bill->fresh()->paidAmount());
        $this->assertSame(1000.0, $bill->fresh()->balanceDue());
        $this->assertSame('1000.00', $bill->fresh()->balance_amount);
        $this->assertSame('10000.00', $payment->paidAccount->fresh()->current_balance);
        $audit = AuditLog::where('event', 'vendor-payment.cashflow-deletion-reversal')->firstOrFail();
        $this->assertSame($payment->id, $audit->auditable_id);
        $this->assertSame(auth()->id(), $audit->actor_id);
        $this->assertSame($id, $audit->context['cashflow_entry_id']);
        $this->assertSame('paid', $audit->context['payment_before']['status']);
    }

    public function test_other_payments_and_opening_paid_amount_are_preserved(): void
    {
        $bill = $this->bill(['amount_paid' => 100]);
        $kept = $this->payment($bill, 200);
        $removed = $this->payment($bill, 700);
        $this->deleteMirror($removed);
        $this->assertSame('paid', $kept->fresh()->status);
        $this->assertNotNull(CashflowEntry::find($kept->cashflow_entry_id));
        $this->assertSame(300.0, $bill->fresh()->paidAmount());
        $this->assertSame(700.0, $bill->fresh()->balanceDue());
        $this->assertSame('partial', $bill->fresh()->status);
        $this->assertSame('100.00', $bill->fresh()->amount_paid);
    }

    public function test_foreign_currency_bill_reverses_document_amount_not_inr_bank_amount(): void
    {
        $bill = $this->bill(['currency' => 'RMB']);
        $payment = $this->payment($bill, 1000);
        $this->assertSame('10000.00', $payment->amount_in_inr);
        $this->deleteMirror($payment);
        $this->assertSame(1000.0, $bill->fresh()->balanceDue());
        $this->assertSame(0.0, $bill->fresh()->paidAmount());
        $this->assertSame('received', $bill->fresh()->status);
    }

    public function test_bulk_deletion_reverses_each_bill_and_recalculates_each_account(): void
    {
        $a = $this->bill(); $b = $this->bill();
        $pa = $this->payment($a); $pb = $this->payment($b, 400);
        $this->post(route('cashflows.bulk'), ['action' => 'delete', 'ids' => [$pa->cashflow_entry_id, $pb->cashflow_entry_id]])
            ->assertSessionHasNoErrors();
        foreach ([$a, $b] as $bill) {
            $this->assertSame('received', $bill->fresh()->status);
            $this->assertSame(1000.0, $bill->fresh()->balanceDue());
        }
        foreach ([$pa, $pb] as $payment) {
            $this->assertSame('cancelled', $payment->fresh()->status);
            $this->assertSame('10000.00', $payment->paidAccount->fresh()->current_balance);
        }
        $this->assertSame(0, CashflowEntry::count());
        $this->assertSame(2, AuditLog::where('event', 'vendor-payment.cashflow-deletion-reversal')->count());
    }

    public function test_cancelled_rows_are_excluded_in_lazy_eager_with_paid_and_sql_totals(): void
    {
        $bill = $this->bill();
        $this->payment($bill, 200);
        $cancelled = $this->payment($bill, 800);
        $cancelled->update(['status' => 'cancelled']);
        $this->assertSame(200.0, $bill->fresh()->paidAmount());
        $this->assertSame(200.0, $bill->fresh()->load('payments')->paidAmount());
        $this->assertSame(200.0, PurchaseInvoice::withPaid()->findOrFail($bill->id)->paidAmount());
        $sql = PurchaseInvoice::whereKey($bill->id)->selectRaw(PurchaseInvoice::PAID_SQL.' as calculated_paid')->firstOrFail();
        $this->assertSame(200.0, (float) $sql->calculated_paid);
    }

    public function test_disabling_cashflow_mirroring_does_not_reverse_a_real_vendor_payment(): void
    {
        $bill = $this->bill();
        $payment = $this->payment($bill);
        app(VendorPaymentCashflowSync::class)->sync($payment, false);
        $this->assertSame(0, CashflowEntry::count());
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1000.0, $bill->fresh()->paidAmount());
        $this->assertSame(0, AuditLog::where('event', 'vendor-payment.cashflow-deletion-reversal')->count());
    }

    public function test_cashflow_originated_payment_keeps_existing_derived_row_removal_behavior(): void
    {
        $bill = $this->bill();
        $entry = CashflowEntry::create(['entry_date' => '2026-10-23', 'particular' => 'Direct bank payment',
            'transaction_type' => 'debit', 'debit_amount' => 1000, 'currency' => 'INR', 'vendor_id' => $bill->vendor_id,
            'account_id' => $this->account()->id]);
        app(CashflowAllocations::class)->sync($entry, [['purchase_invoice_id' => $bill->id, 'amount' => 1000]]);
        $derived = app(CashflowVendorPaymentSync::class)->sync($entry);
        $this->assertSame('paid', $bill->fresh()->status);
        $this->delete(route('cashflows.destroy', $entry))->assertSessionHasNoErrors();
        $this->assertNull($derived->fresh());
        $this->assertSame('received', $bill->fresh()->status);
        $this->assertSame(1000.0, $bill->fresh()->balanceDue());
    }

    public function test_failed_cashflow_deletion_rolls_back_payment_bill_allocations_and_audit(): void
    {
        $bill = $this->bill();
        $payment = $this->payment($bill);
        $id = $payment->cashflow_entry_id;
        CashflowAllocation::create(['cashflow_entry_id' => $id, 'amount' => 100, 'currency' => 'INR']);
        CashflowEntry::deleting(fn () => false);
        $this->delete(route('cashflows.destroy', $id))->assertSessionHasErrors('entry');
        $this->assertNotNull(CashflowEntry::find($id));
        $this->assertSame(1, CashflowAllocation::where('cashflow_entry_id', $id)->count());
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame($id, $payment->fresh()->cashflow_entry_id);
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(0, AuditLog::where('event', 'vendor-payment.cashflow-deletion-reversal')->count());
    }

    public function test_ledger_only_payments_are_never_automatically_reversed(): void
    {
        $bill = $this->bill();
        $payment = $this->payment($bill);
        app(VendorPaymentCashflowSync::class)->sync($payment, false);
        $other = CashflowEntry::create(['entry_date' => '2026-10-23', 'particular' => 'Unrelated entry',
            'transaction_type' => 'debit', 'debit_amount' => 20, 'currency' => 'INR', 'account_id' => $this->account()->id]);
        $this->delete(route('cashflows.destroy', $other))->assertSessionHasNoErrors();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(1000.0, $bill->fresh()->paidAmount());
    }

    public function test_already_orphaned_payment_can_be_cancelled_in_vendor_money_without_guessing(): void
    {
        $bill = $this->bill();
        $payment = $this->payment($bill);
        // Simulate the old deletion behavior: the bank row disappeared, but the payment survived.
        $payment->cashflowEntry->delete();
        $payment->update(['cashflow_entry_id' => null]);
        $this->put(route('vendors.payments.update', [$bill->vendor_id, $payment]), [
            'transaction_date' => '2026-10-23', 'foreign_amount' => 1000, 'foreign_currency' => 'INR',
            'exchange_rate' => 1, 'particular' => $payment->particular, 'status' => 'cancelled',
            'transaction_type' => 'debit', 'entry_category' => 'payment', 'purchase_invoice_id' => $bill->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('received', $bill->fresh()->status);
        $this->assertSame(1000.0, $bill->fresh()->balanceDue());
    }

    public function test_deleting_from_vendor_money_refreshes_bill_too(): void
    {
        $bill = $this->bill();
        $payment = $this->payment($bill);
        $this->delete(route('vendors.payments.destroy', $payment))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($payment->fresh());
        $this->assertSame(0, CashflowEntry::count());
        $this->assertSame('received', $bill->fresh()->status);
        $this->assertSame(1000.0, $bill->fresh()->balanceDue());
    }

    public function test_bulk_failure_rolls_back_all_selected_entries_and_reversals(): void
    {
        $a = $this->bill(); $b = $this->bill();
        $pa = $this->payment($a); $pb = $this->payment($b);
        CashflowEntry::deleting(fn ($entry) => $entry->id === $pb->cashflow_entry_id ? false : null);
        $this->post(route('cashflows.bulk'), ['action' => 'delete', 'ids' => [$pa->cashflow_entry_id, $pb->cashflow_entry_id]])
            ->assertSessionHasErrors('entry');
        $this->assertSame(2, CashflowEntry::count());
        foreach ([$pa, $pb] as $payment) $this->assertSame('paid', $payment->fresh()->status);
        foreach ([$a, $b] as $bill) $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(0, AuditLog::where('event', 'vendor-payment.cashflow-deletion-reversal')->count());
    }

    public function test_failed_payment_cancellation_prevents_cashflow_deletion(): void
    {
        $bill = $this->bill(); $payment = $this->payment($bill);
        VendorPaymentEntry::updating(fn ($entry) => $entry->status === 'cancelled' ? false : null);
        $this->delete(route('cashflows.destroy', $payment->cashflow_entry_id))->assertSessionHasErrors('entry');
        $this->assertNotNull($payment->fresh()->cashflowEntry);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_view_only_finance_staff_cannot_reverse_or_delete_payments(): void
    {
        $bill = $this->bill(); $payment = $this->payment($bill);
        auth()->user()->update(['access_profile' => 'custom', 'permissions' => ['finance.view']]);
        $this->delete(route('cashflows.destroy', $payment->cashflow_entry_id))->assertForbidden();
        $this->post(route('cashflows.bulk'), ['action' => 'delete', 'ids' => [$payment->cashflow_entry_id]])->assertForbidden();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->cashflowEntry);
    }

    public function test_bill_detail_and_listing_do_not_present_cancelled_payment_as_paid(): void
    {
        $bill = $this->bill(); $payment = $this->payment($bill);
        $this->deleteMirror($payment);
        $this->get(route('purchase-invoices.show', $bill))->assertOk()
            ->assertViewHas('payments', fn ($payments) => $payments->isEmpty())
            ->assertSee('Nothing paid against this bill yet.');
        $this->get(route('purchase-invoices.index', ['type' => 'bill']))->assertOk()
            ->assertViewHas('invoices', fn ($invoices) => $invoices->first()->paidAmount() === 0.0);
    }

    public function test_reversing_advance_preserves_order_approval_and_cancelled_bill_state(): void
    {
        foreach ([['invoice_type' => 'order', 'status' => 'approved'], ['status' => 'draft'], ['status' => 'cancelled']] as $attributes) {
            $bill = $this->bill($attributes);
            $payment = $this->payment($bill, 300);
            $this->deleteMirror($payment);
            $this->assertSame($attributes['status'], $bill->fresh()->status);
            $this->assertSame(0.0, $bill->fresh()->paidAmount());
        }
    }
}
