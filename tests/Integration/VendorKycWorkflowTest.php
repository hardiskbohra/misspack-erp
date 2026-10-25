<?php

namespace Tests\Integration;

use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorKyc;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\VendorKycFixture as F;
use Tests\TestCase;

class VendorKycWorkflowTest extends TestCase
{
    private array $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = F::install();
    }

    private function office(string $suffix = ''): string
    {
        return '/vendors/'.$this->f['vendor']->id.'/kyc'.$suffix;
    }

    private function link(): string
    {
        $this->actingAs($this->f['maker'])->post($this->office('/link'), ['action' => 'issue'])->assertRedirect();

        return '/vendor-kyc/'.$this->f['vendor']->fresh()->kyc_public_token;
    }

    private function data(array $changes = []): array
    {
        return F::payload(array_replace(['kyc_version' => $this->f['vendor']->fresh()->kyc_version, 'intent' => 'submit'], $changes));
    }

    private function submit(string $url): void
    {
        $this->post($url, $this->data())->assertRedirect();
    }

    public function test_link_and_public_form_are_scoped_private_and_use_the_client_kyc_design(): void
    {
        $url = $this->link();
        $this->app['auth']->forgetGuards();
        $page = $this->get($url)->assertOk()->assertSee('Vendor KYC')->assertSee('kyc-public.css')->assertSee('kyc-public.js');
        $page->assertDontSee('PRIVATE OFFICE NOTE')->assertDontSee('OLD-BANK'); // bank is blanked on the public initial form
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        $this->get('/vendor-kyc/not-a-token')->assertNotFound();
        $this->assertSame(48, strlen($this->f['vendor']->fresh()->kyc_public_token));
    }

    public function test_draft_and_final_stage_details_without_mutating_live_vendor_or_internal_controls(): void
    {
        $url = $this->link();
        $this->post($url, $this->data(['intent' => 'draft', 'notes' => 'HACK', 'status' => 'active', 'kyc_status' => 'approved', 'rating' => 1, 'preferred_currency' => 'USD']))->assertRedirect();
        $v = $this->f['vendor']->fresh();
        $this->assertSame('Original supplier', $v->vendor_name);
        $this->assertSame('OLD-BANK', $v->account_number);
        $this->assertSame('draft', $v->kyc_status);
        $this->assertNull($v->kyc_submitted_at);
        $this->assertSame('PRIVATE OFFICE NOTE', $v->notes);
        $this->assertSame('on_hold', $v->status);
        $this->assertSame('RMB', $v->preferred_currency);
        $this->submit($url);
        $v->refresh();
        $this->assertSame('under_review', $v->kyc_status);
        $this->assertNotNull($v->kyc_submitted_at);
        $this->assertSame('NEW-BANK', $v->kyc_submitted_data['account_number']);
        $this->postJson($url, $this->data())->assertUnprocessable();
    }

    public function test_manager_applies_submission_atomically_but_reader_and_stale_reviews_cannot(): void
    {
        $url = $this->link();
        $this->submit($url);
        $version = $this->f['vendor']->fresh()->kyc_version;
        $this->actingAs($this->f['reader'])->patchJson($this->office('/review'), ['decision' => 'approved', 'kyc_version' => $version])->assertForbidden();
        $this->actingAs($this->f['checker'])->patchJson($this->office('/review'), ['decision' => 'approved', 'kyc_version' => $version - 1])->assertUnprocessable();
        $this->patch($this->office('/review'), ['decision' => 'approved', 'kyc_version' => $version])->assertRedirect();
        $v = $this->f['vendor']->fresh();
        $this->assertSame('approved', $v->kyc_status);
        $this->assertSame('Submitted packaging partner', $v->vendor_name);
        $this->assertSame('NEW-BANK', $v->account_number);
        $this->assertSame('on_hold', $v->status);
        $this->assertSame('RMB', $v->preferred_currency);
        $this->assertSame($this->f['checker']->id, $v->kyc_reviewed_by);
        $this->patchJson($this->office('/review'), ['decision' => 'rejected', 'reason' => 'duplicate', 'kyc_version' => $v->kyc_version])->assertUnprocessable();
    }

    public function test_revision_requires_reason_reopens_for_resubmission_and_rejection_locks(): void
    {
        $url = $this->link();
        $this->submit($url);
        $this->actingAs($this->f['checker']);
        $this->patchJson($this->office('/review'), ['decision' => 'revision', 'kyc_version' => $this->f['vendor']->fresh()->kyc_version])->assertUnprocessable();
        $this->patch($this->office('/review'), ['decision' => 'revision', 'reason' => 'Confirm bank branch', 'kyc_version' => $this->f['vendor']->fresh()->kyc_version])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Confirm bank branch')->assertSee('Save for later');
        $this->submit($url);
        $this->patch($this->office('/review'), ['decision' => 'rejected', 'reason' => 'Details not verified', 'kyc_version' => $this->f['vendor']->fresh()->kyc_version])->assertRedirect();
        $this->postJson($url, $this->data())->assertUnprocessable();
        $this->get($url)->assertOk()->assertSee('Details not verified');
        $this->assertGreaterThanOrEqual(5, DB::table('vendor_kyc_events')->where('vendor_id', $this->f['vendor']->id)->count());
    }

    public function test_rotated_revoked_and_stale_links_cannot_save_or_download_other_vendor_evidence(): void
    {
        $old = $this->link();
        $stale = $this->data();
        $this->post($this->office('/link'), ['action' => 'rotate'])->assertRedirect();
        $this->get($old)->assertNotFound();
        $this->post($old, $stale)->assertNotFound();
        $new = '/vendor-kyc/'.$this->f['vendor']->fresh()->kyc_public_token;
        $this->postJson($new, $stale)->assertUnprocessable();
        $this->post($this->office('/link'), ['action' => 'revoke'])->assertRedirect();
        $this->get($new)->assertNotFound();
        $this->assertSame('draft', $this->f['vendor']->fresh()->kyc_status);
    }

    public function test_validation_rejects_bad_inputs_but_foreign_service_providers_do_not_need_indian_tax_ids(): void
    {
        $url = $this->link();
        $this->postJson($url, $this->data(['contact_person_email' => 'bad', 'gstin' => 'BAD']))->assertUnprocessable();
        foreach (['consultant', 'forwarder', 'service_provider', 'packaging_supplier'] as $type) {
            $this->post($url, $this->data(['intent' => 'draft', 'vendor_type' => $type, 'country' => 'Singapore', 'gstin' => null, 'pan' => null, 'ifsc_code' => null]))->assertRedirect();
        }
        $this->post($url, $this->data(['vendor_type' => 'forwarder', 'country' => 'Singapore', 'gstin' => null, 'pan' => null, 'ifsc_code' => null]))->assertRedirect();
        $this->assertSame('under_review', $this->f['vendor']->fresh()->kyc_status);
    }

    public function test_office_tab_has_copy_share_review_history_and_documents_without_changing_public_permissions(): void
    {
        $this->link();
        $this->submit('/vendor-kyc/'.$this->f['vendor']->fresh()->kyc_public_token);
        $this->actingAs($this->f['checker'])->get('/vendors/'.$this->f['vendor']->id.'?tab=kyc')->assertOk()->assertSee('Review decision')->assertSee('Review history')->assertSee('Submitted details');
        $this->actingAs($this->f['reader'])->postJson($this->office('/link'), ['action' => 'rotate'])->assertForbidden();
    }

    public function test_evidence_is_private_validated_and_downloads_are_vendor_scoped(): void
    {
        $url = $this->link();
        $this->post($url, $this->data(['intent' => 'draft', 'documents' => [UploadedFile::fake()->create('bank.pdf', 12, 'application/pdf')]]))->assertRedirect();
        $doc = DB::table('vendor_kyc_documents')->first();
        $this->assertNotNull($doc);
        Storage::disk('local')->assertExists($doc->file_path);
        $other = Vendor::create(['vendor_number' => 'OTHER', 'vendor_name' => 'Other vendor']);
        $this->post('/vendors/'.$other->id.'/kyc/link', ['action' => 'issue'])->assertRedirect();
        $this->get('/vendor-kyc/'.$other->fresh()->kyc_public_token.'/documents/'.$doc->id)->assertNotFound();
        $this->get($url.'/documents/'.$doc->id)->assertOk();
        $this->postJson($url, $this->data(['intent' => 'draft', 'documents' => [UploadedFile::fake()->create('script.html', 12, 'text/html')]]))->assertUnprocessable();
    }

    public function test_completed_kyc_can_be_reopened_with_a_reason_for_a_new_review_cycle(): void
    {
        $url = $this->link();
        $this->submit($url);
        $this->actingAs($this->f['checker']);
        $version = $this->f['vendor']->fresh()->kyc_version;
        $this->patch($this->office('/review'), ['decision' => 'approved', 'kyc_version' => $version])->assertRedirect();
        $this->patchJson($this->office('/reopen'), ['kyc_version' => $this->f['vendor']->fresh()->kyc_version])->assertUnprocessable();
        $this->patch($this->office('/reopen'), ['reason' => 'Annual bank reconfirmation', 'kyc_version' => $this->f['vendor']->fresh()->kyc_version])->assertRedirect();
        $this->get($url)->assertOk()->assertSee('Annual bank reconfirmation')->assertSee('Save for later');
        $this->assertSame('NEW-BANK', $this->f['vendor']->fresh()->account_number);
        $this->submit($url);
        $this->assertSame('under_review', $this->f['vendor']->fresh()->kyc_status);
    }

    public function test_office_profile_edits_require_fresh_version_and_invalidate_approved_identity(): void
    {
        $url = $this->link();
        $this->submit($url);
        $this->actingAs($this->f['checker']);
        $version = $this->f['vendor']->fresh()->kyc_version;
        $this->patch($this->office('/review'), ['decision' => 'approved', 'kyc_version' => $version])->assertRedirect();
        $v = $this->f['vendor']->fresh();
        $service = app(VendorKyc::class);
        try {
            $service->updateOfficeProfile($v, ['bank_name' => 'Changed bank'], $this->f['maker'], $version);
            $this->fail('Stale edit accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('kyc_version', $e->errors());
        }
        $service->updateOfficeProfile($v, ['bank_name' => 'Changed bank'], $this->f['maker'], $v->kyc_version);
        $v->refresh();
        $this->assertSame('draft', $v->kyc_status);
        $this->assertNull($v->kyc_reviewed_at);
        $this->assertNull($v->kyc_submitted_data);
        $this->assertSame($this->f['maker']->id, $v->kyc_maker_id);
        $this->assertSame('Changed bank', $v->bank_name);
        $this->assertSame('on_hold', $v->status);
    }

    public function test_notifications_audit_and_documents_do_not_leak_bearer_tokens_or_unapproved_values(): void
    {
        $url = $this->link();
        $token = $this->f['vendor']->fresh()->kyc_public_token;
        $this->submit($url);
        $alert = DB::table('office_alerts')->where('event_key', 'vendor.kyc.submitted')->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('tab=kyc', $alert->action_url);
        $this->assertStringNotContainsString($token, json_encode(DB::table('audit_logs')->get()));
        $this->assertStringNotContainsString('NEW-BANK', json_encode(DB::table('audit_logs')->get()));
        $this->assertSame(1, DB::table('vendor_kyc_events')->where('action', 'submitted')->count());
    }

    public function test_validation_and_document_errors_have_private_response_headers(): void
    {
        $url = $this->link();
        $response = $this->postJson($url, $this->data(['contact_person_email' => 'invalid']))->assertUnprocessable();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->get($url.'/documents/999')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_kyc_schema_migrates_existing_vendors_and_rolls_back_cleanly(): void
    {
        $migration = require database_path('migrations/'.F::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('vendors', 'kyc_status'));
        $this->assertSame('on_hold', $this->f['vendor']->fresh()->status);
        $migration->up();
        $v = $this->f['vendor']->fresh();
        $this->assertSame('draft', $v->kyc_status);
        $this->assertSame(0, $v->kyc_version);
        $this->assertNull($v->kyc_public_token);
        $this->assertSame('OLD-BANK', $v->account_number);
    }

    public function test_mark_as_sent_does_not_stale_an_open_vendor_form_or_claim_email_delivery(): void
    {
        $url = $this->link();
        $payload = $this->data();
        $this->post($this->office('/link'), ['action' => 'mark_sent'])->assertRedirect();
        $this->assertNotNull($this->f['vendor']->fresh()->kyc_sent_at);
        $this->assertSame((int) $payload['kyc_version'], $this->f['vendor']->fresh()->kyc_version);
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame('under_review', $this->f['vendor']->fresh()->kyc_status);
    }

    public function test_private_evidence_limits_and_office_download_permissions_are_enforced(): void
    {
        $url = $this->link();
        $file = fn () => UploadedFile::fake()->create('evidence.pdf', 12, 'application/pdf');
        $this->postJson($url, $this->data(['intent' => 'draft', 'documents' => array_map(fn () => $file(), range(1, 6))]))->assertUnprocessable();
        $this->postJson($url, $this->data(['intent' => 'draft', 'documents' => [UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')]]))->assertUnprocessable();
        $this->post($url, $this->data(['intent' => 'draft', 'documents' => [$file()]]))->assertRedirect();
        $doc = DB::table('vendor_kyc_documents')->first();
        $this->actingAs($this->f['reader'])->get($this->office('/documents/'.$doc->id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = User::create(['name' => 'Sales only', 'email' => 'sales@kyc.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['sales.manage']]);
        $this->actingAs($other)->get($this->office('/documents/'.$doc->id))->assertForbidden();
        $this->get($url)->assertOk()->assertDontSee($doc->file_path);
        $this->actingAs($this->f['maker'])->post($this->office('/link'), ['action' => 'revoke'])->assertRedirect();
        $this->get($url.'/documents/'.$doc->id)->assertNotFound();
    }

    public function test_stale_office_form_cannot_delete_the_existing_vendor_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vendors/original.png', 'original image');
        $this->f['vendor']->update(['image_path' => 'vendors/original.png']);
        $this->link();
        $file = UploadedFile::fake()->createWithContent('replacement.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6GkAAAAASUVORK5CYII='));
        $this->put('/vendors/'.$this->f['vendor']->id,['vendor_name' => 'Stale name', 'kyc_version' => 0, 'vendor_image' => $file])->assertSessionHasErrors('kyc_version');
        Storage::disk('public')->assertExists('vendors/original.png');
        $this->assertSame('Original supplier',$this->f['vendor']->fresh()->vendor_name);
    }
    public function test_public_forms_keep_csrf_authentication_and_rate_limiting_boundaries(): void
    {
        $this->post($this->office('/link'), ['action'=>'issue'])->assertRedirect('/login');
        $url=$this->link();
        $this->app['env']='local'; // Exercise real request-forgery protection, not PHPUnit's normal bypass.
        try { $this->post($url,$this->data())->assertStatus(419); }
        finally { $this->app['env']='testing'; }
        for ($i=0;$i<30;$i++) $this->postJson($url,$this->data(['contact_person_email'=>'invalid']))->assertUnprocessable();
        $this->postJson($url,$this->data())->assertStatus(429);
        $this->assertSame('draft',$this->f['vendor']->fresh()->kyc_status);
    }

    public function test_malformed_phone_arrays_are_validation_errors_not_server_errors(): void
    {
        $url=$this->link();
        $this->postJson($url,$this->data(['contact_person_mobile'=>['not','a','phone']]))->assertUnprocessable();
        $this->assertSame('draft',$this->f['vendor']->fresh()->kyc_status);
    }

    public function test_vendor_creator_can_review_their_own_submission_and_is_recorded_as_reviewer(): void
    {
        $url = $this->link();
        $this->submit($url);
        $v = $this->f['vendor']->fresh();
        $this->actingAs($this->f['maker'])->patch($this->office('/review'), [
            'decision' => 'approved', 'kyc_version' => $v->kyc_version,
        ])->assertRedirect();
        $v->refresh();
        $this->assertSame('approved', $v->kyc_status);
        $this->assertSame($this->f['maker']->id, $v->kyc_reviewed_by);
        $this->assertSame('NEW-BANK', $v->account_number);
        $this->assertSame($this->f['maker']->id, DB::table('vendor_kyc_events')->where('vendor_id', $v->id)->where('action', 'approved')->value('actor_id'));
    }

}
