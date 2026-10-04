<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalDocument;
use App\Models\ProjectAttachment;
use App\Services\ClientPortalNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalProjectController extends ClientPortalBaseController
{
    public function index(Request $request): View
    {
        $client = $this->client($request);
        $search = $request->query('search');
        $status = $request->query('status', 'all');

        $projects = collect();
        if ($this->projectsAvailable()) {
            $projects = \App\Models\Project::query()
                ->where('client_id', $client->id)
                ->where('show_client_portal', true)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($nested) use ($search) {
                        $nested->where('project_number', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('stage', 'like', "%{$search}%");
                    });
                })
                ->when($status !== 'all', function ($query) use ($status) {
                    $query->where('status', $status);
                })
                ->latest('id')
                ->paginate(10)
                ->withQueryString();
        }

        $statusOptions = $this->projectsAvailable() ? \App\Models\Project::statusOptions() : [];

        return view('client_portal.projects.index', compact('projects', 'search', 'status', 'statusOptions'));
    }

    public function show(Request $request, int $project): View
    {
        $project = $this->findProjectForClient($request, $project);

        $load = [
            'products',
            'publicTrackingUpdates.product',
            'clientPortalAttachments.product',
            'publicComments.user',
            'publicComments.product',
            'publicMilestones',
            'clientVisiblePayments',
        ];

        if (class_exists(\App\Models\Shipment::class)
            && Schema::hasTable('shipments')
            && Schema::hasColumn('shipments', 'project_id')
            && Schema::hasColumn('shipments', 'client_id')
            && Schema::hasColumn('shipments', 'show_client_portal')) {
            $load[] = 'publicShipments';
        }

        $project->load($load);
        $portalDocuments = ClientPortalDocument::query()
            ->where('client_id', $this->client($request)->id)
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('is_public_to_client', true)
            ->with('projectProduct')
            ->latest('id')
            ->get();

        if (! $project->relationLoaded('publicShipments')) {
            $project->setRelation('publicShipments', collect());
        }

        $portalComments = \App\Models\ClientPortalComment::where('client_id', $this->client($request)->id)
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('is_public_to_client', true)
            ->with('portalUser', 'internalUser')
            ->latest('id')
            ->get();

        return view('client_portal.projects.show', compact('project', 'portalComments', 'portalDocuments'));
    }

    public function attachmentFile(Request $request, int $project, ProjectAttachment $attachment)
    {
        $visibleProject = $this->findProjectForClient($request, $project);
        abort_unless(
            (int) $attachment->project_id === (int) $visibleProject->id
                && $attachment->is_public
                && $attachment->category !== 'vendor_invoice'
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

    public function storeComment(Request $request, int $project): RedirectResponse
    {
        $project = $this->findProjectForClient($request, $project);
        $portalUser = $this->portalUser($request);

        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'body' => ['required', 'string', 'max:4000']
        ]);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            return back()->with('error', 'Selected product does not belong to this project.');
        }

        if (class_exists(\App\Models\ProjectComment::class) && Schema::hasTable('project_comments')) {
            \App\Models\ProjectComment::create([
                'project_id' => $project->id,
                'project_product_id' => $data['project_product_id'] ?? null,
                'author_type' => 'client',
                'client_name' => $portalUser->displayName(),
                'client_email' => $portalUser->email,
                'body' => $data['body'],
                'is_public' => true,
                'is_pinned' => false,
            ]);
        }

        \App\Models\ClientPortalComment::create([
            'client_id' => $portalUser->client_id,
            'client_portal_user_id' => $portalUser->id,
            'related_type' => 'project',
            'related_id' => $project->id,
            'author_type' => 'client',
            'body' => $data['body'],
            'is_public_to_client' => true,
        ]);

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Comment submitted', 'Your comment was added to project '.$project->name.'.', 'comment', 'project', $project->id, route('client-portal.projects.show', $project->id));

        return back()->with('success', 'Comment submitted successfully.');
    }

    public function upload(Request $request, int $project): RedirectResponse
    {
        $project = $this->findProjectForClient($request, $project);
        $portalUser = $this->portalUser($request);

        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['client_document', 'artwork', 'payment_proof', 'other'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,gif,heic,heif,pdf,doc,docx,xls,xlsx,csv,ppt,pptx,txt,zip'],
        ]);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            return back()->with('error', 'Selected product does not belong to this project.');
        }

        $count = 0;
        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('client-portal/projects/'.$project->id, 'local');

            ClientPortalDocument::create([
                'client_id' => $portalUser->client_id,
                'client_portal_user_id' => $portalUser->id,
                'related_type' => 'project',
                'related_id' => $project->id,
                'project_product_id' => $data['project_product_id'] ?? null,
                'category' => $data['category'] ?? 'project',
                'title' => ($data['title'] ?? null) ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'storage_disk' => 'local',
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'is_public_to_client' => true,
                'notes' => $data['notes'] ?? null,
            ]);
            $count++;
        }

        app(ClientPortalNotifier::class)->notifyPortalUser($portalUser, 'Document uploaded', $count.' document(s) uploaded to project '.$project->name.'.', 'document', 'project', $project->id, route('client-portal.projects.show', $project->id));

        return back()->with('success', 'Document uploaded successfully.');
    }

    private function findProjectForClient(Request $request, int $projectId)
    {
        abort_unless($this->projectsAvailable(), 404);

        return \App\Models\Project::where('client_id', $this->client($request)->id)
            ->where('show_client_portal', true)
            ->whereKey($projectId)
            ->firstOrFail();
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
