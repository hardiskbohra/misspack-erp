<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ProjectProductController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $this->validatedData($request);
        $product = $this->productFromId($data['product_id'] ?? null);

        if ($product) {
            $data['product_name'] = $data['product_name'] ?: $product->name;
            $data['product_snapshot'] = [
                'name' => $product->name,
                'category' => $product->category ?? null,
                'ml_capacities' => $product->ml_capacities ?? null,
                'finish_details' => $product->finish_details ?? null,
                'printing_details' => $product->printing_details ?? null,
                'packaging_details' => $product->packaging_details ?? null,
            ];
        }

        $data['project_id'] = $project->id;
        $data['currency'] = $data['currency'] ?: $project->currency;

        $projectProduct = ProjectProduct::create($data);
        $this->writeLog($project, 'product_added', 'Product added', $projectProduct->product_name.' was added to the project.', ['project_product_id' => $projectProduct->id]);

        return back()->with('success', 'Project product added successfully.');
    }

    public function update(Request $request, ProjectProduct $projectProduct): RedirectResponse
    {
        $data = $this->validatedData($request, $projectProduct);
        $product = $this->productFromId($data['product_id'] ?? null);

        if ($product) {
            $data['product_name'] = $product->name;
        }

        $old = $projectProduct->toArray();
        $projectProduct->update($data);
        $this->writeLog($projectProduct->project, 'product_updated', 'Product updated', $projectProduct->product_name.' details were updated.', ['old' => $old, 'new' => $projectProduct->fresh()->toArray()], $projectProduct->id);

        return back()->with('success', 'Project product updated successfully.');
    }

    public function destroy(ProjectProduct $projectProduct): RedirectResponse
    {
        $project = $projectProduct->project;
        $name = $projectProduct->product_name;
        $projectProduct->delete();

        $this->writeLog($project, 'product_removed', 'Product removed', $name.' was removed from the project.', null);

        return back()->with('success', 'Project product removed successfully.');
    }

    private function validatedData(Request $request, ?ProjectProduct $projectProduct = null): array
    {
        return $request->validate([
            'product_id' => ['nullable', 'integer'],
            'product_name' => ['required_without:product_id', 'nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in(array_keys(ProjectProduct::statusOptions()))],
            'stage' => ['nullable', Rule::in(array_keys(ProjectProduct::stageOptions()))],
            'assignee_id' => ['nullable', 'integer'],
            'vendor_id' => ['nullable', 'integer'],
            'vendor_invoice_number' => ['nullable', 'string', 'max:255'],
            'expected_ready_date' => ['nullable', 'date'],
            'actual_ready_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function productFromId($id)
    {
        if (! $id || ! class_exists(\App\Models\Product::class) || ! Schema::hasTable('products')) {
            return null;
        }

        return \App\Models\Product::find($id);
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
