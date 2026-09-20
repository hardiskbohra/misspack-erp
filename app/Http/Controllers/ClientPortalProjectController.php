<?php

namespace App\Http\Controllers;

use App\Models\ClientPortalDocument;
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
        $project->load([
            'products',
            'publicTrackingUpdates.product',
            'publicAttachments.product',
            'publicComments.user',
            'publicComments.product',
            'publicPayments',
        ]);

        $portalComments = \App\Models\ClientPortalComment::where('client_id', $this->client($request)->id)
            ->where('related_type', 'project')
            ->where('related_id', $project->id)
            ->where('is_public_to_client', true)
            ->with('portalUser', 'internalUser')
            ->latest('id')
            ->get();

        return view('client_portal.projects.show', compact('project', 'portalComments'));
    }

    public function storeComment(Request $request, int $project): RedirectResponse
    {
        $project = $this->findProjectForClient($request, $project);
        $portalUser = $this->portalUser($request);

        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'body' => ['required', 'string'],
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
            'category' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string'],
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:20480'],
        ]);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            return back()->with('error', 'Selected product does not belong to this project.');
        }

        $count = 0;
        foreach ((array) $request->file('attachments', []) as $file) {
            $this->validateAllowedFile($file);
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $path = $file->store('client-portal/projects/'.$project->id, 'public');

            if (class_exists(\App\Models\ProjectAttachment::class) && Schema::hasTable('project_attachments')) {
                \App\Models\ProjectAttachment::create([
                    'project_id' => $project->id,
                    'project_product_id' => $data['project_product_id'] ?? null,
                    'category' => $data['category'] ?? 'client_document',
                    'title' => $data['title'] ?: $file->getClientOriginalName(),
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'extension' => $extension,
                    'is_photo' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'], true),
                    'is_public' => true,
                    'uploaded_by_type' => 'client',
                    'client_name' => $portalUser->displayName(),
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            ClientPortalDocument::create([
                'client_id' => $portalUser->client_id,
                'client_portal_user_id' => $portalUser->id,
                'related_type' => 'project',
                'related_id' => $project->id,
                'category' => $data['category'] ?? 'project',
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
