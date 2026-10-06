<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectProductController extends Controller
{
    /**
     * The office edits what the project owns, and only that.
     *
     * A project product is created by the invoice or purchase document that
     * mentions it — `App\Services\ProjectProducts` is the one writer of the
     * product, the quantity, the rate, the currency, the vendor and the
     * supplier's document number — and there is no add door and no delete door
     * on the tab. What is left here is the project's own half of the row: where
     * the product stands, when it is expected and when it was ready, and the
     * notes the office keeps about it. A request that tries to post the
     * document's fields is not validated, so a crafted form cannot overwrite
     * the facts an invoice owns.
     */
    public function update(Request $request, ProjectProduct $projectProduct): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ProjectProduct::statusOptions()))],
            'expected_ready_date' => ['nullable', 'date'],
            'actual_ready_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $old = $projectProduct->toArray();
        $projectProduct->update($data);
        $this->writeLog($projectProduct->project, 'product_updated', 'Product updated', $projectProduct->product_name.' details were updated.', ['old' => $old, 'new' => $projectProduct->fresh()->toArray()], $projectProduct->id);

        return back()->with('success', 'Project product updated successfully.');
    }

    private function writeLog(Project $project, string $eventType, string $title, ?string $description = null, ?array $newValues = null, ?int $projectProductId = null): void
    {
        ProjectLog::create([
            'project_id' => $project->id,
            'project_product_id' => $projectProductId,
            'user_id' => Auth::id(),
            'actor_type' => 'internal',
            'actor_name' => Auth::user()->name ?? 'Internal Team',
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'new_values' => $newValues,
            'is_public' => false,
        ]);
    }
}
