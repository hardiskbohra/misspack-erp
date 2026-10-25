<?php

namespace Tests\Feature;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\Project;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ClientAccountSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientAccountSummaryTest extends TestCase
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
            '2026_08_26_010100_create_sales_invoice_items_table',
            '2026_08_26_010200_create_sales_invoice_attachments_table',
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

    protected function client(array $attributes = []): Client
    {
        return Client::create(array_merge(['client_number' => 'CL-'.(Client::count() + 1), 'company_name' => 'Client '.(Client::count() + 1), 'preferred_currency' => 'INR'], $attributes));
    }

    protected function invoice(Client $client, array $attributes = []): SalesInvoice
    {
        return SalesInvoice::create(array_merge(['invoice_number' => 'SI-'.(SalesInvoice::count() + 1),
            'invoice_type' => 'proforma', 'status' => 'sent', 'client_id' => $client->id,
            'total_amount' => 1000, 'currency' => 'INR', 'amount_paid' => 0], $attributes));
    }

    protected function cash(?Client $client, float $amount, array $attributes = []): CashflowEntry
    {
        $account = CashflowAccount::firstOrCreate(['account_name' => 'Bank'], ['account_type' => 'bank', 'currency' => 'INR']);
        return CashflowEntry::create(array_merge(['client_id' => $client?->id, 'account_id' => $account->id,
            'entry_date' => '2026-10-23', 'particular' => 'Client receipt', 'currency' => 'INR',
            'transaction_type' => $amount < 0 ? 'debit' : 'credit', 'credit_amount' => max($amount, 0), 'debit_amount' => max(-$amount, 0)], $attributes));
    }

    protected function project(Client $client): Project
    {
        $project = new Project(['client_id' => $client->id, 'name' => 'Delivery project',
            'project_number' => 'P-'.(Project::withTrashed()->count() + 1)]);
        $project->forceFill(['public_token' => 'project-token-'.(Project::withTrashed()->count() + 1)])->save();
        return $project;
    }

    protected function row(Client $client, string $currency = 'INR'): array
    {
        return collect(app(ClientAccountSummary::class)->forClient($client)['currencies'])->firstWhere('currency', $currency);
    }

    public function test_no_history_has_explicit_zero_figures_in_preferred_currency(): void
    {
        $client = $this->client(['preferred_currency' => 'USD']);
        $summary = app(ClientAccountSummary::class)->forClient($client);
        $this->assertSame(0, $summary['project_count']);
        $this->assertSame(0, $summary['invoice_count']);
        $this->assertSame('USD', $summary['currencies'][0]['currency']);
        foreach (['invoice_total', 'payments', 'invoice_balance', 'unapplied_credit', 'balance_due', 'net_credit'] as $field) {
            $this->assertSame(0.0, $summary['currencies'][0][$field], $field);
        }
    }

    public function test_opening_direct_receipts_and_refunds_are_counted_exactly_once(): void
    {
        $client = $this->client();
        $invoice = $this->invoice($client, ['amount_paid' => 100]);
        $this->cash($client, 300, ['sales_invoice_id' => $invoice->id]);
        $this->cash($client, -50, ['sales_invoice_id' => $invoice->id]);
        $row = $this->row($client);
        $this->assertSame(1000.0, $row['invoice_total']);
        $this->assertSame(350.0, $row['payments']);
        $this->assertSame(650.0, $row['invoice_balance']);
        $this->assertSame(650.0, $row['balance_due']);
        $this->assertSame(0.0, $row['unapplied_credit']);
    }

    public function test_partially_allocated_receipt_keeps_remainder_as_advance_without_double_counting(): void
    {
        $client = $this->client();
        $invoice = $this->invoice($client);
        $cash = $this->cash($client, 600);
        $cash->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => 400, 'document_amount' => 400]);
        $row = $this->row($client);
        $this->assertSame(600.0, $row['payments']);
        $this->assertSame(600.0, $row['invoice_balance']);
        $this->assertSame(200.0, $row['unapplied_credit']);
        $this->assertSame(400.0, $row['balance_due']);
        $this->assertSame('0.00', $invoice->fresh()->amount_paid, 'The summary must not apply the advance.');
    }

    public function test_on_account_advance_is_reduced_by_unlinked_client_refund(): void
    {
        $client = $this->client();
        $this->cash($client, 500);
        $this->cash($client, -100);
        $row = $this->row($client);
        $this->assertSame(400.0, $row['payments']);
        $this->assertSame(400.0, $row['unapplied_credit']);
        $this->assertSame(400.0, $row['net_credit']);
        $this->assertSame(0.0, $row['balance_due']);
    }

    public function test_overpaid_invoice_credit_and_refunds_have_consistent_net_position(): void
    {
        $client = $this->client();
        $this->invoice($client, ['amount_paid' => 1200]);
        $this->cash($client, -50);
        $row = $this->row($client);
        $this->assertSame(1150.0, $row['payments']);
        $this->assertSame(150.0, $row['unapplied_credit']);
        $this->assertSame(150.0, $row['net_credit']);
        $this->assertSame(0.0, $row['invoice_balance']);
    }

    public function test_fx_allocations_use_document_currency_and_leave_only_remainder_in_bank_currency(): void
    {
        $client = $this->client();
        $invoice = $this->invoice($client, ['currency' => 'USD', 'total_amount' => 1000]);
        $cash = $this->cash($client, 9000);
        $cash->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => 8000, 'document_amount' => 100, 'currency' => 'INR', 'document_currency' => 'USD']);
        $usd = $this->row($client, 'USD');
        $inr = $this->row($client);
        $this->assertSame(100.0, $usd['payments']);
        $this->assertSame(900.0, $usd['balance_due']);
        $this->assertSame(1000.0, $inr['unapplied_credit']);
        $this->assertSame(1000.0, $inr['payments']);
        $this->assertSame(1000.0, $inr['net_credit']);
        $this->assertSame(0.0, $usd['unapplied_credit']);
    }

    public function test_converted_proforma_and_partial_cash_conversions_never_duplicate_invoice_value(): void
    {
        $client = $this->client();
        $tax = $this->invoice($client, ['invoice_type' => 'tax', 'amount_paid' => 300]);
        $replaced = $this->invoice($client, ['amount_paid' => 0]);
        $replaced->forceFill(['converted_invoice_id' => $tax->id])->save();
        $this->invoice($client, ['total_amount' => 1000, 'cash_converted_total_amount' => 200, 'cash_converted_base_amount' => 200]);
        $this->invoice($client, ['invoice_type' => 'cash', 'total_amount' => 200, 'amount_paid' => 100]);
        $this->invoice($client, ['status' => 'cancelled', 'total_amount' => 99999]);
        $summary = app(ClientAccountSummary::class)->forClient($client);
        $this->assertSame(3, $summary['invoice_count']);
        $row = $summary['currencies'][0];
        $this->assertSame(2000.0, $row['invoice_total']);
        $this->assertSame(400.0, $row['payments']);
        $this->assertSame(1600.0, $row['balance_due']);
    }

    public function test_payments_on_cancelled_invoice_remain_client_credit_but_not_an_invoice_claim(): void
    {
        $client = $this->client();
        $invoice = $this->invoice($client, ['status' => 'cancelled']);
        $this->cash(null, 200, ['sales_invoice_id' => $invoice->id]);
        $row = $this->row($client);
        $this->assertSame(0.0, $row['invoice_total']);
        $this->assertSame(200.0, $row['payments']);
        $this->assertSame(200.0, $row['net_credit']);
    }

    public function test_project_receipts_are_client_advances_but_project_vendor_costs_are_not_client_refunds(): void
    {
        $client = $this->client();
        $project = $this->project($client);
        $this->cash(null, 500, ['project_id' => $project->id]);
        $vendor = Vendor::create(['vendor_number' => 'V-01', 'vendor_name' => 'Supplier']);
        $this->cash(null, -300, ['vendor_id' => $vendor->id, 'project_id' => $project->id]);
        $this->cash(null, -100, ['project_id' => $project->id, 'related_party_type' => 'expense']);
        $summary = app(ClientAccountSummary::class)->forClient($client);
        $this->assertSame(1, $summary['project_count']);
        $this->assertSame(500.0, $summary['currencies'][0]['payments']);
        $this->assertSame(500.0, $summary['currencies'][0]['unapplied_credit']);
        $project->delete();
        $archived = app(ClientAccountSummary::class)->forClient($client);
        $this->assertSame(0, $archived['project_count']);
        $this->assertSame(500.0, $archived['currencies'][0]['payments'], 'Archiving a project must not erase its client receipt.');
    }

    public function test_other_clients_and_ambiguous_shared_remainders_cannot_leak_into_account(): void
    {
        $a = $this->client(['company_name' => 'Acme']);
        $b = $this->client(['company_name' => 'Acme Limited']);
        $ia = $this->invoice($a);
        $ib = $this->invoice($b);
        $cash = $this->cash(null, 1000);
        $cash->allocations()->create(['sales_invoice_id' => $ia->id, 'amount' => 100, 'document_amount' => 100]);
        $cash->allocations()->create(['sales_invoice_id' => $ib->id, 'amount' => 200, 'document_amount' => 200]);
        $this->cash($b, 3000);
        $this->cash(null, 9999, ['related_party_type' => 'client', 'related_party_name' => 'Acme Limited']);
        $this->assertSame(100.0, $this->row($a)['payments']);
        $this->assertSame(0.0, $this->row($a)['unapplied_credit']);
        $this->assertSame(3200.0, $this->row($b)['payments']);
    }

    public function test_endpoint_and_profile_use_same_escaped_summary_with_no_store_response(): void
    {
        $client = $this->client(['company_name' => '<img src=x onerror=alert(1)> & Co']);
        $this->invoice($client, ['amount_paid' => 250]);
        $response = $this->getJson(route('clients.paymentSummary', $client))->assertOk()->assertJsonPath('client_id', $client->id);
        $html = $response->json('html');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('Net payments', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->get(route('clients.show', $client))->assertOk()->assertSee($html, false);
    }

    public function test_summary_endpoint_is_sales_permission_gated_and_not_available_to_employees(): void
    {
        $client = $this->client();
        auth()->user()->update(['access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $this->getJson(route('clients.paymentSummary', $client))->assertOk();
        auth()->user()->update(['permissions' => ['purchase.view']]);
        $this->getJson(route('clients.paymentSummary', $client))->assertForbidden();
        auth()->user()->update(['role' => 'employee']);
        $this->getJson(route('clients.paymentSummary', $client))->assertRedirect(route('my.dashboard'));
    }

    public function test_summary_query_count_does_not_grow_with_invoice_count(): void
    {
        $client = $this->client();
        $this->invoice($client);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(ClientAccountSummary::class)->forClient($client);
        $first = count(DB::getQueryLog());
        for ($i = 0; $i < 10; $i++) $this->invoice($client);
        DB::flushQueryLog();
        app(ClientAccountSummary::class)->forClient($client);
        $this->assertSame($first, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_client_inferred_from_one_allocated_invoice_owns_only_the_unapplied_remainder(): void
    {
        $client = $this->client();
        $invoice = $this->invoice($client);
        $cash = $this->cash(null, 500);
        $cash->allocations()->create(['sales_invoice_id' => $invoice->id, 'amount' => 200, 'document_amount' => 200]);
        $row = $this->row($client);
        $this->assertSame(500.0, $row['payments']);
        $this->assertSame(300.0, $row['unapplied_credit']);
        $this->assertSame(500.0, $row['balance_due']);
    }

    public function test_validation_recovery_keeps_selected_client_and_does_not_fill_payment_fields(): void
    {
        $a = $this->client();
        $b = $this->client();
        $invoice = $this->invoice($a, ['amount_paid' => 125]);
        $this->withSession(['_old_input' => ['client_id' => (string) $b->id, 'amount_paid' => '35']])
            ->get(route('sales-invoices.edit', $invoice))->assertOk()
            ->assertSee('data-invoice-client-account', false)->assertSee('value="35"', false);
        $this->assertSame('125.00', $invoice->fresh()->amount_paid);
        $this->getJson(route('clients.paymentSummary', $b))->assertOk();
        $this->assertSame(0.0, $this->row($b)['payments']);
    }

    public function test_invoice_form_has_summary_for_all_document_types_and_preselected_clients(): void
    {
        $client = $this->client();
        foreach (['proforma', 'tax', 'cash'] as $type) {
            $this->get(route('sales-invoices.create', ['type' => $type, 'client_id' => $client->id]))
                ->assertOk()->assertSee('data-invoice-client-account', false)
                ->assertSee('invoice-client-account.js')->assertSee('client-account-summary.css');
        }
        $this->getJson(route('clients.paymentSummary', 99999))->assertNotFound();
    }
}
