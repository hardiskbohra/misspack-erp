<section class="core-card client-detail-card" aria-labelledby="client-projects-heading">
    <h2 class="client-detail-title" id="client-projects-heading">Client projects</h2>
    @unless(auth()->user()?->hasPermission('projects.view'))
        <p class="master-empty-value">Project details require project-view access.</p>
    @else
    <div class="master-table-wrap">
        <table class="master-table">
            <thead><tr><th>Project</th><th>Status</th><th>Stage</th><th>Target date</th></tr></thead>
            <tbody>
                @forelse($projectEntries ?? [] as $project)
                    <tr>
                        <td>
                            <a class="client-detail-link" href="{{ route('projects.show', $project) }}">{{ $project->project_number }} · {{ $project->name }}</a>
                        </td>
                        <td>{{ $project->statusLabel() }}</td>
                        <td>{{ $project->stageLabel() }}</td>
                        <td>{{ $project->target_date?->format('d M Y') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><p class="master-empty-value">No projects found for this client.</p></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($projectEntries?->hasPages())
        <div class="client-entry-pagination">{{ $projectEntries->links() }}</div>
    @endif
    @endunless
</section>
