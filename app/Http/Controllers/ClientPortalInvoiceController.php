<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalComment;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ClientPortalInvoiceController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $clientId = $this->client($request)->id;

        // ERP SalesInvoices are the only invoice source presented in the portal.
        $salesInvoices = SalesInvoice::query()
            ->where('client_id', $clientId)
            ->where('show_client_portal', true)
            ->where('status', '!=', 'draft')
            ->withClientPortalReceived()
            ->latest('invoice_date')
            ->latest('id')
            ->paginate(10, ['*'], 'sales_page')
            ->withQueryString();

        return view('client_portal.invoices.index', compact('salesInvoices'));
    }

    public function showSales(Request $request, SalesInvoice $invoice): View
    {
        $invoice = $this->publishedSalesInvoice($request, $invoice);
        $invoice->loadMissing(['items', 'publicAttachments', 'payments']);

        $comments = ClientPortalComment::query()
            ->where('client_id', $invoice->client_id)
            ->where('related_type', 'sales_invoice')
            ->where('related_id', $invoice->id)
            ->where('is_public_to_client', true)
            ->with('portalUser', 'internalUser')
            ->latest('id')
            ->get();

        return view('client_portal.invoices.sales-show', compact('invoice', 'comments'));
    }

    public function salesAttachmentFile(Request $request, SalesInvoice $invoice, SalesInvoiceAttachment $attachment)
    {
        $invoice = $this->publishedSalesInvoice($request, $invoice);
        abort_unless(
            (int) $attachment->sales_invoice_id === (int) $invoice->id
                && $attachment->is_public
                && $attachment->file_path,
            404
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($attachment->file_path), 404);

        $extension = strtolower(pathinfo($attachment->file_path, PATHINFO_EXTENSION));
        $isSafeInlineImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            && str_starts_with(strtolower((string) $attachment->mime_type), 'image/');

        return $disk->response(
            $attachment->file_path,
            $attachment->original_name ?: basename($attachment->file_path),
            [
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            $isSafeInlineImage ? 'inline' : 'attachment'
        );
    }

    public function printSales(Request $request, SalesInvoice $invoice): View
    {
        $invoice = $this->publishedSalesInvoice($request, $invoice);
        $invoice->load(['items', 'publicAttachments']);

        return view('sales_invoices.print', ['invoice' => $invoice, 'publicMode' => true]);
    }

    public function storeSalesComment(Request $request, SalesInvoice $invoice): RedirectResponse
    {
        $invoice = $this->publishedSalesInvoice($request, $invoice);
        $this->saveComment($request, 'sales_invoice', $invoice->id, $invoice->client_id);

        return back()->with('success', 'Comment submitted successfully.');
    }

    private function publishedSalesInvoice(Request $request, SalesInvoice $invoice): SalesInvoice
    {
        return SalesInvoice::query()
            ->where('client_id', $this->client($request)->id)
            ->where('show_client_portal', true)
            ->where('status', '!=', 'draft')
            ->whereKey($invoice->id)
            ->firstOrFail();
    }

    private function saveComment(Request $request, string $relatedType, int $relatedId, int $clientId): void
    {
        $portalUser = $this->portalUser($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        ClientPortalComment::create([
            'client_id' => $clientId,
            'client_portal_user_id' => $portalUser->id,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'author_type' => 'client',
            'body' => $data['body'],
            'is_public_to_client' => true,
        ]);
    }

}
