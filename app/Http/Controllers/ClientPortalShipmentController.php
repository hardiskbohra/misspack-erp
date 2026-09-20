<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalComment;
use App\Models\ClientPortalDocument;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalShipmentController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $shipments = collect();

        if ($this->shipmentPortalReady()) {
            $shipments = \App\Models\Shipment::query()
                ->where('client_id', $this->client($request)->id)
                ->where('show_client_portal', true)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('shipment_number', 'like', "%{$search}%")
                            ->orWhere('identity_name', 'like', "%{$search}%")
                            ->orWhere('tracking_number', 'like', "%{$search}%")
                            ->orWhere('logistic_partner', 'like', "%{$search}%");
                    });
                })
                ->when($status !== 'all', function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->latest('id')
                ->paginate(10)
                ->withQueryString();
        }

        $statusOptions = $this->shipmentsAvailable() ? \App\Models\Shipment::statusOptions() : [];

        return view('client_portal.shipments.index', compact('shipments', 'search', 'status', 'statusOptions'));
    }

    public function show(Request $request, int $shipment): View
    {
        $shipment = $this->findShipmentForClient($request, $shipment);
        $shipment->load(['items', 'publicAttachments']);

        $publicHistories = $shipment->histories()->where('is_public', true)->get();
        $comments = ClientPortalComment::where('client_id', $this->client($request)->id)
            ->where('related_type', 'shipment')
            ->where('related_id', $shipment->id)
            ->where('is_public_to_client', true)
            ->with('portalUser', 'internalUser')
            ->latest('id')
            ->get();
        $documents = ClientPortalDocument::where('client_id', $this->client($request)->id)
            ->where('related_type', 'shipment')
            ->where('related_id', $shipment->id)
            ->latest('id')
            ->get();

        return view('client_portal.shipments.show', compact('shipment', 'publicHistories', 'comments', 'documents'));
    }

    public function storeComment(Request $request, int $shipment): RedirectResponse
    {
        $shipment = $this->findShipmentForClient($request, $shipment);
        $portalUser = $this->portalUser($request);
        $data = $request->validate(['body' => ['required', 'string']]);

        ClientPortalComment::create([
            'client_id' => $portalUser->client_id,
            'client_portal_user_id' => $portalUser->id,
            'related_type' => 'shipment',
            'related_id' => $shipment->id,
            'author_type' => 'client',
            'body' => $data['body'],
            'is_public_to_client' => true,
        ]);

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Shipment comment submitted', 'Your comment was added to shipment '.$shipment->shipment_number.'.', 'comment', 'shipment', $shipment->id, route('client-portal.shipments.show', $shipment->id));

        return back()->with('success', 'Comment submitted successfully.');
    }

    public function upload(Request $request, int $shipment): RedirectResponse
    {
        $shipment = $this->findShipmentForClient($request, $shipment);
        $portalUser = $this->portalUser($request);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:20480'],
        ]);

        $count = 0;
        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('client-portal/shipments/'.$shipment->id, 'public');

            ClientPortalDocument::create([
                'client_id' => $portalUser->client_id,
                'client_portal_user_id' => $portalUser->id,
                'related_type' => 'shipment',
                'related_id' => $shipment->id,
                'category' => $data['category'] ?? 'shipment',
                'title' => $data['title'] ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'notes' => $data['notes'] ?? null,
            ]);
            $count++;
        }

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Shipment document uploaded', $count.' document(s) uploaded to shipment '.$shipment->shipment_number.'.', 'document', 'shipment', $shipment->id, route('client-portal.shipments.show', $shipment->id));

        return back()->with('success', 'Document uploaded successfully.');
    }

    private function findShipmentForClient(Request $request, int $shipmentId)
    {
        abort_unless($this->shipmentPortalReady(), 404);

        return \App\Models\Shipment::where('client_id', $this->client($request)->id)
            ->where('show_client_portal', true)
            ->whereKey($shipmentId)
            ->firstOrFail();
    }

    private function shipmentPortalReady(): bool
    {
        return $this->shipmentsAvailable()
            && Schema::hasColumn('shipments', 'client_id')
            && Schema::hasColumn('shipments', 'show_client_portal');
    }

    private function validateAllowedFile($file): void
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip'];
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Only image, PDF, Office, CSV, TXT and ZIP files are allowed.',
            ]);
        }
    }
}
