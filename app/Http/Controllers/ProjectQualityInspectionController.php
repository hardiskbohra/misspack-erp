<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectProduct;
use App\Models\ProjectQualityInspection;
use App\Services\ProjectOperationalReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectQualityInspectionController extends Controller
{
    public function store(Request $request, Project $project, ProjectProduct $product, ProjectOperationalReadiness $readiness): RedirectResponse
    {
        abort_unless($product->project_id === $project->id, 404);
        $rules = [
            'batch_reference'=>['nullable','string','max:255'], 'inspected_quantity'=>['required','numeric','gt:0'],
            'passed_quantity'=>['required','numeric','min:0'], 'rejected_quantity'=>['required','numeric','min:0'],
            'rework_quantity'=>['required','numeric','min:0'],
            'result'=>['required', Rule::in(['passed','passed_with_observations','failed','rework_required'])],
            'notes'=>['nullable','string','max:4000'],
            'evidence_file'=>['required','file','max:20480','mimes:jpg,jpeg,png,webp,pdf,xls,xlsx,doc,docx'],
        ];
        foreach (array_keys(ProjectQualityInspection::CHECKS) as $check) $rules["checklist.$check"] = ['required','boolean'];
        $data = $request->validate($rules);
        $inspected = round((float) $data['inspected_quantity'], 3);
        $passed = round((float) $data['passed_quantity'], 3);
        $rejected = round((float) $data['rejected_quantity'], 3);
        $rework = round((float) $data['rework_quantity'], 3);
        if (abs($inspected - ($passed + $rejected + $rework)) > 0.0001) {
            throw ValidationException::withMessages(['inspected_quantity'=>'Passed, rejected and rework quantities must equal the inspected quantity.']);
        }
        $checklist = collect(ProjectQualityInspection::CHECKS)->map(fn ($label, $key) => ['key'=>$key,'label'=>$label,'passed'=>(bool)data_get($data,"checklist.$key")])->values()->all();
        if (in_array($data['result'], ['passed','passed_with_observations'], true) && ($passed !== $inspected || collect($checklist)->contains(fn ($check) => ! $check['passed']))) {
            throw ValidationException::withMessages(['result'=>'A passed result requires the entire inspected quantity and every checklist item to pass.']);
        }

        DB::transaction(function () use ($request, $product, $project, $data, $checklist, $inspected, $passed, $rejected, $rework) {
            $locked = ProjectProduct::query()->lockForUpdate()->findOrFail($product->id);
            $alreadyPassed = (float) $locked->qualityInspections()->sum('passed_quantity');
            if ($alreadyPassed + $passed > (float) $locked->quantity + 0.0001) {
                throw ValidationException::withMessages(['passed_quantity'=>'This would pass more than the product quantity. Only the remaining '.number_format(max((float)$locked->quantity-$alreadyPassed, 0), 3).' can be passed.']);
            }
            $evidence = $request->file('evidence_file');
            $evidenceHash = hash_file('sha256', $evidence->getRealPath());
            $evidencePath = $evidence->store('projects/quality-inspections', 'public');
            $payload = [
                'project_id'=>$project->id, 'project_product_id'=>$locked->id,
                'batch_reference'=>$data['batch_reference'] ?? null, 'inspected_quantity'=>$inspected,
                'passed_quantity'=>$passed, 'rejected_quantity'=>$rejected, 'rework_quantity'=>$rework,
                'result'=>$data['result'], 'checklist'=>$checklist, 'notes'=>$data['notes'] ?? null,
                'evidence_path'=>$evidencePath, 'evidence_original_name'=>$evidence->getClientOriginalName(),
                'evidence_mime'=>$evidence->getMimeType(), 'evidence_size'=>$evidence->getSize(),
                'evidence_hash'=>$evidenceHash, 'inspected_by'=>Auth::id(), 'inspected_at'=>now(),
            ];
            $payload['content_hash'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            ProjectQualityInspection::create($payload);
        });

        if ($workflow = $project->workflow) $readiness->evaluateAll($workflow, Auth::id());
        return back()->with('success', 'Immutable QC inspection recorded and product readiness recalculated.');
    }
}
