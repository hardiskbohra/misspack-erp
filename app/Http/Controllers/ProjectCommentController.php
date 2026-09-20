<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectCommentController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'project_product_id' => ['nullable', 'integer'],
            'body' => ['required', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['project_product_id']) && ! $project->products()->whereKey($data['project_product_id'])->exists()) {
            return back()->with('error', 'Selected product does not belong to this project.');
        }

        $comment = ProjectComment::create([
            'project_id' => $project->id,
            'project_product_id' => $data['project_product_id'] ?? null,
            'author_type' => 'internal',
            'user_id' => Auth::id(),
            'body' => $data['body'],
            'is_public' => $request->has('is_public'),
            'is_pinned' => $request->has('is_pinned'),
        ]);

        $this->writeLog($project, 'comment_added', 'Comment added', 'A team comment was added.', ['comment_id' => $comment->id], $comment->is_public);

        return back()->with('success', 'Comment added successfully.');
    }

    public function update(Request $request, ProjectComment $projectComment): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string'],
            'is_public' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $projectComment->update([
            'body' => $data['body'],
            'is_public' => $request->has('is_public'),
            'is_pinned' => $request->has('is_pinned'),
        ]);

        $this->writeLog($projectComment->project, 'comment_updated', 'Comment updated', 'A comment was updated.', ['comment_id' => $projectComment->id], $projectComment->is_public);

        return back()->with('success', 'Comment updated successfully.');
    }

    public function destroy(ProjectComment $projectComment): RedirectResponse
    {
        $project = $projectComment->project;
        $projectComment->delete();
        $this->writeLog($project, 'comment_deleted', 'Comment deleted', 'A comment was deleted.', null, false);

        return back()->with('success', 'Comment deleted successfully.');
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
