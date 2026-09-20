<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\ProjectLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectAttachmentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'category' => ['required', Rule::in(array_keys(ProjectAttachment::categoryOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
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
                'category' => $data['category'],
                'title' => $data['title'] ?: $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'extension' => $extension,
                'is_photo' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif'], true),
                'is_public' => $request->has('is_public'),
                'uploaded_by' => Auth::id(),
                'uploaded_by_type' => 'internal',
                'notes' => $data['notes'] ?? null,
            ]);
            $count++;
        }

        $this->writeLog($project, 'attachment_uploaded', 'Attachment uploaded', $count.' attachment(s) uploaded.', ['count' => $count], $request->has('is_public'));

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    public function update(Request $request, ProjectAttachment $projectAttachment): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(ProjectAttachment::categoryOptions()))],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $projectAttachment->update([
            'category' => $data['category'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'is_public' => $request->has('is_public'),
        ]);

        $this->writeLog($projectAttachment->project, 'attachment_updated', 'Attachment updated', 'Attachment visibility/details updated.', ['attachment_id' => $projectAttachment->id], $projectAttachment->is_public);

        return back()->with('success', 'Attachment updated successfully.');
    }

    public function destroy(ProjectAttachment $projectAttachment): RedirectResponse
    {
        $project = $projectAttachment->project;
        if ($projectAttachment->file_path) {
            Storage::disk('public')->delete($projectAttachment->file_path);
        }
        $projectAttachment->delete();

        $this->writeLog($project, 'attachment_deleted', 'Attachment deleted', 'An attachment was deleted.', null, false);

        return back()->with('success', 'Attachment deleted successfully.');
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

    private function writeLog(Project $project, string $eventType, string $title, ?string $description = null, ?array $newValues = null, bool $isPublic = false): void
    {
        ProjectLog::create([
            'project_id' => $project->id,
            'user_id' => Auth::id(),
            'actor_type' => 'internal',
            'actor_name' => Auth::user()->name ?? 'Internal Team',
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $newValues,
            'is_public' => $isPublic,
        ]);
    }
}
