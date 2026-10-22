<?php

namespace App\Http\Controllers;

use App\Models\PackagingList;
use App\Models\PurchaseInvoice;
use App\Models\Shipment;
use App\Models\Vendor;
use App\Models\VendorPortalUser;
use App\Models\VendorShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorPortalManagementController extends Controller
{
    public function enable(Vendor $vendor): RedirectResponse
    {
        $vendor->update(['portal_enabled' => ! $vendor->portal_enabled, 'portal_enabled_at' => $vendor->portal_enabled ? null : now()]);
        return back()->with('success', 'Vendor portal access '.($vendor->portal_enabled ? 'enabled.' : 'disabled.'));
    }

    public function storeUser(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'], 'username' => ['required','string','max:80','alpha_dash',Rule::unique('vendor_portal_users','username')],
            'email' => ['nullable','email','max:255'], 'mobile' => ['nullable','string','max:40'],
        ]);
        $password = Str::password(16);
        $user = VendorPortalUser::create($data + [
            'vendor_id' => $vendor->id, 'password' => Hash::make($password), 'created_by' => Auth::id(), 'updated_by' => Auth::id(),
        ]);
        if (! $vendor->portal_enabled) $vendor->update(['portal_enabled' => true, 'portal_enabled_at' => now()]);
        return back()->with('success', 'Portal user created. Copy the one-time credentials now.')
            ->with('vendor_portal_credentials', ['name' => $user->displayName(), 'username' => $user->username, 'password' => $password, 'url' => route('vendor-portal.login')]);
    }

    public function resetUser(Vendor $vendor, VendorPortalUser $portalUser): RedirectResponse
    {
        abort_unless((int) $portalUser->vendor_id === (int) $vendor->id, 404);
        $password = Str::password(16);
        $portalUser->update(['password' => Hash::make($password), 'must_change_password' => true, 'password_changed_at' => null, 'updated_by' => Auth::id()]);
        return back()->with('success', 'One-time password reset. Copy it now.')
            ->with('vendor_portal_credentials', ['name' => $portalUser->displayName(), 'username' => $portalUser->username, 'password' => $password, 'url' => route('vendor-portal.login')]);
    }

    public function toggleUser(Vendor $vendor, VendorPortalUser $portalUser): RedirectResponse
    {
        abort_unless((int) $portalUser->vendor_id === (int) $vendor->id, 404);
        $portalUser->update(['is_active' => ! $portalUser->is_active, 'updated_by' => Auth::id()]);
        return back()->with('success', 'Portal user '.($portalUser->is_active ? 'activated.' : 'deactivated.'));
    }

    public function storeUpdate(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'purchase_invoice_id' => ['nullable','integer'],
            'status' => ['required', Rule::in(array_keys(\App\Models\VendorCollaborationUpdate::STATUSES))],
            'message' => ['required','string','max:4000'],
            'expected_date' => ['nullable','date'],
        ]);
        if (! empty($data['purchase_invoice_id'])) {
            abort_unless(PurchaseInvoice::where('vendor_id', $vendor->id)->whereKey($data['purchase_invoice_id'])->exists(), 422, 'Choose this vendor’s purchase document.');
        }
        \App\Models\VendorCollaborationUpdate::create($data + [
            'vendor_id' => $vendor->id, 'author_type' => 'staff', 'created_by' => Auth::id(),
        ]);
        return back()->with('success', 'Update shared with the vendor workspace.');
    }

    public function storeShare(Request $request, Vendor $vendor): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required','string','max:255'],
            'resource_key' => ['required','string','max:100'],
            'expiry_days' => ['required','integer',Rule::in([1,3,7,14,30,60,90])],
            'allow_updates' => ['nullable','boolean'],
        ]);
        [$resourceType, $rawId] = array_pad(explode(':', $data['resource_key'], 2), 2, null);
        abort_unless(array_key_exists($resourceType, VendorShareLink::RESOURCE_TYPES), 422, 'Choose a valid share resource.');
        $resourceId = $resourceType === 'workspace' ? null : (int) $rawId;
        if ($resourceType !== 'workspace') $this->assertVendorResource($vendor, $resourceType, $resourceId);
        $permissions = ['view'];
        if ($resourceType === 'purchase_invoice' && $request->boolean('allow_updates')) $permissions[] = 'updates';
        $share = VendorShareLink::create([
            'vendor_id' => $vendor->id, 'label' => $data['label'], 'resource_type' => $resourceType,
            'resource_id' => $resourceId, 'permissions' => $permissions, 'expires_at' => now()->addDays((int) $data['expiry_days']), 'created_by' => Auth::id(),
        ]);
        $vendor->update(['portal_last_shared_at' => now()]);
        return back()->with('success', 'Secure share link created.')->with('vendor_share_url', $share->publicUrl());
    }

    public function revokeShare(Vendor $vendor, VendorShareLink $share): RedirectResponse
    {
        abort_unless((int) $share->vendor_id === (int) $vendor->id, 404);
        if (! $share->revoked_at) $share->update(['revoked_at' => now(), 'revoked_by' => Auth::id()]);
        return back()->with('success', 'Secure share link revoked.');
    }

    private function assertVendorResource(Vendor $vendor, string $type, int $id): void
    {
        $exists = match ($type) {
            'purchase_invoice' => PurchaseInvoice::where('vendor_id', $vendor->id)->whereKey($id)->exists(),
            'shipment' => Shipment::where('vendor_id', $vendor->id)->whereKey($id)->exists(),
            'packaging_list' => PackagingList::where('vendor_id', $vendor->id)->whereKey($id)->exists(),
            default => false,
        };
        abort_unless($exists, 422, 'Choose a record that belongs to this vendor.');
    }
}
