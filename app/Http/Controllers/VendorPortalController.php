<?php

namespace App\Http\Controllers;

use App\Models\PackagingList;
use App\Models\PurchaseInvoice;
use App\Models\Shipment;
use App\Models\VendorCollaborationUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorPortalController extends VendorPortalBaseController
{
    public function dashboard(Request $request): View
    {
        $vendor = $this->vendor($request);
        $documents = PurchaseInvoice::query()->where('vendor_id', $vendor->id)->where('status', '!=', 'draft')
            ->with(['items', 'project', 'payments', 'cashflowAllocations'])->latest('invoice_date')->latest('id')->get();
        $orders = $documents->where('invoice_type', PurchaseInvoice::TYPE_ORDER)->values();
        $bills = $documents->where('invoice_type', PurchaseInvoice::TYPE_BILL)->values();
        $shipments = Shipment::query()->where('vendor_id', $vendor->id)->latest('id')->limit(8)->get();
        $packagingLists = PackagingList::query()->where('vendor_id', $vendor->id)->latest('id')->limit(8)->get();
        $updates = VendorCollaborationUpdate::query()->with(['portalUser','creator','purchaseInvoice'])
            ->where('vendor_id', $vendor->id)->latest('id')->limit(12)->get();
        $openByCurrency = $bills->filter(fn ($bill) => $bill->balanceDue() > 0.009)->groupBy(fn ($bill) => $bill->currency ?: 'INR')
            ->map(fn ($group, $currency) => ['currency' => $currency, 'amount' => $group->sum(fn ($bill) => $bill->balanceDue()), 'count' => $group->count()])->values();
        return view('vendor_portal.dashboard', compact('vendor','orders','bills','shipments','packagingLists','updates','openByCurrency'));
    }

    public function purchase(Request $request, PurchaseInvoice $purchaseInvoice): View
    {
        abort_unless((int) $purchaseInvoice->vendor_id === (int) $this->vendor($request)->id && $purchaseInvoice->status !== 'draft', 404);
        $purchaseInvoice->load(['items','project','payments','cashflowAllocations']);
        $updates = VendorCollaborationUpdate::with(['portalUser','creator'])->where('vendor_id', $this->vendor($request)->id)
            ->where('purchase_invoice_id', $purchaseInvoice->id)->oldest('id')->get();
        return view('vendor_portal.purchase', compact('purchaseInvoice','updates'));
    }

    public function storeUpdate(Request $request, PurchaseInvoice $purchaseInvoice): RedirectResponse
    {
        $user = $this->portalUser($request);
        abort_unless((int) $purchaseInvoice->vendor_id === (int) $user->vendor_id && $purchaseInvoice->status !== 'draft', 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(VendorCollaborationUpdate::STATUSES))],
            'message' => ['required','string','max:4000'],
            'expected_date' => ['nullable','date','after_or_equal:today'],
        ]);
        VendorCollaborationUpdate::create($data + [
            'vendor_id' => $user->vendor_id, 'purchase_invoice_id' => $purchaseInvoice->id,
            'author_type' => 'vendor', 'vendor_portal_user_id' => $user->id,
        ]);
        return back()->with('success', 'Your update was sent to the MissPack team.');
    }
}
