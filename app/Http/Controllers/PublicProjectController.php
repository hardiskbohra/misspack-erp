<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectComment;
use App\Models\ProjectLog;
use App\Models\ProjectMilestone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicProjectController extends Controller
{
    public function show(string $token): View
    {
        $project = Project::where('public_token', $token)->firstOrFail();
        abort_unless($project->show_client_portal, 404);

        $with = [
            'products' => function ($q) { $q->orderBy('sort_order')->orderBy('id'); },
            'products.attachments' => function ($q) { $q->where('is_public', true)->latest('id'); },
            'products.comments' => function ($q) { $q->where('is_public', true)->latest('id'); },
            'publicAttachments.product', 'publicComments.product', 'publicTrackingUpdates.product', 'publicPayments',
            'logs' => function ($q) { $q->where('is_public', true)->latest('id'); },
        ];
        if (class_exists(\App\Models\ProjectMilestone::class) && Schema::hasTable('project_milestones')) {
            $with[] = 'products.publicMilestones';
            $with[] = 'publicMilestones.product';
        }
        if (class_exists(\App\Models\Client::class) && Schema::hasTable('clients')) {
            $with[] = 'client';
        }

        $project->load($with);
        
        if (! $project->relationLoaded('publicMilestones')) {
            $project->setRelation('publicMilestones', collect());
        }

        return view('projects.public', compact('project'));
    }

    public function storeComment(Request $request, string $token): RedirectResponse
    {
        $project = Project::where('public_token', $token)->firstOrFail();
        abort_unless($project->show_client_portal, 404);

        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            return back()->with('error', 'Selected product does not belong to this project.');
        }

        $comment = ProjectComment::create([
            'project_id' => $project->id,
            'project_product_id' => $data['project_product_id'] ?? null,
            'author_type' => 'client',
            'client_name' => $data['client_name'],
            'client_email' => $data['client_email'] ?? null,
            'body' => $data['body'],
            'is_public' => true,
            'is_pinned' => false,
        ]);

        $this->writeClientLog($project, $data['client_name'], 'client_comment_added', 'Client comment added', 'Client added a comment from project portal.', ['comment_id' => $comment->id], $comment->project_product_id);

        return back()->with('success', 'Your comment has been submitted.');
    }

    public function storeAttachment(Request $request, string $token): RedirectResponse
    {
        $project = Project::where('public_token', $token)->firstOrFail();
        abort_unless($project->show_client_portal, 404);

        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'client_name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
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
            $path = $file->store('project-attachments/'.$project->id, 'public');
            $extension = strtolower((string) $file->getClientOriginalExtension());

            ProjectAttachment::create([
                'project_id' => $project->id,
                'project_product_id' => $data['project_product_id'] ?? null,
                'category' => 'client_document',
                'title' => $data['title'] ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'is_photo' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'], true),
                'is_public' => true,
                'uploaded_by_type' => 'client',
                'client_name' => $data['client_name'],
                'notes' => $data['notes'] ?? null,
            ]);
            $count++;
        }

        $this->writeClientLog($project, $data['client_name'], 'client_attachment_uploaded', 'Client attachment uploaded', $count.' attachment(s) uploaded from project portal.', ['count' => $count], $data['project_product_id'] ?? null);

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    private function validateAllowedFile($file): void
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'zip'];
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'attachments' => 'Only JPG, JPEG, PNG, WEBP, GIF, HEIC, HEIF, PDF, DOC, DOCX, XLS, XLSX, CSV, PPT, PPTX, TXT and ZIP files are allowed.',
            ]);
        }
    }

    private function writeClientLog(Project $project, string $clientName, string $eventType, string $title, ?string $description = null, ?array $newValues = null, ?int $projectProductId = null): void
    {
        ProjectLog::create([
            'project_id' => $project->id,
            'project_product_id' => $projectProductId,
            'actor_type' => 'client',
            'actor_name' => $clientName,
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $newValues,
            'is_public' => true,
        ]);
    }
}
