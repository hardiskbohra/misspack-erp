<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalComment;
use App\Models\ClientPortalInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientPortalInvoiceController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $invoices = ClientPortalInvoice::where('client_id', $this->client($request)->id)
            ->where('is_public_to_client', true)
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $statusOptions = ClientPortalInvoice::statusOptions();

        return view('client_portal.invoices.index', compact('invoices', 'status', 'statusOptions'));
    }

    public function show(Request $request, ClientPortalInvoice $invoice): View
    {
        abort_unless($invoice->client_id === $this->client($request)->id && $invoice->is_public_to_client, 404);

        $comments = ClientPortalComment::where('client_id', $invoice->client_id)
            ->where('related_type', 'invoice')
            ->where('related_id', $invoice->id)
            ->where('is_public_to_client', true)
            ->with('portalUser', 'internalUser')
            ->latest('id')
            ->get();

        return view('client_portal.invoices.show', compact('invoice', 'comments'));
    }

    public function storeComment(Request $request, ClientPortalInvoice $invoice): RedirectResponse
    {
        abort_unless($invoice->client_id === $this->client($request)->id && $invoice->is_public_to_client, 404);
        $portalUser = $this->portalUser($request);
        $data = $request->validate(['body' => ['required', 'string']]);

        ClientPortalComment::create([
            'client_id' => $portalUser->client_id,
            'client_portal_user_id' => $portalUser->id,
            'related_type' => 'invoice',
            'related_id' => $invoice->id,
            'author_type' => 'client',
            'body' => $data['body'],
            'is_public_to_client' => true,
        ]);

        return back()->with('success', 'Comment submitted successfully.');
    }
}
