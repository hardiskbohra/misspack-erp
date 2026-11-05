<?php

namespace Tests\Feature;

use App\Http\Controllers\SalesInvoiceController;
use App\Models\AuditLog;
use App\Models\CashflowAccount;
use App\Models\CashflowAllocation;
use App\Models\CashflowEntry;
use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\ProjectWorkflow;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\CashInvoiceConversion;
use App\Services\CashInvoiceDeletion;
use App\Services\CashflowLedger;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashInvoiceDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
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

    public function test_delete_restores_the_pi_value_and_opening_credit_and_redirects_to_it(): void
    {
        $pi = $this->pi(['amount_paid' => 40]);
        $cash = $this->convert($pi, 50, 15);
        $response = (new SalesInvoiceController)->destroy($cash);
        $this->assertSame(route('sales-invoices.show', $pi), $response->getTargetUrl());
        $this->assertModelMissing($cash);
        $pi->refresh();
        $this->assertSame(0.0, (float) $pi->cash_converted_base_amount);
        $this->assertSame(0.0, (float) $pi->cash_converted_total_amount);
        $this->assertSame(118.0, $pi->receivableTotal());
        $this->assertSame(40.0, $pi->receivedAmount());
        $this->assertSame(78.0, (float) $pi->balance_amount);
        $this->assertFalse($pi->hasMonetaryConversions());
        $this->assertSame(1, $pi->items()->count());
        $this->assertSame(9.0, (float) $pi->cgst_amount);
        $this->assertSame(1, AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->count());
    }

    public function test_full_conversion_is_reopened_and_deleted_document_numbers_are_never_reused(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 100);
        $this->assertTrue($pi->fresh()->isSuperseded());
        (new SalesInvoiceController)->destroy($cash);
        $pi->refresh();
        $this->assertNull($pi->converted_invoice_id);
        $this->assertFalse($pi->isSuperseded());
        $this->assertSame(118.0, $pi->balanceDue());
        $newCash = $this->convert($pi, 100);
        $this->assertNotSame($cash->invoice_number, $newCash->invoice_number);
        $this->assertSame(100.0, (float) $newCash->total_amount);
    }

    public function test_a_reversed_invoice_number_cannot_be_reused_manually(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        (new SalesInvoiceController)->destroy($cash);
        try {
            (new SalesInvoiceController)->store(Request::create('/', 'POST', [
                'invoice_number' => $cash->invoice_number, 'invoice_type' => 'cash', 'status' => 'draft',
                'currency' => 'INR', 'gst_type' => 'none',
                'items' => [['product_name' => 'Packaging', 'quantity' => 1, 'unit_price' => 50]],
            ]));
            $this->fail('Audit history must reserve manually entered numbers too.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('invoice_number', $error->errors());
            $this->assertStringContainsString('reserved', $error->errors()['invoice_number'][0]);
        }
        $this->assertSame(0, SalesInvoice::where('invoice_type', 'cash')->count());
    }

    public function test_deleting_an_earlier_sibling_clears_the_full_conversion_pointer_and_preserves_rounding(): void
    {
        $pi = $this->pi();
        $first = $this->convert($pi, 33.33);
        $second = $this->convert($pi, 33.33);
        $last = $this->convert($pi, 33.34);
        $this->assertSame($last->id, $pi->fresh()->converted_invoice_id);
        (new SalesInvoiceController)->destroy($first);
        $pi->refresh();
        $this->assertNull($pi->converted_invoice_id);
        $this->assertSame(66.67, (float) $pi->cash_converted_base_amount);
        $this->assertSame(78.67, (float) $pi->cash_converted_total_amount);
        $this->assertSame(39.33, $pi->receivableTotal());
        $this->assertModelExists($second);
        $this->assertModelExists($last);
        $replacement = $this->convert($pi, 33.33);
        $this->assertSame(118.0, (float) $pi->fresh()->cash_converted_total_amount);
        (new SalesInvoiceController)->destroy($replacement);
        (new SalesInvoiceController)->destroy($second);
        (new SalesInvoiceController)->destroy($last);
        $this->assertSame(118.0, $pi->fresh()->receivableTotal());
        $this->assertSame(0.0, (float) $pi->fresh()->cash_converted_base_amount);
    }

    public function test_split_receipts_and_opening_credit_return_without_duplicating_bank_money(): void
    {
        $pi = $this->pi(['amount_paid' => 10]);
        $receipt = $this->receipt($pi, 100);
        CashflowLedger::recalculateAccount($receipt->account_id);
        $bank = CashflowAccount::findOrFail($receipt->account_id)->current_balance;
        $cash = $this->convert($pi, 50, 40);
        $slices = CashflowAllocation::orderBy('id')->get()->map->only(['id', 'amount', 'document_amount', 'currency', 'document_currency'])->all();
        $this->assertCount(2, $slices);
        (new SalesInvoiceController)->destroy($cash);
        $this->assertSame(110.0, $pi->fresh()->receivedAmount());
        $this->assertSame(1, CashflowEntry::count());
        $this->assertSame($slices, CashflowAllocation::orderBy('id')->get()->map->only(['id', 'amount', 'document_amount', 'currency', 'document_currency'])->all());
        $this->assertSame(2, CashflowAllocation::where('sales_invoice_id', $pi->id)->count());
        $this->assertSame($bank, CashflowAccount::findOrFail($receipt->account_id)->current_balance);
        $this->assertSame(100.0, (float) $receipt->fresh()->credit_amount);
    }

    public function test_later_direct_receipts_refunds_and_pending_status_return_but_unrelated_receipts_do_not_move(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 100);
        $receipt = $this->receipt($cash, 150);
        $receipt->update(['invoice_bill_number' => $cash->invoice_number, 'accounting_status' => 'pending']);
        $refund = $this->receipt($cash, 0);
        $refund->update(['debit_amount' => 5, 'notes' => 'Refund evidence']);
        $other = $this->pi();
        $unrelated = $this->receipt($other, 17);
        CashflowLedger::recalculateAccount($receipt->account_id);
        $bank = CashflowAccount::findOrFail($receipt->account_id)->current_balance;
        (new SalesInvoiceController)->destroy($cash);
        $this->assertSame($pi->id, $receipt->fresh()->sales_invoice_id);
        $this->assertSame($pi->invoice_number, $receipt->fresh()->invoice_bill_number);
        $this->assertSame('pending', $receipt->fresh()->accounting_status);
        $this->assertSame($pi->id, $refund->fresh()->sales_invoice_id);
        $this->assertSame('Refund evidence', $refund->fresh()->notes);
        $this->assertSame(5.0, (float) $refund->fresh()->debit_amount);
        $this->assertSame($other->id, $unrelated->fresh()->sales_invoice_id);
        $this->assertSame(145.0, $pi->fresh()->receivedAmount());
        $this->assertSame(27.0, $pi->fresh()->advanceAmount());
        $this->assertSame($bank, CashflowAccount::findOrFail($receipt->account_id)->current_balance);
    }

    public function test_fx_allocations_keep_their_native_and_document_values_on_return(): void
    {
        $pi = $this->pi(['currency' => 'USD']);
        $receipt = $this->receipt(null, 8300);
        $receipt->allocations()->create(['sales_invoice_id' => $pi->id, 'amount' => 8300,
            'document_amount' => 100, 'currency' => 'INR', 'document_currency' => 'USD']);
        $cash = $this->convert($pi, 50, 40);
        $values = CashflowAllocation::orderBy('id')->get()->map->only(['id', 'cashflow_entry_id', 'amount', 'document_amount', 'currency', 'document_currency'])->all();
        (new SalesInvoiceController)->destroy($cash);
        $this->assertSame(100.0, $pi->fresh()->receivedAmount());
        $this->assertSame($values, CashflowAllocation::orderBy('id')->get()->map->only(['id', 'cashflow_entry_id', 'amount', 'document_amount', 'currency', 'document_currency'])->all());
        $this->assertSame(8300.0, (float) $receipt->fresh()->credit_amount);
    }

    public function test_a_tax_remainder_keeps_cash_deletion_blocked_without_changing_any_money(): void
    {
        $pi = $this->pi(['amount_paid' => 30]);
        $cash = $this->convert($pi, 50, 10);
        (new SalesInvoiceController)->convert(Request::create('/', 'POST'), $pi->fresh());
        $before = $pi->fresh()->getAttributes();
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('A tax remainder must not disappear or be duplicated by a cash reversal.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('tax invoice', $error->getMessage());
        }
        $this->assertModelExists($cash);
        $this->assertSame($before, $pi->fresh()->getAttributes());
        $this->assertSame(10.0, $cash->fresh()->receivedAmount());
        $this->assertSame(0, AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->count());
    }

    public function test_stale_cash_models_cannot_change_the_reversal_values(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $cash->conversion_base_amount = 999;
        $cash->amount_paid = 999;
        $receipt = $this->receipt($cash, 20);
        (new SalesInvoiceController)->destroy($cash);
        $this->assertSame(118.0, $pi->fresh()->receivableTotal());
        $this->assertSame(20.0, $pi->fresh()->receivedAmount());
        $this->assertSame($pi->id, $receipt->fresh()->sales_invoice_id);
    }

    public function test_audit_failure_rolls_back_the_invoice_credit_receipt_links_and_pi_totals(): void
    {
        $pi = $this->pi(['amount_paid' => 20]);
        $cash = $this->convert($pi, 50, 10);
        $receipt = $this->receipt($cash, 30);
        $before = $pi->fresh()->getAttributes();
        $audit = \Mockery::mock(AuditTrail::class);
        $audit->shouldReceive('event')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->app->instance(AuditTrail::class, $audit);
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('An unrecorded reversal must not commit.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit unavailable', $error->getMessage());
        }
        $this->assertModelExists($cash);
        $this->assertSame($before, $pi->fresh()->getAttributes());
        $this->assertSame($cash->id, $receipt->fresh()->sales_invoice_id);
        $this->assertSame(40.0, $cash->fresh()->receivedAmount());
    }

    public function test_inconsistent_caches_or_ownership_block_the_reversal(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $pi->fresh()->update(['cash_converted_base_amount' => 49]);
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('Do not guess how to restore corrupted conversion history.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('reconcile', $error->getMessage());
        }
        $this->assertModelExists($cash);
        $this->assertSame(49.0, (float) $pi->fresh()->cash_converted_base_amount);
    }

    public function test_a_missing_audit_table_blocks_reversal(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        Schema::drop('audit_logs');
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('Reversal needs a persistent audit record.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('audit register', $error->getMessage());
        }
        $this->assertModelExists($cash);
        $this->assertSame(59.0, $pi->fresh()->receivableTotal());
    }

    public function test_repeating_a_delete_never_restores_credit_twice(): void
    {
        $pi = $this->pi(['amount_paid' => 40]);
        $cash = $this->convert($pi, 50, 15);
        (new SalesInvoiceController)->destroy($cash);
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('The second request must not reverse an already deleted invoice.');
        } catch (ModelNotFoundException) {
            $this->assertSame(40.0, $pi->fresh()->receivedAmount());
        }
        $this->assertSame(1, AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->count());
    }

    public function test_audit_retains_the_document_and_attachment_evidence_but_not_the_public_token(): void
    {
        Storage::fake('public');
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        Storage::disk('public')->put('invoices/cash-evidence.pdf', 'evidence');
        $cash->attachments()->create(['file_path' => 'invoices/cash-evidence.pdf', 'original_name' => 'receipt.pdf']);
        (new SalesInvoiceController)->destroy($cash);
        $context = AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->firstOrFail()->context;
        $this->assertSame($cash->invoice_number, $context['invoice']['invoice_number']);
        $this->assertArrayNotHasKey('public_token', $context['invoice']);
        $this->assertSame($pi->id, $context['pi_id']);
        $this->assertCount(1, $context['items']);
        $this->assertSame('invoices/cash-evidence.pdf', $context['attachments'][0]['file_path']);
        Storage::disk('public')->assertExists('invoices/cash-evidence.pdf');
        $this->assertDatabaseMissing('sales_invoice_attachments', ['sales_invoice_id' => $cash->id]);
    }

    public function test_http_delete_requires_sales_manage_and_records_the_reversal_actor(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $user = User::create(['name' => 'Accounts', 'email' => 'accounts@example.test', 'password' => 'password',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $this->actingAs($user)->delete(route('sales-invoices.destroy', $cash))->assertForbidden();
        $this->assertModelExists($cash);
        $user->permissions = ['sales.manage'];
        $this->actingAs($user)->delete(route('sales-invoices.destroy', $cash))->assertRedirect(route('sales-invoices.show', $pi));
        $this->assertSame($user->id, AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->firstOrFail()->actor_id);
        $this->assertModelMissing($cash);
    }

    public function test_csrf_guard_still_protects_the_reversal(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $user = User::create(['name' => 'Accounts', 'email' => 'accounts@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->app->detectEnvironment(fn () => 'local');
        $this->actingAs($user)->delete(route('sales-invoices.destroy', $cash))->assertStatus(419);
        $this->assertModelExists($cash);
        $this->assertSame(59.0, $pi->fresh()->receivableTotal());
    }

    public function test_workflow_cash_baseline_is_restored_to_the_pi_and_dispatch_balance_is_rechecked(): void
    {
        $client = \App\Models\Client::create(['client_number' => 'CLIENT-R', 'company_name' => 'Buyer']);
        $project = new Project(['client_id' => $client->id, 'project_number' => 'PRJ-R', 'name' => 'Packaging', 'currency' => 'INR']);
        $project->forceFill(['public_token' => 'project-reversal'])->save();
        $product = ProjectProduct::create(['project_id' => $project->id, 'product_name' => 'Bottle', 'quantity' => 100, 'unit' => 'pcs', 'unit_price' => 1]);
        $pi = $this->pi(['project_id' => $project->id, 'client_id' => $client->id, 'balance_before_dispatch' => true]);
        $cash = $this->convert($pi, 100);
        $this->receipt($cash, 100);
        $workflow = ProjectWorkflow::create(['project_id' => $project->id, 'commercial_sales_invoice_id' => $cash->id]);
        (new SalesInvoiceController)->destroy($cash);
        $this->assertSame($pi->id, $workflow->fresh()->commercial_sales_invoice_id);
        $this->assertSame(18.0, $pi->fresh()->balanceDue());
        $this->assertSame('blocked', $workflow->evaluations()->where('gate_key', 'dispatch_balance')->latest('id')->firstOrFail()->result);
        $this->assertSame(1, ProjectProduct::count());
        $this->assertSame(100.0, (float) $product->fresh()->quantity);
    }

    public function test_a_rejected_model_delete_rolls_back_the_reversal_and_audit(): void
    {
        $pi = $this->pi(['amount_paid' => 30]);
        $cash = $this->convert($pi, 50, 10);
        $event = 'eloquent.deleting: '.SalesInvoice::class;
        \Illuminate\Support\Facades\Event::listen($event, fn ($model) => $model->id === $cash->id ? false : null);
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('A rejected document delete must roll back its financial reversal.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('No reversal was saved', $error->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertModelExists($cash);
        $this->assertSame(20.0, $pi->fresh()->receivedAmount());
        $this->assertSame(10.0, $cash->fresh()->receivedAmount());
        $this->assertSame(59.0, $pi->fresh()->receivableTotal());
        $this->assertSame(0, AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->count());
    }

    public function test_a_rejected_receipt_reassignment_rolls_back_the_reversal(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $entry = $this->receipt($cash, 20);
        $event = 'eloquent.updating: '.CashflowEntry::class;
        \Illuminate\Support\Facades\Event::listen($event, fn () => false);
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('A rejected receipt move must not delete the invoice.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Nothing was changed', $error->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertModelExists($cash);
        $this->assertSame($cash->id, $entry->fresh()->sales_invoice_id);
        $this->assertSame(59.0, $pi->fresh()->receivableTotal());
        $this->assertSame(0, AuditLog::where('event', CashInvoiceDeletion::AUDIT_EVENT)->count());
    }

    public function test_a_rejected_audit_insert_rolls_back_the_reversal(): void
    {
        $pi = $this->pi(['amount_paid' => 30]);
        $cash = $this->convert($pi, 50, 10);
        $event = 'eloquent.creating: '.AuditLog::class;
        \Illuminate\Support\Facades\Event::listen($event, fn () => false);
        try {
            (new SalesInvoiceController)->destroy($cash);
            $this->fail('No invoice can be deleted without its reversal audit.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('Nothing was changed', $error->getMessage());
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $this->assertModelExists($cash);
        $this->assertSame(20.0, $pi->fresh()->receivedAmount());
        $this->assertSame(10.0, $cash->fresh()->receivedAmount());
        $this->assertSame(59.0, $pi->fresh()->receivableTotal());
    }

    public function test_record_page_and_list_offer_the_safe_cash_delete_but_not_original_pi_deletion(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $user = User::create(['name' => 'Accounts', 'email' => 'views@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($user)->get(route('sales-invoices.show', $cash))->assertOk()
            ->assertSee('reverse its cash conversion')->assertSee('Bank balances will not change')
            ->assertSee('Delete reverses this cash conversion');
        $this->get(route('sales-invoices.show', $pi))->assertOk()
            ->assertDontSee('action="'.route('sales-invoices.destroy', $pi).'"', false);
        $this->get(route('sales-invoices.index'))->assertOk()
            ->assertSee('reverse its cash conversion')
            ->assertDontSee('action="'.route('sales-invoices.destroy', $pi).'"', false);
    }

    public function test_confirmation_explains_that_the_cash_conversion_and_receipts_are_reversed(): void
    {
        $pi = $this->pi();
        $cash = $this->convert($pi, 50);
        $this->assertStringContainsString('reverse its cash conversion', $cash->deleteConfirmation());
        $this->assertStringContainsString('Bank balances will not change', $cash->deleteConfirmation());
        $this->assertSame('Delete '.$pi->invoice_number.'?', $pi->deleteConfirmation());
    }
}
