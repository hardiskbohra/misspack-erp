<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorKycDocument;
use App\Services\VendorKyc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorKycController extends Controller
{
    public function __construct(private VendorKyc $kyc) {}

    private function vendor(string $token): Vendor
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{48}$/D', $token), 404);

        return Vendor::where('kyc_public_token', $token)->firstOrFail();
    }

    public function show(string $token)
    {
        $vendor = $this->vendor($token);

        return response()->view('vendors.kyc', [
            'vendor' => $vendor, 'profile' => VendorKyc::profile($vendor), 'readonly' => ! $vendor->isPublicKycEditable(),
            'documents' => VendorKycDocument::where('vendor_id', $vendor->id)->orderBy('id')->get(),
        ]);
    }

    public function submit(Request $request, string $token)
    {
        $this->vendor($token);
        $this->kyc->savePublic($token, $request);

        return redirect()->route('vendors.publicKyc', $token)
            ->with('success', $request->input('intent') === 'draft' ? 'Draft saved. You can return using this link.' : 'KYC submitted. MissPack will review your details.');
    }

    public function link(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['issue', 'rotate', 'revoke', 'mark_sent'])]]);
        $this->kyc->link($vendor, $request->user(), $data['action']);

        return redirect()->route('vendors.show', [$vendor, 'tab' => 'kyc'])->with('success',
            $data['action'] === 'mark_sent' ? 'KYC link marked as sent. Share the copied link with the vendor; no email is sent automatically.' : 'KYC link updated. Copy and share the current link with the vendor.');
    }

    public function review(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'revision', 'rejected'])],
            'reason' => [Rule::requiredIf(in_array($request->input('decision'), ['revision', 'rejected'], true)), 'nullable', 'string', 'max:5000'],
            'kyc_version' => ['required', 'integer', 'min:0']]);
        $this->kyc->review($vendor, $request->user(), $data);

        return redirect()->route('vendors.show', [$vendor, 'tab' => 'kyc'])->with('success', 'KYC review saved.');
    }

    public function reopen(Request $request, Vendor $vendor)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000'], 'kyc_version' => ['required', 'integer', 'min:0']]);
        $this->kyc->reopen($vendor, $request->user(), $data);

        return redirect()->route('vendors.show', [$vendor, 'tab' => 'kyc'])->with('success', 'KYC reopened. Ask the vendor to update their details and submit again.');
    }

    public function publicDocument(string $token, int $document)
    {
        return $this->document($this->vendor($token), $document);
    }

    public function officeDocument(Vendor $vendor, int $document)
    {
        return $this->document($vendor, $document);
    }

    private function document(Vendor $vendor, int $id)
    {
        $doc = VendorKycDocument::where('vendor_id', $vendor->id)->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($doc->file_path), 404);

        // Force a download, including images: never execute untrusted content inline.
        return Storage::disk('local')->download($doc->file_path, $doc->original_name, [
            'Content-Type' => $doc->mime_type, 'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
