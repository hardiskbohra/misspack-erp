<?php

namespace Tests\Feature;

use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\Project;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientProfileTabsTest extends TestCase
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

    public function test_badges_count_client_records_and_project_tab_lists_only_live_client_projects(): void
    {
        $a = $this->client(['ceo_name' => 'Owner', 'account_person_email' => 'account@example.test',
            'billing_address' => 'Office', 'shipping_city' => 'Ahmedabad']);
        $b = $this->client();
        $this->project($a); $this->project($a);
        $this->project($a)->delete(); $other = $this->project($b);
        $this->invoice($a); $this->invoice($a, ['status' => 'cancelled']); $this->invoice($b);
        $this->cash($a, 100); $this->cash(null, 200, ['related_party_type' => 'client', 'related_party_name' => $a->company_name]);
        $this->cash($b, 1000);
        $response = $this->get(route('clients.show', $a))->assertOk();
        $counts = $response->viewData('tabCounts');
        foreach (['contacts', 'addresses', 'projects', 'invoices', 'payments'] as $key) {
            $this->assertSame(2, $counts[$key], $key);
            $response->assertSee('data-client-tab-count="'.$key.'">2</span>', false);
        }
        $response->assertSee('data-client-tab-count="documents">0</span>', false);
        $this->get(route('clients.show', ['client' => $a, 'tab' => 'projects']))->assertOk()
            ->assertSee('Client projects')->assertDontSee($other->project_number.' · '.$other->name)
            ->assertViewHas('projectEntries', fn ($entries) => $entries->total() === 2);
        $this->get(route('clients.show', ['client' => $a, 'tab' => 'payments']))->assertOk()
            ->assertViewHas('paymentEntries', fn ($entries) => $entries->total() === 2);
    }

    public function test_badges_use_total_records_not_page_size_and_are_stable_on_other_tabs(): void
    {
        $client = $this->client();
        for ($i = 0; $i < 27; $i++) { $this->cash($client, 10); $this->invoice($client); }
        foreach (['overview', 'invoices', 'payments'] as $tab) {
            $response = $this->get(route('clients.show', ['client' => $client, 'tab' => $tab]))->assertOk();
            $this->assertSame(27, $response->viewData('tabCounts')['invoices']);
            $this->assertSame(27, $response->viewData('tabCounts')['payments']);
        }
        $this->assertCount(25, $response->viewData('paymentEntries'));
    }

    public function test_optional_module_badges_count_all_client_rows_without_loading_full_collections(): void
    {
        foreach (['2026_07_20_020000_create_client_portal_users_table',
            '2026_07_20_020200_create_client_portal_notifications_table',
            '2026_07_20_020300_create_client_portal_documents_table'] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
        $a = $this->client(); $b = $this->client();
        foreach ([$a, $a, $b] as $i => $client) {
            \App\Models\ClientPortalUser::create(['client_id' => $client->id, 'username' => 'portal-'.$i, 'password' => 'password']);
            \App\Models\ClientPortalNotification::create(['client_id' => $client->id, 'type' => 'general', 'title' => 'Update', 'message' => 'Hello']);
            \App\Models\ClientPortalDocument::create(['client_id' => $client->id, 'file_path' => 'document.pdf']);
        }
        $response = $this->get(route('clients.show', $a))->assertOk();
        foreach (['portal', 'documents', 'notifications'] as $key) $this->assertSame(2, $response->viewData('tabCounts')[$key]);
        $this->assertCount(0, $response->viewData('portalUsers'));
        $this->assertCount(0, $response->viewData('documents'));
        $this->assertCount(0, $response->viewData('notifications'));
    }

    public function test_sales_only_reader_sees_project_count_but_not_protected_project_details(): void
    {
        $client = $this->client(); $project = $this->project($client);
        auth()->user()->update(['access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $this->get(route('clients.show', ['client' => $client, 'tab' => 'projects']))->assertOk()
            ->assertSee('data-client-tab-count="projects">1</span>', false)
            ->assertSee('Project details require project-view access.')
            ->assertDontSee($project->project_number.' · '.$project->name);
    }

    public function test_compact_advance_rail_keeps_every_entry_and_allocation_link_accessible(): void
    {
        $client = $this->client();
        $entries = collect();
        for ($i = 1; $i <= 8; $i++) $entries->push($this->cash($client, 100 * $i, ['particular' => 'Advance '.$i]));
        $response = $this->get(route('clients.show', $client))->assertOk();
        foreach ($entries as $entry) $response->assertSee(route('cashflows.show', ['cashflow' => $entry, 'allocation' => 'add']), false);
        $response->assertSee('Scroll to browse all 8 entries. Select a card to allocate.')
            ->assertSee('role="region" aria-label="Unallocated entries, scroll horizontally to browse"', false)
            ->assertDontSee('more unallocated');
    }
}
