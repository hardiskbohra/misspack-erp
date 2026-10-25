<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorKyc;
use Tests\TestCase;

class VendorKycPolicyTest extends TestCase
{
    public function test_only_draft_and_revision_are_publicly_editable_without_changing_operational_status(): void
    {
        foreach (['draft' => true, 'revision' => true, 'under_review' => false, 'approved' => false, 'rejected' => false] as $status => $editable) {
            $v = new Vendor(['status' => 'blacklisted']);
            $v->forceFill(['kyc_status' => $status]);
            $this->assertSame($editable, $v->isPublicKycEditable());
            $this->assertSame('blacklisted', $v->status);
        }
    }

    public function test_review_requires_manage_access_but_allows_maker_and_creator(): void
    {
        $v = new Vendor(['created_by' => 1]);
        $v->forceFill(['kyc_status' => 'under_review', 'kyc_maker_id' => 2]);
        foreach ([[1, ['purchase.manage'], true], [2, ['purchase.manage'], true], [3, ['purchase.view'], false], [3, ['purchase.manage'], true]] as [$id,$permissions,$allowed]) {
            $u = new User(['role' => 'admin', 'access_profile' => 'custom', 'permissions' => $permissions]);
            $u->id = $id;
            $this->assertSame($allowed, VendorKyc::canReview($v, $u));
        }
    }

    public function test_all_requested_vendor_types_are_offered_and_public_fields_exclude_commercial_controls(): void
    {
        foreach (['packaging_supplier', 'service_provider', 'consultant', 'forwarder'] as $type) {
            $this->assertArrayHasKey($type, Vendor::typeOptions());
        }
        foreach (['status', 'rating', 'notes', 'preferred_currency', 'payment_terms', 'kyc_status', 'created_by'] as $field) {
            $this->assertNotContains($field, VendorKyc::FIELDS);
        }
    }

    public function test_kyc_metadata_is_not_mass_assignable_or_accidentally_serialized(): void
    {
        $v = new Vendor(['vendor_name' => 'Vendor', 'kyc_status' => 'approved', 'kyc_public_token' => 'injected']);
        $this->assertNull($v->kyc_status);
        $this->assertNull($v->kyc_public_token);
        $v->forceFill(['kyc_public_token' => 'secret', 'kyc_data' => ['account_number' => 'private'], 'kyc_submitted_data' => ['account_number' => 'private']]);
        foreach (['kyc_public_token', 'kyc_data', 'kyc_submitted_data'] as $field) {
            $this->assertArrayNotHasKey($field, $v->toArray());
        }
    }
}
