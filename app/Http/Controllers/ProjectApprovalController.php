<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectApprovalRequest;
use App\Models\ProjectAttachment;
use App\Services\ClientPortalNotifier;
use App\Services\ProjectApprovals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectApprovalController extends Controller
{
    public function store(Request $request, Project $project, ProjectApprovals $approvals): RedirectResponse
    {
        $workflow = $project->workflow;
        abort_unless($workflow, 422, 'Start the project workflow before requesting approval.');
        $data = $request->validate($this->rules());
        abort_unless($project->products()->whereKey($data['project_product_id'])->exists(), 422, 'Product does not belong to this project.');
        [$attachment, $hash] = $this->storeAttachment($request, $project, $data['project_product_id'], $data['approval_type']);
        $approval = $approvals->create($workflow, (int) $data['project_product_id'], $data['approval_type'], $data['title'], $data['instructions'] ?? null, $data['response_due_at'] ?? null, $attachment, $hash, Auth::id());
        $this->notifyClient($project, $approval);

        return back()->with('success', $approval->typeLabel().' version 1 sent to the client portal.');
    }

    public function addVersion(Request $request, Project $project, ProjectApprovalRequest $approval, ProjectApprovals $approvals): RedirectResponse
    {
        $this->assertBelongs($project, $approval);
        $data = $request->validate([
            'submission_notes' => ['nullable', 'string', 'max:2000'],
            'response_due_at' => ['nullable', 'date'],
            'approval_file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,ppt,pptx,zip'],
        ]);
        [$attachment, $hash] = $this->storeAttachment($request, $project, $approval->project_product_id, $approval->approval_type);
        $approval = $approvals->addVersion($approval, $attachment, $hash, $data['submission_notes'] ?? null, $data['response_due_at'] ?? null, Auth::id());
        $this->notifyClient($project, $approval);

        return back()->with('success', $approval->typeLabel().' version '.$approval->current_version_number.' sent for a fresh decision.');
    }

    public function override(Request $request, Project $project, ProjectApprovalRequest $approval, ProjectApprovals $approvals): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->assertBelongs($project, $approval);
        $data = $request->validate(['override_reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $approvals->override($approval, $data['override_reason'], Auth::id());

        return back()->with('warning', 'Manager override recorded. The missing client approval remains visible.');
    }

    public function revokeOverride(Request $request, Project $project, ProjectApprovalRequest $approval, ProjectApprovals $approvals): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
        $this->assertBelongs($project, $approval);
        abort_unless($approval->hasActiveOverride(), 422, 'This approval has no active override.');
        $data = $request->validate(['override_revocation_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $approvals->revokeOverride($approval, $data['override_revocation_reason'], Auth::id());

        return back()->with('success', 'Approval override revoked. The product approval step is blocked again.');
    }

    private function rules(): array
    {
        return [
            'project_product_id' => ['required', 'integer'],
            'approval_type' => ['required', Rule::in(array_keys(ProjectApprovalRequest::typeOptions()))],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'response_due_at' => ['nullable', 'date'],
            'approval_file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,ppt,pptx,zip'],
        ];
    }

    private function storeAttachment(Request $request, Project $project, int $productId, string $type): array
    {
        $file = $request->file('approval_file');
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store('project-approvals/'.$project->id, 'public');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $attachment = ProjectAttachment::create([
            'project_id' => $project->id, 'project_product_id' => $productId,
            'category' => $type === ProjectApprovalRequest::TYPE_ARTWORK ? 'artwork' : 'pps_photo',
            'title' => $file->getClientOriginalName(), 'file_path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(), 'extension' => $extension,
            'is_photo' => in_array($extension, ['jpg','jpeg','png','webp','gif'], true),
            'is_public' => true, 'uploaded_by' => Auth::id(), 'uploaded_by_type' => 'internal',
            'notes' => 'Versioned '.$type.' approval file.',
        ]);
        return [$attachment, $hash];
    }

    private function assertBelongs(Project $project, ProjectApprovalRequest $approval): void
    {
        abort_unless($approval->project_id === $project->id, 404);
    }

    private function notifyClient(Project $project, ProjectApprovalRequest $approval): void
    {
        app(ClientPortalNotifier::class)->notifyClient(
            (int) $project->client_id,
            $approval->typeLabel().' requested',
            $approval->title.' · version '.$approval->current_version_number.' is waiting for your decision.',
            'approval', 'project', (int) $project->id,
            route('client-portal.projects.show', $project->id).'#approvals'
        );
    }
}
