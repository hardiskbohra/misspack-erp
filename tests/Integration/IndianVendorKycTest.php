<?php

namespace Tests\Integration;

use App\Models\Vendor;
use App\Models\VendorKycDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\VendorKycFixture as F;
use Tests\TestCase;

class IndianVendorKycTest extends TestCase
{
    private array $f;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = F::install();
        $this->actingAs($this->f['maker'])->post('/vendors/'.$this->f['vendor']->id.'/kyc/link', ['action' => 'issue'])->assertRedirect();
        $this->url = '/vendor-kyc/'.$this->f['vendor']->fresh()->kyc_public_token;
    }

    private function payload(array $changes = []): array
    {
        return F::payload(array_replace(['country' => 'India', 'intent' => 'submit', 'kyc_version' => $this->f['vendor']->fresh()->kyc_version,
            'gstin' => '24ABCDE1234F1Z6', 'account_number' => '123456789', 'ifsc_code' => 'HDFC0001234',
            'bank_address' => "Bank Road\nAhmedabad", 'beneficiary_address' => "Beneficiary Road\nAhmedabad"], $changes));
    }

    private function evidence(): array
    {
        return ['gst_certificate' => UploadedFile::fake()->create('gst.pdf', 12, 'application/pdf'),
            'pan_document' => UploadedFile::fake()->create('pan.pdf', 12, 'application/pdf')];
    }

    public function test_indian_final_submission_requires_numbers_and_both_classified_documents(): void
    {
        $this->postJson($this->url, $this->payload(['gstin' => null, 'account_number' => null, 'ifsc_code' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['gstin', 'account_number', 'ifsc_code', 'gst_certificate', 'pan_document']);
        $this->assertSame('draft', $this->f['vendor']->fresh()->kyc_status);
        $this->assertSame(0, DB::table('vendor_kyc_documents')->count());
        $this->assertNull($this->f['vendor']->fresh()->kyc_submitted_at);
    }

    public function test_indian_draft_can_be_incomplete_and_saved_evidence_is_reused_on_submission_and_revision(): void
    {
        $this->post($this->url, $this->payload(['intent' => 'draft', 'gstin' => null, 'account_number' => null, 'ifsc_code' => null]))->assertRedirect();
        $this->post($this->url, array_replace($this->payload(['intent' => 'draft']), $this->evidence()))->assertRedirect();
        $this->assertSame(['gst_certificate', 'pan_document'], DB::table('vendor_kyc_documents')->orderBy('id')->pluck('category')->all());
        $this->post($this->url, $this->payload())->assertRedirect();
        $v = $this->f['vendor']->fresh();
        $this->assertSame('under_review', $v->kyc_status);
        $this->patch('/vendors/'.$v->id.'/kyc/review', ['decision' => 'revision', 'reason' => 'Confirm addresses', 'kyc_version' => $v->kyc_version])->assertRedirect();
        $this->post($this->url, $this->payload())->assertRedirect();
        $this->assertSame(2, DB::table('vendor_kyc_documents')->count());
    }

    public function test_typed_uploads_are_private_and_addresses_are_staged_then_applied_on_approval(): void
    {
        $this->post($this->url, array_replace($this->payload(), $this->evidence()))->assertRedirect();
        $v = $this->f['vendor']->fresh();
        $this->assertSame('under_review', $v->kyc_status);
        $this->assertNull($v->bank_address);
        $this->assertNull($v->beneficiary_address);
        $this->assertSame("Bank Road\nAhmedabad", $v->kyc_submitted_data['bank_address']);
        foreach (DB::table('vendor_kyc_documents')->get() as $doc) {
            Storage::disk('local')->assertExists($doc->file_path);
        }
        $this->get('/vendors/'.$v->id.'?tab=kyc')->assertOk()->assertSee('GST Certificate')->assertSee('PAN')->assertSee('Beneficiary Address');
        $this->patch('/vendors/'.$v->id.'/kyc/review', ['decision' => 'approved', 'kyc_version' => $v->kyc_version])->assertRedirect();
        $v->refresh();
        $this->assertSame("Bank Road\nAhmedabad", $v->bank_address);
        $this->assertSame("Beneficiary Road\nAhmedabad", $v->beneficiary_address);
        $this->get('/vendors/'.$v->id.'/edit')->assertOk()->assertSee('name="bank_address"', false)->assertSee('name="beneficiary_address"', false);
    }

    public function test_generic_or_another_vendors_documents_do_not_satisfy_indian_compliance(): void
    {
        $this->post($this->url, $this->payload(['intent' => 'draft', 'documents' => [UploadedFile::fake()->create('GST-and-PAN.pdf', 12, 'application/pdf')]]))->assertRedirect();
        $other = Vendor::create(['vendor_number' => 'OTHER-INDIAN', 'vendor_name' => 'Another Indian vendor']);
        foreach (['gst_certificate', 'pan_document'] as $category) {
            $path = 'vendor-kyc/'.$other->id.'/'.$category.'.pdf';
            Storage::disk('local')->put($path, 'private evidence');
            VendorKycDocument::create(['vendor_id' => $other->id, 'category' => $category, 'file_path' => $path, 'original_name' => $category.'.pdf', 'mime_type' => 'application/pdf', 'file_size' => 16, 'kyc_version' => 0]);
        }
        $this->postJson($this->url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors(['gst_certificate', 'pan_document']);
    }

    public function test_named_evidence_rejects_invalid_files_and_counts_towards_the_combined_upload_limit(): void
    {
        $this->postJson($this->url, $this->payload(['intent' => 'draft', 'gst_certificate' => UploadedFile::fake()->create('bad.html', 12, 'text/html')]))->assertUnprocessable()->assertJsonValidationErrors('gst_certificate');
        $files = array_replace($this->evidence(), ['documents' => array_map(fn () => UploadedFile::fake()->create('extra.pdf', 12, 'application/pdf'), range(1, 4))]);
        $this->postJson($this->url, array_replace($this->payload(['intent' => 'draft']), $files))->assertUnprocessable()->assertJsonValidationErrors('documents');
        $this->assertSame(0, DB::table('vendor_kyc_documents')->count());
    }

    public function test_foreign_vendors_are_exempt_but_indian_country_aliases_are_not(): void
    {
        foreach ([' India ', 'in', 'IND', 'Bharat', 'भारत'] as $country) {
            $this->postJson($this->url, $this->payload(['country' => $country]))->assertUnprocessable()->assertJsonValidationErrors(['gst_certificate', 'pan_document']);
        }
        $this->post($this->url, $this->payload(['country' => 'Singapore', 'gstin' => null, 'ifsc_code' => null, 'account_number' => null]))->assertRedirect();
        $this->assertSame('under_review', $this->f['vendor']->fresh()->kyc_status);
    }

    public function test_legacy_incomplete_indian_submissions_cannot_be_approved(): void
    {
        $v = $this->f['vendor']->fresh();
        $v->forceFill(['kyc_status' => 'under_review', 'kyc_submitted_data' => $this->payload()])->save();
        $this->patchJson('/vendors/'.$v->id.'/kyc/review', ['decision' => 'approved', 'kyc_version' => $v->kyc_version])->assertUnprocessable()->assertJsonValidationErrors(['gst_certificate', 'pan_document']);
        $this->assertSame('under_review', $v->fresh()->kyc_status);
    }

    public function test_office_addresses_are_editable_and_invalidate_previously_approved_kyc(): void
    {
        $this->post($this->url, array_replace($this->payload(), $this->evidence()))->assertRedirect();
        $v = $this->f['vendor']->fresh();
        $this->patch('/vendors/'.$v->id.'/kyc/review', ['decision' => 'approved', 'kyc_version' => $v->kyc_version])->assertRedirect();
        $v->refresh();
        $this->put('/vendors/'.$v->id, ['vendor_name' => $v->vendor_name, 'kyc_version' => $v->kyc_version,
            'bank_address' => "Updated Bank Road\nAhmedabad", 'beneficiary_address' => 'Updated beneficiary address'])->assertRedirect();
        $v->refresh();
        $this->assertSame('draft', $v->kyc_status);
        $this->assertSame("Updated Bank Road\nAhmedabad", $v->bank_address);
        $this->assertSame('Updated beneficiary address', $v->beneficiary_address);
        $this->get('/vendors/'.$v->id.'?tab=commercial')->assertOk()->assertSee('Updated Bank Road')->assertSee('Updated beneficiary address');
    }

    public function test_missing_private_file_does_not_count_as_valid_indian_evidence(): void
    {
        $this->post($this->url, array_replace($this->payload(['intent' => 'draft']), $this->evidence()))->assertRedirect();
        $doc = VendorKycDocument::where('category', 'gst_certificate')->firstOrFail();
        Storage::disk('local')->delete($doc->file_path);
        $this->postJson($this->url, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('gst_certificate')->assertJsonMissingValidationErrors('pan_document');
        $this->assertSame('draft', $this->f['vendor']->fresh()->kyc_status);
    }
}
