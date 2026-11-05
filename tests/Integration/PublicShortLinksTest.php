<?php

namespace Tests\Integration;

use App\Models\AuditLog;
use App\Models\CashflowAccount;
use App\Models\CashflowEntry;
use App\Models\Client;
use App\Models\ClientPortalUser;
use App\Models\FeedbackRequest;
use App\Models\Lead;
use App\Models\PartyStatementShare;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorKycDocument;
use App\Models\VendorShareLink;
use App\Services\AuditTrail;
use App\Services\PartyStatement;
use App\Services\PublicShareLinks;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\IsolatedDatabase;
use Tests\Support\VendorKycFixture;
use Tests\TestCase;

/** Real routes and the complete portable schema, not a stubbed resolver. */
class PublicShortLinksTest extends TestCase
{
    use IsolatedDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->isolatedDatabase();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'short-links@example.test', 'password' => 'password', 'role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function client(): Client
    {
        return Client::create(['client_number' => 'SHORT-CLIENT', 'company_name' => 'Short Link Client', 'preferred_currency' => 'INR']);
    }

    private function vendor(): Vendor
    {
        return Vendor::create(['vendor_number' => 'SHORT-VENDOR', 'vendor_name' => 'Short Link Vendor', 'status' => 'active']);
    }

    private function inputValue(string $html, string $id): string
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $nodes = (new \DOMXPath($document))->query('//*[@id="'.$id.'"]');
        $this->assertSame(1, $nodes->length, "Expected one element with id $id");
        return $nodes->item(0)->getAttribute('value');
    }

    private function assertShortRedirect(string $short, string $canonical, string $secret): void
    {
        $this->assertMatchesRegularExpression('~^https?://[^/]+/s/[a-z][1-9][0-9]{0,19}\.[A-Za-z0-9_-]{22}$~', $short);
        $this->assertLessThan(strlen($canonical), strlen($short));
        $this->assertStringNotContainsString($secret, $short);

        $response = $this->get($short)->assertStatus(302);
        // Only a relative destination, constructed by the server, is permitted.
        $this->assertSame(parse_url($canonical, PHP_URL_PATH), $response->headers->get('Location'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function test_client_kyc_copy_field_contains_a_short_working_link_and_the_old_link_still_works(): void
    {
        $client = $this->client();
        $html = $this->get(route('clients.show', ['client' => $client, 'tab' => 'kyc']))->assertOk()->getContent();
        $share = $this->inputValue($html, 'clientKycLink');
        $this->get(route('clients.index'))->assertOk()->assertSee($share, false);
        $legacy = route('clients.publicKyc', $client->public_token);
        $this->assertShortRedirect($share, $legacy, $client->public_token);

        auth()->logout();
        $page = $this->get($legacy)->assertOk()->assertSee('Short Link Client');
        // Form actions must keep the canonical token, not post to the redirect.
        $page->assertSee(route('clients.publicKyc.submit', $client->public_token), false);
        $this->post($legacy, [
            'intent' => 'draft', 'company_name' => 'Updated through the old form',
            'billing_address' => '123 Road', 'billing_city' => 'Ahmedabad',
            'billing_state' => 'Gujarat', 'billing_country' => 'India', 'billing_pincode' => '380001',
        ])->assertRedirect($legacy)->assertSessionHas('success');
        $this->assertSame('Updated through the old form', $client->fresh()->company_name);
    }

    public function test_vendor_kyc_link_can_be_copied_opened_submitted_rotated_and_revoked(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();
        $this->post(route('vendors.kyc.link', $vendor), ['action' => 'issue'])->assertRedirect();
        $vendor->refresh();
        $legacy = route('vendors.publicKyc', $vendor->kyc_public_token);
        $html = $this->get(route('vendors.show', ['vendor' => $vendor, 'tab' => 'kyc']))->assertOk()->getContent();
        $short = $this->inputValue($html, 'vendorKycLink');
        $this->assertShortRedirect($short, $legacy, $vendor->kyc_public_token);

        auth()->logout();
        $publicPage = $this->get($legacy)->assertOk()->assertSee(route('vendors.publicKyc.submit', $vendor->kyc_public_token), false);
        $this->assertStringContainsString('no-store', $publicPage->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $publicPage->headers->get('Referrer-Policy'));
        $payload = VendorKycFixture::payload(['intent' => 'draft', 'kyc_version' => $vendor->kyc_version, 'vendor_name' => 'Short Link Updated']);
        $payload['documents'] = [UploadedFile::fake()->create('profile.pdf', 1, 'application/pdf')];
        $this->post($legacy, $payload)->assertRedirect();
        $this->assertSame('Short Link Updated', $vendor->fresh()->kyc_data['vendor_name']);
        $doc = VendorKycDocument::where('vendor_id', $vendor->id)->firstOrFail();
        $this->get(route('vendors.publicKyc.documents', [$vendor->kyc_public_token, $doc->id]))->assertOk();
        $other = Vendor::create(['vendor_number' => 'SHORT-OTHER', 'vendor_name' => 'Other Vendor']);
        $other->forceFill(['kyc_public_token' => Str::random(48)])->save();
        $denied = $this->get(route('vendors.publicKyc.documents', [$other->kyc_public_token, $doc->id]))->assertNotFound();
        $this->assertStringContainsString('no-store', $denied->headers->get('Cache-Control'));

        $this->actingAs($this->admin)->post(route('vendors.kyc.link', $vendor), ['action' => 'rotate'])->assertRedirect();
        $vendor->refresh();
        $rotated = PublicShareLinks::url($vendor);
        $this->assertNotSame($short, $rotated);
        $this->assertShortRedirect($rotated, route('vendors.publicKyc', $vendor->kyc_public_token), $vendor->kyc_public_token);
        auth()->logout();
        $this->get($short)->assertNotFound();
        $this->get($legacy)->assertNotFound();
        $this->actingAs($this->admin)->post(route('vendors.kyc.link', $vendor), ['action' => 'revoke'])->assertRedirect();
        auth()->logout();
        $this->get($rotated)->assertNotFound();
    }

    public function test_shipment_share_fields_sticker_qr_and_tracking_link_use_the_same_short_url(): void
    {
        $shipment = Shipment::create(['shipment_number' => 'SHORT-SHIPMENT', 'identity_name' => 'Short Link Shipment', 'shipment_type' => 'domestic', 'status' => 'planning', 'public_token' => Str::random(48)]);
        $legacy = route('shipments.publicTrack', $shipment->public_token);
        $short = PublicShareLinks::url($shipment);
        foreach ([route('shipments.index'), route('shipments.show', $shipment), route('shipments.stickers')] as $page) {
            $this->get($page)->assertOk()->assertSee($short, false);
        }
        $this->assertShortRedirect($short, $legacy, $shipment->public_token);
        auth()->logout();
        $this->get($legacy)->assertOk()->assertSee('SHORT-SHIPMENT')->assertSee($short, false);
        $shipment->forceFill(['public_token' => Str::random(48)])->save();
        $this->get($short)->assertNotFound();
        $this->get($legacy)->assertNotFound();
    }

    public function test_other_bearer_link_types_resolve_only_to_their_existing_public_routes(): void
    {
        $client = $this->client();
        $vendor = $this->vendor();
        $product = Product::create(['product_number' => 'SHORT-PRODUCT', 'name' => 'Short Product']);
        $sales = SalesInvoice::create(['invoice_number' => 'SHORT-SALE', 'invoice_type' => 'proforma', 'status' => 'sent', 'show_client_portal' => true]);
        $purchase = PurchaseInvoice::create(['invoice_number' => 'SHORT-PURCHASE', 'invoice_type' => 'order', 'public_token' => Str::random(48)]);
        $lead = Lead::create(['lead_number' => 'SHORT-LEAD', 'title' => 'Short Lead']);
        $statement = PartyStatementShare::issue(['party_type' => 'client', 'party_id' => $client->id, 'party_name' => $client->company_name]);
        $vendorShare = VendorShareLink::create(['vendor_id' => $vendor->id, 'label' => 'Short Share', 'resource_type' => 'workspace', 'permissions' => ['workspace.view']]);
        $feedback = FeedbackRequest::issue(['client_id' => $client->id]);

        $cases = [
            [$product, 'products.public', $product->public_token],
            [$sales, 'sales-invoices.public', $sales->public_token],
            [$purchase, 'purchase-invoices.public', $purchase->public_token],
            [$lead, 'leads.public.show', $lead->public_token],
            [$statement, 'statements.public', $statement->token],
            [$vendorShare, 'vendor-shares.show', $vendorShare->token],
            [$feedback, 'feedback.public.show', $feedback->token],
        ];
        foreach ($cases as [$model, $route, $token]) {
            $this->assertShortRedirect(PublicShareLinks::url($model), route($route, $token), $token);
        }
        $this->assertSame(PublicShareLinks::url($statement), $statement->url());
        $this->assertSame(PublicShareLinks::url($vendorShare), $vendorShare->publicUrl());
        $this->assertSame(PublicShareLinks::url($feedback), $feedback->url());
        foreach ([$statement, $feedback] as $share) {
            $this->assertStringContainsString($share->url(), $share->message());
            $this->assertStringContainsString($share->url(), rawurldecode($share->whatsappUrl('9876543210')));
            $this->assertStringContainsString($share->url(), rawurldecode($share->mailUrl('recipient@example.test')));
        }
        $this->assertStringContainsString(PublicShareLinks::url($sales), $sales->reminderMessage());
        foreach ([
            [route('products.show', $product), $product],
            [route('products.index'), $product],
            [route('sales-invoices.show', $sales), $sales],
            [route('sales-invoices.index'), $sales],
            [route('purchase-invoices.show', $purchase), $purchase],
            [route('leads.show', $lead), $lead],
            [route('feedback.show', $feedback), $feedback],
            [route('cashflows.statements.show', ['partyType' => 'client', 'party' => $client->id, 'share' => $statement->token]), $statement],
            [route('vendors.show', ['vendor' => $vendor, 'tab' => 'portal']), $vendorShare],
        ] as [$page, $record]) {
            $this->get($page)->assertOk()->assertSee(PublicShareLinks::url($record), false);
        }
        auth()->logout();
        $this->get(route('products.public', $product->public_token))->assertOk()->assertSee('Short Product');
        $this->get(route('purchase-invoices.public', $purchase->public_token))->assertOk()->assertSee('SHORT-PURCHASE');
        $this->get(route('leads.public.show', $lead->public_token))->assertOk()->assertSee('Short Lead');
        $this->get(route('sales-invoices.public', $sales->public_token))->assertOk()->assertSee('SHORT-SALE');
        $sales->forceFill(['show_client_portal' => false])->save();
        // The short alias must not bypass the sales invoice's publication check.
        $this->get(PublicShareLinks::url($sales))->assertStatus(302);
        $this->get(route('sales-invoices.public', $sales->public_token))->assertNotFound();
    }

    public function test_authority_like_mount_prefixes_cannot_make_an_external_redirect(): void
    {
        $client = $this->client();
        $alias = basename(parse_url(PublicShareLinks::url($client), PHP_URL_PATH));
        $generator = app('url');
        try {
            $generator->forceRootUrl('https://erp.example//attacker.example');
            $this->assertSame('/attacker.example/client-kyc/'.$client->public_token, PublicShareLinks::destination($alias));
            $generator->forceRootUrl('https://erp.example/\\attacker.example');
            $this->assertSame('/%5Cattacker.example/client-kyc/'.$client->public_token, PublicShareLinks::destination($alias));
        } finally {
            $generator->forceRootUrl(null);
        }
    }

    public function test_aliases_cannot_be_minted_for_unsaved_unsupported_or_tokenless_records(): void
    {
        $unsaved = new Client;
        $unsaved->forceFill(['id' => 123, 'public_token' => Str::random(48)]);
        foreach ([$unsaved, $this->admin, $this->vendor()] as $record) {
            try {
                PublicShareLinks::url($record);
                $this->fail('Only saved, allowlisted records with active tokens can have a link.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_invalid_tampered_rotated_and_deleted_links_fail_without_redirecting_or_disclosing_secrets(): void
    {
        $client = $this->client();
        $short = PublicShareLinks::url($client);
        $path = parse_url($short, PHP_URL_PATH);
        [$prefix, $signature] = explode('.', $path);
        $changed = ($signature[0] === 'A' ? 'B' : 'A').substr($signature, 1);
        $otherClient = Client::create(['client_number' => 'SHORT-OTHER', 'company_name' => 'Other Client']);
        $vendor = $this->vendor();
        $vendor->forceFill(['kyc_public_token' => Str::random(48)])->save();
        // For 16 bytes, the last base64url character has four padding bits.
        // A noncanonical encoding can decode to identical bytes but must not
        // be accepted as a second spelling of this bearer link.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $noncanonical = substr($signature, 0, -1).$alphabet[strpos($alphabet, substr($signature, -1)) + 1];
        $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/').'==', true);
        $this->assertSame($decode($signature), $decode($noncanonical));
        foreach ([$prefix.'.'.$changed, $prefix.'.'.$noncanonical, '/s/v'.$vendor->id.'.'.$signature,
            '/s/c'.$otherClient->id.'.'.$signature, '/s/z1.'.$signature, '/s/c01.'.$signature, '/s/c0.'.$signature,
            '/s/c'.str_repeat('9', 21).'.'.$signature, $path.'=', '/s/c1.!', '/s/c1'] as $invalid) {
            $response = $this->get($invalid)->assertNotFound();
            $this->assertNull($response->headers->get('Location'));
            $this->assertStringNotContainsString($client->public_token, $response->getContent());
        }
        $this->get($short.'?url=https://evil.example')->assertStatus(302)
            ->assertHeader('Location', parse_url(route('clients.publicKyc', $client->public_token), PHP_URL_PATH));
        $this->post($short)->assertStatus(405);
        $old = $client->public_token;
        $client->forceFill(['public_token' => Str::random(48)])->save();
        $this->get($short)->assertNotFound();
        $this->get(route('clients.publicKyc', $old))->assertNotFound();
        $this->assertShortRedirect(PublicShareLinks::url($client), route('clients.publicKyc', $client->public_token), $client->public_token);
        $new = PublicShareLinks::url($client);
        $client->delete();
        $this->get($new)->assertNotFound();
    }

    public function test_old_signing_key_works_only_while_retained_and_never_invalidates_the_legacy_url(): void
    {
        $client = $this->client();
        $oldKey = config('app.key');
        $old = PublicShareLinks::url($client);
        config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32)), 'app.previous_keys' => [$oldKey]]);
        $new = PublicShareLinks::url($client);
        $this->assertNotSame($old, $new);
        $legacy = route('clients.publicKyc', $client->public_token);
        $this->assertShortRedirect($old, $legacy, $client->public_token);
        $this->assertShortRedirect($new, $legacy, $client->public_token);
        config(['app.previous_keys' => []]);
        $this->get($old)->assertNotFound();
        $this->get($new)->assertStatus(302);
        $this->get($legacy)->assertOk();
    }

    public function test_canonical_handlers_still_enforce_share_expiry_revocation_permissions_and_view_counts(): void
    {
        $client = $this->client();
        $vendor = $this->vendor();
        $statement = PartyStatementShare::issue(['party_type' => 'client', 'party_id' => $client->id, 'party_name' => $client->company_name]);
        $feedback = FeedbackRequest::issue(['client_id' => $client->id]);
        $share = VendorShareLink::create(['vendor_id' => $vendor->id, 'label' => 'View only', 'resource_type' => 'workspace', 'permissions' => ['workspace.view']]);
        // No published project receipts: their collection is a plain empty
        // Collection, which must work for currency discovery as well as build().
        $this->assertContains('INR', app(PartyStatement::class)->currencies('client', $client->id, true));
        auth()->logout();
        foreach ([[$statement, 'statements.public'], [$feedback, 'feedback.public.show'], [$share, 'vendor-shares.show']] as [$model, $route]) {
            $this->get(PublicShareLinks::url($model))->assertStatus(302);
            $this->assertSame(0, $model->fresh()->views, 'Redirect must not count as a public view');
            $this->get(route($route, $model->token))->assertOk();
            $this->assertSame(1, $model->fresh()->views, 'The destination counts the view exactly once');
        }
        $this->post(route('vendor-shares.updates.store', $share->token), [])->assertForbidden();
        foreach ([$statement, $feedback, $share] as $model) {
            $model->forceFill(['revoked_at' => now()])->save();
            $this->get(PublicShareLinks::url($model))->assertStatus(302);
            $destination = match (true) {
                $model instanceof PartyStatementShare => route('statements.public', $model->token),
                $model instanceof FeedbackRequest => route('feedback.public.show', $model->token),
                default => route('vendor-shares.show', $model->token),
            };
            $response = $this->get($destination);
            if ($model instanceof VendorShareLink) {
                $response->assertOk()->assertSee('This link is no longer available');
            } else {
                $response->assertStatus(410);
            }
            $this->assertSame(1, $model->fresh()->views);
            $model->forceFill(['revoked_at' => null, 'expires_at' => now()->subMinute()])->save();
            $this->get(PublicShareLinks::url($model))->assertStatus(302);
            $response = $this->get($destination);
            if ($model instanceof VendorShareLink) {
                $response->assertOk()->assertSee('This link is no longer available');
            } else {
                $response->assertStatus(410);
            }
            $this->assertSame(1, $model->fresh()->views);
        }
    }

    public function test_client_statement_with_an_overlapping_project_receipt_counts_it_only_once(): void
    {
        $client = $this->client();
        $project = Project::forceCreate(['project_number' => 'SHORT-PROJECT', 'name' => 'Shared Project',
            'client_id' => $client->id, 'show_client_portal' => true]);
        $invoice = SalesInvoice::create(['invoice_number' => 'SHORT-STATEMENT-INVOICE', 'invoice_type' => 'tax',
            'status' => 'sent', 'client_id' => $client->id, 'invoice_date' => '2026-10-01',
            'currency' => 'INR', 'total_amount' => 100, 'show_client_portal' => true]);
        $account = CashflowAccount::create(['account_name' => 'Short Test Bank', 'currency' => 'INR']);
        CashflowEntry::create(['entry_date' => '2026-10-02', 'particular' => 'Project and invoice receipt',
            'transaction_type' => 'credit', 'credit_amount' => 40, 'currency' => 'INR', 'account_id' => $account->id,
            'accounting_status' => 'booked', 'client_id' => $client->id, 'project_id' => $project->id, 'sales_invoice_id' => $invoice->id]);
        $share = PartyStatementShare::issue(['party_type' => 'client', 'party_id' => $client->id, 'party_name' => $client->company_name]);
        auth()->logout();
        $this->get(PublicShareLinks::url($share))->assertStatus(302);
        $response = $this->get(route('statements.public', $share->token))->assertOk();
        $totals = $response->viewData('statement')['totals'];
        $this->assertSame(100.0, $totals['debit']);
        $this->assertSame(40.0, $totals['credit']);
        $this->assertSame(60.0, $totals['closing']);
        $this->assertSame(2, $totals['count']);
    }

    public function test_redirect_keeps_the_application_mount_prefix_without_becoming_an_external_url(): void
    {
        $client = $this->client();
        $generator = app('url');
        $previous = request();
        $mounted = Request::create('https://erp.example.test/office/s/test', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/office/index.php', 'SCRIPT_FILENAME' => '/srv/site/index.php', 'PHP_SELF' => '/office/index.php',
        ]);
        $this->assertSame('/office', $mounted->getBaseUrl());
        try {
            $generator->setRequest($mounted);
            $short = PublicShareLinks::url($client);
            $this->assertStringStartsWith('https://erp.example.test/office/s/', $short);
            $alias = basename(parse_url($short, PHP_URL_PATH));
            $this->assertSame('/office/client-kyc/'.$client->public_token, PublicShareLinks::destination($alias));
        } finally {
            $generator->setRequest($previous);
        }
    }

    public function test_short_link_throttle_is_isolated_from_the_existing_vendor_kyc_limit(): void
    {
        $vendor = $this->vendor();
        $vendor->forceFill(['kyc_public_token' => Str::random(48)])->save();
        $short = PublicShareLinks::url($vendor);
        $legacy = route('vendors.publicKyc', $vendor->kyc_public_token);
        auth()->logout();
        $this->get($short)->assertStatus(302)->assertHeader('X-RateLimit-Limit', '120');
        for ($i = 1; $i < 120; $i++) {
            $this->get('/s/c999.'.str_repeat('A', 22))->assertNotFound();
        }
        $this->get($short)->assertStatus(429);
        // Following an alias must not consume or exhaust the canonical bucket.
        $this->get($legacy)->assertOk()->assertHeader('X-RateLimit-Remaining', '119');
    }

    public function test_audit_redacts_the_alias_as_a_bearer_token(): void
    {
        $short = PublicShareLinks::url($this->client());
        $request = Request::create($short, 'POST');
        $route = Route::getRoutes()->getByName('public-links.short')->bind($request);
        $request->setRouteResolver(fn () => $route);
        app(AuditTrail::class)->mutation($request, response('', 405));
        $log = AuditLog::latest('id')->firstOrFail();
        $this->assertStringContainsString('[redacted-token]', $log->url);
        $this->assertStringNotContainsString(basename(parse_url($short, PHP_URL_PATH)), $log->url);
        $this->assertArrayNotHasKey('token', $log->context['route_parameters']);
    }

    public function test_portal_credential_sharing_and_kyc_workspace_use_short_addresses_but_still_require_login(): void
    {
        $client = $this->client();
        $client->forceFill(['portal_enabled' => true])->save();
        ClientPortalUser::create(['client_id' => $client->id, 'name' => 'Portal User', 'username' => 'short-client',
            'password' => Hash::make('test-password-only'), 'portal_enabled' => true, 'is_active' => true, 'must_change_password' => false]);
        $this->get(route('clients.show', ['client' => $client, 'tab' => 'portal']))->assertOk()
            ->assertSee(route('public-links.client-login'), false);
        $this->get(route('leads.index'))->assertOk()->assertSee(route('public-links.enquiry'), false);
        $vendor = $this->vendor();
        $this->get(route('vendors.show', ['vendor' => $vendor, 'tab' => 'portal']))->assertOk()
            ->assertSee(route('public-links.vendor-login'), false);
        $this->post(route('vendors.portal.users.store', $vendor), ['name' => 'Vendor Contact', 'username' => 'short-vendor'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $credentials = session('vendor_portal_credentials');
        $this->assertSame(route('public-links.vendor-login'), $credentials['url']);
        $this->post(route('vendors.shares.store', $vendor), ['label' => 'Short workspace', 'resource_key' => 'workspace', 'expiry_days' => 14])
            ->assertRedirect()->assertSessionHasNoErrors();
        $share = VendorShareLink::where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame(PublicShareLinks::url($share), session('vendor_share_url'));

        auth()->logout();
        $this->get(route('client-portal.kyc.show'))->assertRedirect(route('client-portal.login'));
        $this->get('/s/c')->assertOk();
        $this->post(route('client-portal.login.submit'), ['login' => 'short-client', 'password' => 'test-password-only'])
            ->assertRedirect(route('client-portal.dashboard'));
        $this->get(route('client-portal.kyc.show'))->assertOk()->assertSee(PublicShareLinks::url($client), false);
        $this->get('/s/v')->assertOk();
        $this->post(route('vendor-portal.login.submit'), ['login' => $credentials['username'], 'password' => $credentials['password']])
            ->assertRedirect(route('vendor-portal.password.edit'));
    }

    public function test_static_public_aliases_serve_existing_forms_without_weakening_portal_authentication(): void
    {
        auth()->logout();
        $this->get('/s/e')->assertOk()->assertSee(route('leads.public.store'), false);
        $this->get('/s/c')->assertOk()->assertSee(route('client-portal.login.submit'), false);
        $this->get('/s/v')->assertOk()->assertSee(route('vendor-portal.login.submit'), false);
        $this->get(route('leads.public.create'))->assertOk();
        $this->get(route('client-portal.login'))->assertOk();
        $this->get(route('vendor-portal.login'))->assertOk();
        $this->get('/client-portal')->assertRedirect();
        $this->get('/vendor-portal')->assertRedirect();
    }
}
