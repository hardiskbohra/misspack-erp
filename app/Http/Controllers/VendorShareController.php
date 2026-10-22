<?php

namespace App\Http\Controllers;

use App\Models\PackagingList;
use App\Models\PurchaseInvoice;
use App\Models\Shipment;
use App\Models\VendorCollaborationUpdate;
use App\Models\VendorShareLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorShareController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $share = VendorShareLink::with('vendor')->where('token', $token)->firstOrFail();
        if (! $share->isLive()) return view('vendor_portal.share-unavailable', compact('share'));
        $share->recordView($request->ip());
        return $this->viewFor($share);
    }

    public function storeUpdate(Request $request, string $token): RedirectResponse
    {
        $share = VendorShareLink::with('vendor')->where('token', $token)->firstOrFail();
        abort_unless($share->isLive() && $share->allows('updates') && $share->resource_type === 'purchase_invoice', 403);
        $purchase = PurchaseInvoice::query()->where('id', $share->resource_id)->where('vendor_id', $share->vendor_id)->firstOrFail();
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(VendorCollaborationUpdate::STATUSES))],
            'message' => ['required','string','max:4000'],
            'expected_date' => ['nullable','date','after_or_equal:today'],
        ]);
        VendorCollaborationUpdate::create($data + [
            'vendor_id' => $share->vendor_id, 'purchase_invoice_id' => $purchase->id,
            'author_type' => 'secure_link', 'vendor_share_link_id' => $share->id,
        ]);
        return back()->with('success', 'Update submitted securely to the MissPack team.');
    }

    private function viewFor(VendorShareLink $share): View
    {
        $resource = null; $updates = collect();
        if ($share->resource_type === 'purchase_invoice') {
            $resource = PurchaseInvoice::with(['items','project','payments','cashflowAllocations'])
                ->where('vendor_id', $share->vendor_id)->findOrFail($share->resource_id);
            $updates = VendorCollaborationUpdate::with(['portalUser','creator'])->where('vendor_id', $share->vendor_id)
                ->where('purchase_invoice_id', $resource->id)->oldest('id')->get();
        } elseif ($share->resource_type === 'shipment') {
            $resource = Shipment::with(['histories'])->where('vendor_id', $share->vendor_id)->findOrFail($share->resource_id);
        } elseif ($share->resource_type === 'packaging_list') {
            $resource = PackagingList::with(['cartons.items'])->where('vendor_id', $share->vendor_id)->findOrFail($share->resource_id);
        } else {
            $resource = [
                'orders' => PurchaseInvoice::where('vendor_id', $share->vendor_id)->where('invoice_type', 'order')->where('status', '!=', 'draft')->latest('id')->limit(10)->get(),
                'shipments' => Shipment::where('vendor_id', $share->vendor_id)->latest('id')->limit(10)->get(),
                'packing' => PackagingList::where('vendor_id', $share->vendor_id)->latest('id')->limit(10)->get(),
            ];
        }
        return view('vendor_portal.share', compact('share','resource','updates'));
    }
}
