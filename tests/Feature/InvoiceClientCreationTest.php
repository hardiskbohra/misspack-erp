<?php

namespace Tests\Feature;

use App\Http\Controllers\SalesInvoiceController;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientInvoiceSnapshot;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceClientCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        // Module-only fixture, independent of destructive historical migrations.
        foreach ([
            '0001_01_01_000000_create_users_table',
            '2024_01_02_000001_add_extra_fields_to_users_table',
            '2026_10_03_030000_add_employment_profile_to_users_table',
            '2026_10_19_110000_create_permissions_and_audit_log',
            '2026_06_09_020000_create_clients_table',
            '2026_08_26_010000_create_sales_invoices_table',
            '2026_10_06_060000_create_organisation_tables',
        ] as $migration) {
            (require database_path('migrations/'.$migration.'.php'))->up();
        }
    }

    private function office(array $attributes = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Invoice officer', 'email' => 'office@example.test', 'password' => 'password',
            'role' => 'admin',
        ], $attributes));
        $this->actingAs($user);

        return $user;
    }

    private function details(): array
    {
        return [
            'company_name' => 'New Client & Co.', 'brand_name' => 'New Brand',
            'ceo_name' => 'Priya', 'ceo_email' => 'priya@example.test', 'ceo_contact' => '+91 9900000000',
            'gstin' => '24ABCDE1234F1Z6', 'pan' => 'ABCDE1234F',
            'billing_address' => '20 Market Road', 'billing_city' => 'Ahmedabad', 'billing_state' => 'Gujarat',
            'billing_country' => 'India', 'billing_pincode' => '380004',
            'shipping_address' => '8 Warehouse Lane', 'shipping_city' => 'Mumbai', 'shipping_state' => 'Maharashtra',
            'shipping_country' => 'India', 'shipping_pincode' => '400001', 'shipping_same_as_billing' => false,
        ];
    }

    public function test_inline_creation_saves_both_addresses_and_returns_only_the_invoice_projection(): void
    {
        $user = $this->office();
        $data = $this->details();
        $response = $this->postJson(route('clients.quickStore'), $data + [
            'status' => 'approved', 'created_by' => 999, 'client_number' => 'FORGED', 'public_token' => 'FORGED',
        ])->assertCreated()->assertJsonPath('client.company_name', $data['company_name']);
        $client = Client::findOrFail($response->json('client.id'));
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $client->{$key}, $key);
        }
        $this->assertSame(Client::STATUS_DRAFT, $client->status);
        $this->assertSame($user->id, $client->created_by);
        $this->assertSame('customer', $client->client_type);
        $this->assertNotSame('FORGED', $client->public_token);
        $this->assertNotSame('FORGED', $client->client_number);
        $this->assertNull($client->kyc_submitted_at);
        $this->assertSame(ClientInvoiceSnapshot::from($client), $response->json('client.snapshot'));
        $this->assertArrayNotHasKey('public_token', $response->json('client'));
        $this->assertDatabaseCount('sales_invoices', 0);
    }

    public function test_same_as_billing_copies_every_component_on_the_server(): void
    {
        $this->office();
        $response = $this->postJson(route('clients.quickStore'), array_merge($this->details(), [
            'shipping_same_as_billing' => true,
        ]))->assertCreated();
        $client = Client::findOrFail($response->json('client.id'));
        foreach (['address', 'city', 'state', 'country', 'pincode'] as $part) {
            $this->assertSame($client->{'billing_'.$part}, $client->{'shipping_'.$part});
            $this->assertSame($client->{'billing_'.$part}, $response->json('client.snapshot.shipping_'.$part));
        }
        $this->assertTrue($client->shipping_same_as_billing);
    }

    public function test_legacy_quick_create_still_redirects_and_needs_only_a_company_name(): void
    {
        $this->office();
        $response = $this->post(route('clients.quickStore'), ['company_name' => 'Company only']);
        $client = Client::firstOrFail();
        $response->assertRedirect(route('clients.show', $client));
        $this->assertSame('Company only', $client->company_name);
    }

    public function test_json_validation_stays_on_the_dialog_and_does_not_create_a_client(): void
    {
        $this->office();
        $this->postJson(route('clients.quickStore'), [
            'company_name' => '', 'ceo_email' => 'invalid', 'billing_pincode' => str_repeat('1', 31),
            'shipping_same_as_billing' => 'not-a-boolean',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'company_name', 'ceo_email', 'billing_pincode', 'shipping_same_as_billing',
        ]);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_overlong_and_non_text_addresses_are_rejected_before_saving(): void
    {
        $this->office();
        $this->postJson(route('clients.quickStore'), [
            'company_name' => 'Invalid address', 'billing_address' => str_repeat('x', 5001),
            'shipping_city' => ['not', 'text'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['billing_address', 'shipping_city']);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_unauthenticated_inline_creation_returns_json_401(): void
    {
        $this->postJson(route('clients.quickStore'), $this->details())->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_missing_csrf_token_returns_json_419_without_creating_a_client(): void
    {
        $this->office();
        // Enable the real CSRF middleware (Laravel normally bypasses it in tests).
        $this->app->detectEnvironment(fn () => 'local');
        $this->postJson(route('clients.quickStore'), $this->details())->assertStatus(419)
            ->assertHeader('Content-Type', 'application/json');
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_read_only_sales_user_cannot_create_clients_from_the_invoice(): void
    {
        $this->office(['access_profile' => 'custom', 'permissions' => ['sales.view']]);
        $this->postJson(route('clients.quickStore'), $this->details())->assertForbidden();
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_employee_cannot_create_a_client(): void
    {
        $this->office(['role' => 'employee']);
        $this->postJson(route('clients.quickStore'), $this->details())->assertRedirect(route('my.dashboard'));
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_empty_client_list_still_has_a_complete_snapshot_field_contract(): void
    {
        $data = (new \ReflectionMethod(SalesInvoiceController::class, 'sharedData'))->invoke(new SalesInvoiceController);
        $this->assertCount(0, $data['clients']);
        $this->assertSame(array_keys(ClientInvoiceSnapshot::from(new Client)), $data['clientSnapshotFields']);
        $this->assertCount(18, $data['clientSnapshotFields']);
    }

    public function test_projection_keeps_existing_contact_and_shipping_fallback_rules(): void
    {
        $client = new Client($this->details());
        $client->account_person_name = 'Accounts contact';
        $client->account_person_email = 'accounts@example.test';
        $client->account_person_contact = '111';
        $client->shipping_city = null;
        $snapshot = ClientInvoiceSnapshot::from($client);
        $this->assertSame('Accounts contact', $snapshot['client_contact_name']);
        $this->assertSame('accounts@example.test', $snapshot['client_email']);
        $this->assertSame('111', $snapshot['client_mobile']);
        $this->assertSame('Ahmedabad', $snapshot['shipping_city']);
        $this->assertSame('Gujarat', $snapshot['place_of_supply']);
    }

    public function test_invoice_creation_renders_an_independent_client_form_even_before_the_first_client(): void
    {
        $this->office();
        $response = $this->get(route('sales-invoices.create'))->assertOk();
        $response->assertSee('data-create-invoice-client', false)
            ->assertSee('Save client &amp; use on invoice', false)
            ->assertSee('data-client-shipping-fields', false)
            ->assertSee('assets/js/invoice-client-create.js', false);
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'id="invoiceClientModal"'), strpos($html, '</form>', strpos($html, 'data-invoice-portal-confirm')));
    }

    public function test_a_gstin_that_fails_its_check_digit_is_refused(): void
    {
        $this->office();

        // 24ABCDE1234F1Z5 is structurally fine but its check digit should be 6.
        // (array_merge, not +: union keeps the left operand's gstin unchanged.)
        $this->postJson(route('clients.quickStore'), array_merge($this->details(), [
            'gstin' => '24ABCDE1234F1Z5',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['gstin']);

        $this->assertSame(0, \App\Models\Client::count());
    }
}
